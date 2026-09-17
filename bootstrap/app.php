<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',

        then: function () {

            /*
            |--------------------------------------------------------------------------
            | API Rate Limiter
            |--------------------------------------------------------------------------
            |
            | Free    = 60 requests/minute
            | Premium = 120 requests/minute
            | Admin   = 200 requests/minute
            |
            */

            RateLimiter::for('api', function (Request $request) {

                if ($request->user()) {

                    $user = $request->user();

                    if (($user->role ?? null) === 'admin') {
                        return Limit::perMinute(200)
                            ->by('api:user:' . $user->id);
                    }

                    if (($user->plan ?? 'free') === 'premium') {
                        return Limit::perMinute(120)
                            ->by('api:user:' . $user->id);
                    }

                    return Limit::perMinute(60)
                        ->by('api:user:' . $user->id);
                }

                return Limit::perMinute(60)
                    ->by('api:ip:' . $request->ip());
            });

            /*
            |--------------------------------------------------------------------------
            | Login Rate Limiter
            |--------------------------------------------------------------------------
            |
            | Maximum 5 login attempts per minute per IP.
            |
            */

            RateLimiter::for('login', function (Request $request) {

                return Limit::perMinute(5)
                    ->by('login:ip:' . $request->ip());
            });

            /*
            |--------------------------------------------------------------------------
            | Orders Rate Limiter
            |--------------------------------------------------------------------------
            |
            | Free    = 20 requests/minute
            | Premium = 120 requests/minute
            | Admin   = 200 requests/minute
            |
            */

            RateLimiter::for('orders', function (Request $request) {

                if (!$request->user()) {

                    return Limit::perMinute(20)
                        ->by('orders:ip:' . $request->ip());
                }

                $user = $request->user();

                if (($user->role ?? null) === 'admin') {

                    return Limit::perMinute(200)
                        ->by('orders:user:' . $user->id);
                }

                if (($user->plan ?? 'free') === 'premium') {

                    return Limit::perMinute(120)
                        ->by('orders:user:' . $user->id);
                }

                return Limit::perMinute(20)
                    ->by('orders:user:' . $user->id);
            });

            /*
            |--------------------------------------------------------------------------
            | Admin API Rate Limiter
            |--------------------------------------------------------------------------
            */

            RateLimiter::for('admin-api', function (Request $request) {

                return Limit::perMinute(200)
                    ->by(
                        'admin-api:user:' .
                        ($request->user()?->id ?? $request->ip())
                    );
            });
        },
    )

    ->withMiddleware(function (Middleware $middleware) {

        /*
        |--------------------------------------------------------------------------
        | Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,

            'rate.headers' => \App\Http\Middleware\AddRateLimitHeaders::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | API Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->api(append: [
            'rate.headers',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })

    ->create();