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

# Restart the wiki after an update (stops, pulls, rebuilds, starts)
update:
    docker compose down -v
    sudo git pull
    just copy-file production-compose.yml.example compose.yml
    docker compose up -d --build

# === Configuration Setup ===

# Setup production environment (copies prod compose and env files, installs prod systemd)
setup-prod: (copy-file "production-compose.yml.example" "compose.yml") env sitemap-prod

# Setup staging environment (copies staging compose and env files, installs staging systemd)
setup-staging: (copy-file "staging-compose.yml.example" "compose.yml") env sitemap-staging

# Copy environment example to .env
env: (copy-file ".example.env" ".env")

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

# Copy a file with backup (DO NOT RUN MANUALLY)
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
