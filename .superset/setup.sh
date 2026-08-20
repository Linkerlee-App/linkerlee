#!/usr/bin/env bash
# Prepares a fresh Superset workspace: dependencies, .env, an isolated MySQL
# database and migrations. Start the app afterwards with the Run button.
set -euo pipefail

cd "${SUPERSET_WORKSPACE_PATH:-$PWD}"
source "./.superset/common.sh"

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

echo "==> Installing JS dependencies"
if [ -f package-lock.json ]; then
    npm ci --no-audit --no-fund || npm install --no-audit --no-fund
else
    npm install --no-audit --no-fund
fi

echo "==> Preparing .env"
if [ ! -f .env ]; then
    if [ -n "${SUPERSET_ROOT_PATH:-}" ] && [ -f "$SUPERSET_ROOT_PATH/.env" ]; then
        cp "$SUPERSET_ROOT_PATH/.env" .env
        echo "    copied from $SUPERSET_ROOT_PATH/.env"
    else
        cp .env.example .env
        echo "    copied from .env.example"
    fi
fi

[ -n "$(env_value APP_KEY)" ] || php artisan key:generate --ansi --no-interaction

APP_PORT="$(first_free_port "$(workspace_port 8000 400)")"
set_env APP_URL "http://localhost:$APP_PORT"
set_env APP_ENV local
echo "    APP_URL=http://localhost:$APP_PORT"

if [ "$(env_value DB_CONNECTION)" = "mysql" ]; then
    DB_NAME="$(workspace_database)"
    echo "==> Creating isolated database $DB_NAME"
    if mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;" 2>/dev/null; then
        set_env DB_DATABASE "$DB_NAME"
    else
        echo "    !! MySQL unreachable - keeping DB_DATABASE=$(env_value DB_DATABASE)"
    fi
fi

php artisan storage:link --quiet || true

echo "==> Running migrations"
php artisan migrate --force --no-interaction

echo "==> Ready. Press Run to start the dev server on http://localhost:$APP_PORT"
