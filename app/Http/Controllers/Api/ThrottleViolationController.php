<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThrottleViolation;
use Illuminate\Http\Request;

class ThrottleViolationController extends Controller
{
    /**
     * Display throttle violation statistics and recent records.
     */
    public function index(Request $request)
    {
        $query = ThrottleViolation::query()
            ->with('user:id,name,email,role')
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Filter by IP address
        |--------------------------------------------------------------------------
        */

        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%' . $request->ip . '%');
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by limiter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('limiter')) {
            $query->where('limiter', $request->limiter);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by endpoint
        |--------------------------------------------------------------------------
        */

        if ($request->filled('endpoint')) {
            $query->where(
                'endpoint',
                'like',
                '%' . $request->endpoint . '%'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by user
        |--------------------------------------------------------------------------
        */

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $violations = $query->paginate(
            $request->integer('per_page', 10)
        );

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        $totalViolations = ThrottleViolation::count();

        $todayViolations = ThrottleViolation::whereDate(
            'created_at',
            today()
        )->count();

        $loginViolations = ThrottleViolation::where(
            'limiter',
            'login'
        )->count();

        $orderViolations = ThrottleViolation::where(
            'limiter',
            'orders'
        )->count();

        $adminApiViolations = ThrottleViolation::where(
            'limiter',
            'admin-api'
        )->count();

        return response()->json([
            'status' => true,
            'message' => 'Throttle violation records retrieved successfully.',

            'statistics' => [
                'total_violations' => $totalViolations,
                'today_violations' => $todayViolations,
                'login_violations' => $loginViolations,
                'order_violations' => $orderViolations,
                'admin_api_violations' => $adminApiViolations,
            ],

            'filters' => [
                'ip' => $request->ip,
                'limiter' => $request->limiter,
                'endpoint' => $request->endpoint,
                'user_id' => $request->user_id,
            ],

            'violations' => $violations,
        ]);
    }
}