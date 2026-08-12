<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_requires_question_and_three_cards(): void
    {
        $this->postJson(route('reading'), [
            'question' => 'túl rövid',
            'cards' => [],
        ])->assertStatus(422);
    }

    public function test_reading_returns_model_text_and_logs_exchange(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "1. Jelen helyzet\nEgy.\nKettő."]],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'locale' => 'hu',
            'astro' => [
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'szilárd',
            ],
            'cards' => [
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'keyword' => 'teremtő erő',
                    'background_id' => 'leo-positive',
                    'background' => 'leo',
                    'variant' => 'positive',
                    'polarity' => 'positive',
                    'astro_caption' => 'napjegy: Oroszlán',
                ],
                [
                    'figure_id' => '02-the-high-priestess',
                    'figure_name' => 'A Főpapnő',
                    'keyword' => 'intuíció',
                    'background_id' => 'fire-shadow',
                    'background' => 'fire',
                    'variant' => 'shadow',
                    'polarity' => 'shadow',
                    'astro_caption' => 'elem dominancia: tűz',
                ],
                [
                    'figure_id' => '07-the-chariot',
                    'figure_name' => 'A Diadalszekér',
                    'keyword' => 'haladás',
                    'background_id' => 'fixed-positive',
                    'background' => 'fixed',
                    'variant' => 'positive',
                    'polarity' => 'positive',
                    'astro_caption' => 'minőség dominancia: szilárd',
                ],
            ],
        ]);

        $response->assertOk()->assertJson([
            'reading' => "1. Jelen helyzet\nEgy.\nKettő.",
        ]);

        $this->assertDatabaseCount('reading_logs', 1);
        $log = ReadingLog::query()->first();
        $this->assertSame('success', $log->status);
        $this->assertSame('Mit tegyek most a kapcsolatommal?', $log->question);
        $this->assertNotEmpty($log->system_prompt);
        $this->assertNotEmpty($log->user_prompt);
        $this->assertSame("1. Jelen helyzet\nEgy.\nKettő.", $log->response);
    }
}
