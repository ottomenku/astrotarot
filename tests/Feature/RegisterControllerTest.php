<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
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
            'terms' => '1',
            'privacy' => '1',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'teszt@example.com',
            'name' => 'Teszt User',
            'credits' => 10,
        ]);
        $this->assertNotNull(User::query()->where('email', 'teszt@example.com')->value('terms_accepted_at'));
        $this->assertNotNull(User::query()->where('email', 'teszt@example.com')->value('privacy_accepted_at'));
        $response->assertSessionHas('credits_welcome', true);
        $response->assertSessionHas('credits_balance', 10);
    }

    public function test_guest_cannot_register_without_legal_acceptance(): void
    {
        $payload = [
            'name' => 'Teszt User',
            'email' => 'teszt@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->withSession(['locale' => 'hu'])
            ->from(route('home'))
            ->post(route('register'), $payload)
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors([
                'privacy' => 'Kérjük, olvassa el és fogadja el az adatkezelési tájékoztatót.',
                'terms' => 'Kérjük, olvassa el és fogadja el az ÁSZF-et.',
            ]);

        $this->withSession(['locale' => 'en'])
            ->from(route('home'))
            ->post(route('register'), $payload)
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors([
                'privacy' => 'Please read and accept the privacy notice.',
                'terms' => 'Please read and accept the terms and conditions.',
            ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'teszt@example.com']);

        $this->withSession(['locale' => 'hu'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('data-accept-message="Kérjük, olvassa el és fogadja el az adatkezelési tájékoztatót."', false)
            ->assertSee('data-accept-message="Kérjük, olvassa el és fogadja el az ÁSZF-et."', false);

        $this->withSession(['locale' => 'en'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('data-accept-message="Please read and accept the privacy notice."', false)
            ->assertSee('data-accept-message="Please read and accept the terms and conditions."', false);
    }

    public function test_guest_home_shows_landing_instead_of_draw(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.landing_title'), false)
            ->assertDontSee('data-guest-gate', false);
    }

    public function test_authenticated_home_skips_guest_gate(): void
    {
        $user = User::factory()->create();

        $this->mock(CardDrawProfileService::class, function ($mock) {
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

        Template::factory()->create([
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

        $this->mock(CardDrawProfileService::class, function ($mock) {
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

        Template::factory()->create([
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
