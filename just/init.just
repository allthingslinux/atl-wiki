# === Setup Environment Scripts ===

# Setup production environment files and configure sitemap service
production-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."

    echo "Setting up production environment files..."
    just _copy-file compose.production.yaml.example compose.yaml
    just _copy-file .env.example .env
    just sitemap-production
    echo "Created .env from .env.example and compose.yaml from production-compose.yaml.example. Please review and modify as needed."

# Setup staging environment files and configure sitemap service
staging-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."

    echo "Setting up staging environment files..."
    just _copy-file compose.staging.yaml.example compose.yaml
    just _copy-file .env.example .env
    just sitemap-staging
    echo "Created .env from .env.example and compose.yaml from staging-compose.yaml.example. Please review and modify as needed."

# Setup local development environment files
local-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."

    echo "Setting up local development environment files..."
    just _copy-file compose.local.yaml.example compose.yaml
    just _copy-file .env.local.example .env
    echo "Created .env from .env.local.example and compose.yaml from local-compose.yaml.example. Please review and modify as needed."

# Initialize MediaWiki database and create admin user
mediawiki-init:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will reset Mediawiki and your database if already initialized. Only run this on new installs of Mediawiki."
    just _verify-file .env
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    # Extract database credentials from .env
    DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2 | tr -d '"')
    DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"')
    if [ -z "$DB_USER" ] || [ -z "$DB_PASSWORD" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mRequired variables not found in .env (DB_USER, DB_PASSWORD)\033[0m\n"
        exit 1
    fi

    # Generate secure random admin password
    ADMIN_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-20)
    echo ""
    echo "=========================================="
    echo "IMPORTANT: Save this admin password now! It won't be shown again."
    echo "Admin password: $ADMIN_PASSWORD"
    echo "This is to login to MediaWiki as the initial admin."
    echo "=========================================="
    echo ""

    # Run MediaWiki pre-configured installation
    docker exec ${ENV}-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/run.php installPreConfigured \
      --dbuser="${DB_USER}" --dbpass="${DB_PASSWORD}"

    just clean-restart

    # Run database schema updates
    docker exec ${ENV}-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/update.php --quick

