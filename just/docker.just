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

update:
    #!/usr/bin/env bash
    set -euo pipefail
    just stop
    git pull
    just start
