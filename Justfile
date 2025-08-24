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

# Restart the wiki after an update (stops, rebuilds, starts)
update:
    docker compose down -v
    docker compose up -d --build

# === Configuration Setup ===

# Setup production environment (copies prod compose and env files)
setup-prod: (copy-file "production-compose.yml.example" "compose.yml") env

# Setup staging environment (copies staging compose and env files)
setup-staging: (copy-file "staging-compose.yml.example" "compose.yml") env

# Copy environment example to .env
env: (copy-file ".example.env" ".env")

# === System Services ===

# Setup sitemap systemd timer
sitemap:
    #!/usr/bin/env bash
    set -euo pipefail

    echo "Installing systemd service files..."
    sudo cp systemd/wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/wiki-sitemap.timer /etc/systemd/system/

    echo "Reloading systemd daemon..."
    sudo systemctl daemon-reload

    echo "Enabling and starting sitemap timer..."
    sudo systemctl enable wiki-sitemap.timer
    sudo systemctl start wiki-sitemap.timer

    echo "Sitemap timer setup complete!"

# === Utility Functions ===

# Copy a file with backup (internal function)
[private]
copy-file src dst:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC="{{src}}"
    DST="{{dst}}"

    if [ ! -f "$SRC" ]; then
        echo "Error: source '$SRC' not found." >&2
        exit 1
    fi

    if [ -f "$DST" ]; then
        TS=$(date +%s)
        BACKUP="${DST}.bak.${TS}"
        cp -v "$DST" "$BACKUP"
        echo "Existing $DST backed up to $BACKUP"
    fi

    cp -v "$SRC" "$DST"
    echo "Successfully copied $SRC to $DST"
