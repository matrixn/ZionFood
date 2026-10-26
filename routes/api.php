<?php

use App\Http\Controllers\CheckoutAddressController;
use App\Http\Controllers\DiningController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PublicOrderingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('public/locations/{location}/reservation-slots', [ReservationController::class, 'available'])->middleware('throttle:reservation-slots');
    Route::post('public/locations/{location}/reservations', [ReservationController::class, 'store'])->middleware('throttle:reservations');
    Route::get('public/reservations/{reservation}', [ReservationController::class, 'track'])->middleware('throttle:tracking');
    Route::post('public/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('throttle:reservations');
    Route::post('public/tables/{table}/connect', [DiningController::class, 'connect'])->middleware('throttle:table-connect');
    Route::post('public/locations/{location}/address-from-pin', CheckoutAddressController::class)->middleware('throttle:checkout-address');
    Route::get('public/locations/{location}/menu', [PublicOrderingController::class, 'menu']);
    Route::post('public/locations/{location}/checkout', [PublicOrderingController::class, 'checkout'])->middleware('throttle:checkout');
    Route::get('public/orders/{order}', [PublicOrderingController::class, 'tracking'])->middleware('throttle:tracking');
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('locations/{location}/webhooks', [WebhookController::class, 'index']);
        Route::post('locations/{location}/webhooks', [WebhookController::class, 'store']);
        Route::put('locations/{location}/webhooks/{endpoint}', [WebhookController::class, 'update']);
        Route::delete('locations/{location}/webhooks/{endpoint}', [WebhookController::class, 'destroy']);
        Route::post('locations/{location}/webhook-deliveries/{delivery}/retry', [WebhookController::class, 'retry'])->middleware('throttle:webhook-retry');
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
