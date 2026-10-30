<?php

namespace App\Http\Controllers;

use App\Domain\Orders\ManagePromotion;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Promotion;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionController extends Controller
{
    public function index(Request $r, string $location)
    {
        $l = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($r->user(), $l, 'orders', true);

        return Inertia::render('restaurant/Promotions', [...app(WorkspaceController::class)->workspaceProps($r, $location, 'settings'), 'promotions' => Promotion::where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id)->where('location_id', $l->id)->latest('id')->get()]);
    }

    public function save(Request $r, string $location, ?string $promotion = null)
    {
        $l = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $p = $promotion ? Promotion::where('restaurant_id', $l->restaurant_id)->where('location_id', $l->id)->where('public_id', $promotion)->firstOrFail() : null;
        app(ManagePromotion::class)->execute($r->user(), $l, $r->all(), $p);

        return back();
    }
}
