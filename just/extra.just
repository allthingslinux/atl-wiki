# === Status ===

# Display comprehensive status of wiki deployment including containers, health, and database
status:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Verifying compose.yaml..."
    just _verify-file compose.yaml
    echo "compose.yaml exists."

    echo "Detecting environment..."
    ENV=$(just _detect-env)
    echo "Environment: $ENV"

    echo "Verifying containers online..."
    just _check-containers
    echo "Containers are running"

    echo "Container Status:"
    docker-compose ps

    echo "Verifying container health..."
    just health

    echo "Verifying database connectivity..."
    just db-status

# Check health status of all wiki containers
health:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    echo "Checking container health for environment: $ENV"

    # Find containers without healthy status
    UNHEALTHY_CONTAINERS=$(docker ps --filter "name=${ENV}-wiki-" --format "{{{{.Names}}}}\t{{{{.Status}}}}" | grep -v "healthy" | grep -v "Up")

    if [ -n "$UNHEALTHY_CONTAINERS" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mUnhealthy containers detected:\033[0m\n"
        echo "$UNHEALTHY_CONTAINERS"
        exit 1
    fi

    # Display healthy containers
    CONTAINERS_WITH_HEALTH=$(docker ps --filter "name=${ENV}-wiki-" --format "{{{{.Names}}}}\t{{{{.Status}}}}" | grep "health")

    if [ -n "$CONTAINERS_WITH_HEALTH" ]; then
        echo "All containers with health checks are healthy:"
        echo "$CONTAINERS_WITH_HEALTH"
    fi

# Verify database connectivity for the wiki
db-status:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    echo "Checking database connectivity for environment: $ENV"

    # Local environment uses containerized MariaDB
    if [ "$ENV" = "local" ]; then
        if ! docker ps --format '{{{{.Names}}}}' | grep -q "^local-wiki-mariadb$"; then
            echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mDatabase container 'local-wiki-mariadb' does not exist or is not running.\033[0m\n"
            exit 1
        fi

        if docker exec local-wiki-mediawiki nc -zv local-wiki-mariadb 3306 2>&1; then
            echo "Database container is reachable from mediawiki"
        else
            echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mFailed to reach database container from mediawiki.\033[0m\n"
            exit 1
        fi
    else
        # Staging/production use external database from .env
        DB_SERVER=$(grep "^DB_SERVER=" .env | cut -d'=' -f2 | tr -d '"')

        if nc -zv "$DB_SERVER" 3306 2>&1 | grep -q "succeeded"; then
            echo "Database server is reachable at $DB_SERVER:3306"
        else
            echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mDatabase server is unreachable at $DB_SERVER:3306.\033[0m\n"
            exit 1
        fi
    fi

# === System Services ===

# Setup production sitemap generation as a systemd timer service
sitemap-prod:
    #!/usr/bin/env bash
    set -euo pipefail
    just _check-sudo

    echo "Installing production systemd service files..."
    sudo cp systemd/wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/wiki-sitemap.timer /etc/systemd/system/

    echo "Reloading systemd daemon..."
    sudo systemctl daemon-reload

    echo "Enabling and starting production sitemap timer..."
    sudo systemctl enable wiki-sitemap.timer
    sudo systemctl start wiki-sitemap.timer

    echo "Production sitemap timer setup complete!"

# Setup staging sitemap generation as a systemd timer service
sitemap-staging:
    #!/usr/bin/env bash
    set -euo pipefail
    just _check-sudo

    echo "Installing staging systemd service files..."
    sudo cp systemd/staging-wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/staging-wiki-sitemap.timer /etc/systemd/system/

    echo "Reloading systemd daemon..."
    sudo systemctl daemon-reload

    echo "Enabling and starting staging sitemap timer..."
    sudo systemctl enable staging-wiki-sitemap.timer
    sudo systemctl start staging-wiki-sitemap.timer

    echo "Staging sitemap timer setup complete!"

# Update MediaWiki database schema to latest version
mediawiki-schema-update:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will update the MediaWiki database schema to the latest version. Ensure you have a backup before proceeding."
    just _verify-file .env
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    # Run database schema updates
    docker exec ${ENV}-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/update.php --quick
    echo "MediaWiki database schema update complete!"
