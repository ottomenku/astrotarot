<?php

use App\Services\AppSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = app(AppSettings::class);
        $updates = [];
        $map = [
            AppSettings::BASE_PROMPT_HU => [$settings->defaultBasePromptHu(), ['értelmetlen karakterhalmaz', 'Mestered a kérdést']],
            AppSettings::BASE_PROMPT_EN => [$settings->defaultBasePromptEn(), ['gibberish', 'sends a poem']],
            AppSettings::SYSTEM_PROMPT_HU => [$settings->defaultSystemPromptHu(), ['light-shadow: igen']],
            AppSettings::SYSTEM_PROMPT_EN => [$settings->defaultSystemPromptEn(), ['light-shadow: yes']],
        ];

        foreach ($map as $key => [$prompt, $markers]) {
            $stored = (string) $settings->get($key, '');
            if ($stored === '') {
                continue;
            }
            foreach ($markers as $marker) {
                if (str_contains($stored, $marker)) {
                    $updates[$key] = $prompt;
                    break;
                }
            }
        }

        if ($updates !== []) {
            $settings->putMany($updates);
        }
    }

    public function down(): void
    {
    }
};
