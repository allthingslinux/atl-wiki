# Extension and Dependency Management Analysis

## Overview

This analysis examines extension and dependency management strategies across MediaWiki deployment repositories, focusing on composer vs submodules vs tarball approaches, update processes, and database migration handling.

## Key Findings

### Extension Management Strategies

#### 1. Composer-Based Management (Modern Approach)

**WikiDocker** demonstrates the most sophisticated composer approach:
```json
{
    "require": {
        "mediawiki/admin-links": "1.39.0",
        "mediawiki/citizen-skin": "dev-main",
        "mediawiki/semantic-media-wiki": "dev-master",
        "edwardspec/mediawiki-aws-s3": "v0.13.0",
        "starcitizenwiki/apiunto": "dev-develop"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "mediawiki/admin-links",
                "type": "mediawiki-extension",
                "version": "1.39.0",
                "dist": {
                    "url": "https://github.com/wikimedia/mediawiki-extensions-AdminLinks/archive/REL1_39.zip",
                    "type": "zip"
                }
            }
        }
    ]
}
```

**Benefits:**
- Version pinning and dependency resolution
- Automated installation and updates
- Support for custom repositories
- Integration with MediaWiki's merge-plugin

#### 2. Git Submodules Approach (Traditional)

**mediawiki-starcitizen** uses extensive submodules:
```properties
[submodule "extensions/AdvancedSearch"]
    path = extensions/AdvancedSearch
    url = https://github.com/wikimedia/mediawiki-extensions-AdvancedSearch
    branch = REL1_35
    ignore = dirty
```

**Benefits:**
- Direct git integration
- Specific branch/commit tracking
- No additional tooling required
- Clear version control history

**Drawbacks:**
- Manual dependency management
- Complex update procedures
- Potential for inconsistent states

#### 3. Hybrid Approaches

**mediawiki-docker-ubc** combines multiple strategies:
```json
{
    "require": {
        "mediawiki/maps": "~10.0",
        "pear/mail_mime-decode": "1.5.5.2"
    },
    "extra": {
        "merge-plugin": {
            "include": [
                "extensions/caliper/composer.json"
            ]
        }
    }
}
```

Plus environment-based extension loading:
```bash
MEDIAWIKI_EXTENSIONS=SmiteSpam,VisualEditor,WikiEditor,ParserFunctions...
```

#### 4. Script-Based Installation

**gcpedia** uses shell scripts for extension management:
```bash
EXTENSIONS=(
    "AJAXPoll"
    "CategoryWatch" 
    "TimedMediaHandler"
    "PluggableAuth"
)

for EXT in "${EXTENSIONS[@]}"; do
    git clone --depth=1 -b $MEDIAWIKI_EXT_BRANCH \
        "https://gerrit.wikimedia.org/r/mediawiki/extensions/$EXT" \
        "$WORKDIR/extensions/$EXT"
done
```

### Update Process Patterns

#### Automated Update Handling

**mediawiki-docker-ubc** shows comprehensive update automation:
```bash
# Database creation and migration
if [ ! -e "$MEDIAWIKI_SHARED/installed" ]; then
    php maintenance/install.php \
        --confpath /var/www/html \
        --dbname "$MEDIAWIKI_DB_NAME" \
        --with-extensions \
        "$MEDIAWIKI_SITE_NAME" \
        "$MEDIAWIKI_ADMIN_USER"
    
    php maintenance/update.php --quick
fi

# Conditional updates
if [ "$MEDIAWIKI_UPDATE" = 'true' ]; then
    php maintenance/update.php --quick --conf ./LocalSettings.php
fi
```

#### Composer Integration

**WikiDocker** integrates composer with MediaWiki's merge-plugin:
```json
"extra": {
    "merge-plugin": {
        "include": [
            "extensions/AbuseFilter/composer.json",
            "extensions/AWS/composer.json",
            "extensions/CirrusSearch/composer.json"
        ]
    }
}
```

#### Extension-Specific Composer Handling

**gcpedia** handles composer dependencies per extension:
```bash
COMPOSER_EXTENSIONS=(
    "OpenIDConnect"
    "PluggableAuth" 
    "TimedMediaHandler"
)

for EXT in "${COMPOSER_EXTENSIONS[@]}"; do
    cd "$WORKDIR/extensions/$EXT" && \
    composer install --no-dev --no-interaction
done
```

### Database Migration Strategies

#### Integrated Migration Process

Most repositories integrate database migrations with MediaWiki's update.php:
- Automatic schema updates
- Extension table creation
- Data migration handling
- Rollback capabilities

#### Lock File Management

**mediawiki-docker-ubc** implements update locking:
```bash
if [ ! -f "$MEDIAWIKI_SHARED/update.lock" ]; then
    touch $MEDIAWIKI_SHARED/update.lock
    php maintenance/update.php --quick
    rm $MEDIAWIKI_SHARED/update.lock
fi
```

### Version Management Approaches

#### Semantic Versioning

**WikiDocker** uses precise version constraints:
```json
"mediawiki/admin-links": "1.39.0",
"mediawiki/page-forms": "5.8",
"octfx/template-styles-extender": "1.2.2"
```

