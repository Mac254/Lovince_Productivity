#!/usr/bin/env bash
# Serves the built app on its own port with a throwaway database, for the Playwright smoke tests.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
API="$ROOT/apps/api"
DB="$API/storage/framework/testing/e2e.sqlite"

export APP_ENV=testing
export APP_DEBUG=true
export APP_URL="http://127.0.0.1:${E2E_PORT}"
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB"
export SESSION_DRIVER=database
export SESSION_DOMAIN=
export SESSION_SECURE_COOKIE=false
export SANCTUM_STATEFUL_DOMAINS="127.0.0.1:${E2E_PORT}"
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export FOCUS_OWNER_NAME="E2E Owner"
export FOCUS_SEED_DEMO=false

mkdir -p "$(dirname "$DB")"
rm -f "$DB"
touch "$DB"

if [ "${E2E_SKIP_BUILD:-}" != "1" ]; then
    pnpm --dir "$ROOT" --filter @focus/web build >/dev/null
fi

cd "$API"
php artisan config:clear >/dev/null
php artisan migrate --force --seed >/dev/null
cd "$API/public"
exec php -q -S "127.0.0.1:${E2E_PORT}" "$API/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
