<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitController extends Controller
{
    /**
     * 4. Show current rate-limit status.
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

        $limit = $this->getUserLimit($user);

        $key = 'orders:user:' . $user->id;

        $used = RateLimiter::attempts($key);

        $remaining = max(
            $limit - $used,
            0
        );

        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'status' => true,
            'message' => 'Rate limit status retrieved successfully.',

            'rate_limit' => [
                'type' => 'orders',
                'role' => $user->role,
                'plan' => $user->plan ?? 'free',
                'limit_per_minute' => $limit,
                'used' => $used,
                'remaining' => $remaining,
                'reset_after_seconds' => $retryAfter,
                'reset_at' => now()
                    ->addSeconds($retryAfter)
                    ->toDateTimeString(),
            ],
        ]);
    }

    /**
     * 8. Reset authenticated user's order rate limit.
     */
    public function reset(Request $request)
    {
        $user = $request->user();

        $key = 'orders:user:' . $user->id;

        $attemptsBefore = RateLimiter::attempts($key);

        RateLimiter::clear($key);

        return response()->json([
            'status' => true,
            'message' => 'Your order rate limit has been reset successfully.',
            'user_id' => $user->id,
            'attempts_cleared' => $attemptsBefore,
        ]);
    }

    /**
     * Admin reset another user's order rate limit.
     */
    public function resetUser(
        Request $request,
        int $userId
    ) {
        $key = 'orders:user:' . $userId;

        $attemptsBefore = RateLimiter::attempts($key);

        RateLimiter::clear($key);

        return response()->json([
            'status' => true,
            'message' => 'User rate limit reset successfully.',
            'user_id' => $userId,
            'attempts_cleared' => $attemptsBefore,
        ]);
    }

    /**
     * 9. Advanced rate-limit summary.
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $limit = $this->getUserLimit($user);

        $key = 'orders:user:' . $user->id;

        $used = RateLimiter::attempts($key);

        $remaining = max(
            $limit - $used,
            0
        );

        return response()->json([
            'status' => true,
            'message' => 'Rate limit summary retrieved successfully.',

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'plan' => $user->plan ?? 'free',
            ],

            'rate_limit' => [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $remaining,
                'usage_percentage' => $limit > 0
                    ? round(($used / $limit) * 100, 2)
                    : 0,
                'reset_after_seconds' =>
                    RateLimiter::availableIn($key),
            ],
        ]);
    }

    /**
     * Determine dynamic API limit.
     */
    private function getUserLimit($user): int
    {
        if ($user->role === 'admin') {
            return 200;
        }

        if (($user->plan ?? 'free') === 'premium') {
            return 120;
        }

        return 20;
    }
}