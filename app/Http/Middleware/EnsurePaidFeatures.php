<?php

namespace App\Http\Middleware;

use App\Services\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidFeatures
{
    public function __construct(private readonly AppSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->paidFeaturesEnabled()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
