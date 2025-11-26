import 'just/docker.just'
import 'just/base.just'

# Show available recipes
default:
    @just --list

# Restart the local wiki after an update (stops, pulls, rebuilds, starts)
update-local:
    docker compose down -v
    sudo git pull
    just copy-file deployment/local-compose.yaml.example compose.yaml
    docker compose -f compose.yaml up -d --build

# === Configuration Setup ===

# Setup local environment (copies local compose and env files)
setup-local: (copy-file "deployment/local-compose.yaml.example" "compose.yaml") env-local

# Initialize local wiki (configure nginx and database - assumes containers are already running)
init-local:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Setting up MediaWiki database..."
    docker exec local-wiki-mediawiki mv /var/www/wiki/mediawiki/LocalSettings.php /var/www/wiki/mediawiki/LocalSettings.php.bak

    docker exec local-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/install.php \
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
    docker exec local-wiki-mediawiki rm /var/www/wiki/mediawiki/LocalSettings.php
    docker exec local-wiki-mediawiki mv /var/www/wiki/mediawiki/LocalSettings.php.bak /var/www/wiki/mediawiki/LocalSettings.php

    echo ""
    echo "✓ Local wiki initialization complete!"
    echo "Access your wiki at: http://localhost:3000"
    echo "User Login credentials: admin / AdminPassword123!"
    echo ""

    echo "Restarting containers to apply changes..."
    docker compose down
    docker compose up -d --build
    echo "Containers restarted."

    echo "Waiting for MediaWiki container to be healthy..."
    MAX_WAIT=120  # Maximum wait time in seconds
    ELAPSED=0
    while [ $ELAPSED -lt $MAX_WAIT ]; do
        HEALTH=$(docker inspect --format='{{.State.Health.Status}}' local-wiki-mediawiki 2>/dev/null || echo "starting")
        if [ "$HEALTH" = "healthy" ]; then
            echo "✓ MediaWiki container is healthy"
            break
        fi
        if [ "$ELAPSED" -eq 0 ]; then
            echo -n "Waiting for health check"
        else
            echo -n "."
        fi
        sleep 2
        ELAPSED=$((ELAPSED + 2))
    done

    if [ "$HEALTH" != "healthy" ]; then
        echo ""
        echo "⚠ Warning: Container health check did not pass within ${MAX_WAIT}s"
        echo "Attempting to continue anyway..."
    else
        echo ""
    fi

    echo "Updating MediaWiki database schema..."
    docker exec local-wiki-mediawiki php /var/www/wiki/mediawiki/maintenance/update.php --quick
    echo "✓ Database schema update complete!"

# Copy local environment to .env
env-local: (copy-file "deployment/.env.local.example" ".env")

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
