<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_registration_blocks_signup_and_keeps_the_app_for_members(): void
    {
        Template::factory()->create([
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        app(AppSettings::class)->putMany([
            AppSettings::REGISTRATION_ENABLED => '0',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.landing_title'), false)
            ->assertSee(__('messages.landing_cta_closed'), false)
            ->assertDontSee(__('messages.landing_cta_register'), false);

        $this->get(route('promo.share'))->assertRedirect(route('home'));

        $this->post(route('register'), [
            'name' => 'Új Vendég',
            'email' => 'vendeg@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'vendeg@example.com']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-modal', false)
            ->assertSee(__('messages.registration_closed'), false)
            ->assertDontSee(__('messages.register_submit'), false);

        $member = User::factory()->create();

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
            ]);
        });

        $this->actingAs($member)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-deck-slider', false);
    }
}
