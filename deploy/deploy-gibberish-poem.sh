#!/usr/bin/env bash
set -euo pipefail
cd /var/www/tarot
git pull origin master
php artisan view:clear
php artisan config:clear
php deploy/reset-prompts.php 2>/dev/null || php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s = app(App\Services\AppSettings::class);
$s->putMany([
  App\Services\AppSettings::SYSTEM_PROMPT_HU => $s->defaultSystemPromptHu(),
  App\Services\AppSettings::SYSTEM_PROMPT_EN => $s->defaultSystemPromptEn(),
  App\Services\AppSettings::CLARIFY_PROMPT_HU => $s->defaultClarifyPromptHu(),
  App\Services\AppSettings::CLARIFY_PROMPT_EN => $s->defaultClarifyPromptEn(),
  App\Services\AppSettings::EXPAND_PROMPT_HU => $s->defaultExpandPromptHu(),
  App\Services\AppSettings::EXPAND_PROMPT_EN => $s->defaultExpandPromptEn(),
]);
echo "prompts reset\n";
'
echo "deploy_ok $(git rev-parse --short HEAD)"