#### Branch-Based Versioning

**Submodule repositories** use branch tracking:
```properties
branch = REL1_35
branch = REL1_44
branch = main
branch = master
```

#### Development Versions

Mixed usage of development branches:
```json
"mediawiki/citizen-skin": "dev-main",
"mediawiki/semantic-media-wiki": "dev-master",
"starcitizenwiki/apiunto": "dev-develop"
```

## Comparison Matrix

| Repository | Primary Method | Version Control | Update Automation | Dependency Resolution |
|------------|----------------|-----------------|-------------------|----------------------|
| WikiDocker | Composer | Semantic + Dev | Manual | Excellent |
| mediawiki-starcitizen | Submodules | Branch-based | Manual | Manual |
| mediawiki-docker-ubc | Hybrid | Mixed | Automated | Good |
| gcpedia | Script-based | Branch-based | Semi-automated | Manual |
| archwiki | Submodules | Branch-based | Manual | Manual |
| mw-config | Extension list | Mixed | Complex | Manual |

## Best Practices Identified

### Composer Management
1. **Version pinning**: Specific versions for stability
2. **Custom repositories**: Support for non-packagist extensions
3. **Merge plugin integration**: Proper MediaWiki integration
4. **Development branches**: Careful use of dev versions

### Submodule Management
1. **Branch tracking**: Consistent branch usage
2. **Ignore dirty**: Prevent commit pollution
3. **Shallow clones**: Reduced repository size
4. **Regular updates**: Systematic update procedures

### Update Processes
1. **Automated migrations**: Integration with update.php
2. **Lock file usage**: Prevent concurrent updates
3. **Conditional updates**: Environment-based control
4. **Rollback procedures**: Safe update mechanisms

### Dependency Resolution
1. **Extension dependencies**: Proper handling of inter-extension dependencies
2. **PHP dependencies**: Composer for PHP libraries
3. **Asset management**: Frontend dependency handling
4. **Version compatibility**: MediaWiki version alignment

## Challenges Identified

### Composer Challenges
- **Custom extensions**: Not all extensions available via composer
- **Version conflicts**: Dependency resolution complexity
- **Update coordination**: Coordinating MediaWiki and extension updates
- **Development workflows**: Managing development vs production versions

### Submodule Challenges
- **Update complexity**: Manual coordination required
- **Dependency tracking**: No automatic dependency resolution
- **State management**: Risk of inconsistent states
- **Merge conflicts**: Complex conflict resolution

### Hybrid Approach Challenges
- **Complexity**: Multiple management systems
- **Consistency**: Ensuring consistent versioning
- **Documentation**: Complex setup procedures
- **Maintenance**: Multiple update procedures

## Recommendations

### Short-term Improvements
1. **Standardize on composer** where possible
2. **Implement update automation** with proper locking
3. **Add dependency validation** in CI/CD
4. **Document update procedures** clearly

### Medium-term Enhancements
1. **Create extension registry** for custom extensions
2. **Implement automated testing** for updates
3. **Add rollback mechanisms** for failed updates
4. **Standardize version constraints** across environments

### Long-term Strategy
1. **Migrate to composer-first** approach
2. **Implement semantic versioning** for all extensions
3. **Create automated update pipelines** with testing
4. **Develop extension compatibility matrix**

## Implementation Priority

### High Priority
- Composer adoption for new deployments
- Update automation implementation
- Version pinning standardization

### Medium Priority
- Submodule migration planning
- Dependency validation automation
- Update testing procedures

### Low Priority
- Custom registry development
- Advanced rollback mechanisms
- Automated compatibility testing

### Tarball/Archive-Based Management

Several repositories demonstrate direct archive installation patterns:

**mediawiki-docker-offspot** shows extensive tarball usage:
```bash
# MediaWiki core installation
curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz
tar -xz --strip-components=1 -f mediawiki.tar.gz
rm mediawiki.tar.gz

# Extension installation via ZIP archives
curl -fSL https://github.com/kolzchut/mediawiki-extensions-MetaDescriptionTag/archive/master.zip -o MetaDescriptionTag.zip
unzip MetaDescriptionTag.zip -d extensions/
mv extensions/mediawiki-extensions-MetaDescriptionTag-master extensions/MetaDescriptionTag
rm -f MetaDescriptionTag.zip
```

**mw-docker** repositories use official MediaWiki tarballs with GPG verification:
```bash
curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz
curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig
gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz
tar -x --strip-components=1 -f mediawiki.tar.gz
```

**Benefits:**
- Direct control over source
- No dependency on external package managers
- Suitable for custom/private extensions
- Simple deployment process

**Drawbacks:**
- Manual version management
- No automatic dependency resolution
- Security verification complexity
- Update process requires manual intervention

### Theme/Skin Management Patterns

#### Submodule-Based Skin Management

**mediawiki-starcitizen** includes skins in submodules:
```properties
[submodule "skins/Citizen"]
    path = skins/Citizen
    url = https://github.com/StarCitizenTools/mediawiki-skins-Citizen
    branch = main
    ignore = dirty

[submodule "skins/Vector"]
    path = skins/Vector
    url = https://github.com/wikimedia/mediawiki-skins-Vector
    branch = REL1_35
    ignore = dirty
```

