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
Route::middleware('throttle:api')->group(function () {

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
])->middleware('throttle:login');


/*
|--------------------------------------------------------------------------
| Authenticated Customer/Admin APIs
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'throttle:orders'
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

});


/*
|--------------------------------------------------------------------------
| Admin APIs
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'is_admin',
    'throttle:admin-api'
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

});