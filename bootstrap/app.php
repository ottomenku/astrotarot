<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSiteOpen;
use App\Http\Middleware\RedirectToCanonicalHost;
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
        ]);

        $middleware->web(
            prepend: [
                RedirectToCanonicalHost::class,
            ],
            append: [
                SetLocale::class,
                EnsureSiteOpen::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && $request->expectsJson()) {
                return response()->json([
                    'error' => app()->getLocale() === 'en'
                        ? 'Your session expired. Please refresh the page and try again.'
                        : 'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.',
                ], 419);
            }

            return $response;
        });
    })->create();
