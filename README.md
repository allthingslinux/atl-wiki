# atl.wiki Mediawiki Configs

This repository contains all the configs for atl.wiki mediawiki setup. It can be called from `LocalSettings.php` using the following code. Credentials are stored in a seperate directory and are not stored in a git repository.
Fundamentally the setup is everything is in the webroot `/var/www/atlwiki`, then Mediawiki is stored inside `/var/www/atlwiki/mediawiki`. This allows for everything to be in one place and be easy to manage, please note all instructions below are for the atl.wiki setup, you will likely need to rename these vars for your own setup.
You will need to clone Mediawiki yourself and set it up, as of now we use tag REL1_43 and you'll need to run `composer install --no-dev` inside the Mediawiki git repo locally and ensure you have setup a symlink for the LocalSettings.php.

# How it's Structured
Files are named `n-NAME.php`, `n` is a number such as `05` or `98` which dictates the load order of the files. User rights must be the final to load and extensions must be the penultimate to load. The name is a capatlised name relevant to the configs it stores and the number is zeropadded. All miscallaneious configs go in `00-Core.php`.

The configurations must be in `/var/www/atlwiki/configs` and the `Credentials.php` file must be in `/etc/mediawiki/secrets`, both directories should have `750` permissions and cannot be owned by the webserver but the group MUST be the webserver, e.g. `atl-wiki:www-data`.
