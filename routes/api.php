<?php

use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PublicOrderingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('public/locations/{location}/menu', [PublicOrderingController::class, 'menu']);
    Route::post('public/locations/{location}/checkout', [PublicOrderingController::class, 'checkout'])->middleware('throttle:checkout');
    Route::get('public/orders/{order}', [PublicOrderingController::class, 'tracking'])->middleware('throttle:tracking');
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('locations/{location}/integration-tokens', [IntegrationController::class, 'index']);
        Route::post('locations/{location}/integration-tokens', [IntegrationController::class, 'store']);
        Route::delete('locations/{location}/integration-tokens/{token}', [IntegrationController::class, 'destroy']);
        Route::get('locations/{location}/orders', [OperationsController::class, 'index']);
        Route::patch('orders/{order}/status', [OperationsController::class, 'transition']);
        Route::get('locations/{location}/menu', [MenuController::class, 'show']);
        Route::post('locations/{location}/items', [MenuController::class, 'store']);
        Route::put('items/{item}', [MenuController::class, 'update']);
    });
});
