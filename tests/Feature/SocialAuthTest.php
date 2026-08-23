<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Template::query()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        config([
            'services.google.client_id' => 'google-id',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => 'https://astrotarot.test/auth/google/callback',
            'services.facebook.client_id' => 'facebook-id',
            'services.facebook.client_secret' => 'facebook-secret',
            'services.facebook.redirect' => 'https://astrotarot.test/auth/facebook/callback',
        ]);
    }

    public function test_login_page_shows_social_buttons_when_configured(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-modal', false)
            ->assertSee('data-auth-home', false)
            ->assertSee('app-shell', false)
            ->assertSee(__('messages.auth_modal_title'), false)
            ->assertSee(__('messages.login_google'), false)
            ->assertSee(__('messages.login_facebook'), false);
    }

    public function test_google_callback_creates_and_logs_in_user(): void
    {
        $this->mockSocialite('google', [
            'id' => 'g-123',
            'name' => 'Google User',
            'email' => 'google.user@example.com',
        ]);

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'provider' => 'google',
            'provider_id' => 'g-123',
            'role' => Role::User->value,
        ]);
    }

    public function test_social_login_links_existing_email_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'shared@example.com',
            'role' => Role::Admin,
        ]);

        $this->mockSocialite('facebook', [
            'id' => 'fb-9',
            'name' => 'Shared',
            'email' => 'shared@example.com',
        ]);

        $this->get(route('auth.social.callback', 'facebook'))
            ->assertRedirect(route('admin.ephemeris.index'));

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame('facebook', $existing->fresh()->provider);
        $this->assertSame('fb-9', $existing->fresh()->provider_id);
    }

    public function test_unconfigured_provider_is_blocked(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('auth.social.redirect', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_new_social_user_is_blocked_when_registration_is_closed(): void
    {
        app(\App\Services\AppSettings::class)->putMany([
            \App\Services\AppSettings::REGISTRATION_ENABLED => '0',
        ]);

        $this->mockSocialite('google', [
            'id' => 'g-new',
            'name' => 'New Google',
            'email' => 'new.google@example.com',
        ]);

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'new.google@example.com',
        ]);
    }

    /**
     * @param  array{id:string,name:string,email:string}  $attrs
     */
    private function mockSocialite(string $provider, array $attrs): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($attrs['id']);
        $abstract->shouldReceive('getName')->andReturn($attrs['name']);
        $abstract->shouldReceive('getNickname')->andReturn(null);
        $abstract->shouldReceive('getEmail')->andReturn($attrs['email']);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($abstract);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }
}
