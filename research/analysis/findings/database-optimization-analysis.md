# Database Optimization Patterns Analysis

## Overview

This analysis examines database optimization strategies, tuning approaches, and persistence patterns across MediaWiki deployment repositories. The focus is on MySQL/MariaDB configurations, backup strategies, and performance optimization techniques.

## Key Findings

### 1. Database Configuration Management

#### Configuration Approaches
- **Template-based Configuration**: Meza uses Jinja2 templates (`my.cnf.j2`) for systematic configuration management
- **Environment Variable Configuration**: MMB project uses dynamic configuration via environment variables (MYSQLD_*)
- **Runtime Configuration**: Several projects modify settings at runtime rather than static files

#### Configuration Patterns
```yaml
# Common configuration categories identified:
memory_settings:
  - key_buffer_size
  - innodb_buffer_pool_size
  - query_cache_size
  - max_connections
  - tmp_table_size

performance_settings:
  - performance_schema
  - query_cache_limit
  - thread_cache_size
  - innodb_log_file_size

replication_settings:
  - server_id
  - log_bin
  - read_only
  - binlog_format
```

### 2. Memory Optimization Strategies

#### InnoDB Buffer Pool Sizing
- **Meza**: Default 256M with recommendation to set up to 80% of RAM
- **Best Practice**: Buffer pool size should be 25% of log file size ratio
- **Monitoring**: Load monitoring checks `Innodb_buffer_pool_pages_data/total` ratio

#### Query Cache Configuration
- **Default Settings**: 16M cache size with 1M limit (Meza)
- **Deprecation Note**: Query cache is deprecated in newer MySQL versions
- **Alternative**: Focus on application-level caching (Redis/Memcached)

#### Connection Management
- **Default**: 151 max connections (Meza)
- **High-load**: Up to 1000 connections (WBStack replica setup)
- **Varnish**: 500 max connections to backend

### 3. Storage Engine Optimization

#### InnoDB Configuration Patterns
```ini
# Common InnoDB optimizations found:
innodb_file_per_table = 1
innodb_buffer_pool_size = 256M-80% of RAM
innodb_log_file_size = 64M (25% of buffer pool)
innodb_log_buffer_size = 8M
innodb_flush_log_at_trx_commit = 1
innodb_lock_wait_timeout = 50
```

#### File Format and Prefix Support
- **Large Prefix Support**: Enabled where supported for longer index keys
- **File Format**: Barracuda format for compressed tables
- **File Per Table**: Universally enabled for better space management

### 4. Backup and Persistence Strategies

#### Backup Approaches
1. **mysqldump with Compression**:
   ```bash
   # Standard approach with XZ compression
   mysqldump --single-transaction -u $USER --password=$PASS $DB | xz > backup.xz
   
   # Gzip compression (faster, larger files)
   mysqldump --opt -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE} | gzip > backup.sql.gz
   ```

2. **Combined Backup Strategy** (backup-mediawiki):
   - SQL dump with XZ compression for maximum space efficiency
   - File system backup with tar compression
   - Combined archive creation with `--remove-files` for cleanup
   - Automatic credential extraction from LocalSettings.php
   - Previous backup preservation with `.old` suffix
   - Comprehensive error handling with exit codes

3. **Container-based Backup**:
   - Dedicated backup containers (schnitzler/mysqldump)
   - Scheduled execution via cron or container orchestration
   - Volume mounting for backup storage
   - Environment variable configuration

4. **Volume-based Persistence**:
   - Docker volumes for `/var/lib/mysql` data persistence
   - Host directory mounting for direct access
   - Separate backup volumes for restore operations
   - Health check integration for backup validation

