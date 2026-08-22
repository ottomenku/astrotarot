<?php

namespace App\Console\Commands;

use App\Services\AppSettings;
use App\Services\OpenAIClient;
use Illuminate\Console\Command;
use Throwable;

class CheckReading extends Command
{
    protected $signature = 'reading:check
                            {--ping : Send a tiny request to the configured LLM}';

    protected $description = 'Verify LLM reading config (and optionally the live API) before deploy';

    public function handle(AppSettings $settings, OpenAIClient $openAI): int
    {
        $ok = true;

        if (! $settings->llmEnabled()) {
            $this->error('llm_enabled=0');
            $ok = false;
        } else {
            $this->info('llm=1');
        }

        $key = trim((string) config('services.openai.api_key'));
        if ($key === '') {
            $this->error('OPENAI_API_KEY missing');
            $ok = false;
        } else {
            $this->info('key_set=1');
        }

        $this->info('model='.(string) config('services.openai.model'));
        $this->info('base='.(string) config('services.openai.base_url'));

        if (! $this->option('ping')) {
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        if ($key === '') {
            return self::FAILURE;
        }

        try {
            $response = $openAI->chat([
                ['role' => 'user', 'content' => 'Reply with the single word OK.'],
            ], null, [
                'max_tokens' => 8,
                'temperature' => 0,
            ]);
        } catch (Throwable $e) {
            $this->error('openai_exception='.substr($e->getMessage(), 0, 200));

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('openai_http='.$response->status());

            return self::FAILURE;
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            $this->error('openai_empty');

            return self::FAILURE;
        }

        $this->info('openai_ok=1');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
