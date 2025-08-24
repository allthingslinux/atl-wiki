# update: Restart the wiki after an update
update:
    #!/usr/bin/env bash
    set -euo pipefail

	docker compose down -v
	docker compose up -d --build

# sitemap: Setup sitemap systemd timer
sitemap:
    #!/usr/bin/env bash
    set -euo pipefail

    sudo cp systemd/wiki-sitemap.service /etc/systemd/system/
    sudo cp systemd/wiki-sitemap.timer /etc/systemd/system/

    sudo systemctl daemon-reload

    sudo systemctl enable wiki-sitemap.timer
    sudo systemctl start wiki-sitemap.timer

# compose: Copy prod compose file into compose.yml
compose:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC="production-compose.yml.example"
    DST="compose.yml"

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
    echo "Wrote $DST from $SRC"

# staging-compose: Copy staging compose file into compose.yml
staging-compose:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC="staging-compose.yml.example"
    DST="compose.yml"

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
    echo "Wrote $DST from $SRC"

# env: Copy environment example to .env
env:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC=".example.env"
    DST=".env"

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
    echo "Wrote $DST from $SRC"

# start: Start the application
start:
    #!/usr/bin/env bash
    set -euo pipefail

    docker compose up -d --build
