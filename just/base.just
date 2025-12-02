# Verify that a file exists, exit with error if not found
_verify-file file:
    #!/usr/bin/env bash
    set -euo pipefail
    echo "Verifying file: {{file}}"
    if [ ! -f "{{file}}" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mFile '{{file}}' not found\033[0m\n"
        exit 1
    fi

# Detect the environment (local/staging/production) from compose.yaml name field
_detect-env:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml

    # Extract name field from compose.yaml
    name=$(grep -m 1 "^name:" compose.yaml | cut -d':' -f2 | tr -d ' ')

    # Match name pattern to determine environment
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

# Check if containers for the detected environment are running
_check-containers:
    #!/usr/bin/env bash
    set -euo pipefail
    ENV=$(just _detect-env)
    echo "Environment detected: $ENV"

    # Count running containers matching the environment prefix
    echo "Checking for running containers..."
    CONTAINER_COUNT=$(docker ps --filter "name=${ENV}-wiki-" --format "{{{{.Names}}}}" | wc -l)
    if [ "$CONTAINER_COUNT" -eq 0 ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mNo running containers found for '$ENV'. Please start the services first.\033[0m\n"
        exit 1
    fi
    echo "Found $CONTAINER_COUNT running container(s)"

# Copy a file from source to destination with verification
_copy-file src dst:
    #!/usr/bin/env bash
    set -euo pipefail

    SRC="{{src}}"
    DST="{{dst}}"

    echo "Copying file: $SRC → $DST"
    if [ ! -f "$SRC" ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mSource '$SRC' not found\033[0m\n" >&2
        exit 1
    fi

    cp -v "$SRC" "$DST"
    echo "Successfully copied $SRC to $DST"

# Check if the script is running with sudo/root privileges
_check-sudo:
    #!/usr/bin/env bash
    set -euo pipefail

    if [ "$EUID" -ne 0 ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mThis script must be run with 'sudo'.\033[0m\n"
        exit 1
    fi

# Check if current directory is owned by root and require sudo if so
_check-sudo-dir:
    #!/usr/bin/env bash
    set -euo pipefail

    # Get directory owner
    OWNER=$(stat -c '%U' .)

    if [ "$OWNER" = "root" ] && [ "$EUID" -ne 0 ]; then
        echo -e "\n\033[1;37m\033[41m ERROR \033[0m \033[1;31mCurrent directory is owned by root. Please run with 'sudo'.\033[0m\n"
        exit 1
    fi

# Display a warning for destructive actions and prompt for confirmation
_destructive-warning reason="":
    #!/usr/bin/env bash
    set -euo pipefail

    echo -e "\n\033[1;37m\033[41m WARNING \033[0m \033[1;33mThis action is destructive. Proceed with caution.\033[0m"

    if [ -n "{{reason}}" ]; then
        echo -e "          \033[3;38;5;208m{{reason}}\033[0m"
    fi

    echo -e "\033[1;33mPlease check the help command or documentation for this script before proceeding to understand the consequences.\033[0m\n"
    read -p "Do you want to continue? (y/n): " -r REPLY

    # Normalize to lowercase for comparison
    REPLY_LOWER=$(echo "$REPLY" | tr '[:upper:]' '[:lower:]')
    if [[ ! "$REPLY_LOWER" =~ ^(y|yes)$ ]]; then
        echo "Aborting."
        exit 1
    fi
