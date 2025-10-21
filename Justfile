# Show available recipes
default:
    @just --list

# === Docker Management ===

# Start the application
start:
    docker compose up -d --build

# Stop the application
stop:
    docker compose down

# Restart the production wiki after an update (stops, pulls, rebuilds, starts)
update-prod:
    docker compose down -v
    sudo git pull
    just copy-file production-compose.yaml.example compose.yaml
    docker compose up -d --build

# Restart the staging wiki after an update (stops, pulls, rebuilds, starts)
update-staging:
    docker compose down -v
    sudo git pull
    just copy-file staging-compose.yaml.example compose.yaml
    docker compose up -d --build

# Restart the local wiki after an update (stops, pulls, rebuilds, starts)
update-local:
    docker compose down -v
    sudo git pull
    just copy-file local-compose.yaml.example compose.yaml
    docker compose -f compose.yaml up -d --build

# === Configuration Setup ===

# Setup production environment (copies prod compose and env files, installs prod systemd)
setup-prod: (copy-file "production-compose.yaml.example" "compose.yaml") env sitemap-prod

# Setup staging environment (copies staging compose and env files, installs staging systemd)
setup-staging: (copy-file "staging-compose.yaml.example" "compose.yaml") env sitemap-staging

# Setup local environment (copies local compose and env files)
setup-local: (copy-file "local-compose.yaml.example" "compose.yaml") env-local

# Initialize local wiki (configure database - assumes containers are already running)
init-local:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Setting up MediaWiki database..."
    docker exec local-atlwiki-mediawiki mv /var/www/atlwiki/mediawiki/LocalSettings.php /var/www/atlwiki/mediawiki/LocalSettings.php.bak

    docker exec local-atlwiki-mediawiki php /var/www/atlwiki/mediawiki/maintenance/install.php \
      --dbtype=mysql \
      --dbserver=mariadb \
      --dbname=local-maria-db \
      --dbuser=local-maria-user \
      --dbpass=local-maria-password \
      --dbprefix=mw_ \
      --server="http://localhost:3000" \
      --scriptpath="" \
      --lang=en \
      --pass=AdminPassword123! \
      "ATL Wiki" \
      "admin"

    echo "Restoring custom LocalSettings.php..."
    docker exec local-atlwiki-mediawiki rm /var/www/atlwiki/mediawiki/LocalSettings.php
    docker exec local-atlwiki-mediawiki mv /var/www/atlwiki/mediawiki/LocalSettings.php.bak /var/www/atlwiki/mediawiki/LocalSettings.php

    echo ""
    echo "✓ Local wiki initialization complete!"
    echo "Access your wiki at: http://localhost:3000"
    echo "User Login credentials: admin / AdminPassword123!"
    echo ""

    echo "Restarting containers to apply changes..."
    docker compose down
    docker compose up -d --build
    echo "Containers restarted."

    echo "Updating MediaWiki database schema..."
    docker exec local-atlwiki-mediawiki php /var/www/atlwiki/mediawiki/maintenance/update.php --quick
    echo "✓ Database schema update complete!"

# Copy environment example to .env
env: (copy-file ".example.env" ".env")

# Copy local environment to .env
env-local: (copy-file ".env.local.example" ".env")

# === System Services ===

# Setup production sitemap systemd timer
sitemap-prod:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Installing production systemd service files..."
    sudo cp systemd/wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/wiki-sitemap.timer /etc/systemd/system/

    echo "Reloading systemd daemon..."
    sudo systemctl daemon-reload

    echo "Enabling and starting production sitemap timer..."
    sudo systemctl enable wiki-sitemap.timer
    sudo systemctl start wiki-sitemap.timer

    echo "Production sitemap timer setup complete!"

# Setup staging sitemap systemd timer
sitemap-staging:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Installing staging systemd service files..."
    sudo cp systemd/staging-wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/staging-wiki-sitemap.timer /etc/systemd/system/

    echo "Reloading systemd daemon..."
    sudo systemctl daemon-reload

    echo "Enabling and starting staging sitemap timer..."
    sudo systemctl enable staging-wiki-sitemap.timer
    sudo systemctl start staging-wiki-sitemap.timer

    echo "Staging sitemap timer setup complete!"

# === Utility Functions ===

# Copy a file (DO NOT RUN MANUALLY)
copy-file src dst:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC="{{src}}"
    DST="{{dst}}"

    if [ ! -f "$SRC" ]; then
        echo "Error: source '$SRC' not found." >&2
        exit 1
    fi

    cp -v "$SRC" "$DST"
    echo "Successfully copied $SRC to $DST"
