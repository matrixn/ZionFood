<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\DiningController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicOrderingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RestaurantSetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\WorkspaceController;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request, PublicOrderingController $controller) {
    if ($id = $request->attributes->get('storefront_location')) {
        return Inertia::render('storefront/Menu', $controller->data(RestaurantLocation::findOrFail($id)));
    }

    return Inertia::render('Welcome');
});
Route::get('/store/{location}', function (Request $r, string $location, PublicOrderingController $c) {
    return Inertia::render('storefront/Menu', $c->data($c->resolve($r, $location)));
});
Route::post('/locale', [LocaleController::class, 'update']);
Route::get('/reserve/{location}', [ReservationController::class, 'form']);
Route::get('/reservations/{reservation}', fn (string $reservation) => Inertia::render('storefront/Reservation', ['reservationId' => $reservation]));
Route::get('/table/{table}', [DiningController::class, 'menu']);
Route::get('/track/{order}', fn (string $order) => Inertia::render('storefront/Tracking', ['orderId' => $order]));
Route::get('/invitations/{invitation}', [StaffController::class, 'invitation']);
// Temporary public diagnostic page; remove after email delivery is verified.
Route::get('/test-email', [TestEmailController::class, 'show']);
Route::post('/test-email', [TestEmailController::class, 'send'])->middleware('throttle:admin-mail-test');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/cuisines', [CuisineController::class, 'index']);
    Route::post('/admin/cuisines', [CuisineController::class, 'save']);
    Route::post('/admin/cuisines/{cuisine}', [CuisineController::class, 'save']);
    Route::get('/app/locations/{location}/reservations', [ReservationController::class, 'manage']);
    Route::get('/app/locations/{location}/tables', [DiningController::class, 'manage']);
    Route::post('/app/reservations/{reservation}/status', [ReservationController::class, 'transition']);
    Route::post('/app/tables/{table}/{operation}', [DiningController::class, 'operation'])->middleware('throttle:table-operations');
    Route::get('/app/locations/{location}/setup/{step?}', [RestaurantSetupController::class, 'show']);
    Route::post('/app/locations/{location}/setup/geocode', [RestaurantSetupController::class, 'locate'])->middleware('throttle:geocoding');
    Route::post('/app/locations/{location}/setup/reverse-geocode', [RestaurantSetupController::class, 'reverse'])->middleware('throttle:geocoding');
    Route::put('/app/locations/{location}/setup/{step}', [RestaurantSetupController::class, 'update']);
    Route::post('/invitations/{invitation}', [StaffController::class, 'accept']);
    Route::post('/app/locations/{location}/staff/invitations', [StaffController::class, 'invite'])->middleware('throttle:staff-invitations');
    Route::delete('/app/locations/{location}/staff/invitations/{invitation}', [StaffController::class, 'revoke']);
    Route::put('/app/locations/{location}/staff/{membership}', [StaffController::class, 'update']);
    Route::delete('/app/locations/{location}/staff/{membership}', [StaffController::class, 'destroy']);
    Route::post('/app/locations/{location}/branding', [BrandingController::class, 'update']);
    Route::get('/app', [WorkspaceController::class, 'home'])->name('dashboard');
    Route::get('/onboarding', fn () => Inertia::render('Onboarding'));
    Route::post('/onboarding', [WorkspaceController::class, 'onboard']);
    Route::get('/app/locations/{location}/{section?}', [WorkspaceController::class, 'show']);
    Route::post('/app/locations/{location}/categories', [WorkspaceController::class, 'category']);
    Route::post('/app/locations/{location}/modifiers', [WorkspaceController::class, 'modifier']);
    Route::put('/app/locations/{location}/settings', [WorkspaceController::class, 'settings']);
    Route::delete('/app/items/{item}', [MenuController::class, 'destroy']);
    Route::get('/admin/{organization?}', [WorkspaceController::class, 'admin']);
});
