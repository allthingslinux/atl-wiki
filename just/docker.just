start:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml

    docker compose up -d --build

stop:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose down

restart:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    just stop
    just start

clean-restart:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    just clean-stop
    just start

clean-stop:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose down -v

update:
    #!/usr/bin/env bash
    set -euo pipefail
    just _destructive-warning "This will pull the latest code and replace your compose.yaml, check the changelog before proceeding."
    just _check-sudo
    just _verify-file compose.yaml
    just _check-containers
    ENV=$(just _detect-env)

    just clean-stop
    git pull
    just _copy-file deployment/${ENV}-compose.yaml.example compose.yaml
    just start