#### Git Clone-Based Skin Installation

**docker-mediawiki-radiorabe** uses direct git clones:
```bash
RUN git clone --depth=1 -b $MEDIAWIKI_EXT_BRANCH \
    https://gerrit.wikimedia.org/r/mediawiki/skins/Material.git \
    /var/www/html/skins/Material
```

#### Composer-Based Skin Management

**WikiDocker** includes skins via composer:
```json
{
    "require": {
        "mediawiki/citizen-skin": "dev-main"
    }
}
```

#### Configuration-Based Skin Loading

**WikiDocker** shows modular skin configuration:
```php
# Skin Citizen
require_once "$wgWikiConfigPath/skins/citizen.php";
```

### Advanced Update Process Patterns

#### Lock-Based Update Coordination

**mediawiki-docker-ubc** implements sophisticated update locking:
```bash
# Prevent concurrent updates
if [ -e "LocalSettings.php" -a "$MEDIAWIKI_UPDATE" = 'true' -a ! -f "$MEDIAWIKI_SHARED/update.lock" ]; then
    touch $MEDIAWIKI_SHARED/update.lock
    echo >&2 'info: Running maintenance/update.php';
    php maintenance/update.php --quick --conf ./LocalSettings.php
    rm $MEDIAWIKI_SHARED/update.lock
fi
```

#### Extension-Specific Update Handling

**gcpedia** demonstrates extension-specific composer handling:
```bash
COMPOSER_EXTENSIONS=(
    "OpenIDConnect"
    "PluggableAuth" 
    "TimedMediaHandler"
)

for EXT in "${COMPOSER_EXTENSIONS[@]}"; do
    cd "$WORKDIR/extensions/$EXT" && \
    composer install --no-dev --no-interaction
done
```

#### Conditional Update Execution

**docker-mediawiki-radiorabe** shows conditional update patterns:
```bash
if [ ! -f ./extensions/SemanticMediaWiki/.smw.json ]; then
    php maintenance/update.php --skip-external-dependencies --quick
fi
```

### Database Migration Integration

#### Automated Schema Updates

Most repositories integrate database migrations seamlessly:
- **Automatic table creation** during extension installation
- **Schema updates** via update.php integration
- **Data migration** handling for version upgrades
- **Rollback capabilities** for failed updates

#### Extension Database Dependencies

**archwiki** shows proper dependency checking:
```php
if ( !$this->dbw->tableExists( 'block_target', __METHOD__ ) ) {
    $this->fatalError( "Run update.php to create the block and block_target tables." );
}
```

## Enhanced Comparison Matrix

| Repository | Extension Method | Skin Method | Update Automation | DB Migration | Version Control |
|------------|------------------|-------------|-------------------|--------------|-----------------|
| WikiDocker | Composer | Composer | Manual | Integrated | Semantic + Dev |
| mediawiki-starcitizen | Submodules | Submodules | Manual | Manual | Branch-based |
| mediawiki-docker-ubc | Hybrid | Mixed | Automated + Locking | Automated | Mixed |
| gcpedia | Script + Composer | Git Clone | Semi-automated | Integrated | Branch-based |
| archwiki | Submodules | Submodules | Manual | Manual | Branch-based |
| mw-docker | Tarball | Included | Manual | Manual | Version-pinned |
| mediawiki-docker-offspot | Tarball + ZIP | Included | Automated | Integrated | Version-pinned |
| docker-mediawiki-radiorabe | Git Clone | Git Clone | Conditional | Integrated | Branch-based |

## Updated Best Practices

### Extension Management
1. **Composer-first approach** for modern deployments
2. **Submodules for development** environments requiring git integration
3. **Tarballs for air-gapped** or highly controlled environments
4. **Hybrid approaches** for transition periods

### Update Process Automation
1. **Lock file implementation** to prevent concurrent updates
2. **Conditional execution** based on environment flags
3. **Extension-specific handling** for complex dependencies
4. **Rollback mechanisms** for failed updates

### Database Migration Handling
1. **Integrated update.php** execution with extension installation
2. **Dependency validation** before schema changes
3. **Automated table creation** during container startup
4. **Lock-based coordination** for multi-container deployments

### Theme/Skin Management
1. **Consistent versioning** with MediaWiki core
2. **Modular configuration** for easy theme switching
3. **Composer integration** where available
4. **Fallback mechanisms** for missing themes

## Conclusion

The analysis reveals a clear evolution from traditional submodule-based management toward modern composer-based approaches, with tarball methods serving specific use cases. The most successful implementations combine automated update processes with proper dependency management, while maintaining flexibility for custom extensions and development workflows. Organizations should prioritize composer adoption while maintaining backward compatibility during transition periods.

Key trends identified:
- **Composer adoption** increasing for dependency management
- **Hybrid approaches** common during transition periods
- **Automation focus** on update processes and database migrations
- **Lock-based coordination** for production deployments
- **Modular configuration** for themes and extensions