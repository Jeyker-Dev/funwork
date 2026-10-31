#!/bin/sh
set -e

# Cache config, routes, events and views using the container's paths and runtime .env.
php artisan optimize

exec "$@"
