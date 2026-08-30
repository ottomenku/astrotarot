<?php

namespace Tests\Feature;

use App\Models\CreditRequest;
use App\Models\DataDeletionRequest;
use App\Models\ReadingLog;
use App\Models\ReadingShare;
use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_account(): void
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
    }

    public function test_free_user_can_view_own_data_and_conversations(): void
    {
        $user = User::factory()->create([
            'name' => 'Teszt Elek',
            'email' => 'elek@example.com',
            'plan' => 'free',
        ]);
        $other = User::factory()->create();

        $own = $this->makeReading($user, 'Saját kérdésem', 'Saját válasz');
        $this->makeReading($other, 'Idegen kérdés', 'Idegen válasz');

        CreditRequest::query()->create([
            'user_id' => $user->id,
            'package_key' => 'credits_50',
            'credits' => 50,
            'status' => 'pending',
            'note' => 'banki utalás',
        ]);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('Teszt Elek', false)
            ->assertSee('elek@example.com', false)
            ->assertSee('Saját kérdésem', false)
            ->assertDontSee('Idegen kérdés', false)
            ->assertSee('credits_50', false)
            ->assertSee(__('messages.account_erasure_submit'), false);

        $this->actingAs($user)
            ->get(route('account.readings.show', $own))
            ->assertOk()
            ->assertSee('Saját kérdésem', false)
            ->assertSee('Saját válasz', false);
    }

    public function test_user_cannot_open_another_users_reading_in_settings(): void
    {
        $user = User::factory()->create();
        $foreign = $this->makeReading(User::factory()->create(), 'Titkos', 'Titkos válasz');

        $this->actingAs($user)
            ->get(route('account.readings.show', $foreign))
            ->assertNotFound();
    }

    public function test_user_can_delete_own_reading_but_not_another_users(): void
    {
        $user = User::factory()->create();
        $own = $this->makeReading($user, 'Törlendő', 'Válasz');
        $foreign = $this->makeReading(User::factory()->create(), 'Másik', 'Másik válasz');

        $this->actingAs($user)
            ->delete(route('account.readings.destroy', $foreign))
            ->assertNotFound();
        $this->assertDatabaseHas('reading_logs', ['id' => $foreign->id]);

        $this->actingAs($user)
            ->delete(route('account.readings.destroy', $own))
            ->assertRedirect(route('account.show'));
        $this->assertDatabaseMissing('reading_logs', ['id' => $own->id]);
    }

    public function test_user_can_delete_all_conversations_and_shares(): void
    {
        $user = User::factory()->create();
        $this->makeReading($user, 'Egyik', 'A');
        $this->makeReading($user, 'Másik', 'B');
        ReadingShare::query()->create([
            'user_id' => $user->id,
            'token' => str_repeat('a', 24),
            'payload' => ['question' => 'Egyik'],
        ]);
        $kept = $this->makeReading(User::factory()->create(), 'Marad', 'C');

        $this->actingAs($user)
            ->delete(route('account.readings.destroy-all'))
            ->assertRedirect(route('account.show'));

        $this->assertSame(0, $user->readingLogs()->count());
        $this->assertSame(0, $user->readingShares()->count());
        $this->assertDatabaseHas('reading_logs', ['id' => $kept->id]);
    }

    public function test_user_can_submit_erasure_request_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.deletion-request.store'), ['note' => 'Kérem a törlést'])
            ->assertRedirect(route('account.show'));

        $this->assertDatabaseHas('data_deletion_requests', [
            'user_id' => $user->id,
            'status' => DataDeletionRequest::STATUS_PENDING,
            'note' => 'Kérem a törlést',
        ]);

        $this->actingAs($user)
            ->post(route('account.deletion-request.store'))
            ->assertSessionHasErrors('erasure');

        $this->assertSame(1, $user->dataDeletionRequests()->count());
    }

    public function test_admin_processes_erasure_and_keeps_payment_records(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Törlendő User',
            'email' => 'keepme@example.com',
            'plan' => 'premium',
            'credits' => 40,
        ]);
        $this->makeReading($user, 'Privát kérdés', 'Privát válasz');
        ReadingShare::query()->create([
            'user_id' => $user->id,
            'token' => str_repeat('b', 24),
            'payload' => ['question' => 'Privát kérdés'],
        ]);
        $payment = CreditRequest::query()->create([
            'user_id' => $user->id,
            'package_key' => 'credits_50',
            'credits' => 50,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('account.deletion-request.store'))
            ->assertRedirect(route('account.show'));

        $deletion = DataDeletionRequest::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.deletion-requests.index'))
            ->assertOk()
            ->assertSee('keepme@example.com', false);

        $this->actingAs($admin)
            ->post(route('admin.deletion-requests.process', $deletion))
            ->assertRedirect(route('admin.deletion-requests.index'));

        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame('Törölt fiók', $user->name);
        $this->assertNotSame('keepme@example.com', $user->email);
        $this->assertSame('premium', $user->plan);
        $this->assertSame(40, (int) $user->credits);
        $this->assertSame(0, $user->readingLogs()->count());
        $this->assertSame(0, $user->readingShares()->count());
        $this->assertDatabaseHas('credit_requests', [
            'id' => $payment->id,
            'user_id' => $user->id,
            'credits' => 50,
        ]);
        $this->assertSame(DataDeletionRequest::STATUS_PROCESSED, $deletion->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => 'keepme@example.com']);
    }

    public function test_non_admin_cannot_process_erasure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.deletion-request.store'));
        $deletion = DataDeletionRequest::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.deletion-requests.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.deletion-requests.process', $deletion))
            ->assertForbidden();
    }

    public function test_home_menu_includes_settings_for_logged_in_users(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->once()->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'negatív', 'pozitív'],
                'variants' => ['positive', 'shadow', 'positive'],
                'slots' => ['leo', 'fire', 'cardinal'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('account.show'), false)
            ->assertSee(__('messages.account_menu'), false);
    }

    private function makeReading(User $user, string $question, string $response): ReadingLog
    {
        return ReadingLog::query()->create([
            'user_id' => $user->id,
            'locale' => 'hu',
            'question' => $question,
            'cards' => [],
            'astro' => [],
            'system_prompt' => 'sys',
            'user_prompt' => 'user',
            'response' => $response,
            'model' => 'gpt-4o-mini',
            'status' => 'success',
        ]);
    }
}
