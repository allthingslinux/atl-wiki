# Docker Configuration Analysis

## Overview

This document analyzes Docker configurations across 20 MediaWiki deployment repositories, examining Dockerfile patterns, base image choices, multi-stage builds, and container orchestration approaches.

## Repository Analysis Summary

### Repositories with Docker Configurations

1. **WikiDocker** - Comprehensive single-container approach
2. **docker-mediawiki-chiefy** - Alpine-based lightweight approach
3. **docker-mediawiki-ldap** - Extension-focused approach
4. **gcpedia** - Multi-stage build with Kubernetes support
5. **mediawiki-docker-offspot** - Feature-rich offline deployment
6. **mediawiki-docker-ubc** - Educational institution approach
7. **mediawiki-wbstack** - Wikibase-focused deployment
8. **sct-docker-images** - Production-optimized multi-stage build
9. **docker-mediawiki-radiorabe** - Simple radio station deployment
10. **mediawiki-starcitizen** - Development-focused approach

### Repositories without Docker Configurations

- **ansible-role-mediawiki** - Ansible-based deployment
- **archwiki** - Traditional server deployment
- **backup-mediawiki** - Backup utility only
- **mediawiki-manager** - Management tooling
- **meza** - Ansible-based enterprise deployment
- **mmb** - Multi-service deployment (has docker-compose for other services)
- **mw-config** - Configuration management only
- **mw-docker** - Multiple version containers (legacy approach)
- **mwExtUpgrader** - Extension management utility
- **tutorials** - Educational content only

## Base Image Analysis

### PHP-Apache Combinations
- **php:8.1-apache** (WikiDocker) - Modern PHP with integrated Apache
- **php:8.3-apache** (mediawiki-docker-ubc) - Latest PHP with Apache
- **php:7.4-apache** (mediawiki-wbstack) - Stable PHP version
- **php:8.3-fpm** (sct-docker-images) - PHP-FPM for better performance

### Specialized Base Images
- **alpine:latest** (docker-mediawiki-chiefy) - Minimal Alpine Linux
- **nginx:1.21.3** (mediawiki-docker-offspot) - Nginx-based approach
- **mediawiki:1.43.3** (docker-mediawiki-ldap) - Official MediaWiki image
- **mediawiki:1.40.4** (gcpedia) - Specific MediaWiki version

### Base Image Patterns

#### Modern PHP Versions
- **PHP 8.3**: sct-docker-images, mediawiki-docker-ubc (latest)
- **PHP 8.1**: WikiDocker (stable modern)
- **PHP 7.4**: mediawiki-wbstack (conservative stable)
- **PHP 7.3**: mediawiki-docker-offspot (older stable)

#### Web Server Approaches
- **Apache Integration**: Most repositories use php:*-apache for simplicity
- **Nginx + PHP-FPM**: docker-mediawiki-chiefy, sct-docker-images (better performance)
- **Nginx Only**: mediawiki-docker-offspot (custom PHP setup)

## Multi-Stage Build Analysis

### Advanced Multi-Stage Builds

#### gcpedia (3-stage build)
```dockerfile
FROM mediawiki:1.40.4 as base
# Install dependencies and copy extensions

FROM base as setup  
# Run setup scripts and composer install

FROM base
# Copy built artifacts from setup stage
```

#### sct-docker-images (2-stage build)
```dockerfile
FROM php:8.3-fpm AS builder
# Build dependencies, install extensions, run composer

FROM php:8.3-fpm
# Copy built application and runtime dependencies only
```

### Benefits Observed
- **Reduced image size**: Build dependencies excluded from final image
- **Security**: No build tools in production image
- **Caching**: Separate build and runtime layers
- **Reproducibility**: Consistent build environment

### Single-Stage Patterns
Most repositories use single-stage builds for simplicity:
- WikiDocker: Comprehensive single stage with all dependencies
- mediawiki-docker-ubc: Single stage with extensive extension installation
- docker-mediawiki-chiefy: Minimal Alpine-based single stage

## Container Orchestration Analysis

### Docker Compose Patterns

#### Simple Multi-Container (docker-mediawiki-chiefy)
```yaml
services:
  mediawiki: # PHP-FPM
  nginx:     # Web server
  db:        # MariaDB
```

#### Complex Multi-Service (WikiDocker)
```yaml
services:
  star-citizen.wiki-varnish:  # Caching layer
  star-citizen.wiki-live:     # MediaWiki
  ofelia:                     # Cron jobs
  db:                         # MariaDB
  elasticsearch:              # Search
  redis:                      # Cache
  jobrunner:                  # Background jobs
```

#### Development-Focused (mediawiki-docker-ubc)
```yaml
services:
  web:          # MediaWiki
  db:           # MariaDB
  adminer:      # Database admin
  idp:          # SAML identity provider
  sp:           # SAML service provider
  nodeservices: # Node.js services
  traefik:      # Reverse proxy
  ldap:         # LDAP server
  memcached:    # Caching
```

### Kubernetes Deployment (gcpedia)
- **Multi-container pods**: MediaWiki + Parsoid + Render service
- **Persistent volumes**: NFS-based storage for database and uploads
- **Service separation**: Dedicated database deployment
- **ConfigMaps**: External configuration management

## Volume Management and Persistence Strategies

### Data Persistence Patterns

#### Named Volumes
```yaml
volumes:
  mediawiki:        # Application files
  mysql-data:       # Database storage
```

#### Host Bind Mounts
```yaml
volumes:
  - "./images:/var/www/html/images"           # Upload directory
  - "./LocalSettings.php:/var/www/html/LocalSettings.php"  # Configuration
```

