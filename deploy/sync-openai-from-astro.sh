#!/bin/bash
set -euo pipefail

TAROT_ENV=/var/www/tarot/.env
ASTRO_ENV=/var/www/astro/.env

if [ ! -f "$ASTRO_ENV" ]; then
  echo "Missing Astro .env"
  exit 1
fi

copy_or_set() {
  local key="$1"
  local default="${2:-}"
  local value
  value=$(grep -E "^${key}=" "$ASTRO_ENV" | head -n1 | cut -d= -f2- || true)
  if [ -z "$value" ] && [ -n "$default" ]; then
    value="$default"
  fi
  if [ -z "$value" ]; then
    echo "SKIP $key (empty)"
    return
  fi

  if grep -qE "^${key}=" "$TAROT_ENV"; then
    # replace existing line
    sed -i -E "s|^${key}=.*|${key}=${value}|" "$TAROT_ENV"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$TAROT_ENV"
  fi
  if [ "$key" = "OPENAI_API_KEY" ]; then
    echo "SET $key=SET"
  else
    echo "SET $key=$value"
  fi
}

copy_or_set OPENAI_API_KEY
copy_or_set OPENAI_BASE_URL 'https://api.openai.com/v1'
copy_or_set OPENAI_MODEL 'gpt-4o-mini'
copy_or_set OPENAI_TIMEOUT '120'

cd /var/www/tarot
php artisan config:clear
php artisan optimize:clear

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'key_set='.(config('services.openai.api_key') ? '1' : '0').PHP_EOL;
echo 'model='.config('services.openai.model').PHP_EOL;
PHP

echo SYNC_OK
