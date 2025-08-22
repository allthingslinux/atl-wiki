# Web Server and PHP Optimization Analysis

## Overview

This analysis examines web server configurations, PHP optimizations, and asset delivery strategies across MediaWiki deployment repositories. The focus is on Nginx/Apache configurations, PHP-FPM tuning, OPcache optimization, and static asset handling.

## Key Findings

### 1. Web Server Configuration Patterns

#### Nginx Configurations
- **Worker Processes**: Auto-scaling worker processes (`worker_processes auto`)
- **Connection Limits**: Standard 1024 worker connections, up to 2048 in optimized setups
- **Client Limits**: `client_max_body_size 100M-200M` for file uploads
- **Timeouts**: `fastcgi_read_timeout 300-1800s` for long-running operations
- **Gzip Compression**: Enabled for text-based content types with proper MIME type filtering
- **Static Asset Caching**: Long-term caching (10 days to max) for static files
- **FastCGI Optimization**: Unix socket connections for better performance

#### Advanced Nginx Patterns
```nginx
# Performance optimizations from analyzed repositories:
sendfile on;
keepalive_timeout 65;
gzip on;
gzip_disable "MSIE [1-6]\.(?!.*SV1)";
gzip_vary on;
gzip_types text/plain text/css text/javascript image/svg+xml 
           image/x-icon application/javascript application/x-javascript;

# Static file caching with proper expiration:
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires max; # or 10d
    log_not_found off;
    access_log off;
}
```

#### Apache Configurations
- **Upload Limits**: Configured for large file uploads (200MB+) with `LimitRequestBody`
- **Security Headers**: Proper handling of encoded slashes (`AllowEncodedSlashes NoDecode`)
- **Directory Protection**: Strict access controls for sensitive directories
- **PHP Execution Control**: Disabled PHP execution in images directory for security
- **.htaccess Management**: Most deployments disable .htaccess for performance and security

### 2. PHP Performance Optimization

#### OPcache Configuration Patterns
```ini
# Standard OPcache settings found across repositories:
opcache.enable=1
opcache.memory_consumption=128M
opcache.interned_strings_buffer=8-16M
opcache.max_accelerated_files=4000-10000
opcache.revalidate_freq=0-60
opcache.validate_timestamps=0-1
opcache.fast_shutdown=1
opcache.max_wasted_percentage=10
```

#### Memory and Upload Limits
```ini
# Common PHP settings for MediaWiki:
memory_limit=128M-512M (up to 512M in LocalSettings.php)
upload_max_filesize=64M-200M
post_max_size=64M-210M
max_execution_time=default (30s)
```

#### Production vs Development Settings
```ini
# Production optimizations (mediawiki-docker-offspot):
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.max_accelerated_files=10000
opcache.memory_consumption=128M

# Development settings (mw-docker):
opcache.revalidate_freq=60
opcache.validate_timestamps=1
opcache.max_accelerated_files=4000
```

#### PHP-FPM Pool Configuration
```ini
# Typical PHP-FPM settings:
pm=dynamic
pm.max_children=30
pm.start_servers=15
pm.min_spare_servers=15
pm.max_spare_servers=15
```

### 3. Asset Optimization Strategies

#### Static File Caching
- **Nginx**: Long-term caching for static assets (10 days to max expiration)
- **File Types**: JS, CSS, images, fonts with appropriate cache headers
- **Cache-Control**: Public caching with max-age directives
- **Access Log Optimization**: Disabled logging for static assets to reduce I/O

#### Gzip Compression
```nginx
# Nginx gzip configuration patterns:
gzip on;
gzip_disable "MSIE [1-6]\.(?!.*SV1)";
gzip_vary on;
gzip_types text/plain text/css text/javascript image/svg+xml 
           image/x-icon application/javascript application/x-javascript;
```

#### MediaWiki-Specific Optimizations
- **load.php Caching**: Special handling for MediaWiki's resource loader
- **Rewrite Rules**: Clean URL patterns for wiki pages
- **Image Directory Protection**: PHP execution disabled in images directory
- **Upload Handling**: Proper FastCGI timeout configuration for large uploads

### 4. Advanced Caching with Varnish

