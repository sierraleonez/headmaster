#!/bin/sh
set -e

# The web container and the worker share this image; only one of them should
# touch the schema.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "==> migrating"
    php artisan migrate --force --no-interaction
fi

# Caches are built here rather than at image build time: they bake in the
# environment, which is only known once the container starts.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
