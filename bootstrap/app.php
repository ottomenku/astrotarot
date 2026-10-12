<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsurePaidFeatures;
use App\Http\Middleware\EnsureSiteOpen;
use App\Http\Middleware\EnsureUserNotBanned;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RestrictGuestsWhenRegistrationClosed;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'feature' => EnsureFeatureAccess::class,
            'paid' => EnsurePaidFeatures::class,
        ]);

        $middleware->web(
            prepend: [
                RedirectToCanonicalHost::class,
            ],
            append: [
                SetLocale::class,
                EnsureUserNotBanned::class,
                EnsureSiteOpen::class,
                RestrictGuestsWhenRegistrationClosed::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return $response;
            }

            if ($response->getStatusCode() === 419) {
                return response()->json([
                    'error' => app()->getLocale() === 'en'
                        ? 'Your session expired. Please refresh the page and try again.'
                        : 'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.',
                ], 419);
            }

            $status = $response->getStatusCode();
            if ($status < 400) {
                return $response;
            }

            $payload = json_decode($response->getContent(), true);
            if (! is_array($payload)) {
                return $response;
            }

            $existing = $payload['error'] ?? null;
            if (is_string($existing) && $existing !== '') {
                return $response;
            }

            $message = $payload['message'] ?? null;
            if (! is_string($message) || $message === '') {
                return $response;
            }

            $payload['error'] = $message;

            return response()->json($payload, $status);
        });
    })->create();
