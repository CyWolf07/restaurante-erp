<?php

use App\Http\Controllers\Api\MobileController;
use App\Http\Middleware\MobileAccess;
use App\Http\Middleware\MobileTransport;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(MobileTransport::class)->group(function () {
    Route::post('auth/login', [MobileController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', MobileAccess::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [MobileController::class, 'me']);
        Route::post('auth/logout', [MobileController::class, 'logout']);
        Route::get('orders', [MobileController::class, 'orders']);
        Route::post('orders/{order}/ready', [MobileController::class, 'ready']);
    });
});
