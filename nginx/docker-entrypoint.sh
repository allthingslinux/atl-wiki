#!/bin/sh
# Custom nginx entrypoint that removes the default config

# Remove the default nginx config to prevent conflicts with our MediaWiki config
rm -f /etc/nginx/conf.d/default.conf

echo "Removed default nginx config to prevent conflicts"

# Skip nginx config test since it requires mediawiki service to be available
# Run nginx directly instead of using the entrypoint that does config testing
exec nginx -g "daemon off;"
