<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(App\Services\AppSettings::class);
$s->putMany([
    App\Services\AppSettings::SYSTEM_PROMPT_HU => $s->defaultSystemPromptHu(),
    App\Services\AppSettings::SYSTEM_PROMPT_EN => $s->defaultSystemPromptEn(),
    App\Services\AppSettings::BASE_PROMPT_HU => $s->defaultBasePromptHu(),
    App\Services\AppSettings::BASE_PROMPT_EN => $s->defaultBasePromptEn(),
]);

echo "prompts reset\n";
