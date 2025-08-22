# Environment and Secret Management Analysis

## Overview

This analysis examines environment variable usage, secret management strategies, and 12-factor app compliance across MediaWiki deployment repositories. The findings reveal diverse approaches to configuration externalization and security practices.

## Key Findings

### 12-Factor App Compliance Patterns

#### High Compliance Repositories
- **mediawiki-docker-ubc**: Excellent 12-factor compliance with comprehensive environment variable usage
- **sct-docker-images**: Comprehensive environment variable usage for all secrets and configuration
- **WikiDocker**: Strong environment externalization with .env file pattern
- **mmb**: Comprehensive environment variable coverage with consistent naming

#### Moderate Compliance Repositories  
- **mediawiki-wbstack**: Mixed approach with some hardcoded values alongside environment variables
- **mw-config**: Complex configuration system with some environment awareness
- **mediawiki-manager**: Uses shell exports for environment configuration

#### Low Compliance Repositories
- **meza**: Template-based configuration with Ansible variables, not true 12-factor
- **docker-mediawiki-chiefy**: Minimal environment usage with hardcoded secrets

### Environment Variable Usage Patterns

#### Comprehensive Environment Configuration
**mediawiki-docker-ubc** demonstrates the most comprehensive approach:
```yaml
environment:
  MEDIAWIKI_SITE_SERVER: http://wiki.docker:8080
  MEDIAWIKI_SITE_NAME: My Awesome Wiki
  MEDIAWIKI_DB_HOST: db
  MEDIAWIKI_DB_PASSWORD: password
  MEDIAWIKI_EXTENSIONS: SmiteSpam,VisualEditor,WikiEditor...
  MEDIAWIKI_MAIN_CACHE: CACHE_MEMCACHED
  MEDIAWIKI_MEMCACHED_SERVERS: '["memcached:11211"]'
```

**WikiDocker** uses .env file pattern:
```bash
# Database credentials
MYSQL_DATABASE=scw
MYSQL_USER=scw
MYSQL_PASSWORD=scw
MYSQL_ROOT_PASSWORD=scw

# Wiki configuration
SECRET_KEY=

# Extension-specific configuration
EXT_AWS_KEY=
EXT_AWS_SECRET=
EXT_DISCORD_WEBHOOK_URL=
```

#### PHP Configuration Integration
**mediawiki-docker-ubc** shows excellent PHP integration:
```php
function loadenv($envName, $default = "") {
    return getenv($envName) ? getenv($envName) : $default;
}

$wgSitename = loadenv('MEDIAWIKI_SITE_NAME', 'MediaWiki');
$wgServer = loadenv('MEDIAWIKI_SITE_SERVER', '//localhost');
$wgDBhost = loadenv('MEDIAWIKI_DB_HOST', "db");
$wgDBpassword = loadenv('MEDIAWIKI_DB_PASSWORD', "mediawikipass");
```

### Secret Management Strategies

#### Environment Variable Approach
Most repositories use environment variables for secrets:
- Database passwords
- API keys  
- Webhook URLs
- Encryption keys

**mmb** demonstrates comprehensive environment variable usage:
```yaml
environment:
  - WG_DB_SERVER=${WG_DB_SERVER}
  - WG_DB_NAME=${WG_DB_NAME}
  - WG_DB_USER=${WG_DB_USER}
  - WG_DB_PASSWORD=${WG_DB_PASSWORD}
  - WG_SITENAME=${WG_SITENAME}
  - WG_EMERGENCY_CONTACT=${WG_EMERGENCY_CONTACT}
  - CREDENTIALS=${CREDENTIALS}
```

**sct-docker-images** shows comprehensive environment variable usage for all secrets:
```php
$wgSecretKey = getenv( 'MEDIAWIKI_SECRETKEY' );
$wgUpgradeKey = getenv( 'MEDIAWIKI_UPGRADEKEY' );
$wgDBpassword = getenv( 'PRD_DB_PASSWORD' );
$wgAWSCredentials = [
    'key' => getenv( 'IMAGES_ACCESS_KEY' ),
    'secret' => getenv( 'IMAGES_SECRET_KEY' ),
];
$wgDiscordWebhookURL = [ getenv( 'DISCORD_WEBHOOKURL' ) ];
```

#### Shell Export Pattern
**mediawiki-manager** uses shell export pattern:
```bash
export MEDIAWIKI_IMAGE=docker.io/dataspects/mediawiki:1.35.0-2104141705
export SYSTEM_DOMAIN_NAME=localhost
export MARIADB_ROOT_PASSWORD=123456
export MARIADB_FOLDER_ON_HOSTING_SYSTEM="/home/$LOCALUSER/mariadb_data"
```

