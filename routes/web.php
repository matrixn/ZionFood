<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\DiningController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuContentController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MigrationCenterController;
use App\Http\Controllers\PublicOrderingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RestaurantSetupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceNotificationController;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request, PublicOrderingController $controller) {
    if ($id = $request->attributes->get('storefront_location')) {
        return $controller->page($request, RestaurantLocation::findOrFail($id));
    }

    return Inertia::render('Welcome');
});
Route::get('/store/{location}', function (Request $r, string $location, PublicOrderingController $c) {
    return redirect($c->resolve($r, $location)->storefront_path, 301);
});
Route::get('/restaurant/{slug}/{location?}', [PublicOrderingController::class, 'slug']);
Route::post('/locale', [LocaleController::class, 'update']);
Route::get('/reserve/{location}', [ReservationController::class, 'form']);
Route::get('/reservations/{reservation}', fn (string $reservation) => Inertia::render('storefront/Reservation', ['reservationId' => $reservation]));
Route::get('/table/{table}', [DiningController::class, 'menu']);
Route::get('/track/{order}', fn (string $order) => Inertia::render('storefront/Tracking', ['orderId' => $order]));
Route::get('/invitations/{invitation}', [StaffController::class, 'invitation']);
Route::post('/invitations/{invitation}/prepare', [StaffController::class, 'prepare'])->middleware('throttle:invitation-prepare');
Route::post('/invitations/{invitation}', [StaffController::class, 'accept'])->middleware(['auth', 'throttle:invitation-prepare']);
// Temporary public diagnostic page; remove after email delivery is verified.
Route::get('/test-email', [TestEmailController::class, 'show']);
Route::post('/test-email', [TestEmailController::class, 'send'])->middleware('throttle:admin-mail-test');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/cuisines', [CuisineController::class, 'index']);
    Route::post('/admin/cuisines', [CuisineController::class, 'save']);
    Route::post('/admin/cuisines/{cuisine}', [CuisineController::class, 'save']);
    Route::get('/app/locations/{location}/reservations', [ReservationController::class, 'manage']);
    Route::post('/app/locations/{location}/tables/bulk', [DiningController::class, 'bulk'])->middleware('throttle:table-operations');
    Route::get('/app/locations/{location}/tables', [DiningController::class, 'manage']);
    Route::post('/app/reservations/{reservation}/status', [ReservationController::class, 'transition']);
    Route::post('/app/tables/{table}/{operation}', [DiningController::class, 'operation'])->middleware('throttle:table-operations');
    Route::get('/app/locations/{location}/setup/{step?}', [RestaurantSetupController::class, 'show']);
    Route::post('/app/locations/{location}/setup/geocode', [RestaurantSetupController::class, 'locate'])->middleware('throttle:geocoding');
    Route::post('/app/locations/{location}/setup/reverse-geocode', [RestaurantSetupController::class, 'reverse'])->middleware('throttle:geocoding');
    Route::post('/app/locations/{location}/setup/company', [RestaurantSetupController::class, 'company'])->middleware('throttle:company-lookup');
    Route::put('/app/locations/{location}/setup/{step}', [RestaurantSetupController::class, 'update']);
    Route::post('/app/locations/{location}/staff/invitations', [StaffController::class, 'invite'])->middleware('throttle:staff-invitations');
    Route::delete('/app/locations/{location}/staff/invitations/{invitation}', [StaffController::class, 'revoke']);
    Route::put('/app/locations/{location}/staff/{membership}', [StaffController::class, 'update']);
    Route::delete('/app/locations/{location}/staff/{membership}', [StaffController::class, 'destroy']);
    Route::post('/app/locations/{location}/branding', [BrandingController::class, 'update']);
    Route::get('/app', [WorkspaceController::class, 'home'])->name('dashboard');
    Route::get('/onboarding', fn () => Inertia::render('Onboarding'));
    Route::post('/onboarding', [WorkspaceController::class, 'onboard']);
    Route::get('/admin/ingredients', [IngredientController::class, 'adminIndex']);
    Route::post('/admin/ingredients/{ingredient?}', [IngredientController::class, 'adminSave']);
    Route::delete('/admin/ingredients/{ingredient}', [IngredientController::class, 'adminDestroy']);
    Route::get('/app/locations/{location}/ingredients', [IngredientController::class, 'index']);
    Route::post('/app/locations/{location}/ingredients/{ingredient?}', [IngredientController::class, 'save']);
    Route::delete('/app/locations/{location}/ingredients/{ingredient}', [IngredientController::class, 'destroy']);
    Route::get('/admin/menu-ai', [MenuContentController::class, 'aiSettings']);
    Route::post('/admin/menu-ai', [MenuContentController::class, 'aiSettings'])->middleware('throttle:menu-content');
    Route::get('/app/notifications', [WorkspaceNotificationController::class, 'index']);
    Route::post('/app/notifications/{notification}/read', [WorkspaceNotificationController::class, 'read']);
    Route::get('/app/locations/{location}/migration-center', [MigrationCenterController::class, 'index']);
    Route::post('/app/locations/{location}/migration-center', [MigrationCenterController::class, 'start'])->middleware('throttle:menu-import');
    Route::get('/app/locations/{location}/migration-center/{batch}', [MigrationCenterController::class, 'detail']);
    Route::get('/app/locations/{location}/migration-center/{batch}/print', [MigrationCenterController::class, 'printable']);
    Route::get('/app/locations/{location}/migration-center/{batch}/report', [MigrationCenterController::class, 'download']);
    Route::post('/app/locations/{location}/migration-center/{batch}/resolve', [MigrationCenterController::class, 'resolve'])->middleware('throttle:menu-import-review');
    Route::post('/app/locations/{location}/migration-center/{batch}/publish', [MigrationCenterController::class, 'publish'])->middleware('throttle:menu-import-review');
    Route::post('/app/locations/{location}/migration-center/{batch}/revert', [MigrationCenterController::class, 'revert'])->middleware('throttle:menu-import-review');
    Route::get('/app/locations/{location}/menu-content/items/{item}', [MenuContentController::class, 'itemData']);
    Route::get('/app/locations/{location}/menu-content/drafts/{draft}', [MenuContentController::class, 'detail']);
    Route::get('/app/locations/{location}/menu-content', [MenuContentController::class, 'index']);
    Route::put('/app/locations/{location}/menu-content/items/{item}', [MenuContentController::class, 'save'])->middleware('throttle:menu-content');
    Route::post('/app/locations/{location}/menu-content/generate', [MenuContentController::class, 'generate'])->middleware('throttle:menu-content');
    Route::post('/app/locations/{location}/menu-content/drafts/{draft}', [MenuContentController::class, 'review'])->middleware('throttle:menu-content');
    Route::get('/app/locations/{location}/{section?}', [WorkspaceController::class, 'show']);
    Route::post('/app/locations/{location}/categories', [WorkspaceController::class, 'category']);
    Route::post('/app/locations/{location}/modifiers', [WorkspaceController::class, 'modifier']);
    Route::put('/app/locations/{location}/settings', [WorkspaceController::class, 'settings']);
    Route::delete('/app/items/{item}', [MenuController::class, 'destroy']);
    Route::get('/admin/{organization?}', [WorkspaceController::class, 'admin']);
});
