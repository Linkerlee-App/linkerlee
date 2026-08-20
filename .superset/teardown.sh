#!/usr/bin/env bash
# Removes the workspace-local MySQL database created by setup.sh.
set -uo pipefail

cd "${SUPERSET_WORKSPACE_PATH:-$PWD}"
source "./.superset/common.sh"

DB_NAME="$(env_value DB_DATABASE)"
case "$DB_NAME" in
    "$DB_PREFIX"*)
        echo "==> Dropping database $DB_NAME"
        mysql_cmd -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" 2>/dev/null \
            || echo "    !! Could not reach MySQL - $DB_NAME left in place"
        ;;
    *)
        echo "==> No workspace-owned database to drop (DB_DATABASE=$DB_NAME)"
        ;;
esac
