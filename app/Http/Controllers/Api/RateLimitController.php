<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitController extends Controller
{
    /**
     * Show the authenticated user's current order API rate-limit status.
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Determine limit based on user role
        |--------------------------------------------------------------------------
        */

        $limit = $user->role === 'admin' ? 200 : 20;

        $key = 'orders:user:'.$user->id;

        $used = RateLimiter::attempts($key);

        $remaining = max($limit - $used, 0);

        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'status' => true,
            'message' => 'Rate limit status retrieved successfully.',

            'rate_limit' => [
                'type' => 'orders',
                'role' => $user->role,
                'limit_per_minute' => $limit,
                'used' => $used,
                'remaining' => $remaining,
                'reset_after_seconds' => $retryAfter,
            ],
        ]);
    }
}