#### Template-Based Configuration
**meza** uses Ansible template-based configuration with complex variable substitution:
```php
$wikiId = getenv( $mezaWikiEnvVarName );
// Complex template-based configuration with Ansible variables
```

#### File-Based Secrets
**mw-config** uses dedicated private settings file:
```php
// Load PrivateSettings (e.g. $wgDBpassword)
require_once '/srv/mediawiki/config/PrivateSettings.php';
```

#### Hardcoded Values (Anti-Pattern)
**docker-mediawiki-chiefy** shows poor practices with hardcoded secrets:
```php
$wgDBpassword = "mediawiki";
$wgSecretKey = "asdfasdfasdf";
$wgUpgradeKey = "asdfasdfasdf";
```

#### Docker Secrets (Limited Usage)
No repositories demonstrated Docker secrets usage, indicating an opportunity for improvement.

### Configuration Externalization Patterns

#### Hierarchical Configuration
**WikiDocker** demonstrates modular configuration:
```php
$wgWikiConfigPath = __DIR__ . '/../config';

# Extensions
require_once "$wgWikiConfigPath/extensions/load_extensions.php";
require_once "$wgWikiConfigPath/extensions/config/abuse_filter.php";
require_once "$wgWikiConfigPath/extensions/config/aws.php";
```

#### Environment-Specific Configuration
**mediawiki-docker-ubc** shows environment-aware configuration:
```php
// setup debug environment
if (filter_var(loadenv('DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
    error_reporting(-1);
    ini_set('display_errors', 1);
    $wgShowExceptionDetails = true;
    $wgDebugLogFile = "/tmp/mw-debug-{$wgDBname}.log";
}
```

#### Multi-Wiki Configuration
**meza** demonstrates complex multi-wiki configuration:
```php
// get $wikiId from environment variable or URI
$wikiId = $wgCommandLineMode ? getenv('WIKI') : strtolower($uriParts[1]);

// Dynamic wiki selection and configuration loading
if (!in_array($wikiId, $wikis)) {
    die("No sir, I ain't heard'a no wiki that goes by the name \"$wikiId\"\n");
}
```

#### Infrastructure as Code Configuration
**meza** uses Ansible templates for configuration management:
```yaml
# Extension configuration via YAML
list:
  - name: Semantic MediaWiki
    composer: "mediawiki/semantic-media-wiki"
    version: "3.2.2"
    config: |
      enableSemantics( $wikiId );
      $smwgQMaxSize = 5000;
```

#### Container-Native Configuration
**mmb** shows container-native environment variable usage:
```yaml
environment:
  - PHP_INI_post_max_size=${PHP_INI_post_max_size}
  - PHP_INI_upload_max_filesize=${PHP_INI_upload_max_filesize}
  - ALLOW_ACCOUNT_CREATION=${ALLOW_ACCOUNT_CREATION}
  - METRIC_COUNTER=${METRIC_COUNTER}
```

### Security Practices

#### Credential Protection
- **Good**: Environment variables for database credentials (mmb, mediawiki-docker-ubc, WikiDocker)
- **Good**: Separate files for private settings (mw-config)
- **Good**: Shell export patterns for deployment (mediawiki-manager)
- **Poor**: Hardcoded secrets in configuration files (docker-mediawiki-chiefy)
- **Missing**: Docker secrets usage across all repositories
- **Missing**: Credential rotation mechanisms

#### Secret Scanning Prevention
Limited evidence of practices to prevent secrets in version control:
- .env-example files (WikiDocker, docker-mediawiki-chiefy)
- Separate private settings files (mw-config)
- Shell export files outside version control (mediawiki-manager)

#### Security Anti-Patterns Identified
- **Hardcoded secrets**: docker-mediawiki-chiefy has hardcoded passwords and keys
- **Weak secrets**: Use of simple passwords like "mediawiki" and "asdfasdfasdf"
- **No secret validation**: Missing validation for required environment variables

## Comparison Matrix

