#!/bin/sh
#
# Prepares a LinkerLee container and then hands over to the real command.
#
# What it does depends on what it is about to run:
#
#   php-fpm        wait for the database, migrate, warm the caches
#   queue worker   wait for the database, warm the caches
#   anything else  prepare storage/ and get out of the way, so one-off
#                  commands such as `php artisan tinker` start immediately
#
set -e

cd /var/www/html

# The role is derived from the command, so `docker compose run app php artisan ...`
# does not re-run migrations or rebuild caches behind your back.
case "$*" in
    php-fpm*) role=app ;;
    *queue:work*|*queue:listen*|*schedule:work*) role=worker ;;
    *) role=oneoff ;;
esac

is_root() {
    [ "$(id -u)" = "0" ]
}

# Run as www-data so that files created here stay writable by the PHP-FPM
# workers, which drop to that user themselves.
as_www() {
    if is_root; then
        su-exec www-data "$@"
    else
        "$@"
    fi
}

# ---------------------------------------------------------------------------
# storage/ is a volume, so it can start out empty on a fresh deployment.
# ---------------------------------------------------------------------------
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if is_root; then
    chown -R www-data:www-data storage bootstrap/cache
fi
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

if [ "$role" = "oneoff" ]; then
    if is_root; then
        exec su-exec www-data "$@"
    fi
    exec "$@"
fi

# ---------------------------------------------------------------------------
# A missing APP_KEY fails in confusing ways much later, so stop here instead.
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY}" ] && [ ! -f .env ]; then
    cat >&2 <<'MSG'
[linkerlee] APP_KEY is not set.

Generate one, put it in your .env, and start the stack again:

    docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show

MSG
    exit 1
fi

# ---------------------------------------------------------------------------
# Wait for the database. Compose healthchecks cover the common case; this
# covers the rest (external database, slow first-run initialisation).
# ---------------------------------------------------------------------------
if [ "${DB_CONNECTION:-mysql}" != "sqlite" ]; then
    attempt=0
    until as_www php artisan db:show --quiet >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 30 ]; then
            echo "[linkerlee] database at ${DB_HOST:-mysql}:${DB_PORT:-3306} never became reachable" >&2
            exit 1
        fi
        echo "[linkerlee] waiting for the database (${attempt}/30)..."
        sleep 2
    done
fi

# ---------------------------------------------------------------------------
# Migrations run from the app container only, so workers cannot race it.
# Set RUN_MIGRATIONS=false to manage schema changes yourself.
# ---------------------------------------------------------------------------
if [ "$role" = "app" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[linkerlee] running migrations"
    as_www php artisan migrate --force --no-interaction
fi

# ---------------------------------------------------------------------------
# Caches are built here rather than at image build time: they bake in
# configuration that only exists once the container has its environment.
# ---------------------------------------------------------------------------
echo "[linkerlee] caching configuration, routes and views"
as_www php artisan config:cache --no-interaction
as_www php artisan route:cache --no-interaction
as_www php artisan view:cache --no-interaction

# php-fpm's master process stays root and drops its own workers to www-data.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

if is_root; then
    exec su-exec www-data "$@"
fi

exec "$@"
