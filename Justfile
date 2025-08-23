# rebuild: Restart the wiki after an update
rebuild:
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

    sudo systemctl status wiki-sitemap.timer --no-pager