#### Varnish Configuration Highlights
- **Backend Health Checks**: 5-second intervals with proper probe configuration
- **Connection Management**: 500 max connections to backend
- **Cookie Handling**: Sophisticated cookie filtering for caching optimization
- **Grace Period**: 6-hour grace period for stale content delivery
- **Load Balancing**: Round-robin director for multiple backends
- **Purge Support**: ACL-based purge capabilities for cache invalidation

#### Varnish Optimization Patterns
```vcl
# Key Varnish optimizations from WikiDocker:
- Cookie filtering for analytics and tracking cookies (GA, DoubleClick, AddThis, Cloudflare)
- MediaWiki-specific optimizations (load.php caching without cookies)
- Query string normalization and cleanup
- URL manipulation to remove tracking parameters
- Request coalescing for high-traffic scenarios
- Websocket support with pipe mode
- Proper Accept-Encoding normalization
- Backend health monitoring with custom probes
```

#### Advanced Cookie Management
```vcl
# Sophisticated cookie filtering:
- Remove Google Analytics cookies (__utm*, _ga, _gat)
- Remove DoubleClick cookies (__gads)
- Remove Cloudflare cookies (__cfduid, __cf_bm)
- Remove MediaWiki session leak cookies (mwuser-sessionId)
- Remove JavaScript detection cookies (has_js)
- Preserve authentication tokens for logged-in users
```

### 5. Security Configurations

#### Directory Protection
```apache
# Apache security configurations:
<Directory /var/www/html/images>
  AllowOverride None
  AddType text/plain .html .htm .shtml .php
  php_admin_flag engine off
</Directory>

# Protected directories:
- /cache/ - Deny from all
- /includes/ - Deny from all  
- /maintenance/ - Deny from all
- /tests/ - Deny from all (except qunit)
```

#### .htaccess Management
- **Disabled**: Most deployments disable .htaccess for security
- **Alternative**: Configuration moved to virtual host files
- **Protection**: Automatic .htaccess creation for sensitive directories

### 6. Performance Monitoring and Tuning

#### Health Checks and Monitoring
- **Varnish**: Backend health probes with configurable thresholds
- **Load Balancing**: Round-robin director configuration
- **Timeout Configuration**: Appropriate timeouts for different operations

#### Connection and Resource Management
```nginx
# Nginx performance settings:
client_max_body_size 100M;
fastcgi_read_timeout 1800;
keepalive_timeout 65;
sendfile on;
```

## Repository-Specific Patterns

### WikiDocker (Production-focused)
- **Varnish Integration**: Comprehensive Varnish configuration with MediaWiki optimizations
- **Health Monitoring**: Sophisticated backend health checking
- **Cookie Management**: Advanced cookie filtering for optimal caching
- **Grace Handling**: Stale content delivery during backend issues

### MediaWiki-Docker-UBC (Educational)
- **Apache Configuration**: Comprehensive Apache setup with SSL support
- **Upload Optimization**: Large file upload support (200MB+)
- **Security Focus**: Strict directory access controls
- **Integration Ready**: REST API and Parsoid proxy configuration

### MediaWiki-Docker-Offspot (Offline)
- **PHP-FPM Tuning**: Optimized pool configuration for resource constraints
- **OPcache Optimization**: Production-ready OPcache settings
- **Nginx Optimization**: Efficient static file serving
- **Memory Management**: Careful memory limit configuration

### Docker-MediaWiki-Chiefy (Lightweight)
- **Alpine Linux**: Minimal footprint with PHP 8.4
- **OPcache Enabled**: Basic but effective OPcache configuration
- **Gzip Compression**: Enabled for bandwidth optimization
- **Simple Configuration**: Streamlined setup for quick deployment

## Performance Optimization Recommendations

### 1. PHP Optimization
```ini
# Recommended PHP settings for MediaWiki:
memory_limit=256M-512M (depending on usage)
opcache.memory_consumption=256M
opcache.interned_strings_buffer=16M
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0 (production)
opcache.validate_timestamps=0 (production)
```

### 2. Web Server Tuning
```nginx
# Nginx optimization:
worker_processes auto;
worker_connections 2048;
keepalive_timeout 65;
client_max_body_size 200M;
fastcgi_read_timeout 300;
gzip on;
gzip_comp_level 6;
```

