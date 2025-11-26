# === System Services ===

# Setup production sitemap systemd timer (REQUIRES SUDO)
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

# Setup staging sitemap systemd timer (REQUIRES SUDO)
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
