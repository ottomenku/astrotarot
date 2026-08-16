<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_requires_question_and_two_cards(): void
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
                    ['message' => ['content' => 'Az Égi mestered, a Mars, most a Skorpióban áll. A Mágus úgy értelmezi, hogy cselekedj.']],
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
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'keyword' => 'akarat',
                    'blurb' => '',
                    'background_id' => 'shared-light',
                    'background' => 'shared',
                    'variant' => 'positive',
                    'polarity' => 'positive',
                    'deck' => 'oracle',
                    'slot_title' => 'Választott Égi mestered',
                    'astro_caption' => 'Mars · Skorpió 12° · 8. ház · direkt',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'keyword' => 'teremtő erő',
                    'blurb' => 'A Mágus tudatos alkotóerőt hoz.',
                    'background_id' => 'shared-shadow',
                    'background' => 'shared',
                    'variant' => 'shadow',
                    'polarity' => 'shadow',
                    'deck' => 'tarot',
                    'slot_title' => 'Választott Figurád',
                    'astro_caption' => 'A Mágus — A Mágus tudatos alkotóerőt hoz.',
                ],
            ],
        ]);

        $response->assertOk()->assertJson([
            'reading' => 'Az Égi mestered, a Mars, most a Skorpióban áll. A Mágus úgy értelmezi, hogy cselekedj.',
            'mode' => 'initial',
        ]);

        $this->assertDatabaseCount('reading_logs', 1);
        $log = ReadingLog::query()->first();
        $this->assertSame('success', $log->status);
        $this->assertSame('Mit tegyek most a kapcsolatommal?', $log->question);
        $this->assertNotEmpty($log->system_prompt);
        $this->assertNotEmpty($log->user_prompt);
        $this->assertStringContainsString('Lapok:', $log->user_prompt);
        $this->assertStringContainsString('chart_or_caption:', $log->user_prompt);
        $this->assertStringContainsString('10–12 mondat', $log->user_prompt);
    }

    public function test_clarify_follow_up_requires_text_and_previous_reading(): void
    {
        $this->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'mode' => 'clarify',
            'follow_up' => '',
            'previous_reading' => 'Korábbi szöveg',
            'cards' => [
                [
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'background_id' => 'shared-light',
                    'deck' => 'oracle',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'background_id' => 'shared-shadow',
                    'deck' => 'tarot',
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_expand_follow_up_returns_model_text(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Bővebb válasz a korábbi olvasathoz.']],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'locale' => 'hu',
            'mode' => 'expand',
            'previous_reading' => 'Az Égi mestered a Mars. A Mágus úgy értelmezi, hogy cselekedj.',
            'cards' => [
                [
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'background_id' => 'shared-light',
                    'deck' => 'oracle',
                    'astro_caption' => 'Mars · Skorpió',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'background_id' => 'shared-shadow',
                    'deck' => 'tarot',
                    'blurb' => 'Alkotóerő',
                ],
            ],
        ]);

        $response->assertOk()->assertJson([
            'reading' => 'Bővebb válasz a korábbi olvasathoz.',
            'mode' => 'expand',
        ]);

        $log = ReadingLog::query()->latest('id')->first();
        $this->assertSame('success', $log->status);
        $this->assertStringContainsString('[bővebb kifejtés]', $log->question);
        $this->assertStringContainsString('Korábbi olvasat:', $log->user_prompt);
    }
}
