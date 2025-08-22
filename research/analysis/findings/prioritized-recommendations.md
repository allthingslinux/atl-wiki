# Prioritized Recommendations for MediaWiki Dockerization

## Executive Summary

Based on comprehensive analysis of 20 MediaWiki deployment repositories and assessment of our current multi-VPS architecture, this document provides prioritized, actionable recommendations for modernizing our MediaWiki deployment. The recommendations are organized by implementation priority, considering impact, complexity, and risk factors.

## Recommendation Prioritization Framework

**Priority Levels:**
- **P0 (Critical)**: Essential for basic containerized deployment
- **P1 (High)**: Significant impact with manageable complexity
- **P2 (Medium)**: Important improvements with moderate effort
- **P3 (Low)**: Nice-to-have features for future consideration

**Evaluation Criteria:**
- **Impact**: Performance, security, and operational improvements
- **Complexity**: Implementation effort and technical challenges
- **Risk**: Migration risks and potential for issues
- **Dependencies**: Prerequisites and sequencing requirements

## P0 (Critical Priority) Recommendations

### P0.1: Implement Multi-Container Docker Architecture

**Objective**: Establish containerized deployment foundation
**Timeline**: Weeks 1-4
**Impact**: High - Enables all subsequent modernization efforts
**Complexity**: Medium - Well-established patterns available

**Implementation Strategy:**
```yaml
services:
  mediawiki:
    image: php:8.3-apache
    environment:
      - MEDIAWIKI_DB_HOST=database
      - MEDIAWIKI_DB_NAME=mediawiki
    volumes:
      - mediawiki_data:/var/www/html
      - ./configs:/var/www/html/configs:ro
    depends_on:
      - database
      - redis

  database:
    image: mariadb:10.11
    environment:
      - MYSQL_DATABASE=mediawiki
      - MYSQL_USER=mediawiki
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
```

**Key Benefits:**
- Service isolation and independent scaling
- Environment consistency across development/staging/production
- Simplified deployment and rollback procedures
- Foundation for all performance and security improvements

**Migration Approach:**
1. **Week 1**: Set up development Docker environment
2. **Week 2**: Containerize MediaWiki application with current configuration
3. **Week 3**: Implement database and cache containers
4. **Week 4**: Test complete multi-container setup

**Success Metrics:**
- Successful container startup and service communication
- MediaWiki functionality equivalent to current deployment
- Database connectivity and data persistence
- Configuration management through environment variables

### P0.2: Establish Environment-Based Configuration Management

**Objective**: Replace file-based configuration with environment variables
**Timeline**: Weeks 2-3 (parallel with P0.1)
**Impact**: High - Enables proper containerization and deployment automation
**Complexity**: Low - Direct translation of existing configuration

**Implementation Strategy:**
```php
// Replace hardcoded values with environment variables
$wgDBserver = getenv('MEDIAWIKI_DB_HOST') ?: 'localhost';
$wgDBname = getenv('MEDIAWIKI_DB_NAME') ?: 'mediawiki';
$wgDBuser = getenv('MEDIAWIKI_DB_USER') ?: 'mediawiki';
$wgDBpassword = getenv('MEDIAWIKI_DB_PASSWORD') ?: '';

// CDN configuration from environment
$wgUseCdn = filter_var(getenv('MEDIAWIKI_USE_CDN'), FILTER_VALIDATE_BOOLEAN);
$wgServer = getenv('MEDIAWIKI_SERVER') ?: 'https://atl.wiki';
```

