<?php

namespace Tests\Feature;

use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_cannot_view_history(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $this->actingAs($user)
            ->get(route('readings.history'))
            ->assertForbidden();
    }

    public function test_premium_user_sees_own_successful_readings(): void
    {
        $user = User::factory()->create(['plan' => 'premium']);
        $other = User::factory()->create(['plan' => 'premium']);

        $own = ReadingLog::query()->create([
            'user_id' => $user->id,
            'locale' => 'hu',
            'question' => 'Saját kérdésem',
            'cards' => [],
            'astro' => [],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => 'Válasz',
            'model' => 'gpt-4o-mini',
            'status' => 'success',
        ]);
        ReadingLog::query()->create([
            'user_id' => $user->id,
            'locale' => 'hu',
            'question' => 'Hibás',
            'cards' => [],
            'astro' => [],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'status' => 'error',
        ]);
        $foreign = ReadingLog::query()->create([
            'user_id' => $other->id,
            'locale' => 'hu',
            'question' => 'Másik user kérdése',
            'cards' => [],
            'astro' => [],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => 'Idegen válasz',
            'status' => 'success',
        ]);

        $this->actingAs($user)
            ->get(route('readings.history'))
            ->assertOk()
            ->assertSee('Saját kérdésem', false)
            ->assertDontSee('Másik user kérdése', false)
            ->assertDontSee('Hibás', false);

        $this->actingAs($user)
            ->get(route('readings.history.show', $own))
            ->assertOk()
            ->assertSee('Saját kérdésem', false);

        $this->actingAs($user)
            ->get(route('readings.history.show', $foreign))
            ->assertNotFound();
    }
}
