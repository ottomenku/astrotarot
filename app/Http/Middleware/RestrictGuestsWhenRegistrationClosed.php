<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGuestsWhenRegistrationClosed
{
    public function __construct(private readonly AppSettings $settings) {}

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
        return $request->is('login')
            || $request->routeIs([
                'home',
                'login',
                'login.store',
                'logout',
                'locale.switch',
                'auth.social.redirect',
                'auth.social.callback',
                'legal.terms',
                'legal.privacy',
                'legal.contact',
                'blog.index',
                'blog.show',
                'blog.like',
                'blog.comments.store',
                'blog.comments.destroy',
                'register.legal',
                'register.legal.store',
                'reading',
                'reading.share.show',
                'reading.share.og',
            ]);
    }
}
