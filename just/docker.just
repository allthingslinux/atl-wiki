# Start all wiki containers
start:
    #!/usr/bin/env bash
    set -euo pipefail
    JUSTFILE_DIR="{{justfile_directory()}}"
    just _verify-file compose.yaml

    cd "$JUSTFILE_DIR" && docker compose up -d --build

# Stop all running wiki containers
stop:
    #!/usr/bin/env bash
    set -euo pipefail
    JUSTFILE_DIR="{{justfile_directory()}}"
    just _verify-file compose.yaml
    just _check-containers

    cd "$JUSTFILE_DIR" && docker compose down

# Restart all wiki containers
restart:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    echo "Stopping wiki containers..."
    just stop
    echo "Starting wiki containers..."
    just start

# Restart all wiki containers and removes cache volumes
clean-restart:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    echo "Stopping wiki containers and removing cache volumes..."
    just clean-stop
    echo "Starting wiki containers..."
    just start

# Stop all wiki containers and removes cache volumes
clean-stop:
    #!/usr/bin/env bash
    set -euo pipefail
    JUSTFILE_DIR="{{justfile_directory()}}"
    just _verify-file compose.yaml
    just _check-containers

    cd "$JUSTFILE_DIR" && docker compose down -v

# Pull latest code, updates compose.yaml from template, and restarts wiki containers
update:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will pull the latest code and replace your compose.yaml, check the changelog before proceeding."
    just _check-sudo-dir
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    # Stop wiki containers and pull updates
    echo "Stopping wiki containers..."
    just clean-stop
    echo "Pulling latest code from git..."
    git pull
    # Copy environment-specific compose template
    echo "Updating compose.yaml for environment: $ENV"
    just _copy-file compose.${ENV}.yaml.example compose.yaml
    echo "Restarting wiki containers..."
    just start
