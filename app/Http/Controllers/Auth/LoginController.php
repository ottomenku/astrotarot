<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    public function create(Request $request, AppSettings $settings): View
    {
        $wantsRegister = $request->boolean('register') || filled($request->old('name'));

        return view('auth.login', [
            'assetBase' => asset('templates/art-deco'),
            'registrationEnabled' => $settings->registrationEnabled(),
            'authStartTab' => $wantsRegister ? 'register' : 'login',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.failed')]);
        }

        $user = $request->user();
        if ($user?->isBanned()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.banned')]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if ($user) {
            $this->credits->grantDailyLoginBonus($user);
        }

        if ($user?->isAdmin()) {
            return redirect()->intended(route('admin.ephemeris.index'));
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
