# Service Architecture Analysis

## Overview

This document analyzes service architecture patterns across MediaWiki deployment repositories, examining multi-container vs single-container approaches, database separation strategies, service distribution patterns, and network configuration approaches.

## Service Architecture Classification

### Single-Container Deployments

#### Simple Single-Container Pattern
**Repositories**: docker-mediawiki-ldap, some tutorial examples

**Architecture**:
```
┌─────────────────────────────┐
│     Single Container        │
│  ┌─────────┬─────────────┐  │
│  │MediaWiki│   Database  │  │
│  │   +     │   (SQLite)  │  │
│  │ Apache  │             │  │
│  └─────────┴─────────────┘  │
└─────────────────────────────┘
```

**Characteristics**:
- All services in one container
- SQLite database (file-based)
- Minimal external dependencies
- Suitable for development/testing only

#### Enhanced Single-Container Pattern
**Repositories**: WikiDocker (main container), mediawiki-docker-offspot

**Architecture**:
```
┌─────────────────────────────────────┐
│        Enhanced Container           │
│  ┌─────────┬─────────┬───────────┐  │
│  │MediaWiki│ Apache  │ PHP-FPM   │  │
│  │   +     │   +     │     +     │  │
│  │Extensions│ Nginx   │ Cron Jobs │  │
│  └─────────┴─────────┴───────────┘  │
└─────────────────────────────────────┘
```

**Characteristics**:
- Multiple services within single container
- External database connection
- Job processing capabilities
- Production-ready with external dependencies

### Multi-Container Deployments

#### Basic Multi-Container Pattern
**Repositories**: docker-mediawiki-chiefy, docker-mediawiki-radiorabe, gcpedia

**Architecture**:
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Nginx     │    │  MediaWiki  │    │   MariaDB   │
│             │◄──►│  (PHP-FPM)  │◄──►│             │
│ Web Server  │    │             │    │  Database   │
└─────────────┘    └─────────────┘    └─────────────┘
```

**Services**:
- **Web Server**: Nginx (reverse proxy, static files)
- **Application**: MediaWiki with PHP-FPM
- **Database**: MariaDB/MySQL

#### Complex Multi-Service Pattern
**Repositories**: WikiDocker, mediawiki-docker-ubc, mediawiki-wbstack

**Architecture**:
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Varnish   │    │  MediaWiki  │    │   MariaDB   │
│   Cache     │◄──►│ Application │◄──►│  Database   │
└─────────────┘    └─────────────┘    └─────────────┘
       │                   │                   │
       │            ┌─────────────┐    ┌─────────────┐
       │            │    Redis    │    │Elasticsearch│
       │            │   Cache     │    │   Search    │
       │            └─────────────┘    └─────────────┘
       │                   │
┌─────────────┐    ┌─────────────┐
│ Job Runner  │    │    Cron     │
│ Background  │    │ Scheduler   │
└─────────────┘    └─────────────┘
```

**Services**:
- **Caching Layer**: Varnish (HTTP cache)
- **Application Cache**: Redis/Memcached
- **Search Engine**: Elasticsearch
- **Background Processing**: Dedicated job runners
- **Scheduling**: Cron service (Ofelia)

#### Enterprise Multi-Service Pattern
**Repositories**: mediawiki-docker-ubc (development), gcpedia (Kubernetes)

