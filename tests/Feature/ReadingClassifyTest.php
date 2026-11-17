<?php

namespace Tests\Feature;

use App\Models\BirthProfile;
use App\Models\Game;
use App\Models\GameRule;
use App\Models\LlmExchange;
use App\Models\ReadingLog;
use App\Models\ReadingSession;
use App\Models\User;
use App\Services\CardDrawProfileService;
use App\Services\EphemerisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadingClassifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.driver' => 'ollama',
            'services.openai.api_key' => '',
            'services.openai.base_url' => 'http://127.0.0.1:11434/v1',
            'services.openai.model' => 'llama3.2',
        ]);
    }

    public function test_an_unclear_question_uses_the_default_spread(): void
    {
        $game = $this->game(['BINARY'], ['REL']);
        $game->makeDefault();
        $this->fakeReply('{"category":"N/A","dimension":"REL"}');

        $this->postJson(route('reading.classify'), [
            'question' => 'asdf qwerty zzz',
            'locale' => 'hu',
        ])->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('category', 'N/A')
            ->assertJsonPath('game.id', $game->id);

        $this->assertSame('N/A', ReadingSession::query()->first()?->category);
        $this->assertSame($game->id, ReadingSession::query()->first()?->game_id);

        $exchange = LlmExchange::query()->first();
        $this->assertNotNull($exchange);
        $this->assertStringContainsString('asdf qwerty zzz', $exchange->request_body);
        $this->assertStringContainsString('"role": "system"', $exchange->request_body);
        $decoded = json_decode($exchange->response_body, true);
        $this->assertSame('{"category":"N/A","dimension":"REL"}', $decoded['choices'][0]['message']['content'] ?? null);
    }

    public function test_a_classified_question_opens_the_most_specific_spread(): void
    {
        $this->game(['BINARY', 'CHOICE'], ['REL', 'FIN']);
        $specific = $this->game(['BINARY'], ['REL'], 'personal');
        $this->fakeReply('{"category":"BINARY","dimension":"REL"}');
        $user = User::factory()->create(['birth_at' => '1990-04-02 08:15:00']);

        $this->actingAs($user)
            ->postJson(route('reading.classify'), [
                'question' => 'Igen vagy nem, maradjak a kapcsolatban?',
                'locale' => 'hu',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('category', 'BINARY')
            ->assertJsonPath('dimension', 'REL')
            ->assertJsonPath('game.id', $specific->id)
            ->assertJsonPath('needs_user', true)
            ->assertJsonPath('needs_relation', false)
            ->assertJsonPath('has_user', true);

        $session = ReadingSession::query()->first();
        $this->assertSame($specific->id, $session?->game_id);
        $this->assertSame(2, $session?->turns()->count());
    }

    public function test_invalid_classifier_output_is_retried_once(): void
    {
        $game = $this->game(['TIMING'], ['CAR']);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            $content = $calls === 1 ? 'nem json' : '{"category":"TIMING","dimension":"CAR"}';

            return Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]);
        });

        $this->postJson(route('reading.classify'), [
            'question' => 'Mikor érdemes váltanom a munkahelyemen?',
            'locale' => 'hu',
        ])->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('game.id', $game->id);

        $this->assertSame(2, $calls);
    }

    public function test_no_matching_spread_uses_the_default_spread(): void
    {
        $game = $this->game(['BINARY'], ['REL']);
        $game->makeDefault();
        $this->fakeReply('{"category":"FORECAST","dimension":"LEG"}');

        $this->postJson(route('reading.classify'), [
            'question' => 'Mi várható a per kimenetelében?',
            'locale' => 'hu',
        ])->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('game.id', $game->id);
    }

    public function test_a_classified_reading_answers_instead_of_hiding_behind_a_data_request(): void
    {
        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->zeroOrMoreTimes()->andReturn(['chart' => ['objects' => []]]);
        });
        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')->andReturn([
                'objects' => [
                    [
                        'name' => 'Sun',
                        'sign' => 'Gemini',
                        'degree' => 11,
                        'house' => 10,
                        'longitude' => 71,
                        'retrograde' => false,
                    ],
                ],
                'houses' => [],
            ]);
        });

        $game = $this->game(['BINARY'], ['FIN'], 'now');
        $user = User::factory()->create(['credits' => 10]);
        $session = ReadingSession::query()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'locale' => 'hu',
            'question' => 'Nyerni fogok a lottón?',
            'category' => 'BINARY',
            'dimension' => 'FIN',
            'cards' => [],
        ]);

        $calls = 0;
        $bodies = [];
        Http::fake(function ($request) use (&$calls, &$bodies) {
            $calls++;
            $bodies[] = $request->body();
            $content = $calls === 1
                ? '{"status":"need_data","requests":[{"id":"1","kind":"chart","moment":"now","include":["planets"]}]}'
                : '{"status":"answer","text":"A lottó most nem a te utad."}';

            return Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('reading'), [
                'question' => $session->question,
                'locale' => 'hu',
                'mode' => 'initial',
                'game_id' => $game->id,
                'session_id' => $session->public_id,
                'cards' => [[
                    'figure_id' => 'the-star',
                    'figure_name' => 'A csillag',
                    'deck' => 'tarot',
                    'row' => 1,
                    'place' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('reading', 'A lottó most nem a te utad.')
            ->assertJsonPath('need', null);

        $this->assertSame(2, $calls);

        $exchanges = LlmExchange::query()->orderBy('sequence')->get();
        $this->assertCount(2, $exchanges);
        $this->assertSame(\App\Services\LlmTranscript::readable($bodies[0]), $exchanges[0]->request_body);
        $this->assertSame(\App\Services\LlmTranscript::readable($bodies[1]), $exchanges[1]->request_body);
        $this->assertStringContainsString('"role": "system"', $exchanges[0]->request_body);
        $this->assertStringNotContainsString('\u00', $exchanges[0]->request_body);
        $this->assertStringContainsString('need_data', $exchanges[1]->request_body);
        $this->assertStringContainsString('A lottó most nem a te utad.', $exchanges[1]->response_body);
        $decoded = json_decode($exchanges[1]->response_body, true);
        $this->assertSame(
            '{"status":"answer","text":"A lottó most nem a te utad."}',
            $decoded['choices'][0]['message']['content'] ?? null,
        );

        $admin = User::factory()->admin()->create();
        $log = ReadingLog::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.readings.show', $log))
            ->assertOk()
            ->assertSee(__('admin.readings.trace'), false)
            ->assertSee($exchanges[0]->request_body)
            ->assertSee($exchanges[0]->response_body)
            ->assertSee($exchanges[1]->request_body)
            ->assertSee($exchanges[1]->response_body);
    }

    public function test_logged_in_user_can_save_a_relation_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('account.birth-profiles.store'), [
                'role' => 'relation',
                'label' => 'Anna',
                'birth_at' => '1991-06-15 14:00:00',
                'introduction' => 'A testvérem.',
            ])
            ->assertOk()
            ->assertJsonPath('label', 'Anna');

        $this->assertSame(1, BirthProfile::query()->where('user_id', $user->id)->count());
    }

    public function test_classified_reading_reuses_the_session_and_sends_the_sky(): void
    {
        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->zeroOrMoreTimes()->andReturn(['chart' => ['objects' => []]]);
        });
        $this->mock(EphemerisService::class, function ($mock) {
            $mock->shouldReceive('calculate')->andReturn([
                'objects' => [
                    [
                        'name' => 'Sun',
                        'sign' => 'Gemini',
                        'degree' => 11,
                        'house' => 10,
                        'longitude' => 71,
                        'retrograde' => true,
                    ],
                    [
                        'name' => 'Moon',
                        'sign' => 'Aries',
                        'degree' => 12,
                        'house' => 5,
                        'longitude' => 12,
                        'retrograde' => false,
                    ],
                ],
                'houses' => [],
            ]);
        });

        $game = $this->game(['BINARY', 'TIMING', 'CHOICE', 'EXPLORATORY'], ['REL', 'FIN', 'N/A'], 'now');
        $user = User::factory()->create(['credits' => 10]);
        $session = ReadingSession::query()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'locale' => 'hu',
            'question' => 'Mikor jön el a változás a kapcsolatomban?',
            'category' => 'TIMING',
            'dimension' => 'REL',
            'cards' => [],
        ]);
        $session->turns()->create([
            'sequence' => 1,
            'role' => 'user',
            'kind' => 'classify',
            'content' => $session->question,
        ]);
        $session->turns()->create([
            'sequence' => 2,
            'role' => 'assistant',
            'kind' => 'classify',
            'content' => '{"category":"TIMING","dimension":"REL"}',
        ]);

        $sent = null;
        Http::fake(function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => 'A változás a közeli hetekben érkezik.']]],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('reading'), [
                'question' => $session->question,
                'locale' => 'hu',
                'mode' => 'initial',
                'game_id' => $game->id,
                'session_id' => $session->public_id,
                'cards' => [[
                    'figure_id' => 'mars',
                    'figure_name' => 'Mars',
                    'keyword' => 'akarat',
                    'blurb' => '',
                    'deck' => 'oracle',
                    'row' => 1,
                    'place' => 1,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('session_id', $session->public_id)
            ->assertJsonPath('reading', 'A változás a közeli hetekben érkezik.');

        $this->assertSame(1, ReadingSession::query()->count());
        $prompt = (string) data_get($sent, 'messages.1.content');
        $this->assertStringContainsString('NOW', $prompt);
        $this->assertStringContainsString('Nap: Ikrek 11fok, 10.ház, R', $prompt);
        $this->assertStringContainsString('60fok Hold', $prompt);
        $this->assertStringNotContainsString('ÉGI MESTER', $prompt);
        $this->assertStringContainsString('Az égi mester és az égi segítő a NOW blokkban van', $prompt);
        $this->assertStringNotContainsString('Ebben a körben asztrológiai képlet nincs.', $prompt);
        $this->assertStringNotContainsString('{"category"', $prompt);
        $this->assertStringContainsString('Kategória: TIMING (alkalmas időpont, mikor). A spread_type ez maradjon.', $prompt);
        $this->assertStringContainsString('Dimenzió: REL (párkapcsolat, házasság, társulás). A kifejtés ezen belül maradjon.', $prompt);
        $this->assertStringContainsString('KATEGORIA VALASZ', $prompt);
        $this->assertStringContainsString('TIMING: A legelső mondat KÖTELEZŐEN így kezdődjön: „Időpont:', $prompt);
        $this->assertStringNotContainsString('Összességében: igen.', $prompt);
        $this->assertStringNotContainsString('BINARY', $prompt);
        $this->assertStringNotContainsString('A spread_type ezek egyike legyen.', $prompt);
    }

    private function fakeReply(string $content): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $dimensions
     */
    private function game(array $types, array $dimensions, string $astro = 'none'): Game
    {
        $game = Game::factory()->create([
            'spread_types' => $types,
            'spread_dimensions' => $dimensions,
            'astro_data_modes' => $astro === 'none' ? [] : [$astro],
        ]);
        GameRule::query()->create([
            'game_id' => $game->id,
            'pack_slug' => 'tarot',
            'title_hu' => 'Lap',
            'title_en' => 'Card',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
            'draw_order' => 1,
        ]);

        return $game;
    }
}
