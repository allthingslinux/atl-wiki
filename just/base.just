_verify-file file:
    #!/usr/bin/env bash
    set -euo pipefail
    if [ ! -f "{{file}}" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mFile '{{file}}' not found\033[0m\n"
        exit 1
    fi

# detects the environment from compose.yaml
_detect-env:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml

    # Read the name field from compose.yaml
    name=$(grep -m 1 "^name:" compose.yaml | cut -d':' -f2 | tr -d ' ')

    # Determine environment based on the name
    if [[ "$name" == *"local-wiki"* ]]; then
        echo "local"
    elif [[ "$name" == *"staging-wiki"* ]]; then
        echo "staging"
    elif [[ "$name" == *"prod-wiki"* ]]; then
        echo "production"
    else
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mCould not detect environment from compose.yaml\033[0m\n"
        exit 1
    fi

_check-containers:
    #!/usr/bin/env bash
    set -euo pipefail
    ENV=$(just _detect-env)

    CONTAINER_COUNT=$(docker ps --filter "name=${ENV}-wiki-" --format "{{{{.Names}}}}" | wc -l)
    if [ "$CONTAINER_COUNT" -eq 0 ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mNo running containers found for '$ENV'. Please start the services first.\033[0m\n"
        exit 1
    fi

_copy-file src dst:
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