**Architecture**:
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Traefik   │    │  MediaWiki  │    │   MariaDB   │
│Load Balancer│◄──►│ Application │◄──►│  Primary    │
└─────────────┘    └─────────────┘    └─────────────┘
       │                   │                   │
       │            ┌─────────────┐    ┌─────────────┐
       │            │    SAML     │    │   MariaDB   │
       │            │ Auth (IdP)  │    │  Replica    │
       │            └─────────────┘    └─────────────┘
       │                   │
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    LDAP     │    │ Memcached   │    │ Node.js     │
│ Directory   │    │   Cache     │    │ Services    │
└─────────────┘    └─────────────┘    └─────────────┘
```

**Additional Services**:
- **Authentication**: SAML IdP/SP, LDAP
- **Load Balancing**: Traefik with service discovery
- **Database Replication**: Master-replica setup
- **Microservices**: Node.js services for specific functions

## Database Separation Strategies

### Same-Container Database
**Pattern**: SQLite within application container
**Repositories**: Simple tutorial examples, development setups

**Pros**:
- Simplest deployment
- No network configuration needed
- Atomic container deployment

**Cons**:
- Not suitable for production
- No horizontal scaling
- Data persistence issues
- Performance limitations

### Separate Container Database
**Pattern**: Dedicated database container
**Repositories**: Most production deployments

**Implementation Examples**:

#### Basic Separation (docker-mediawiki-chiefy)
```yaml
services:
  mediawiki:
    depends_on: [db]
    environment:
      DB_HOST: db
  db:
    image: mariadb:latest
    environment:
      MYSQL_DATABASE: mediawiki
```

#### Advanced Separation with Replication (mediawiki-wbstack)
```yaml
services:
  mysql:
    image: mariadb:10.5
    # Master database
  mysql-replica:
    image: mariadb:10.5
    depends_on: [mysql]
    # Read replica
```

**Configuration Patterns**:
- **Environment Variables**: Database connection parameters
- **Service Discovery**: Container name resolution
- **Health Checks**: Database readiness verification
- **Volume Persistence**: Separate data volumes

### External Database Service
**Pattern**: Database outside container orchestration
**Repositories**: Enterprise deployments, cloud-native setups

**Characteristics**:
- Managed database services (RDS, Cloud SQL)
- Dedicated database servers
- External connection configuration
- Enhanced backup and monitoring

## Service Distribution Strategies

### Horizontal Service Distribution

#### Load Balancer + Multiple App Instances
**Repository**: WikiDocker (production setup)

```yaml
services:
  varnish:
    # HTTP cache and load balancer
  mediawiki-1:
    # Application instance 1
  mediawiki-2:
    # Application instance 2
  shared-db:
    # Shared database
  shared-cache:
    # Shared Redis cache
```

#### Microservices Architecture
**Repository**: mediawiki-docker-ubc

**Service Breakdown**:
- **Web Application**: Core MediaWiki
- **Authentication Service**: SAML IdP/SP
- **Node Services**: Parsoid, RESTBase alternatives
- **Directory Service**: LDAP
- **Proxy Service**: Traefik with service discovery

### Vertical Service Distribution

#### Layered Architecture (WikiDocker)
```
┌─────────────────────────────────────┐
│           Presentation Layer        │
│  ┌─────────────┐  ┌─────────────┐   │
│  │   Varnish   │  │   Traefik   │   │
│  └─────────────┘  └─────────────┘   │
└─────────────────────────────────────┘
┌─────────────────────────────────────┐
│          Application Layer          │
│  ┌─────────────┐  ┌─────────────┐   │
│  │  MediaWiki  │  │ Job Runner  │   │
│  └─────────────┘  └─────────────┘   │
└─────────────────────────────────────┘
┌─────────────────────────────────────┐
│            Data Layer               │
│  ┌─────────────┐  ┌─────────────┐   │
│  │   MariaDB   │  │    Redis    │   │
│  └─────────────┘  └─────────────┘   │
└─────────────────────────────────────┘
```

#### Specialized Service Roles

**Cache Services**:
- **HTTP Cache**: Varnish (WikiDocker)
- **Application Cache**: Redis (WikiDocker, mediawiki-wbstack)
- **Session Cache**: Memcached (mediawiki-docker-ubc)

**Search Services**:
- **Elasticsearch**: Full-text search (WikiDocker, mediawiki-wbstack)
- **CirrusSearch**: MediaWiki search extension integration

**Background Processing**:
- **Job Runners**: Dedicated containers for background tasks
- **Cron Services**: Scheduled task execution (Ofelia)

## Network Configuration Approaches

### Default Bridge Networks
**Pattern**: Docker Compose default networking
**Repositories**: Simple deployments

```yaml
services:
  mediawiki:
    # Automatically joins default network
  db:
    # Accessible via service name 'db'
