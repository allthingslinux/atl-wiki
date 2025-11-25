import 'just/docker.just'
import 'just/base.just'

# Show available recipes
default:
    @just --list

# Restart the local wiki after an update (stops, pulls, rebuilds, starts)
update-local:
    docker compose down -v
    sudo git pull
    just copy-file local-compose.yaml.example compose.yaml
    docker compose -f compose.yaml up -d --build

# === Configuration Setup ===

# Setup local environment (copies local compose and env files)
setup-local: (copy-file "local-compose.yaml.example" "compose.yaml") env-local

# Initialize local wiki (configure nginx and database - assumes containers are already running)
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

# Copy local environment to .env
env-local: (copy-file ".env.local.example" ".env")

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
