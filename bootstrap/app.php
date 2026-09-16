<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Models\ThrottleViolation;

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
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })

    /*
    |--------------------------------------------------------------------------
    | Custom Throttle Exception Response
    |--------------------------------------------------------------------------
    */
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            ThrottleRequestsException $e,
            $request
        ) {

            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Store throttle violation
            |--------------------------------------------------------------------------
            */

            try {
                $limiter = 'unknown';

                $route = $request->route();

                if ($route) {
                    $middleware = $route->gatherMiddleware();

                    foreach ($middleware as $middlewareItem) {
                        if (str_starts_with($middlewareItem, 'throttle:')) {
                            $limiter = str_replace(
                                'throttle:',
                                '',
                                $middlewareItem
                            );

                            break;
                        }
                    }
                }

                ThrottleViolation::create([
                    'user_id' => $request->user()?->id,
                    'ip_address' => $request->ip(),
                    'method' => $request->method(),
                    'endpoint' => $request->path(),
                    'route_name' => $route?->getName(),
                    'limiter' => $limiter,
                    'retry_after' => $retryAfter,
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $exception) {
                /*
                |--------------------------------------------------------------------------
                | Do not break the API if violation logging fails.
                |--------------------------------------------------------------------------
                */
            }

            return response()->json([
                'status' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'error' => 'rate_limit_exceeded',
            ], 429);
        });
    })

    /*
    |--------------------------------------------------------------------------
    | Rate Limiters
    |--------------------------------------------------------------------------
    */
    ->booted(function () {

        /*
        |--------------------------------------------------------------------------
        | General API
        |--------------------------------------------------------------------------
        | 60 requests/minute per authenticated user or IP.
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('api', function (Request $request) {

            $key = $request->user()
                ? 'api:user:'.$request->user()->id
                : 'api:ip:'.$request->ip();

            return Limit::perMinute(60)->by($key);
        });


        /*
        |--------------------------------------------------------------------------
        | Login Protection
        |--------------------------------------------------------------------------
        | Maximum 5 login attempts/minute per IP.
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('login', function (Request $request) {

            return Limit::perMinute(5)
                ->by('login:ip:'.$request->ip());
        });


        /*
        |--------------------------------------------------------------------------
        | Dynamic Order Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Customer = 20/min
        | Admin    = 200/min
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('orders', function (Request $request) {

            $user = $request->user();

            if (!$user) {
                return Limit::perMinute(20)
                    ->by('orders:ip:'.$request->ip());
            }

            if ($user->role === 'admin') {
                return Limit::perMinute(200)
                    ->by('orders:user:'.$user->id);
            }

            return Limit::perMinute(20)
                ->by('orders:user:'.$user->id);
        });


        /*
        |--------------------------------------------------------------------------
        | Admin API
        |--------------------------------------------------------------------------
        | 200 requests/minute per admin user.
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('admin-api', function (Request $request) {

            $user = $request->user();

            return Limit::perMinute(200)
                ->by(
                    $user
                        ? 'admin-api:user:'.$user->id
                        : 'admin-api:ip:'.$request->ip()
                );
        });

    })

    ->create();