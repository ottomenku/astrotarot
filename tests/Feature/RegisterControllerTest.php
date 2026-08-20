<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Teszt User',
            'email' => 'teszt@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'teszt@example.com',
            'name' => 'Teszt User',
            'credits' => 10,
        ]);
        $response->assertSessionHas('credits_welcome', true);
        $response->assertSessionHas('credits_balance', 10);
    }

    public function test_home_marks_guest_for_auth_gate(): void
    {
        $this->mock(\App\Services\CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'pozitív'],
                'variants' => ['positive', 'positive'],
                'slots' => ['leo', 'fire'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-20T12:00:00Z',
                'chart' => ['objects' => [], 'aspects' => []],
            ]);
        });

        \App\Models\Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-authenticated="0"', false)
            ->assertSee('data-guest-gate', false)
            ->assertSee('data-auth-modal', false);
    }

    public function test_authenticated_home_skips_guest_gate(): void
    {
        $user = User::factory()->create();

        $this->mock(\App\Services\CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'pozitív'],
                'variants' => ['positive', 'positive'],
                'slots' => ['leo', 'fire'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-20T12:00:00Z',
                'chart' => ['objects' => [], 'aspects' => []],
            ]);
        });

        \App\Models\Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-authenticated="1"', false)
            ->assertSee('Kreditek / Csomagok', false)
            ->assertDontSee('data-guest-gate', false);
    }

    public function test_registration_welcome_message_is_shown_on_home(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $this->mock(\App\Services\CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'pozitív'],
                'variants' => ['positive', 'positive'],
                'slots' => ['leo', 'fire'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-20T12:00:00Z',
                'chart' => ['objects' => [], 'aspects' => []],
            ]);
        });

        \App\Models\Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['credits_welcome' => true, 'credits_balance' => 10])
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.credits_welcome_title'), false)
            ->assertSee(__('messages.credits_welcome_body'), false);
    }
}
