#!/usr/bin/env bash
# Actualización después de un git pull / auto-deploy de hPanel.
set -euo pipefail
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP_BIN:-php}"
cd "$APP_DIR"

if command -v composer >/dev/null 2>&1; then COMPOSER="composer"; else COMPOSER="$PHP composer.phar"; fi

"$PHP" artisan down --retry=30 || true
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=PlanSeeder --force
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan up
echo "✅ Actualizado."
