# Update OpenSearch index configuration in MediaWiki
opensearch-update-config:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php

# Reindex OpenSearch in MediaWiki
opensearch-reindex:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip
    docker compose exec mediawiki php extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipParse

# Run pending jobs in MediaWiki (including OpenSearch indexing jobs)
opensearch-run-jobs:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php maintenance/runJobs.php

# Update OpenSearch suggester index in MediaWiki
opensearch-suggester:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php extensions/CirrusSearch/maintenance/UpdateSuggesterIndex.php
