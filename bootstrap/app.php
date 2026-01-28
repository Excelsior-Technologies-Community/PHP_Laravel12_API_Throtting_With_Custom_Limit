<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    */
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'is_admin' => \App\Http\Middleware\IsAdmin::class, // 👑 admin check
        ]);
    })

    /*
    |--------------------------------------------------------------------------
    | Custom Exception Handling
    |--------------------------------------------------------------------------
    */
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return response()->json([
                'status' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429);
        });
    })

    /*
    |--------------------------------------------------------------------------
    | Rate Limiters (AFTER APP BOOTS)
    |--------------------------------------------------------------------------
    */
    ->booted(function () {

        // General API limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Login protection (brute-force stop)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Customer order limit
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Admin high usage limit
        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute(200)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

    })

    ->create();
