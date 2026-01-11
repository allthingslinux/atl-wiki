# Update OpenSearch index configuration in MediaWiki
opensearch-update-config:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php mediawiki/maintenance/run.php mediawiki/extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php

# Reindex OpenSearch in MediaWiki
opensearch-reindex:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php mediawiki/maintenance/run.php mediawiki/extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip
    docker compose exec mediawiki php mediawiki/maintenance/run.php mediawiki/extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipParse
    docker compose exec mediawiki php mediawiki/maintenance/run.php mediawiki/extensions/CirrusSearch/maintenance/UpdateSuggesterIndex.php

# Run pending jobs in MediaWiki
run-jobs:
    #!/usr/bin/env bash
    set -euo pipefail
    just _verify-file compose.yaml
    just _check-containers

    docker compose exec mediawiki php mediawiki/maintenance/run.php runJobs.php