#### Advanced Backup Patterns
1. **Automated Credential Management**:
   ```bash
   # Extract credentials from LocalSettings.php
   DB_SERV=`grep "wgDBserver" $WIKI_WEB_DIR/LocalSettings.php | cut -d\" -f2`
   DB_NAME=`grep "wgDBname" $WIKI_WEB_DIR/LocalSettings.php | cut -d\" -f2`
   DB_LOGIN=`grep "wgDBuser" $WIKI_WEB_DIR/LocalSettings.php | cut -d\" -f2`
   DB_PASS=`grep '^\$wgDBpassword' $WIKI_WEB_DIR/LocalSettings.php | cut -d\" -f2`
   ```

2. **Backup Validation and Error Handling**:
   ```bash
   # Validate mysqldump success
   MySQL_RET_CODE=$?
   if [ $MySQL_RET_CODE != 0 ]; then
       echo "MySQL Dump failed! (return code: $MySQL_RET_CODE)"
       exit 3
   fi
   ```

3. **Cron-based Maintenance**:
   ```bash
   # Weekly SQLite maintenance (offspot pattern)
   echo "mysqldump --databases ${DATABASE_NAME} > ${DATA_DIR}/${DATABASE_NAME}.sql" > /etc/cron.weekly/MySQLDump
   chmod 0500 /etc/cron.weekly/MySQLDump
   ```

#### Backup Best Practices Identified
- **Single Transaction**: `--single-transaction` ensures consistency during backup
- **Compression**: XZ compression for space efficiency, gzip for speed
- **Retention Management**: Configurable retention periods (14 days default)
- **Archive Versioning**: Previous backup preservation for rollback capability
- **Process Priority**: `nice -n 19` for low-priority background processing
- **Validation**: Return code checking and error handling
- **Security**: Credential extraction from configuration files
- **Automation**: Container-based and cron-based scheduling

### 5. Replication and High Availability

#### Master-Slave Configuration Patterns
1. **Basic Replication Setup** (Meza):
   ```ini
   # Master configuration
   server-id = 1
   log_bin = mysql-bin
   log-bin-index = mysql-bin.index
   expire_logs_days = 2
   max_binlog_size = 100M
   binlog_format = ROW
   
   # Selective database replication
   binlog_do_db = mediawiki_db1
   binlog_do_db = mediawiki_db2
   ```

2. **Read Replica Configuration** (WBStack):
   ```ini
   # Replica configuration
   server-id = 2
   log-bin = mysql-bin
   log-slave-updates = 1
   read-only = 1
   slave-skip-errors = 1062  # Skip duplicate key errors
   
   # Auto-increment management for multi-master
   auto_increment_increment = 2
   auto_increment_offset = 2
   ```

3. **Performance-Optimized Master** (WBStack):
   ```ini
   # Master with performance tuning
   server-id = 1
   log-bin = mysql-bin
   log-slave-updates = 1
   innodb_flush_log_at_trx_commit = 2  # Performance over durability
   innodb_flush_method = O_DIRECT      # Direct I/O for better performance
   ```

#### Replication Safety and Best Practices
- **Wikimedia Practice**: Set `read_only=1` on all servers by default, enable writes at runtime
- **Selective Replication**: Database-specific replication rules with `binlog_do_db`
- **Error Handling**: `slave-skip-errors = 1062` for duplicate key tolerance
- **Network Optimization**: `skip-host-cache` and `skip-name-resolve` for performance
- **Monitoring Integration**: Slave status monitoring and lag detection

#### High Availability Patterns
1. **Automated Failover**: Replica promotion scripts and monitoring
2. **Load Balancing**: Read traffic distribution across replicas
3. **Connection Management**: High connection limits (1000+) for replica servers
4. **Backup Integration**: Replica-based backup strategies to reduce master load

### 6. Performance Monitoring and Tuning

#### Monitoring Approaches
- **Performance Schema**: Enabled for detailed performance metrics
- **Load Monitoring**: Buffer pool utilization tracking
- **Connection Monitoring**: Max connections and usage tracking

#### Tuning Parameters by Use Case
```yaml
small_deployment:
  innodb_buffer_pool_size: "256M"
  max_connections: "151"
  query_cache_size: "16M"

medium_deployment:
  innodb_buffer_pool_size: "1G-2G"
  max_connections: "500"
  query_cache_size: "32M"

large_deployment:
  innodb_buffer_pool_size: "4G+"
  max_connections: "1000"
  query_cache_size: "disabled" # Use application caching
