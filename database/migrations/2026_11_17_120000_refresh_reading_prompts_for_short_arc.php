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
        $markers = [
            AppSettings::SYSTEM_PROMPT_HU => [$settings->defaultSystemPromptHu(), ['A stílust, a szerepet, a hosszt', 'A text mező a kész olvasat, az ALAPPROMPT szerint']],
            AppSettings::SYSTEM_PROMPT_EN => [$settings->defaultSystemPromptEn(), ['Style, role, length and answering rules live in the BASE section']],
            AppSettings::BASE_PROMPT_HU => [$settings->defaultBasePromptHu(), ['10–12 mondat', '14–16 mondatban']],
            AppSettings::BASE_PROMPT_EN => [$settings->defaultBasePromptEn(), ['10–12 sentence', '14–16 practical sentences']],
            AppSettings::CLASSIFY_PROMPT_HU => [$settings->defaultClassifyPromptHu(), ['Egy kérdést sorolsz be']],
            AppSettings::CLASSIFY_PROMPT_EN => [$settings->defaultClassifyPromptEn(), ['You classify one question']],
        ];

        foreach ($markers as $key => [$prompt, $needles]) {
            $stored = (string) $settings->get($key, '');
            if ($stored === '') {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($stored, $needle)) {
                    $updates[$key] = $prompt;
                    break;
                }
            }
        }

        $answers = [
            AppSettings::CATEGORY_ANSWERS_HU => [$settings->defaultCategoryAnswersHu(), ['8–10 mondat', 'A kifejtés sorrendje kötött', '20–25 mondat']],
            AppSettings::CATEGORY_ANSWERS_EN => [$settings->defaultCategoryAnswersEn(), ['8–10 sentences', '20–25 sentences', 'The reading order is fixed']],
        ];
        foreach ($answers as $key => [$defaults, $needles]) {
            $stored = (string) $settings->get($key, '');
            if ($stored === '') {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($stored, $needle)) {
                    $encoded = json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if (is_string($encoded)) {
                        $updates[$key] = $encoded;
                    }
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
