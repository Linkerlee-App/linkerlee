#!/usr/bin/env bash
# Shared helpers for the Superset workspace scripts.

WORKSPACE_NAME="${SUPERSET_WORKSPACE_NAME:-$(basename "$PWD")}"

# Slug safe for MySQL identifiers.
workspace_slug() {
    printf '%s' "$WORKSPACE_NAME" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '_' | cut -c1-32
}

DB_PREFIX="linkerlee_ws_"
workspace_database() {
    printf '%s%s' "$DB_PREFIX" "$(workspace_slug)"
}

# Deterministic per-workspace port so every workspace keeps the same URL.
workspace_port() {
    local base="$1" span="$2"
    local hash
    hash="$(printf '%s' "$WORKSPACE_NAME" | cksum | cut -d' ' -f1)"
    printf '%s' $(( base + hash % span ))
}

port_is_free() {
    ! lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
}

first_free_port() {
    local port="$1"
    for _ in $(seq 1 50); do
        if port_is_free "$port"; then
            printf '%s' "$port"
            return 0
        fi
        port=$(( port + 1 ))
    done
    printf '%s' "$1"
}

env_value() {
    grep -E "^ *$1=" .env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs
}

set_env() {
    local key="$1" value="$2"
    if grep -qE "^ *#? *$key=" .env; then
        # BSD sed: replace the whole line, uncommenting it if needed.
        sed -i '' -E "s|^ *#? *$key=.*|$key=$value|" .env
    else
        printf '\n%s=%s\n' "$key" "$value" >> .env
    fi
}

mysql_cmd() {
    local host port user pass
    host="$(env_value DB_HOST)"; port="$(env_value DB_PORT)"
    user="$(env_value DB_USERNAME)"; pass="$(env_value DB_PASSWORD)"
    mysql --protocol=TCP -h "${host:-127.0.0.1}" -P "${port:-3306}" -u "${user:-root}" \
        ${pass:+-p"$pass"} "$@"
}