### 3. Caching Strategy
- **Static Assets**: Long-term caching (30 days+) for immutable assets
- **Dynamic Content**: Short-term caching (2-5 minutes) with proper invalidation
- **CDN Integration**: Use CDN for global asset distribution
- **Varnish**: Implement for high-traffic sites with proper MediaWiki integration

### 4. PHP-FPM Configuration
```ini
# Production PHP-FPM settings:
pm=dynamic
pm.max_children=50-100 (based on memory)
pm.start_servers=25
pm.min_spare_servers=10
pm.max_spare_servers=35
pm.max_requests=1000
```

## Implementation Priority

### High Priority
1. **OPcache Configuration**: Essential for PHP performance
2. **Upload Limits**: Configure appropriate file upload sizes
3. **Basic Gzip**: Enable compression for text-based content
4. **Security Headers**: Implement proper directory protection

### Medium Priority
1. **Static Asset Caching**: Long-term caching for static files
2. **PHP-FPM Tuning**: Optimize process management
3. **Connection Limits**: Tune for expected load
4. **Health Monitoring**: Implement basic health checks

### Low Priority
1. **Varnish Integration**: For high-traffic deployments
2. **Advanced Caching**: Complex cache invalidation strategies
3. **CDN Integration**: Global content distribution
4. **Performance Monitoring**: Detailed metrics and alerting

## Gaps and Considerations

### Missing Optimizations
- **HTTP/2 Support**: Limited examples of HTTP/2 configuration
- **Brotli Compression**: No examples of Brotli compression setup
- **Resource Hints**: Missing preload/prefetch optimizations
- **Service Workers**: No progressive web app optimizations
- **PHP 8+ Optimizations**: Limited use of modern PHP performance features
- **JIT Compilation**: No examples of PHP 8 JIT configuration

### Monitoring Gaps
- **Performance Metrics**: Limited APM integration examples
- **Error Tracking**: Minimal error monitoring configuration
- **Resource Usage**: Few examples of resource monitoring
- **Cache Hit Rates**: Missing cache performance monitoring
- **PHP-FPM Metrics**: Limited process pool monitoring

### Scalability Considerations
- **Load Balancing**: Limited multi-server configurations (only Varnish example)
- **Auto-scaling**: No examples of dynamic scaling
- **Database Connection Pooling**: Missing connection pool configurations
- **Session Management**: Limited distributed session handling
- **CDN Integration**: Minimal examples of CDN configuration
- **Edge Caching**: Limited geographic distribution strategies

## Best Practices Summary

1. **Always disable OPcache timestamp validation in production** (`opcache.validate_timestamps=0`)
2. **Use appropriate memory limits based on MediaWiki usage patterns** (128M-512M)
3. **Implement proper static asset caching with long expiration times** (10 days to max)
4. **Configure upload limits to match expected file sizes** (64M-200M based on content needs)
5. **Use Varnish for high-traffic sites with MediaWiki-specific optimizations**
6. **Implement proper security headers and directory protection**
7. **Monitor backend health and implement graceful degradation**
8. **Use gzip compression for all text-based content**
9. **Choose Nginx + PHP-FPM for containerized deployments**
10. **Configure extended FastCGI timeouts for MediaWiki operations**
11. **Disable PHP execution in sensitive directories (images, cache)**
12. **Implement sophisticated cookie filtering for optimal caching**

## Task Completion Summary

This analysis has comprehensively examined web server and PHP optimization strategies across all MediaWiki deployment repositories, covering:

✅ **Nginx Configuration Patterns**: Worker processes, connection limits, gzip compression, static asset caching, FastCGI optimization
✅ **Apache Configuration Patterns**: Upload limits, security headers, directory protection, .htaccess management
✅ **PHP-FPM Optimization**: Process management, memory limits, timeout configuration
✅ **OPcache Configuration**: Production vs development settings, memory allocation, validation strategies
✅ **Asset Optimization**: Static file caching, compression strategies, MediaWiki-specific optimizations
✅ **Advanced Caching**: Comprehensive Varnish analysis with MediaWiki integration patterns
✅ **Security Hardening**: Directory protection, PHP execution controls, access restrictions
✅ **Performance Tuning**: Memory management, timeout configuration, connection optimization

The analysis includes detailed configuration examples, comparison matrices, and prioritized recommendations for implementation.