**Configuration Migration Plan:**
1. **Current Config Analysis**: Map all hardcoded values in configs/*.php
2. **Environment Variable Design**: Create comprehensive environment variable schema
3. **Configuration Templates**: Develop environment-aware configuration templates
4. **Validation**: Implement configuration validation and defaults

**Key Benefits:**
- 12-factor app compliance for better deployment practices
- Environment-specific configuration without code changes
- Simplified secret management and rotation
- Reduced configuration drift between environments

### P0.3: Implement Basic Health Checks and Monitoring

**Objective**: Establish container health monitoring and basic observability
**Timeline**: Week 3-4
**Impact**: High - Critical for production reliability
**Complexity**: Low - Standard Docker health check patterns

**Implementation Strategy:**
```yaml
services:
  mediawiki:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api.php?action=query&meta=siteinfo"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

  database:
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 30s
      timeout: 10s
      retries: 3

  redis:
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 30s
      timeout: 10s
      retries: 3
```

**Monitoring Components:**
- Container health status monitoring
- Basic application availability checks
- Database connectivity verification
- Cache service availability
- Log aggregation setup

**Key Benefits:**
- Automated failure detection and recovery
- Container orchestration reliability
- Foundation for advanced monitoring
- Operational visibility into system health

## P1 (High Priority) Recommendations

### P1.1: Implement Redis Object Caching

**Objective**: Replace file-based caching with Redis for performance improvement
**Timeline**: Weeks 4-5
**Impact**: High - 60-90% reduction in database load
**Complexity**: Low - Well-documented MediaWiki integration

**Implementation Strategy:**
```php
// MediaWiki Redis configuration
$wgObjectCaches['redis'] = [
    'class' => 'RedisBagOStuff',
    'servers' => [ getenv('REDIS_HOST') . ':6379' ],
    'password' => getenv('REDIS_PASSWORD'),
    'persistent' => false,
    'database' => 0,
];

$wgMainCacheType = 'redis';
$wgSessionCacheType = 'redis';
$wgMessageCacheType = 'redis';
$wgParserCacheType = 'redis';
```

**Performance Benefits:**
- Persistent object caching across requests
- Shared cache between multiple application instances
- Session storage for horizontal scaling preparation
- Reduced database query load

**Implementation Steps:**
1. **Redis Container Setup**: Deploy Redis container with persistence
2. **MediaWiki Integration**: Configure MediaWiki to use Redis
3. **Cache Warming**: Implement cache warming strategies
4. **Performance Testing**: Validate performance improvements

### P1.2: Database Performance Optimization

**Objective**: Optimize MySQL/MariaDB for MediaWiki workloads
**Timeline**: Weeks 5-6
**Impact**: High - 50-80% query performance improvement
**Complexity**: Medium - Requires database tuning expertise

**Implementation Strategy:**
```ini
# MariaDB optimization for MediaWiki
[mysqld]
# InnoDB Configuration
innodb_buffer_pool_size = 2G  # 70-80% of available RAM
innodb_log_file_size = 256M
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 2

# Query Cache (if using MySQL 5.7)
query_cache_type = 1
query_cache_size = 128M

# Connection Settings
max_connections = 200
thread_cache_size = 16

# MediaWiki-specific optimizations
sql_mode = ""
```

**Database Optimization Areas:**
1. **InnoDB Buffer Pool**: Size to 70-80% of available RAM
2. **Query Optimization**: Analyze and optimize slow queries
3. **Index Analysis**: Review and optimize MediaWiki table indexes
4. **Connection Pooling**: Implement proper connection management

**Performance Monitoring:**
- Buffer pool hit ratio (target: >95%)
- Query execution time analysis
- Connection usage patterns
- Database lock contention monitoring

### P1.3: Implement Automated Dependency Management

**Objective**: Establish automated security updates and dependency management
**Timeline**: Weeks 6-7
**Impact**: High - Reduces security patch lag from weeks to hours
**Complexity**: Low - GitHub Dependabot integration

**Implementation Strategy:**
```yaml
# .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "docker"
    directory: "/"
    schedule:
      interval: "daily"
    open-pull-requests-limit: 10

  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "daily"
    open-pull-requests-limit: 5

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

**Security Automation Components:**
- Automated dependency updates via Dependabot
- Container vulnerability scanning with Trivy
- Security policy documentation
- Automated security patch approval for minor updates

**Key Benefits:**
- Proactive security vulnerability management
- Reduced manual update overhead
- Consistent dependency management
- Improved security posture

## P2 (Medium Priority) Recommendations

### P2.1: Implement Nginx + PHP-FPM Architecture

**Objective**: Replace Apache with Nginx + PHP-FPM for better performance
**Timeline**: Weeks 8-10
**Impact**: Medium - 30-50% better concurrent request handling
**Complexity**: Medium - Requires web server reconfiguration

**Implementation Strategy:**
```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - mediawiki_data:/var/www/html:ro
    depends_on:
      - php-fpm

  php-fpm:
    build:
      context: .
      dockerfile: Dockerfile.php-fpm
    volumes:
      - mediawiki_data:/var/www/html
    environment:
      - PHP_FPM_PM_MAX_CHILDREN=30
      - PHP_FPM_PM_MAX_REQUESTS=200
```

**Nginx Configuration Highlights:**
```nginx
# Optimized for MediaWiki
location ~ \.php$ {
    fastcgi_pass php-fpm:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

# Static asset optimization
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

**Performance Benefits:**
- Better resource utilization and concurrent request handling
- Improved static asset serving
- More efficient PHP process management
- Better separation of concerns between web server and PHP

### P2.2: Implement Comprehensive Performance Monitoring

**Objective**: Establish detailed performance monitoring and alerting
**Timeline**: Weeks 10-12
**Impact**: Medium - Enables performance optimization and issue detection
**Complexity**: Medium - Requires monitoring stack setup

**Implementation Strategy:**
```yaml
services:
  prometheus:
    image: prom/prometheus
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus

  grafana:
    image: grafana/grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - grafana_data:/var/lib/grafana

  node-exporter:
    image: prom/node-exporter
    
  mysql-exporter:
    image: prom/mysqld-exporter
    environment:
      - DATA_SOURCE_NAME=exporter:password@(database:3306)/
```

**Monitoring Metrics:**
- Application response times and throughput
- Database performance (query time, buffer pool hit ratio)
- Cache hit rates (Redis, OPcache)
- System resources (CPU, memory, disk I/O)
- Container health and restart counts

**Alerting Rules:**
- High response times (>2 seconds)
- Database connection issues
- Cache service failures
- High resource utilization (>80%)
- Container restart loops

### P2.3: Implement Container Security Hardening

**Objective**: Enhance container security with scanning and hardening
**Timeline**: Weeks 12-14
**Impact**: Medium - Improved security posture and compliance
**Complexity**: Medium - Security tooling integration

**Implementation Strategy:**
```yaml
# GitHub Actions security workflow
name: Security Scan
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: 'mediawiki:latest'
          format: 'sarif'
          output: 'trivy-results.sarif'
      - name: Upload Trivy scan results
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: 'trivy-results.sarif'
```

**Security Hardening Measures:**
- Container vulnerability scanning with Trivy
- Non-root user execution in containers
- Read-only root filesystems where possible
- Security context constraints
- Network policy implementation
- Secret management with Docker secrets

## P3 (Low Priority) Recommendations

### P3.1: Implement Varnish HTTP Caching

**Objective**: Add HTTP-level caching for high-traffic scenarios
**Timeline**: Weeks 16-18
**Impact**: High (for high traffic) - Significant performance improvement
**Complexity**: High - Requires VCL configuration expertise

**Implementation Considerations:**
- Complex VCL configuration for MediaWiki compatibility
- Cache invalidation strategy for dynamic content
- Integration with existing CDN (Cloudflare)
- Monitoring and debugging capabilities

**When to Implement:**
- Traffic exceeds current capacity
- Response time requirements become critical
- CDN costs become significant

### P3.2: Kubernetes Migration Path

**Objective**: Prepare for Kubernetes deployment for advanced scaling
**Timeline**: Weeks 20-24
**Impact**: Medium - Enables advanced orchestration features
**Complexity**: High - Requires Kubernetes expertise

**Migration Considerations:**
- Helm chart development
- Persistent volume management
- Service mesh integration
- Advanced networking requirements
- Operational complexity increase

**Prerequisites:**
- Successful Docker Compose deployment
- Operational experience with containers
- Scaling requirements that exceed single-host capabilities

## Implementation Timeline and Roadmap

### Phase 1: Foundation (Weeks 1-7)
**Objective**: Establish containerized deployment with basic optimization

**Week 1-2**: Docker Environment Setup
- Development environment containerization
- Basic multi-container architecture
- Configuration management implementation

**Week 3-4**: Health Monitoring and Testing
- Health check implementation
- Basic monitoring setup
- Integration testing

**Week 5-6**: Performance Foundation
- Redis caching implementation
- Database optimization
- Performance baseline establishment

**Week 7**: Security Foundation
- Dependabot setup
- Basic security scanning
- Security policy documentation

**Deliverables:**
- Working Docker Compose deployment
- Environment-based configuration
- Redis object caching
- Basic monitoring and health checks
- Automated dependency updates

### Phase 2: Optimization (Weeks 8-14)
**Objective**: Performance optimization and enhanced monitoring

**Week 8-10**: Web Server Optimization
- Nginx + PHP-FPM implementation
- Static asset optimization
- Performance testing and validation

**Week 11-12**: Monitoring Enhancement
- Comprehensive monitoring stack
- Performance dashboards
- Alerting configuration

**Week 13-14**: Security Hardening
- Container security scanning
- Security hardening implementation
- Security monitoring setup

**Deliverables:**
- Optimized web server architecture
- Comprehensive monitoring and alerting
- Enhanced security posture
- Performance benchmarks and optimization

### Phase 3: Production Deployment (Weeks 15-18)
**Objective**: Production migration and stabilization

**Week 15-16**: Production Environment Setup
- Production infrastructure provisioning
- Staging environment validation
- Migration procedure testing

**Week 17**: Production Migration
- Blue-green deployment execution
- Data migration and validation
- DNS and CDN reconfiguration

**Week 18**: Stabilization and Optimization
- Performance monitoring and tuning
- Issue resolution and optimization
- Documentation and runbook completion

**Deliverables:**
- Production containerized deployment
- Migrated data and configurations
- Operational documentation
- Performance validation

## Risk Mitigation Strategies

### High-Risk Mitigation

**Data Loss Prevention:**
- Comprehensive backup strategy before migration
- Database replication during transition
- Rollback procedures for each phase
- Data validation at each migration step

**Service Availability:**
- Blue-green deployment strategy
- Parallel infrastructure during migration
- Health check validation before traffic switching
- Immediate rollback capabilities

**Performance Regression:**
- Baseline performance measurement
- Load testing at each phase
- Performance monitoring during migration
- Capacity planning and resource allocation

### Medium-Risk Mitigation

**Configuration Management:**
- Configuration validation tools
- Environment parity testing
- Gradual configuration migration
- Rollback procedures for configuration changes

**Security Vulnerabilities:**
- Security scanning at each phase
- Vulnerability assessment before production
- Security monitoring implementation
- Incident response procedures

**Operational Complexity:**
- Comprehensive documentation
- Team training and knowledge transfer
- Operational runbooks and procedures
- Monitoring and alerting setup

## Success Metrics and KPIs

### Performance Metrics
- **Response Time**: Target <500ms for 95th percentile
- **Throughput**: Support current traffic + 50% growth
- **Database Performance**: Buffer pool hit ratio >95%
- **Cache Hit Rate**: Redis cache hit rate >80%
- **Availability**: 99.9% uptime target

### Operational Metrics
- **Deployment Time**: Reduce from hours to minutes
- **Recovery Time**: Reduce MTTR by 75%
- **Security Patch Time**: Reduce from weeks to hours
- **Configuration Drift**: Eliminate through automation

### Business Metrics
- **Infrastructure Costs**: Optimize resource utilization
- **Maintenance Overhead**: Reduce manual operations by 80%
- **Scalability**: Enable horizontal scaling capabilities
- **Developer Productivity**: Faster development and deployment cycles

## Conclusion

This prioritized recommendation framework provides a structured approach to modernizing our MediaWiki deployment through containerization and performance optimization. The phased approach minimizes risks while delivering incremental value at each stage.

**Key Success Factors:**
1. **Start with Foundation**: Establish containerization before optimization
2. **Measure Everything**: Implement monitoring early and continuously
3. **Automate Gradually**: Build automation capabilities incrementally
4. **Plan for Scale**: Design architecture for future growth
5. **Prioritize Security**: Integrate security throughout the process

The recommendations balance immediate needs with long-term strategic goals, providing a clear path from our current multi-VPS architecture to a modern, scalable, and secure containerized deployment that follows industry best practices identified through comprehensive analysis of the MediaWiki ecosystem.