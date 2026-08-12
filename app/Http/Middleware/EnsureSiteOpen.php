<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteOpen
{
    public function __construct(private readonly AppSettings $settings)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->siteEnabled()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => __('messages.maintenance_title'),
                'message' => __('messages.maintenance_body'),
            ], 503);
        }

        return response()->view('maintenance', [], 503);
    }

    private function isExempt(Request $request): bool
    {
        return $request->is([
            'login',
            'logout',
            'admin',
            'admin/*',
            'locale/*',
            'up',
        ]);
    }
}
