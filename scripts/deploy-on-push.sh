#!/usr/bin/env bash

# This script is started by the signed GitHub webhook. It deliberately takes no
# request values as arguments: the only deploy target is origin/main.
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
LOG_FILE="$APP_DIR/storage/logs/deploy.log"
LOCK_FILE="$APP_DIR/storage/framework/deploy.lock"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

mkdir -p "$(dirname "$LOG_FILE")" "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
flock 9
exec >>"$LOG_FILE" 2>&1

maintenance_enabled=0

finish() {
    local exit_code=$?

    if (( maintenance_enabled )); then
        "$PHP_BIN" artisan up || true
    fi

    printf '[%s] Deployment finished with exit code %s\n' "$(date --iso-8601=seconds)" "$exit_code"
    exit "$exit_code"
}

trap finish EXIT

cd "$APP_DIR"

printf '[%s] Deployment requested\n' "$(date --iso-8601=seconds)"
previous_commit="$(git rev-parse HEAD)"

if [[ "$(git branch --show-current)" != 'main' ]]; then
    printf 'Refusing deployment: working tree is not on main.\n' >&2
    exit 1
fi

# A deploy must never silently discard files changed directly on the server.
if ! git diff --quiet || ! git diff --cached --quiet; then
    printf 'Refusing deployment: tracked files have uncommitted changes.\n' >&2
    exit 1
fi

git fetch --prune origin main

if git diff --quiet HEAD origin/main; then
    printf '[%s] Already at origin/main; nothing to deploy.\n' "$(date --iso-8601=seconds)"
    exit 0
fi

if [[ ! -f storage/framework/down ]]; then
    "$PHP_BIN" artisan down --retry=60
    maintenance_enabled=1
fi

# --ff-only avoids replacing unexpected local history.
git merge --ff-only origin/main

"$COMPOSER_BIN" install --no-dev --optimize-autoloader --prefer-dist --no-interaction

if [[ -f package-lock.json ]] && ! git diff --quiet "$previous_commit" HEAD -- \
    package.json \
    package-lock.json \
    vite.config.js \
    resources/css \
    resources/js \
    resources/views; then
    if ! command -v npm >/dev/null 2>&1; then
        printf 'Frontend sources changed, but npm is unavailable.\n' >&2
        exit 1
    fi

    npm ci --include=dev --no-audit --no-fund
    npm run build
else
    printf '[%s] Frontend sources unchanged; skipping npm build.\n' "$(date --iso-8601=seconds)"
fi

"$PHP_BIN" artisan migrate --force --no-interaction
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

printf '[%s] Deployment completed successfully.\n' "$(date --iso-8601=seconds)"
