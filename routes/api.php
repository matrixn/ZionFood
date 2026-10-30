<?php

use App\Http\Controllers\CheckoutAddressController;
use App\Http\Controllers\DiningController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PublicOrderingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WordpressApiController;
use App\Http\Middleware\AuthenticateWordpress;
use App\Http\Middleware\WordpressIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('public/locations/{location}/reservation-slots', [ReservationController::class, 'available'])->middleware('throttle:reservation-slots');
    Route::post('public/locations/{location}/reservations', [ReservationController::class, 'store'])->middleware('throttle:reservations');
    Route::get('public/reservations/{reservation}', [ReservationController::class, 'track'])->middleware('throttle:tracking');
    Route::post('public/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('throttle:reservations');
    Route::get('public/tables/{table}/orders', [DiningController::class, 'orders'])->middleware('throttle:table-orders');
    Route::post('public/tables/{table}/connect', [DiningController::class, 'connect'])->middleware('throttle:table-connect');
    Route::post('public/locations/{location}/address-from-pin', CheckoutAddressController::class)->middleware('throttle:checkout-address');
    Route::post('public/locations/{location}/visit', [PublicOrderingController::class, 'visit'])->middleware('throttle:30,1');
    Route::get('public/locations/{location}/availability', [PublicOrderingController::class, 'availability'])->middleware('throttle:public-availability');
    Route::get('public/tables/{table}/availability', [DiningController::class, 'availability'])->middleware('throttle:public-availability');
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
        Route::get('locations/{location}/kitchen-orders', [OperationsController::class, 'kitchen']);
        Route::patch('kitchen-orders/{order}/status', [OperationsController::class, 'kitchenTransition']);
        Route::get('locations/{location}/orders', [OperationsController::class, 'index']);
        Route::patch('orders/{order}/status', [OperationsController::class, 'transition']);
        Route::get('locations/{location}/menu', [MenuController::class, 'show']);
        Route::post('locations/{location}/items', [MenuController::class, 'store']);
        Route::put('items/{item}', [MenuController::class, 'update']);
    });
});

Route::prefix('v1/wordpress')->middleware(['auth:sanctum', 'verified', AuthenticateWordpress::class, 'throttle:wordpress'])->group(function () {
    $controller = WordpressApiController::class;
    Route::get('bootstrap', [$controller, 'bootstrap']);
    Route::prefix('locations/{location}')->group(function () use ($controller) {
        foreach (['menu' => 'menu', 'availability' => 'availability', 'schedule-slots' => 'slots', 'reservation-slots' => 'reservationSlots', 'orders' => 'orders', 'kitchen' => 'kitchen', 'reservations' => 'reservations', 'tables' => 'tables', 'editable-menu' => 'editableMenu', 'promotions' => 'promotions', 'analytics' => 'analytics', 'events' => 'events'] as $path => $method) {
            Route::get($path, [$controller, $method]);
        }
        Route::get('orders/{order}', [$controller, 'order']);
        Route::get('reservations/{reservation}', [$controller, 'reservation']);
        Route::get('promotions/{promotion}', [$controller, 'promotion']);
        Route::get('tables/{table}/orders', [$controller, 'tableOrders']);
        Route::post('quote', [$controller, 'quote']);
        Route::post('orders/{order}/track', [$controller, 'trackOrder']);
        Route::post('reservations/{reservation}/track', [$controller, 'reservationTrack']);
        Route::post('tables/{table}/active-orders', [$controller, 'guestTableOrders']);
        Route::middleware(WordpressIdempotency::class)->group(function () use ($controller) {
            Route::post('checkout', [$controller, 'checkout']);
            Route::post('reservations', [$controller, 'reserve']);
            Route::post('reservations/{reservation}/cancel', [$controller, 'reservationCancel']);
            Route::patch('reservations/{reservation}/status', [$controller, 'reservationStatus']);
            Route::patch('orders/{order}/status', [$controller, 'orderStatus']);
            Route::post('tables/{table}/connect', [$controller, 'tableConnect']);
            Route::post('tables/{table}/orders/{order}/served', [$controller, 'tableServe']);
            Route::post('tables/{table}/{operation}', [$controller, 'tableOperation'])->whereIn('operation', ['open', 'close', 'rotate']);
            Route::post('categories', [$controller, 'category']);
            Route::post('modifiers', [$controller, 'modifier']);
            Route::post('items', [$controller, 'createItem']);
            Route::put('items/{item}', [$controller, 'updateItem']);
            Route::delete('items/{item}', [$controller, 'deleteItem']);
            Route::post('promotions', [$controller, 'savePromotion']);
            Route::put('promotions/{promotion}', [$controller, 'savePromotion']);
            Route::put('callback', [$controller, 'callback']);
        });
    });
});