```

**Characteristics**:
- Automatic service discovery
- Container name DNS resolution
- Single network segment
- Suitable for simple deployments

### Custom Networks
**Pattern**: Explicit network definition
**Repositories**: WikiDocker, complex deployments

```yaml
networks:
  frontend:
    # Public-facing services
  backend:
    # Internal services only
  
services:
  varnish:
    networks: [frontend, backend]
  mediawiki:
    networks: [backend]
  database:
    networks: [backend]
```

**Benefits**:
- Network segmentation
- Security isolation
- Traffic control
- Service grouping

### External Networks
**Pattern**: Pre-created networks
**Repository**: WikiDocker

```yaml
networks:
  star-citizen.wiki:
    external: true
  star-citizen.wiki-internal:
    external: true
```

**Use Cases**:
- Integration with existing infrastructure
- Shared networks across multiple stacks
- External service access
- Network policy enforcement

### Advanced Network Patterns

#### Multi-Network Service Placement (WikiDocker)
```yaml
services:
  varnish:
    networks:
      star-citizen.wiki:
        ipv4_address: 172.16.0.2  # Public network
  mediawiki:
    networks:
      star-citizen.wiki:
        ipv4_address: 172.16.0.3  # Public network
      star-citizen.wiki-internal:
        ipv4_address: 10.16.0.3   # Internal network
```

**Benefits**:
- Static IP assignment
- Network isolation
- Service accessibility control

#### Service Mesh Integration
**Repository**: Advanced Kubernetes deployments

**Patterns**:
- Sidecar proxies for service communication
- Traffic encryption between services
- Observability and monitoring integration
- Policy enforcement at network level

## Inter-Service Communication Patterns

### HTTP-Based Communication
**Pattern**: REST API communication
**Examples**: MediaWiki ↔ Parsoid, MediaWiki ↔ RESTBase

```yaml
environment:
  PARSOID_DOMAIN: localhost
  RESTBASE_URL: http://nodeservices:7231
```

### Database Connection Patterns
**Pattern**: Direct database connections
**Configuration**:

```yaml
environment:
  MEDIAWIKI_DB_HOST: db
  MEDIAWIKI_DB_PASSWORD: password
  MW_DB_SERVER_MASTER: mysql.svc:3306
  MW_DB_SERVER_REPLICA: mysql-replica.svc:3306
```

### Cache Communication
**Pattern**: Redis/Memcached protocols
**Examples**:

```yaml
environment:
  MEDIAWIKI_REDIS_HOST: redis
  MEDIAWIKI_MEMCACHED_SERVERS: '["memcached:11211"]'
```

### Message Queue Patterns
**Pattern**: Asynchronous job processing
**Implementation**: Job runners with shared database queue

## Service Discovery Mechanisms

### DNS-Based Discovery
**Pattern**: Container name resolution
**Implementation**: Docker's built-in DNS

```yaml
services:
  mediawiki:
    environment:
      DB_HOST: database  # Resolves to database container IP
  database:
    # Accessible via 'database' hostname
```

### Service Registry Pattern
**Pattern**: External service registry
**Implementation**: Consul, etcd integration

### Load Balancer Discovery
**Pattern**: Dynamic backend registration
**Example**: Traefik with Docker provider

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.wiki.rule=PathPrefix(`/`)"
```

## Kubernetes Service Architecture

### Pod Design Patterns (gcpedia)

#### Multi-Container Pods
```yaml
spec:
  containers:
  - name: gcpedia        # Main MediaWiki application
  - name: parsoid        # Parsoid service
  - name: render         # Electron render service
```

**Benefits**:
- Shared storage volumes
- Localhost communication
- Atomic deployment unit
- Resource sharing

#### Separate Service Deployments
```yaml
# MediaWiki deployment
apiVersion: apps/v1beta1
kind: Deployment
metadata:
  name: gcpedia-deployment

# Database deployment  
apiVersion: apps/v1beta1
kind: Deployment
metadata:
  name: gcpedia-db-deployment
```