```

## Repository-Specific Patterns

### Meza (Enterprise-focused)
- **Comprehensive Configuration Management**: Uses Jinja2 templates (`my.cnf.j2`) with Ansible for systematic configuration
- **Memory Optimization**: Default 256M buffer pool with recommendation to set up to 80% of RAM
- **Replication Support**: Full master-slave configuration with selective database replication
- **Performance Monitoring**: Performance schema enabled, buffer pool utilization tracking
- **InnoDB Optimization**: Large prefix support, Barracuda file format, file-per-table enabled

**Key Configuration Values**:
```yaml
mysql_innodb_buffer_pool_size: "256M"  # Up to 80% of RAM
mysql_innodb_log_file_size: "64M"      # 25% of buffer pool
mysql_max_connections: "151"
mysql_query_cache_size: "16M"
mysql_innodb_flush_log_at_trx_commit: "1"  # ACID compliance
```

### MMB (Container-focused)
- **Dynamic Configuration**: Runtime modification via environment variables (MYSQLD_*)
- **Container Optimization**: Custom entrypoint script for configuration management
- **Simplified Deployment**: Docker-native approach with minimal configuration files
- **Configuration Utility**: Python script (`change_ini_param.py`) for INI file management
- **Security**: Automatic password generation with pwgen

### WikiDocker (Production-focused)
- **Health Monitoring**: Comprehensive health checks with `--su-mysql --connect --innodb_initialized`
- **Volume Persistence**: Dedicated volumes for `/var/lib/mysql`
- **Network Security**: Isolated database networks
- **Integration**: Seamless integration with Redis caching layers
- **Engine Specification**: Explicit InnoDB engine with binary charset

### WBStack (Multi-tenant)
- **Read Scaling**: Dedicated replica configuration with `read-only=1`
- **High Availability**: Master-slave replication with automatic failover
- **Performance Tuning**: Optimized for high-load scenarios (1000+ connections)
- **Replication Safety**: `slave-skip-errors = 1062` for duplicate key handling
- **Auto-increment Management**: Offset configuration for multi-master scenarios

**Replica Configuration**:
```ini
[mysqld]
server-id=2
log-bin=mysql-bin
read-only=1
slave-skip-errors = 1062
auto_increment_increment=2
auto_increment_offset=2
```

### Backup-MediaWiki (Backup-focused)
- **Comprehensive Backup Strategy**: Combined SQL dump and file system backup
- **Compression Optimization**: XZ compression for maximum space efficiency
- **Consistency Guarantee**: `--single-transaction` for consistent backups
- **Automated Extraction**: Parses database credentials from LocalSettings.php
- **Archive Management**: Retention and versioning with old backup preservation
- **Error Handling**: Comprehensive exit codes and validation

**Backup Command Pattern**:
```bash
mysqldump --single-transaction -u $USER --password=$PASS $DB | xz > backup.xz
```

### Docker-MediaWiki-Radiorabe (Backup Integration)
- **Scheduled Backups**: Automated backup container with retention policies
- **Backup Retention**: Configurable retention periods (14 days default)
- **Volume Management**: Separate backup volumes for restore operations
- **Compression Strategy**: Gzip compression for SQL dumps and tar archives

## Recommendations

### 1. Configuration Management
- **Template-based Configuration**: Use Jinja2 templates (Meza pattern) for systematic configuration management
- **Environment Variables**: Implement MMB-style dynamic configuration via environment variables
- **Runtime Monitoring**: Enable performance schema and implement buffer pool utilization tracking
- **Documentation**: Document all performance-related settings with rationale and expected values

### 2. Memory Optimization
- **Buffer Pool Sizing**: Set InnoDB buffer pool to 70-80% of available RAM (Meza recommendation)
- **Log File Ratio**: Configure log file size to 25% of buffer pool size for optimal performance
- **Query Cache**: Disable query cache on MySQL 8.0+, focus on application-level caching
- **Connection Management**: Configure appropriate limits based on load (151 for small, 1000+ for high-load)

### 3. Backup Strategy
- **Comprehensive Approach**: Implement backup-mediawiki pattern with SQL dump + file system backup
- **Compression**: Use XZ compression for space efficiency, gzip for speed requirements
- **Consistency**: Always use `--single-transaction` for consistent backups
- **Automation**: Implement container-based backup scheduling with retention policies
- **Validation**: Include error handling and backup validation in all scripts

### 4. Performance Tuning
- **InnoDB Optimization**: Enable file-per-table, large prefix support, and Barracuda file format
- **I/O Configuration**: Use `innodb_flush_method = O_DIRECT` for performance-critical deployments
- **Network Optimization**: Enable `skip-host-cache` and `skip-name-resolve`
- **Monitoring**: Implement comprehensive health checks and performance monitoring

### 5. High Availability and Replication
- **Read Replicas**: Configure WBStack-style read replicas for scaling
- **Safety First**: Use `read_only=1` by default on all servers (Wikimedia practice)
- **Error Tolerance**: Configure `slave-skip-errors = 1062` for duplicate key handling
- **Load Distribution**: Implement proper load balancing across read replicas

### 6. Container Integration
- **Health Checks**: Implement WikiDocker-style comprehensive health monitoring
- **Volume Management**: Use dedicated volumes for data persistence and backup storage
- **Network Security**: Isolate database networks and implement proper access controls
- **Credential Management**: Automate credential extraction and management

## Implementation Priority

1. **High Priority**: Basic InnoDB optimization and backup strategy
2. **Medium Priority**: Replication setup and monitoring
3. **Low Priority**: Advanced performance tuning and multi-master setup

## Gaps and Considerations

### Current Limitations
- **Automated Performance Tuning**: Limited examples of dynamic performance optimization based on workload
- **Connection Pooling**: Few implementations of proper connection pooling (PgBouncer/ProxySQL patterns)
- **Database Sharding**: Minimal examples of horizontal scaling strategies
- **Disaster Recovery**: Limited comprehensive disaster recovery documentation and testing procedures

### Emerging Patterns
- **Container-Native Approaches**: Growing adoption of container-based database management
- **Environment-Driven Configuration**: Shift towards environment variable-based configuration
- **Automated Backup Validation**: Integration of backup validation into CI/CD pipelines
- **Multi-Tenant Optimization**: Specialized configurations for multi-tenant MediaWiki deployments

### Security Considerations
- **Credential Management**: Need for better secret management integration (Vault, Docker Secrets)
- **Network Security**: Database network isolation and access control patterns
- **Backup Security**: Encryption and secure storage of backup archives
- **Audit Logging**: Performance impact and configuration of audit logging

### Performance Monitoring Gaps
- **Real-time Metrics**: Limited integration with modern monitoring stacks (Prometheus, Grafana)
- **Automated Alerting**: Few examples of proactive performance alerting
- **Capacity Planning**: Limited automated capacity planning and scaling recommendations
- **Query Performance**: Minimal query performance monitoring and optimization examples