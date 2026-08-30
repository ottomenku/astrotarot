<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    public function __construct(private readonly FeatureAccessService $features) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! in_array($key, FeatureCatalog::keys(), true)) {
            abort(404);
        }

        $decision = $this->features->decide($request->user(), $key);
        if ($decision['allowed']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => $decision['message'],
                'credits' => (int) ($request->user()?->credits ?? 0),
            ], $decision['status']);
        }

        abort($decision['status'], $decision['message']);
    }
}
