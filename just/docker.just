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

clean-stop:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose down -v

update:
    #!/usr/bin/env bash
    set -euo pipefail
    ENV=$(just _detect-env)

    just clean-stop
    git pull
    just _copy-file deployment/${ENV}-compose.yaml.example compose.yaml
    just start

