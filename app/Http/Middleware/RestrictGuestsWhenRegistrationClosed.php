<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGuestsWhenRegistrationClosed
{
    public function __construct(private readonly AppSettings $settings)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->registrationEnabled() || $request->user()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => __('messages.registration_closed'),
            ], 403);
        }

        return redirect()
            ->route('home')
            ->withErrors(['email' => __('messages.registration_closed')]);
    }

    private function isExempt(Request $request): bool
    {
        return $request->routeIs([
            'home',
            'login',
            'logout',
            'locale.switch',
            'auth.social.redirect',
            'auth.social.callback',
            'reading.share.show',
            'reading.share.og',
        ]);
    }
}
