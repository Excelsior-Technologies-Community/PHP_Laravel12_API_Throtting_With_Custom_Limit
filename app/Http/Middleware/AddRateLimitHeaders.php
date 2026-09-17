<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRateLimitHeaders
{
    /**
     * Add useful rate-limit information to API responses.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Add rate-limit headers
        |--------------------------------------------------------------------------
        */

        if ($response->headers->has('X-RateLimit-Limit')) {
            $limit = $response->headers->get('X-RateLimit-Limit');

            $remaining = $response->headers->get(
                'X-RateLimit-Remaining'
            );

            $retryAfter = $response->headers->get(
                'Retry-After'
            );

            if (!$response->headers->has('X-RateLimit-Reset')) {
                $response->headers->set(
                    'X-RateLimit-Reset',
                    now()
                        ->addSeconds((int) ($retryAfter ?? 60))
                        ->timestamp
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Expose headers for browser/API clients
            |--------------------------------------------------------------------------
            */

            $response->headers->set(
                'Access-Control-Expose-Headers',
                'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After'
            );
        }

        return $response;
    }
}