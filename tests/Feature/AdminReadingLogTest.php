<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReadingLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_list_shows_user_and_eye_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Olvasó Anna']);
        $log = ReadingLog::query()->create([
            'user_id' => $user->id,
            'locale' => 'hu',
            'question' => 'Mit hoz a hét?',
            'cards' => [
                ['slot_title' => 'Égi mester', 'figure_name' => 'A Nap'],
            ],
            'astro' => [],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => 'Derűs napok jönnek.',
            'model' => 'gpt-4o-mini',
            'status' => 'success',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.readings.index'))
            ->assertOk()
            ->assertSee('Olvasó Anna', false)
            ->assertSee('Mit hoz a hét?', false)
            ->assertDontSee(__('admin.readings.status_all'), false)
            ->assertSee(route('admin.readings.preview', $log), false)
            ->assertSee(route('admin.readings.show', $log), false);
    }

    public function test_blue_eye_preview_shows_question_answer_and_cards(): void
    {
        $admin = User::factory()->admin()->create();
        $log = ReadingLog::query()->create([
            'user_id' => $admin->id,
            'locale' => 'en',
            'question' => 'Should I wait?',
            'cards' => [
                ['slot_title' => 'Figure', 'figure_name' => 'The Moon'],
            ],
            'astro' => [],
            'system_prompt' => 'hidden system',
            'user_prompt' => 'hidden user prompt',
            'response' => 'Wait a little longer.',
            'model' => 'gpt-4o-mini',
            'status' => 'success',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.readings.preview', $log))
            ->assertOk()
            ->assertSee('Should I wait?', false)
            ->assertSee('Wait a little longer.', false)
            ->assertSee('The Moon', false)
            ->assertDontSee('hidden system', false);
    }

    public function test_full_log_shows_a_forint_cost_estimate(): void
    {
        $admin = User::factory()->admin()->create();
        $log = ReadingLog::query()->create([
            'user_id' => $admin->id,
            'locale' => 'hu',
            'question' => 'Mit hoz a hét?',
            'cards' => [],
            'astro' => [],
            'system_prompt' => str_repeat('á', 3000),
            'user_prompt' => str_repeat('b', 3000),
            'response' => str_repeat('c', 900),
            'model' => 'gpt-5.4-mini',
            'llm_provider' => 'openai',
            'status' => 'success',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.readings.show', $log))
            ->assertOk()
            ->assertSee('Költségbecslés', false)
            ->assertSee('Bemenet, kb. token', false)
            ->assertSee('2 000', false)
            ->assertSee('300', false)
            ->assertSee('2 300', false)
            ->assertSee('Ft', false)
            ->assertSee('1 USD = 328 Ft', false);
    }

    public function test_follow_ups_and_feedback_belong_to_one_admin_reading(): void
    {
        $admin = User::factory()->admin()->create();
        $session = ReadingSession::query()->create([
            'user_id' => $admin->id,
            'locale' => 'hu',
            'question' => 'Mit hoz a hét?',
            'cards' => [],
            'feedback_rating' => 4,
            'feedback_verdict' => 'too_general',
            'feedback_comment' => 'Kevés konkrétum.',
        ]);
        $root = ReadingLog::query()->create([
            'user_id' => $admin->id,
            'session_public_id' => $session->public_id,
            'locale' => 'hu',
            'question' => 'Mit hoz a hét?',
            'cards' => [],
            'astro' => ['mode' => 'initial', 'session_id' => $session->public_id],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => 'Nyugodt napok jönnek.',
            'model' => 'gpt-5.4-mini',
            'status' => 'success',
        ]);
        ReadingLog::query()->create([
            'user_id' => $admin->id,
            'session_public_id' => $session->public_id,
            'locale' => 'hu',
            'question' => "Mit hoz a hét?\n\n[pontosítás] És a munka?",
            'cards' => [],
            'astro' => ['mode' => 'clarify', 'follow_up' => 'És a munka?', 'session_id' => $session->public_id],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => 'A munkában várj keddig.',
            'model' => 'gpt-5.4-mini',
            'status' => 'success',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.readings.index'))
            ->assertOk()
            ->assertSee('Mit hoz a hét?', false)
            ->assertSee('4/5', false)
            ->assertSee('túl általános', false)
            ->assertDontSee('[pontosítás]', false);

        $this->actingAs($admin)
            ->get(route('admin.readings.show', $root))
            ->assertOk()
            ->assertSee('Nyugodt napok jönnek.', false)
            ->assertSee('És a munka?', false)
            ->assertSee('A munkában várj keddig.', false)
            ->assertSee('túl általános', false)
            ->assertSee('Kevés konkrétum.', false)
            ->assertSee('4/5', false);
    }

    public function test_reading_feedback_is_stored_on_the_session(): void
    {
        $session = ReadingSession::query()->create([
            'locale' => 'hu',
            'question' => 'Mit hoz a hét?',
            'cards' => [],
        ]);

        $this->postJson(route('reading.feedback'), [
            'session_id' => $session->public_id,
            'rating' => 2,
            'verdict' => 'too_spiritual',
            'comment' => 'Túl ködös.',
        ])->assertOk()->assertJsonPath('ok', true);

        $session->refresh();
        $this->assertSame(2, $session->feedback_rating);
        $this->assertSame('too_spiritual', $session->feedback_verdict);
        $this->assertSame('Túl ködös.', $session->feedback_comment);
    }
}
