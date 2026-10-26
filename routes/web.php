<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicOrderingController;
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
Route::get('/track/{order}', fn (string $order) => Inertia::render('storefront/Tracking', ['orderId' => $order]));
Route::get('/invitations/{invitation}', [StaffController::class, 'invitation']);
Route::middleware(['auth', 'verified', 'can:platform-admin'])->group(function () {
    Route::get('/test-email', [TestEmailController::class, 'show']);
    Route::post('/test-email', [TestEmailController::class, 'send'])->middleware('throttle:admin-mail-test');
});
Route::middleware(['auth', 'verified'])->group(function () {
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
