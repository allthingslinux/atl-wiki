# atl.wiki Mediawiki Configs

tbc

## Code quality

- Install PHP dependencies inside `wiki/` via `composer install` (Composer 2).
- Run `composer test` from `wiki/` to execute MediaWiki CodeSniffer checks defined in `.phpcs.xml`.
- Run `composer fix` to automatically fix sniffs via `phpcbf` and re-run the sniffer if needed.

# License

Copyright 2025 Atmois <atmois@allthingslinux.org>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at
    http://www.apache.org/licenses/LICENSE-2.0
