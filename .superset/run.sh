#!/usr/bin/env bash
# Dev server: Laravel + queue worker + log tail + Vite, on workspace-local ports.
set -euo pipefail

cd "${SUPERSET_WORKSPACE_PATH:-$PWD}"
source "./.superset/common.sh"

[ -d vendor ] && [ -d node_modules ] || { echo "Dependencies missing - run .superset/setup.sh first"; exit 1; }

APP_PORT="$(first_free_port "$(workspace_port 8000 400)")"
VITE_PORT="$(first_free_port "$(workspace_port 5200 400)")"

if [ -f .env ] && [ "$(env_value APP_URL)" != "http://localhost:$APP_PORT" ]; then
    set_env APP_URL "http://localhost:$APP_PORT"
fi

echo "==> App:  http://localhost:$APP_PORT"
echo "==> Vite: http://localhost:$VITE_PORT"

exec npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
    "php artisan serve --port=$APP_PORT" \
    "php artisan queue:listen --tries=1 --timeout=0" \
    "php artisan pail --timeout=0" \
    "npm run dev -- --port=$VITE_PORT" \
    --names=server,queue,logs,vite --kill-others
