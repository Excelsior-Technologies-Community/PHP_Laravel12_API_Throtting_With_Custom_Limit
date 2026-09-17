<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThrottleViolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThrottleViolationController extends Controller
{
    /**
     * 6. Display violations with filters.
     */
    public function index(Request $request)
    {
        $query = ThrottleViolation::query()
            ->with('user:id,name,email,role')
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | IP filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('ip')) {
            $query->where(
                'ip_address',
                'like',
                '%' . $request->ip . '%'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Limiter filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('limiter')) {
            $query->where(
                'limiter',
                $request->limiter
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Endpoint filter
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
        | User filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Date range filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->from
            );
        }

        if ($request->filled('to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->to
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Per page
        |--------------------------------------------------------------------------
        */

        $perPage = min(
            max(
                $request->integer('per_page', 10),
                1
            ),
            100
        );

        $violations = $query->paginate($perPage);

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
            'message' =>
                'Throttle violation records retrieved successfully.',

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
                'from' => $request->from,
                'to' => $request->to,
            ],

            'violations' => $violations,
        ]);
    }

    /**
     * 7. Export violations as CSV.
     */
    public function export(Request $request)
    {
        $query = ThrottleViolation::query()
            ->with('user:id,name,email,role')
            ->latest();

        if ($request->filled('ip')) {
            $query->where(
                'ip_address',
                'like',
                '%' . $request->ip . '%'
            );
        }

        if ($request->filled('limiter')) {
            $query->where(
                'limiter',
                $request->limiter
            );
        }

        if ($request->filled('endpoint')) {
            $query->where(
                'endpoint',
                'like',
                '%' . $request->endpoint . '%'
            );
        }

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        if ($request->filled('from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->from
            );
        }

        if ($request->filled('to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->to
            );
        }

        $filename =
            'throttle_violations_' .
            now()->format('Y_m_d_H_i_s') .
            '.csv';

        return response()->streamDownload(
            function () use ($query) {

                $handle = fopen('php://output', 'w');

                fputcsv($handle, [
                    'ID',
                    'User ID',
                    'User Name',
                    'User Email',
                    'IP Address',
                    'Method',
                    'Endpoint',
                    'Route Name',
                    'Limiter',
                    'Retry After',
                    'User Agent',
                    'Created At',
                ]);

                $query->chunkById(
                    500,
                    function ($violations) use ($handle) {

                        foreach ($violations as $violation) {

                            fputcsv($handle, [
                                $violation->id,
                                $violation->user_id,
                                $violation->user?->name,
                                $violation->user?->email,
                                $violation->ip_address,
                                $violation->method,
                                $violation->endpoint,
                                $violation->route_name,
                                $violation->limiter,
                                $violation->retry_after,
                                $violation->user_agent,
                                $violation->created_at,
                            ]);
                        }
                    }
                );

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * 9. Advanced throttle statistics.
     */
    public function statistics(Request $request)
    {
        $baseQuery = ThrottleViolation::query();

        if ($request->filled('from')) {
            $baseQuery->whereDate(
                'created_at',
                '>=',
                $request->from
            );
        }

        if ($request->filled('to')) {
            $baseQuery->whereDate(
                'created_at',
                '<=',
                $request->to
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Top IP addresses
        |--------------------------------------------------------------------------
        */

        $topIps = (clone $baseQuery)
            ->select(
                'ip_address',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('ip_address')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Top endpoints
        |--------------------------------------------------------------------------
        */

        $topEndpoints = (clone $baseQuery)
            ->select(
                'endpoint',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('endpoint')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Top users
        |--------------------------------------------------------------------------
        */

        $topUsers = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total')
            )
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Limiter statistics
        |--------------------------------------------------------------------------
        */

        $byLimiter = (clone $baseQuery)
            ->select(
                'limiter',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('limiter')
            ->orderByDesc('total')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Daily statistics
        |--------------------------------------------------------------------------
        */

        $daily = (clone $baseQuery)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(
                DB::raw('DATE(created_at)')
            )
            ->orderBy('date')
            ->get();

        return response()->json([
            'status' => true,
            'message' =>
                'Advanced throttle statistics retrieved successfully.',

            'summary' => [
                'total' => (clone $baseQuery)->count(),

                'today' => (clone $baseQuery)
                    ->whereDate(
                        'created_at',
                        today()
                    )
                    ->count(),

                'this_week' => (clone $baseQuery)
                    ->whereBetween(
                        'created_at',
                        [
                            now()->startOfWeek(),
                            now()->endOfWeek(),
                        ]
                    )
                    ->count(),

                'this_month' => (clone $baseQuery)
                    ->whereBetween(
                        'created_at',
                        [
                            now()->startOfMonth(),
                            now()->endOfMonth(),
                        ]
                    )
                    ->count(),
            ],

            'top_ips' => $topIps,
            'top_endpoints' => $topEndpoints,
            'top_users' => $topUsers,
            'by_limiter' => $byLimiter,
            'daily' => $daily,
        ]);
    }
}