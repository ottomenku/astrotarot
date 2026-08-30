<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegalAcceptController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function create(): View|RedirectResponse
    {
        if (! is_array(session('pending_social'))) {
            return redirect()->route('login');
        }

        return view('auth.legal-accept', [
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function store(Request $request, AppSettings $settings): RedirectResponse
    {
        $pending = $request->session()->get('pending_social');
        if (! is_array($pending)) {
            return redirect()->route('login');
        }

        if (! $settings->registrationEnabled()) {
            $request->session()->forget('pending_social');

            return redirect()
                ->route('home')
                ->withErrors(['email' => __('messages.registration_closed')]);
        }

        $request->validate([
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
        ]);

        $email = strtolower(trim((string) ($pending['email'] ?? '')));
        $provider = (string) ($pending['provider'] ?? '');
        $providerId = (string) ($pending['provider_id'] ?? '');
        $name = trim((string) ($pending['name'] ?? ''));

        if ($email === '' || $provider === '' || $providerId === '') {
            $request->session()->forget('pending_social');

            return redirect()
                ->route('login')
                ->withErrors(['email' => __('messages.social_failed')]);
        }

        $user = User::query()->where('email', $email)->first()
            ?? User::query()->where('provider', $provider)->where('provider_id', $providerId)->first();

        if (! $user) {
            $user = new User([
                'name' => $name !== '' ? $name : 'User',
                'email' => $email,
                'role' => Role::User,
                'credits' => 0,
                'plan' => 'free',
                'template_id' => Template::query()->where('is_active', true)->value('id'),
            ]);
        }

        $isNew = ! $user->exists;
        $user->provider = $provider;
        $user->provider_id = $providerId;
        $user->email_verified_at ??= now();
        $user->terms_accepted_at ??= now();
        $user->privacy_accepted_at ??= now();
        if ($user->name === '' || $user->name === null) {
            $user->name = $name !== '' ? $name : 'User';
        }
        $user->save();

        $request->session()->forget('pending_social');
        Auth::login($user, true);
        $request->session()->regenerate();

        if ($isNew) {
            $this->credits->grantSignupBonus($user);
            event(new Registered($user));

            return redirect()
                ->intended(route('home'))
                ->with('credits_welcome', true)
                ->with('credits_balance', (int) $user->fresh()->credits);
        }

        $this->credits->grantDailyLoginBonus($user);

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.ephemeris.index'));
        }

        return redirect()->intended(route('home'));
    }
}
