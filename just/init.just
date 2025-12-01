# === Setup Environment Scripts ===

prod-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."

    just _copy-file production-compose.yaml.example compose.yaml
    just _copy-file .env.example .env
    just sitemap-prod

staging-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."

    just _copy-file staging-compose.yaml.example compose.yaml
    just _copy-file .env.example .env
    just sitemap-staging

local-files:
    #!/usr/bin/env bash
    set -euo pipefail
    just just _destructive-warning "This will override your compose.yaml and .env files if they exist. Please back them up if needed."
    
    just _copy-file local-compose.yaml.example compose.yaml
    just _copy-file .env.local.example .env

mediawiki-init:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will reset Mediawiki and your database if already initialized. Only run this on new installs of Mediawiki."
    just _verify-file .env
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2 | tr -d '"')
    DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"')
    if [ -z "$DB_USER" ] || [ -z "$DB_PASSWORD" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mRequired variables not found in .env (DB_USER, DB_PASSWORD)\033[0m\n"
        exit 1
    fi

    ADMIN_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-20)
    echo "Generated admin password: $ADMIN_PASSWORD"

    docker exec ${ENV}-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/run.php installPreConfigured \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASSWORD}" \
      --pass="${ADMIN_PASSWORD}" \
      "admin"

    just clean-restart

    docker exec ${ENV}-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/update.php --quick