#### Kubernetes Persistent Volumes
```yaml
persistentVolumeClaim:
  claimName: gcpedia-data-volume-claim  # NFS-backed storage
```

### Volume Strategies by Repository

#### WikiDocker (Production)
- External configuration directory: `/etc/star-citizen.wiki/config`
- Persistent uploads: `/srv/star-citizen.wiki/images`
- Cache directory: `/var/lib/star-citizen.wiki/cache`
- Database storage: `/var/lib/star-citizen.wiki/db`

#### mediawiki-docker-ubc (Development)
- Local data directory: `./.data/web:/data`
- Configuration override: `./CustomSettings.php:/conf/CustomSettings.php`
- Shared SAML volume: `simplesamlphp:/var/www/simplesamlphp`

#### gcpedia (Enterprise)
- NFS persistent volumes for database (200Gi)
- NFS persistent volumes for uploads (2Gi)
- ConfigMap for LocalSettings.php

## Extension and Dependency Management

### Composer-Based Approach
Most modern repositories use composer for extension management:

```dockerfile
COPY composer.local.json /var/www/html/composer.local.json
RUN composer install --no-dev
```

### Direct Download Approach (mediawiki-docker-ubc)
```dockerfile
RUN EXTS=`curl https://extdist.wmflabs.org/dist/extensions/` \
    && for i in SmiteSpam VisualEditor Scribunto...; do \
      FILENAME=`echo "$EXTS" | grep ^${i}-REL${WIKI_VERSION_STR}`; \
      curl -Ls https://extdist.wmflabs.org/dist/extensions/$FILENAME | tar xz -C extensions; \
    done
```

### Git Submodules (Limited Usage)
Some repositories reference git submodules but most have moved away from this approach.

### Custom Extension Installation (mediawiki-docker-offspot)
```dockerfile
COPY ./add_mw_extension.py /usr/local/bin/add_mw_extension
RUN add_mw_extension ${MEDIAWIKI_EXT_VERSION} ${WIKI_DIR} Nuke Scribunto...
```

## PHP Configuration Patterns

### Performance Optimizations

#### OPcache Configuration (Common Pattern)
```dockerfile
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini
```

#### Memory and Execution Limits
```dockerfile
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
RUN echo 'max_execution_time = 60' >> /usr/local/etc/php/conf.d/docker-php-executiontime.ini
```

#### PHP-FPM Tuning (sct-docker-images)
```dockerfile
RUN echo 'pm.max_children = 30' >> /usr/local/etc/php-fpm.d/zz-docker.conf
RUN echo 'pm.max_requests = 200' >> /usr/local/etc/php-fpm.d/zz-docker.conf
```

### Extension Installation Patterns

#### Using install-php-extensions (Modern Approach)
```dockerfile
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    calendar exif intl mysqli zip \
    apcu luasandbox redis wikidiff2
```

#### Manual Extension Installation (Traditional)
```dockerfile
RUN docker-php-ext-install -j "$(nproc)" \
    calendar gd gmp intl mysqli opcache sockets zip
```

## Security Patterns

### User Management
```dockerfile
USER www-data  # Run as non-root user
```

### Security Options in Docker Compose
```yaml
security_opt:
  - no-new-privileges:true
```

### Minimal Attack Surface (Alpine-based)
```dockerfile
FROM alpine:latest
RUN apk add --no-cache ${PHP_PACKAGES}
```

## Network Configuration

### Custom Networks (WikiDocker)
```yaml
networks:
  star-citizen.wiki:
    external: true
  star-citizen.wiki-internal:
    external: true
```

### Service Discovery
- Internal DNS resolution between containers
- Service aliases for consistent naming
- Load balancer integration (Traefik)

## Key Findings and Patterns

### Base Image Trends
1. **Modern PHP versions** (8.1+) are becoming standard
2. **php:*-apache** images dominate for simplicity
3. **Alpine Linux** used for minimal footprint
4. **Official MediaWiki images** used as base for extensions

### Build Optimization Trends
1. **Multi-stage builds** for production deployments
2. **Layer caching** optimization with proper ordering
3. **Build argument** usage for flexibility
4. **Dependency separation** between build and runtime

### Orchestration Complexity
1. **Simple setups**: MediaWiki + Database + Cache
2. **Complex setups**: Add search, job runners, monitoring
3. **Development setups**: Include debugging and development tools
4. **Enterprise setups**: Add authentication, monitoring, backup services

### Volume Management Evolution
1. **Named volumes** for portability
2. **Bind mounts** for development
3. **Persistent volumes** for Kubernetes
4. **External storage** (NFS) for enterprise deployments

### Extension Management Maturity
1. **Composer** is the preferred method
2. **Direct downloads** still used for specific extensions
3. **Custom installation scripts** for complex setups
4. **Version pinning** for stability

## Recommendations for Our Deployment

Based on this analysis, recommendations for our MediaWiki dockerization:

### Base Image Selection
- Use **php:8.3-apache** for simplicity and modern PHP
- Consider **php:8.3-fpm + nginx** for better performance
- Implement **multi-stage builds** for production optimization

### Orchestration Strategy
- Start with **docker-compose** for initial deployment
- Plan **Kubernetes migration** path for scalability
- Implement **service separation** (web, database, cache, jobs)

### Volume Strategy
- Use **named volumes** for database persistence
- Implement **bind mounts** for configuration
- Plan **external storage** integration for uploads

### Extension Management
- Adopt **composer-based** extension management
- Implement **version pinning** for stability
- Create **custom extension** installation process

### Performance Optimization
- Implement **OPcache** configuration
- Configure **PHP-FPM** tuning parameters
- Add **Redis/Memcached** caching layer
- Consider **Varnish** for HTTP caching