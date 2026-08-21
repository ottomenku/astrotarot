#!/bin/bash
set -euo pipefail
cd /var/www/tarot

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s = app(App\Services\AppSettings::class);
echo 'llm='.($s->llmEnabled() ? '1' : '0').PHP_EOL;
echo 'site='.($s->siteEnabled() ? '1' : '0').PHP_EOL;
echo 'key_set='.(config('services.openai.api_key') ? '1' : '0').PHP_EOL;
echo 'model='.(config('services.openai.model') ?: 'null').PHP_EOL;
echo 'base='.(config('services.openai.base_url') ?: 'null').PHP_EOL;
PHP

echo '== env openai presence =='
grep -E '^OPENAI_' .env | sed -E 's/(OPENAI_API_KEY=).*/\1SET/' || echo NONE

echo '== recent reading errors =='
grep -E 'Tarot reading|OPENAI' storage/logs/laravel.log | tail -n 15 || true

echo '== astro key presence =='
grep -E '^OPENAI_API_KEY=' /var/www/astro/.env | sed -E 's/(OPENAI_API_KEY=).*/\1SET/' || echo NO_ASTRO_KEY
