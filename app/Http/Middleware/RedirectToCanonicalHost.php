<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalHost
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->is('.well-known/*')) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        $current = $request->getHost();

        if (! is_string($canonical) || $canonical === '' || $this->isLocalHost($current)) {
            return $next($request);
        }

        if (strcasecmp($current, $canonical) === 0) {
            return $next($request);
        }

        $target = rtrim((string) config('app.url'), '/').$request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function isLocalHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }
}
