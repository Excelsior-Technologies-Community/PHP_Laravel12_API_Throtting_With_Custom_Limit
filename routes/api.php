<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Default API throttle (60 req/min)
Route::middleware('throttle:api')->group(function () {
    Route::get('/test', [TestController::class, 'index']);
});

// Strict login throttle
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// Order throttle (20/min)
Route::middleware(['auth:sanctum', 'throttle:orders'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});

// Admin high-limit APIs
Route::middleware(['auth:sanctum', 'throttle:admin-api'])->group(function () {
    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
});
