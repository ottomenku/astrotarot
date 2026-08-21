#!/bin/bash
set -euo pipefail

ENV_FILE=/var/www/tarot/.env

set_env() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    # Use | as delimiter; escape | in value if any
    local escaped
    escaped=$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')
    sed -i -E "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

set_env GOOGLE_CLIENT_ID "$1"
set_env GOOGLE_CLIENT_SECRET "$2"
set_env GOOGLE_REDIRECT_URI 'https://astrotarot.hu/auth/google/callback'
set_env FACEBOOK_CLIENT_ID "$3"
set_env FACEBOOK_CLIENT_SECRET "$4"
set_env FACEBOOK_REDIRECT_URI 'https://astrotarot.hu/auth/facebook/callback'

cd /var/www/tarot
php artisan config:clear
php artisan optimize:clear

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'google='.(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) ? 'ok' : 'missing').PHP_EOL;
echo 'facebook='.(filled(config('services.facebook.client_id')) && filled(config('services.facebook.client_secret')) ? 'ok' : 'missing').PHP_EOL;
echo 'google_redirect='.config('services.google.redirect').PHP_EOL;
echo 'facebook_redirect='.config('services.facebook.redirect').PHP_EOL;
PHP

echo DONE