| Repository | 12-Factor Score | Env Vars Usage | Secret Management | Config Externalization |
|------------|----------------|----------------|-------------------|------------------------|
| mediawiki-docker-ubc | 9/10 | Comprehensive | Environment vars | Excellent |
| sct-docker-images | 8/10 | Comprehensive | Environment vars | Good |
| WikiDocker | 8/10 | Extensive | .env + environment | Good |
| mmb | 8/10 | Comprehensive | Environment vars | Good |
| gcpedia | 6/10 | Basic | Environment vars | Moderate |
| mediawiki-wbstack | 5/10 | Mixed | Environment vars | Basic |
| mw-config | 4/10 | Limited | Private files | Complex |
| meza | 3/10 | Template-based | Ansible variables | Complex |
| docker-mediawiki-chiefy | 2/10 | Minimal | Hardcoded values | Poor |
| mediawiki-manager | 6/10 | Shell exports | Environment files | Moderate |

## Best Practices Identified

### Environment Variable Patterns
1. **Comprehensive coverage**: All configuration should be externalized (mediawiki-docker-ubc, mmb)
2. **Default values**: Provide sensible defaults with fallbacks (mediawiki-docker-ubc)
3. **Type conversion**: Proper boolean and numeric conversion (mediawiki-docker-ubc)
4. **Validation**: Environment variable validation and error handling
5. **Consistent naming**: Use consistent prefixes for related variables (mmb: WG_*)

### Secret Management
1. **No hardcoded secrets**: All secrets via environment or files
2. **Separate example files**: .env-example pattern for documentation (WikiDocker, docker-mediawiki-chiefy)
3. **Private settings files**: Separate files for sensitive configuration (mw-config)
4. **Environment-specific secrets**: Different secrets per environment
5. **Shell export patterns**: Use shell exports for deployment automation (mediawiki-manager)

### Configuration Organization
1. **Modular structure**: Separate files for different concerns (WikiDocker, meza)
2. **Hierarchical loading**: Base + environment-specific overrides (meza)
3. **Extension-specific config**: Separate configuration per extension (WikiDocker, meza)
4. **Environment detection**: Automatic environment detection and configuration (meza)
5. **Multi-wiki support**: Dynamic wiki selection based on environment (meza)
6. **Infrastructure as Code**: Template-based configuration management (meza)

## Recommendations

### Immediate Improvements
1. **Adopt Docker secrets** for sensitive data in production
2. **Implement secret rotation** mechanisms
3. **Add configuration validation** with clear error messages
4. **Use structured logging** for configuration debugging
5. **Eliminate hardcoded secrets** (critical for docker-mediawiki-chiefy pattern)
6. **Standardize environment variable naming** across deployments

### Security Enhancements
1. **Secret scanning tools** in CI/CD pipelines to prevent hardcoded secrets
2. **Encrypted configuration** for highly sensitive data
3. **Audit logging** for configuration changes
4. **Principle of least privilege** for configuration access
5. **Strong secret generation** and validation
6. **Environment variable validation** with required/optional checks

### Operational Improvements
1. **Configuration templates** for consistent deployment (learn from meza)
2. **Environment parity** between development and production
3. **Configuration documentation** with examples (.env-example pattern)
4. **Health checks** for configuration validity
5. **Multi-environment support** with proper isolation
6. **Automated configuration deployment** using Infrastructure as Code

### Architecture Patterns to Adopt
1. **Helper functions** for environment variable loading (mediawiki-docker-ubc pattern)
2. **Modular configuration** with separate files per concern (WikiDocker pattern)
3. **Environment-aware debugging** configuration (mediawiki-docker-ubc pattern)
4. **Multi-wiki configuration** for scalable deployments (meza pattern)
5. **Container-native configuration** with comprehensive environment variables (mmb pattern)

## Implementation Priority

### High Priority
- Environment variable standardization
- Secret management improvement
- Configuration validation

### Medium Priority
- Docker secrets adoption
- Configuration templates
- Audit logging

### Low Priority
- Advanced encryption
- Automated rotation
- Configuration UI

## Conclusion

The analysis reveals a wide spectrum of 12-factor app compliance across MediaWiki deployment repositories. Leading implementations like **mediawiki-docker-ubc**, **mmb**, and **WikiDocker** demonstrate excellent practices with comprehensive environment variable usage, proper secret management, and modular configuration organization. 

However, significant gaps exist in repositories like **docker-mediawiki-chiefy** which still use hardcoded secrets, and **meza** which relies on complex template-based configuration rather than true environment externalization.

Key success factors identified:
- **Comprehensive environment variable coverage** for all configuration
- **Helper functions** for environment variable loading with defaults
- **Modular configuration structure** separating concerns
- **Proper secret management** avoiding hardcoded values
- **Environment-aware configuration** for different deployment stages

The analysis provides a clear roadmap for improving 12-factor compliance and security practices in MediaWiki deployments, with specific patterns and anti-patterns identified for implementation guidance.