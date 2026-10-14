<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    public function test_guest_can_request_the_first_reading_but_not_a_follow_up(): void
    {
        $this->fakeOpenAi('Vendég olvasat.');

        $this->postJson(route('reading'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reading', 'Vendég olvasat.');

        $this->postJson(route('reading'), $this->validPayload([
            'mode' => 'clarify',
            'follow_up' => 'Mit jelent ez pontosan?',
            'previous_reading' => 'Vendég olvasat.',
        ]))->assertUnauthorized();

        $this->postJson(route('reading'), $this->validPayload([
            'mode' => 'expand',
            'previous_reading' => 'Vendég olvasat.',
        ]))->assertUnauthorized();
    }

    public function test_reading_requires_question_and_two_cards(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)->postJson(route('reading'), [
            'question' => 'túl rövid',
            'cards' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_reading_returns_model_text_and_logs_exchange(): void
    {
        $this->fakeOpenAi('Az Égi mestered, a Mars, most a Skorpióban áll. A Mágus úgy értelmezi, hogy cselekedj.');

        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAs($user)->postJson(route('reading'), $this->validPayload());

        $response->assertOk()->assertJson([
            'reading' => 'Az Égi mestered, a Mars, most a Skorpióban áll. A Mágus úgy értelmezi, hogy cselekedj.',
            'mode' => 'initial',
            'credits' => 9,
            'source' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'fallback' => false,
            ],
        ]);

        $this->assertSame(9, (int) $user->fresh()->credits);

        $this->assertDatabaseCount('reading_logs', 1);
        $log = ReadingLog::query()->first();
        $this->assertSame('success', $log->status);
        $this->assertSame('openai', $log->llm_provider);
        $this->assertFalse((bool) $log->llm_fallback);
        $this->assertSame('gpt-4o-mini', $log->model);
        $this->assertSame('OpenAI · gpt-4o-mini', $log->sourceLabel('hu'));
        $this->assertSame('Mit tegyek most a kapcsolatommal?', $log->question);
        $this->assertNotEmpty($log->system_prompt);
        $this->assertNotEmpty($log->user_prompt);
        $this->assertStringContainsString('Húzott lapok:', $log->user_prompt);
        $this->assertStringContainsString('1. sor 1. lap', $log->user_prompt);
        $this->assertStringContainsString('1. sor 2. lap', $log->user_prompt);
        $this->assertStringContainsString('asztrológiai adat:', $log->user_prompt);
        $this->assertStringContainsString('elem: tűz', $log->user_prompt);
        $this->assertStringContainsString('minőség: szilárd', $log->user_prompt);
        $this->assertStringContainsString('10–12 mondat', $log->system_prompt);
    }

    public function test_reading_sends_game_prompt_places_and_moment_scoring(): void
    {
        $this->fakeOpenAi('Az Asztro tarot játék szerint a Mars és a Mágus együtt szól.');

        $game = Game::query()->where('slug', 'astro-tarot')->firstOrFail();
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)->postJson(route('reading'), $this->validPayload([
            'game_id' => $game->id,
            'astro' => [
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'szilárd',
                'tone' => 'pozitív',
                'datetime_utc' => '2026-08-30T17:00:00Z',
                'scoring' => [
                    'element_name' => 'tűz',
                    'element_value' => 11.5,
                    'modality_name' => 'szilárd',
                    'modality_value' => 9,
                    'tone_name' => 'pozitív',
                    'tone_positive' => 14,
                    'tone_negative' => 5,
                    'tone_net' => 9,
                    'elements' => ['tűz' => 12, 'víz' => 4, 'föld' => 3, 'levegő' => 2],
                    'modalities' => ['fő' => 3, 'szilárd' => 9, 'változó' => 2],
                ],
            ],
        ]))->assertOk();

        $log = ReadingLog::query()->first();
        $this->assertSame($game->prompt('hu'), $log->system_prompt);
        $this->assertStringContainsString('1. sor 1. lap — Égi Orákulum', $log->user_prompt);
        $this->assertStringContainsString('1. sor 2. lap — Tarot figura', $log->user_prompt);
        $this->assertStringContainsString('asztrológiai adat:', $log->user_prompt);
        $this->assertStringContainsString('jelentés:', $log->user_prompt);
        $this->assertStringContainsString('elem: tűz (11.5)', $log->user_prompt);
        $this->assertStringContainsString('minőség: szilárd (9)', $log->user_prompt);
        $this->assertStringContainsString('pozitív / negatív: pozitív (pozitív 14, negatív 5, nettó 9)', $log->user_prompt);
        $this->assertStringContainsString('elemek: tűz 12, víz 4, föld 3, levegő 2', $log->user_prompt);
        $this->assertStringNotContainsString('Most írd meg az olvasatot', $log->user_prompt);
    }

    public function test_reading_blocked_without_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertStatus(402)
            ->assertJsonPath('credits', 0);

        $this->assertSame(0, (int) $user->fresh()->credits);
        $this->assertDatabaseCount('reading_logs', 0);
    }

    public function test_clarify_follow_up_requires_text_and_previous_reading(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)->postJson(route('reading'), [
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

    public function test_expand_follow_up_returns_model_text_without_debit(): void
    {
        $this->fakeOpenAi('Bővebb válasz a korábbi olvasathoz.');

        $user = User::factory()->create(['credits' => 3]);

        $response = $this->actingAs($user)->postJson(route('reading'), [
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
            'credits' => 3,
        ]);

        $this->assertSame(3, (int) $user->fresh()->credits);

        $log = ReadingLog::query()->latest('id')->first();
        $this->assertSame('success', $log->status);
        $this->assertStringContainsString('[bővebb kifejtés]', $log->question);
        $this->assertStringContainsString('Korábbi olvasat:', $log->user_prompt);
    }

    public function test_credit_package_request_is_stored(): void
    {
        $user = User::factory()->create(['credits' => 5]);

        $this->actingAs($user)
            ->post(route('credits.request'), [
                'package_key' => 'pack_50',
                'note' => 'Kérem a csomagot',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_requests', [
            'user_id' => $user->id,
            'package_key' => 'pack_50',
            'credits' => 50,
            'status' => 'pending',
            'note' => 'Kérem a csomagot',
        ]);
    }

    public function test_signup_bonus_constant(): void
    {
        $this->assertSame(10, CreditService::SIGNUP_BONUS);
        $this->assertSame(1, CreditService::QUESTION_COST);
    }

    public function test_missing_api_key_returns_error_without_spending_credit(): void
    {
        Http::preventStrayRequests();
        config([
            'services.openai.driver' => 'openai',
            'services.openai.api_key' => '',
        ]);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('error', 'Az értelmezés most nem elérhető. Próbáld újra később.');

        $this->assertSame(10, (int) $user->fresh()->credits);
        $this->assertSame('error', ReadingLog::query()->first()?->status);
        $this->assertStringContainsString('OPENAI_API_KEY', (string) ReadingLog::query()->first()?->error);
    }

    public function test_ollama_reading_works_without_api_key(): void
    {
        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => '',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'llama3.2',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Az Ollama szerint cselekedj.']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reading', 'Az Ollama szerint cselekedj.')
            ->assertJsonPath('source.provider', 'ollama')
            ->assertJsonPath('source.model', 'llama3.2')
            ->assertJsonPath('source.fallback', false);

        $this->assertSame(9, (int) $user->fresh()->credits);
        $log = ReadingLog::query()->first();
        $this->assertSame('llama3.2', $log?->model);
        $this->assertSame('ollama', $log?->llm_provider);
        $this->assertFalse((bool) $log?->llm_fallback);
        $this->assertSame('Ollama · llama3.2', $log?->sourceLabel('hu'));
    }

    public function test_ollama_fallback_is_logged_as_openai(): void
    {
        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => 'sk-test',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'qwen2.5:14b',
            'services.openai.fallback_to_openai' => true,
            'services.openai.cloud_base_url' => 'https://api.openai.com/v1',
            'services.openai.cloud_model' => 'gpt-4o-mini',
        ]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '11434')) {
                throw new \Illuminate\Http\Client\ConnectionException('Failed to connect');
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Cloud válasz.']],
                ],
            ], 200);
        });

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reading', 'Cloud válasz.')
            ->assertJsonPath('source.provider', 'openai')
            ->assertJsonPath('source.model', 'gpt-4o-mini')
            ->assertJsonPath('source.fallback', true)
            ->assertJsonPath('source.label', 'OpenAI · gpt-4o-mini (tartalék)');

        $log = ReadingLog::query()->first();
        $this->assertSame('openai', $log?->llm_provider);
        $this->assertTrue((bool) $log?->llm_fallback);
        $this->assertSame('gpt-4o-mini', $log?->model);
        $this->assertSame('OpenAI · gpt-4o-mini (tartalék)', $log?->sourceLabel('hu'));
    }

    public function test_openai_only_route_does_not_call_ollama(): void
    {
        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => 'sk-test',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'qwen2.5:14b',
            'services.openai.fallback_to_openai' => true,
            'services.openai.cloud_base_url' => 'https://api.openai.com/v1',
            'services.openai.cloud_model' => 'gpt-4o-mini',
        ]);
        app(AppSettings::class)->putMany([
            AppSettings::LLM_ROUTE => AppSettings::LLM_ROUTE_OPENAI,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Csak felhő.']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reading', 'Csak felhő.')
            ->assertJsonPath('source.provider', 'openai')
            ->assertJsonPath('source.fallback', false)
            ->assertJsonPath('source.model', 'gpt-4o-mini');

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '11434');
        });
    }

    public function test_openai_http_error_returns_error_without_spending_credit(): void
    {
        $this->fakeOpenAiHttp(401, [
            'error' => ['message' => 'Incorrect API key provided'],
        ]);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('error', 'Az értelmezés most nem elérhető. Próbáld újra később.');

        $this->assertSame(10, (int) $user->fresh()->credits);
        $log = ReadingLog::query()->first();
        $this->assertSame('error', $log?->status);
        $this->assertStringContainsString('HTTP 401', (string) $log?->error);
    }

    public function test_empty_model_response_returns_error_without_spending_credit(): void
    {
        $this->fakeOpenAi('   ');

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('error', 'Üres választ kaptunk az értelmezőtől.');

        $this->assertSame(10, (int) $user->fresh()->credits);
        $this->assertSame('Empty model response', ReadingLog::query()->first()?->error);
    }

    public function test_long_astro_caption_is_accepted(): void
    {
        $this->fakeOpenAi('Rendben.');

        $payload = $this->validPayload();
        $payload['cards'][0]['astro_caption'] = str_repeat('ég ', 900);

        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $payload)
            ->assertOk()
            ->assertJsonPath('reading', 'Rendben.');
    }

    public function test_expired_csrf_json_includes_error_key(): void
    {
        $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request = \Illuminate\Http\Request::create('/reading', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $handler->render($request, new \Illuminate\Session\TokenMismatchException);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame(
            'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.',
            json_decode($response->getContent(), true)['error'] ?? null
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeOpenAiHttp(int $status, array $body): void
    {
        config([
            'services.openai.driver' => 'openai',
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response($body, $status),
        ]);
    }

    private function fakeOpenAi(string $content): void
    {
        $this->fakeOpenAiHttp(200, [
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ]);
    }
}
