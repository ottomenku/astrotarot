<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialAuthController extends Controller
{
    /** @var list<string> */
    private const PROVIDERS = ['google', 'facebook'];

    public function __construct(private readonly CreditService $credits)
    {
    }

    public function redirect(string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        if (! $this->isSupported($provider) || ! $this->isConfigured($provider)) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('messages.social_unavailable')]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! $this->isSupported($provider) || ! $this->isConfigured($provider)) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('messages.social_unavailable')]);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('messages.social_failed')]);
        }

        $email = strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('messages.social_email_required')]);
        }

        $providerId = (string) $socialUser->getId();
        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@')));

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        $isNew = false;

        if (! $user) {
            if (! app(AppSettings::class)->registrationEnabled()) {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => __('messages.registration_closed')]);
            }

            $isNew = true;
            $user = new User([
                'name' => $name !== '' ? $name : 'User',
                'email' => $email,
                'role' => Role::User,
                'credits' => 0,
                'plan' => 'free',
                'template_id' => Template::query()->where('is_active', true)->value('id'),
            ]);
        }

        $user->provider = $provider;
        $user->provider_id = $providerId;
        $user->email_verified_at ??= now();
        if ($user->name === '' || $user->name === null) {
            $user->name = $name !== '' ? $name : 'User';
        }
        $user->save();

        if ($isNew) {
            $this->credits->grantSignupBonus($user);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if (! $isNew) {
            $this->credits->grantDailyLoginBonus($user);
        }

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.ephemeris.index'));
        }

        $redirect = redirect()->intended(route('home'));

        if ($isNew) {
            $redirect
                ->with('credits_welcome', true)
                ->with('credits_balance', (int) $user->fresh()->credits);
        }

        return $redirect;
    }

    private function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    private function isConfigured(string $provider): bool
    {
        $clientId = (string) config("services.{$provider}.client_id");
        $clientSecret = (string) config("services.{$provider}.client_secret");

        return $clientId !== '' && $clientSecret !== '';
    }
}
