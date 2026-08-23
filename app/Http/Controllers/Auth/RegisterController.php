<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CreditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    public function store(Request $request, AppSettings $settings): RedirectResponse
    {
        if (! $settings->registrationEnabled()) {
            return redirect()
                ->route('home')
                ->withErrors(['email' => __('messages.registration_closed')]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::User,
            'credits' => 0,
            'plan' => 'free',
        ]);

        $this->credits->grantSignupBonus($user);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->intended(route('home'))
            ->with('credits_welcome', true)
            ->with('credits_balance', (int) $user->fresh()->credits);
    }
}
