<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\RateLimitController;
use App\Http\Controllers\Api\ThrottleViolationController;


/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/

// General API throttle: 60 requests/minute
Route::middleware([
    'throttle:api',
    'rate.headers',
])->group(function () {

    Route::get('/test', [
        TestController::class,
        'index'
    ]);

});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

// Login throttle: 5 attempts/minute/IP
Route::post('/login', [
    AuthController::class,
    'login'
])->middleware([
    'throttle:login',
    'rate.headers',
]);


/*
|--------------------------------------------------------------------------
| Authenticated Customer/Admin APIs
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'throttle:orders',
    'rate.headers',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Place Order
    |--------------------------------------------------------------------------
    */

    Route::post('/orders', [
        OrderController::class,
        'store'
    ]);


    /*
    |--------------------------------------------------------------------------
    | Rate Limit Status
    |--------------------------------------------------------------------------
    */

    Route::get('/rate-limit/status', [
        RateLimitController::class,
        'status'
    ]);


    /*
    |--------------------------------------------------------------------------
    | 8. Reset Own Rate Limit
    |--------------------------------------------------------------------------
    */

    Route::post('/rate-limit/reset', [
        RateLimitController::class,
        'reset'
    ]);


    /*
    |--------------------------------------------------------------------------
    | 9. Rate Limit Summary
    |--------------------------------------------------------------------------
    */

    Route::get('/rate-limit/summary', [
        RateLimitController::class,
        'summary'
    ]);


    /*
    |--------------------------------------------------------------------------
    | API Token Management
    |--------------------------------------------------------------------------
    */

    // 1. List tokens
    Route::get('/tokens', [
        AuthController::class,
        'tokens'
    ]);

    // 2. Revoke one token
    Route::delete('/tokens/{id}', [
        AuthController::class,
        'revokeToken'
    ]);

    // 3. Revoke all tokens
    Route::delete('/tokens', [
        AuthController::class,
        'revokeAllTokens'
    ]);

});


/*
|--------------------------------------------------------------------------
| Admin APIs
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'is_admin',
    'throttle:admin-api',
    'rate.headers',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Orders
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/orders', [
        AdminOrderController::class,
        'index'
    ]);


    /*
    |--------------------------------------------------------------------------
    | Throttle Violation Monitoring
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/throttle-violations', [
        ThrottleViolationController::class,
        'index'
    ]);


    /*
    |--------------------------------------------------------------------------
    | 7. Export Throttle Violations
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/throttle-violations/export', [
        ThrottleViolationController::class,
        'export'
    ]);


    /*
    |--------------------------------------------------------------------------
    | 9. Advanced Throttle Statistics
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/throttle-violations/statistics', [
        ThrottleViolationController::class,
        'statistics'
    ]);


    /*
    |--------------------------------------------------------------------------
    | Admin Reset User Rate Limit
    |--------------------------------------------------------------------------
    */

    Route::post('/admin/rate-limit/reset/{userId}', [
        RateLimitController::class,
        'resetUser'
    ]);

});