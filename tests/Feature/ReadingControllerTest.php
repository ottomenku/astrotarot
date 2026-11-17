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

    public function test_guest_can_request_the_first_reading_and_follow_ups(): void
    {
        $this->fakeOpenAi('Vendég olvasat.');

        $this->postJson(route('reading'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('reading', 'Vendég olvasat.');

        $this->postJson(route('reading'), $this->validPayload([
            'mode' => 'clarify',
            'follow_up' => 'Mit jelent ez pontosan?',
            'previous_reading' => 'Vendég olvasat.',
        ]))->assertOk()
            ->assertJsonPath('reading', 'Vendég olvasat.');

        $this->postJson(route('reading'), $this->validPayload([
            'mode' => 'expand',
            'previous_reading' => 'Vendég olvasat.',
        ]))->assertOk()
            ->assertJsonPath('reading', 'Vendég olvasat.');
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
        $this->assertStringContainsString('jelentés: tudást kereső ember', $log->user_prompt);
        $this->assertStringContainsString('Ebben a körben asztrológiai képlet nincs.', $log->user_prompt);
        $this->assertStringNotContainsString('elem: tűz', $log->user_prompt);
        $this->assertStringNotContainsString('minőség: szilárd', $log->user_prompt);
        $this->assertStringNotContainsString('need_data', $log->system_prompt);
        $this->assertStringContainsString('status":"answer"', $log->system_prompt);
        $this->assertStringContainsString('8–12 mondat', $log->user_prompt);
        $this->assertNotEmpty($response->json('session_id'));
    }

    public function test_openai_agent_keeps_the_standing_prompt_and_sends_the_cards(): void
    {
        config([
            'services.openai.use_agent' => true,
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.agent_model' => 'gpt-5.4-mini',
            'services.openai.driver' => 'openai',
            'services.openai.timeout' => 5,
        ]);

        $game = Game::query()->where('slug', 'astro-tarot')->firstOrFail();
        $follow = false;
        $seen = [];

        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$seen, &$follow) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $seen[] = ['method' => $request->method(), 'path' => $path, 'data' => $request->data()];

            if (str_ends_with($path, '/events')) {
                $follow = true;

                return Http::response(['ok' => true], 202);
            }
            if (str_contains($path, '/items')) {
                return Http::response([
                    'data' => [[
                        'id' => $follow ? 'msg_follow' : 'msg_first',
                        'role' => 'assistant',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => $follow ? 'Pontosítás kész.' : 'Első olvasat.',
                        ]],
                    ]],
                ]);
            }
            if (str_contains($path, '/agents/sessions/')) {
                return Http::response(['id' => 'sess_test', 'status' => 'idle']);
            }
            if ($path === '/v1/agents/sessions') {
                return Http::response(['id' => 'sess_test', 'status' => 'in_progress'], 201);
            }
            if ($path === '/v1/agents') {
                return Http::response(['id' => 'agent_test'], 201);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$path]], 500);
        });

        $first = $this->postJson(route('reading'), $this->validPayload([
            'game_id' => $game->id,
        ]));

        $first->assertOk()
            ->assertJsonPath('reading', 'Első olvasat.')
            ->assertJsonPath('source.model', 'gpt-5.4-mini');

        $created = collect($seen)->first(fn ($call) => $call['path'] === '/v1/agents' && $call['method'] === 'POST');
        $opened = collect($seen)->first(fn ($call) => $call['path'] === '/v1/agents/sessions');
        $this->assertIsArray($created);
        $this->assertStringContainsString('8–12 mondat', (string) $created['data']['instructions']);
        $this->assertStringNotContainsString('need_data', (string) $created['data']['instructions']);
        $this->assertStringContainsString('status":"answer"', (string) $created['data']['instructions']);
        $this->assertStringContainsString('keresd a neten', (string) $created['data']['instructions']);
        $this->assertSame('web_search', $created['data']['tools'][0]['type']);
        $this->assertCount(1, $created['data']['tools']);
        $this->assertIsArray($opened);
        $this->assertStringContainsString('Mars', (string) $opened['data']['input']);
        $this->assertStringContainsString('Húzott lapok:', (string) $opened['data']['input']);
        $this->assertStringContainsString('jelentés: tudást kereső ember', (string) $opened['data']['input']);
        $this->assertStringContainsString('Ebben a körben asztrológiai képlet nincs.', (string) $opened['data']['input']);
        $this->assertStringNotContainsString('positions:', (string) $opened['data']['input']);
        $this->assertStringContainsString('Mit tegyek most a kapcsolatommal?', (string) $opened['data']['input']);
        $this->assertStringNotContainsString('8–12 mondat', (string) $opened['data']['input']);
        $this->assertStringContainsString($game->prompt('hu'), (string) data_get($opened, 'data.agent.instructions'));
        $this->assertStringContainsString($game->prompt('hu'), (string) $opened['data']['input']);

        $log = ReadingLog::query()->first();
        $this->assertStringNotContainsString('8–12 mondat', (string) $log->user_prompt);
        $this->assertStringContainsString('Mars', (string) $log->user_prompt);

        $seen = [];
        $this->postJson(route('reading'), $this->validPayload([
            'game_id' => $game->id,
            'mode' => 'clarify',
            'session_id' => $first->json('session_id'),
            'follow_up' => 'Mit jelent ez pontosan?',
            'previous_reading' => 'Első olvasat.',
        ]))->assertOk()->assertJsonPath('reading', 'Pontosítás kész.');

        $event = collect($seen)->first(fn ($call) => str_ends_with($call['path'], '/events'));
        $this->assertIsArray($event);
        $sent = json_encode($event['data'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Mit jelent ez pontosan?', $sent);
        $this->assertStringNotContainsString('Húzott lapok:', $sent);
        $this->assertStringNotContainsString('8–12 mondat', $sent);
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
        $this->assertStringContainsString($game->prompt('hu'), $log->user_prompt);
        $this->assertStringNotContainsString('need_data', $log->system_prompt);
        $this->assertStringContainsString('status":"answer"', $log->system_prompt);
        $this->assertStringContainsString('1. sor 1. lap — Égi Orákulum', $log->user_prompt);
        $this->assertStringContainsString('1. sor 2. lap — Tarot figura', $log->user_prompt);
        $this->assertStringContainsString('asztrológiai adat:', $log->user_prompt);
        $this->assertStringContainsString('jelentés:', $log->user_prompt);
        $this->assertStringContainsString('jelentés: tudást kereső ember', $log->user_prompt);
        $this->assertStringContainsString('Ebben a körben asztrológiai képlet nincs.', $log->user_prompt);
        $this->assertStringNotContainsString('positions:', $log->user_prompt);
        $this->assertStringNotContainsString('elem: tűz (11.5)', $log->user_prompt);
        $this->assertStringNotContainsString('Most írd meg az olvasatot', $log->user_prompt);
    }

    public function test_spread_settings_add_types_sky_data_and_the_celestial_helper(): void
    {
        $this->fakeOpenAi('A segítő Mars.');

        $this->mock(\App\Services\CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'chart' => [
                    'objects' => [
                        ['id' => 'mercury', 'kind' => 'planet', 'sign' => 'virgo', 'degree' => 4, 'house' => 6, 'retrograde' => false],
                        ['id' => 'venus', 'kind' => 'planet', 'sign' => 'leo', 'degree' => 2, 'house' => 5, 'retrograde' => false],
                        ['id' => 'mars', 'kind' => 'planet', 'sign' => 'leo', 'degree' => 11, 'house' => 5, 'retrograde' => true],
                    ],
                    'aspects' => [
                        ['a_id' => 'venus', 'b_id' => 'mars', 'type' => 'szextil'],
                        ['a_id' => 'mars', 'b_id' => 'mercury', 'type' => 'kvadrát'],
                    ],
                ],
            ]);
        });

        $game = Game::query()->where('slug', 'astro-tarot')->firstOrFail();
        $game->update([
            'astro_data_modes' => [Game::ASTRO_DATA_NOW],
            'spread_types' => [Game::SPREAD_BINARY, Game::SPREAD_TIMING],
            'spread_dimensions' => [Game::DIMENSION_REL],
        ]);
        $rule = $game->rules()->orderBy('place')->firstOrFail();
        $rule->update([
            'celestial_helper' => 'mars',
            'light_shadow' => 'astro',
        ]);

        $payload = $this->validPayload(['game_id' => $game->id]);
        $payload['cards'][0]['celestial_helper'] = 'mars';
        $payload['cards'][0]['celestial_polarity'] = 'shadow';

        $user = User::factory()->create(['credits' => 10]);
        $this->actingAs($user)->postJson(route('reading'), $payload)->assertOk();

        $log = ReadingLog::query()->first();
        $this->assertStringContainsString('Kategória: BINARY (eldöntendő kérdés, igen vagy nem), TIMING (alkalmas időpont, mikor). A spread_type ezek egyike legyen.', $log->user_prompt);
        $this->assertStringContainsString('Dimenzió: REL (párkapcsolat, házasság, társulás). A kifejtés ezek egyike maradjon.', $log->user_prompt);
        $this->assertStringContainsString('Ebben a körben asztrológiai képlet nincs.', $log->user_prompt);
        $this->assertStringNotContainsString('positions:', $log->user_prompt);
        $this->assertStringContainsString($game->prompt('hu'), $log->user_prompt);
        $this->assertStringContainsString('égi segítő: ♂ Mars (mars), polaritás shadow', $log->user_prompt);
        $this->assertStringContainsString('leírás: Bátorság, harag', $log->user_prompt);
        $this->assertStringContainsString('asztro: ♂ Mars; pos: ♌ Oroszlán, R, 11°, V. ház; Rel: ☿ Merkúr 90°, ♀ Vénusz 60°', $log->user_prompt);
        $this->assertStringNotContainsString('elem: tűz', $log->user_prompt);
        $this->assertMatchesRegularExpression('/lap sorszám: 1[\s\S]*LightShadow:light/', $log->user_prompt);
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

    public function test_an_empty_clarification_asks_for_a_general_reading(): void
    {
        $this->fakeOpenAi('{"status":"answer","text":"Általános kifejtés."}');
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'locale' => 'hu',
            'mode' => 'clarify',
            'follow_up' => '',
            'previous_reading' => 'Korábbi szöveg',
            'cards' => [
                [
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'deck' => 'oracle',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
                    'deck' => 'tarot',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('reading', 'Általános kifejtés.');

        $this->assertStringContainsString('Pontosító kérdés: üres.', ReadingLog::query()->first()->user_prompt);
        $this->assertStringNotContainsString('Korábbi olvasat:', ReadingLog::query()->first()->user_prompt);

        $this->actingAs($user)->postJson(route('reading'), [
            'question' => 'Mit tegyek most a kapcsolatommal?',
            'mode' => 'clarify',
            'follow_up' => '',
            'previous_reading' => '',
            'cards' => [
                ['figure_id' => 'mars', 'figure_name' => 'Mars', 'deck' => 'oracle'],
                ['figure_id' => '01-the-magician', 'figure_name' => 'A Mágus', 'deck' => 'tarot'],
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
                    'deck' => 'oracle',
                    'astro_caption' => 'Mars · Skorpió',
                ],
                [
                    'figure_id' => '01-the-magician',
                    'figure_name' => 'A Mágus',
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
        $this->assertStringContainsString('## FORDULO — BŐVEBB KIFEJTÉS', $log->user_prompt);
        $this->assertStringNotContainsString('Korábbi olvasat:', $log->user_prompt);
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

    public function test_profile_and_data_requests_travel_with_the_reading(): void
    {
        config([
            'services.openai.driver' => 'openai',
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'status' => 'answer',
                            'text' => 'Előzetes olvasat.',
                            'need' => 'birth',
                            'requests' => [[
                                'id' => '1',
                                'kind' => 'chart',
                                'moment' => 'birth',
                                'include' => ['planets'],
                            ]],
                        ], JSON_UNESCAPED_UNICODE)],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'status' => 'answer',
                            'text' => 'A születési idő után ez a kész olvasat.',
                        ], JSON_UNESCAPED_UNICODE)],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['content' => 'Pontosítom a korábbi olvasatot.'],
                    ]],
                ]),
        ]);

        $user = User::factory()->create([
            'credits' => 10,
            'birth_at' => '1990-05-01 12:30:00',
            'gender' => 'female',
            'introduction' => 'Kertész vagyok.',
        ]);

        $first = $this->actingAs($user)->postJson(route('reading'), $this->validPayload());

        $first->assertOk()
            ->assertJsonPath('reading', 'Előzetes olvasat.')
            ->assertJsonPath('need', null);

        $log = ReadingLog::query()->first();
        $this->assertStringContainsString('Kertész vagyok.', $log->user_prompt);
        $this->assertStringContainsString('Születési idő (Europe/Budapest):', $log->user_prompt);
        $this->assertStringContainsString('Nem: nő', $log->user_prompt);
        $this->assertSame(1, \App\Models\ReadingTurn::query()->where('role', 'assistant')->count());
        $this->assertSame(0, \App\Models\ReadingTurn::query()->where('kind', 'data')->count());

        $sessionId = $first->json('session_id');

        $this->mock(\App\Services\ReadingSkyLookup::class, function ($mock) {
            $mock->shouldReceive('chartCoordinates')->andReturn([
                'lat' => 47.4979,
                'lon' => 19.0402,
                'location' => 'default_site_coordinates',
            ]);
            $mock->shouldReceive('chartLines')->once()->andReturn([
                'positions' => 'Sun,Leo,1.0,5,N',
                'aspects' => '',
            ]);
            $mock->shouldReceive('fulfill')->andReturn([]);
        });

        $refined = $this->actingAs($user)->postJson(route('reading'), $this->validPayload([
            'mode' => 'refine',
            'session_id' => $sessionId,
            'refine' => [
                'need' => 'birth',
                'birth_at' => '1990-05-01 12:30:00',
            ],
        ]));
        $refined->assertOk()
            ->assertJsonPath('reading', 'A születési idő után ez a kész olvasat.');
        $this->assertStringContainsString('positions: Sun,Leo,1.0,5,N', ReadingLog::query()->latest('id')->first()->user_prompt);

        $follow = $this->actingAs($user)->postJson(route('reading'), $this->validPayload([
            'mode' => 'clarify',
            'follow_up' => 'Mit jelent ez a kapcsolatban?',
            'previous_reading' => 'A születési idő után ez a kész olvasat.',
            'session_id' => $sessionId,
        ]));
        $follow->assertOk()
            ->assertJsonPath('reading', 'Pontosítom a korábbi olvasatot.')
            ->assertJsonPath('session_id', $sessionId);

        $followUp = ReadingLog::query()->latest('id')->first();
        $this->assertStringNotContainsString('## ALAPPROMPT', $followUp->user_prompt);
        $this->assertStringContainsString('Pontosító kérdés: Mit jelent ez a kapcsolatban?', $followUp->user_prompt);
        $this->assertStringNotContainsString('Korábbi olvasat:', $followUp->user_prompt);
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

    public function test_celestial_light_shadow_is_requested_in_the_prompt_and_returned(): void
    {
        $this->fakeOpenAi(json_encode([
            'status' => 'answer',
            'text' => 'Az ég shadow felé dönt.',
            'light_shadow' => [
                ['slot' => 1, 'row' => 1, 'place' => 1, 'value' => 'shadow'],
                ['slot' => 2, 'row' => 1, 'place' => 2, 'value' => 'light'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $game = Game::query()->where('slug', 'astro-tarot')->firstOrFail();
        $game->update(['light_shadow_mode' => Game::LIGHT_SHADOW_FULL_ASTRO]);
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload([
                'game_id' => $game->id,
            ]))
            ->assertOk()
            ->assertJsonPath('reading', 'Az ég shadow felé dönt.')
            ->assertJsonPath('light_shadow.0.slot', 1)
            ->assertJsonPath('light_shadow.0.value', 'shadow')
            ->assertJsonPath('light_shadow.1.value', 'light');

        $log = ReadingLog::query()->first();
        $this->assertMatchesRegularExpression('/lap sorszám: 1[\s\S]*LightShadow:Get/', $log->user_prompt);
        $this->assertMatchesRegularExpression('/lap sorszám: 2[\s\S]*LightShadow:Get/', $log->user_prompt);
        $this->assertStringContainsString('light_shadow', $log->system_prompt);
        $this->assertSame('shadow', $log->cards[0]['light_shadow'] ?? null);
        $this->assertSame('shadow', $log->cards[0]['polarity'] ?? null);
        $this->assertSame('light', $log->cards[1]['light_shadow'] ?? null);
        $this->assertSame('positive', $log->cards[1]['variant'] ?? null);
    }

    public function test_oracle_astro_mode_requests_celestial_light_shadow_only_for_oracle_cards(): void
    {
        $this->fakeOpenAi('{"status":"answer","text":"Csak az orákulum kér eget.","light_shadow":[{"slot":1,"value":"light"}]}');

        $game = Game::query()->where('slug', 'astro-tarot')->firstOrFail();
        $game->update(['light_shadow_mode' => Game::LIGHT_SHADOW_ORACLE_ASTRO]);
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAs($user)
            ->postJson(route('reading'), $this->validPayload(['game_id' => $game->id]))
            ->assertOk()
            ->assertJsonPath('light_shadow.0.value', 'light');

        $log = ReadingLog::query()->first();
        $this->assertMatchesRegularExpression('/lap sorszám: 1[\s\S]*LightShadow:Get/', $log->user_prompt);
        $this->assertMatchesRegularExpression('/lap sorszám: 2[\s\S]*LightShadow:shadow/', $log->user_prompt);
        $this->assertSame('light', $log->cards[0]['light_shadow'] ?? null);
        $this->assertArrayNotHasKey('light_shadow', $log->cards[1]);
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