### Service Types and Exposure

#### ClusterIP Services (Internal)
```yaml
apiVersion: v1
kind: Service
metadata:
  name: gcpedia-db
spec:
  clusterIP: None  # Headless service
  selector:
    app: gcpedia-db
```

#### LoadBalancer Services (External)
```yaml
apiVersion: v1
kind: Service
metadata:
  name: gcpedia
  annotations:
    VIRTUAL_HOST: "gcpedia.gctools.nrc.ca"
spec:
  ports:
    - protocol: TCP
      port: 80
```

## Performance and Scalability Patterns

### Horizontal Scaling Strategies

#### Stateless Application Scaling
- Multiple MediaWiki instances behind load balancer
- Shared database and cache layers
- Session storage in external cache (Redis)

#### Database Scaling
- Read replicas for query distribution
- Connection pooling
- Query optimization

### Vertical Scaling Considerations

#### Resource Allocation
```yaml
resources:
  requests:
    memory: "512Mi"
    cpu: "500m"
  limits:
    memory: "1Gi" 
    cpu: "1000m"
```

#### Performance Optimization
- PHP-FPM process management
- OPcache configuration
- Database connection limits

## Security Architecture Patterns

### Network Security

#### Network Segmentation
- Frontend networks for public services
- Backend networks for internal communication
- Database networks with restricted access

#### Service Isolation
```yaml
security_opt:
  - no-new-privileges:true
```

### Authentication Architecture

#### Centralized Authentication (mediawiki-docker-ubc)
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  MediaWiki  │◄──►│    SAML     │◄──►│    LDAP     │
│             │    │   IdP/SP    │    │ Directory   │
└─────────────┘    └─────────────┘    └─────────────┘
```

#### External Authentication Integration
- SAML 2.0 for federated authentication
- LDAP for user directory services
- OAuth for API access

## Key Architectural Findings

### Single vs Multi-Container Trade-offs

**Single Container Advantages**:
- Simpler deployment and management
- Reduced network complexity
- Atomic updates
- Lower resource overhead

**Multi-Container Advantages**:
- Better separation of concerns
- Independent scaling
- Technology diversity
- Fault isolation
- Easier maintenance

### Database Separation Benefits
1. **Performance**: Dedicated database resources
2. **Scalability**: Independent database scaling
3. **Backup**: Separate backup strategies
4. **Security**: Database access control
5. **Maintenance**: Independent database updates

### Service Distribution Patterns
1. **Microservices**: Fine-grained service separation
2. **Layered Architecture**: Logical service grouping
3. **Shared Services**: Common infrastructure components
4. **Specialized Services**: Purpose-built components

### Network Architecture Evolution
1. **Simple**: Default bridge networks
2. **Segmented**: Custom networks for isolation
3. **External**: Integration with existing infrastructure
4. **Service Mesh**: Advanced traffic management

## Recommendations for Our Architecture

### Service Architecture Strategy
1. **Start Simple**: Begin with multi-container approach (MediaWiki + Database + Cache)
2. **Plan Growth**: Design for horizontal scaling
3. **Separate Concerns**: Isolate database, cache, and application services
4. **Add Complexity Gradually**: Introduce additional services as needed

### Database Strategy
1. **Separate Database Container**: Immediate implementation
2. **Plan Replication**: Design for read replicas
3. **External Database Path**: Plan migration to managed database service
4. **Backup Strategy**: Implement container-aware backup solutions

### Network Design
1. **Custom Networks**: Implement network segmentation from start
2. **Service Discovery**: Use DNS-based discovery initially
3. **Load Balancer Ready**: Design for load balancer integration
4. **Security First**: Implement network isolation patterns

### Scaling Preparation
1. **Stateless Design**: Ensure application statelessness
2. **Shared Storage**: Plan for shared file storage
3. **Cache Strategy**: Implement external caching from start
4. **Monitoring**: Design observability into architecture