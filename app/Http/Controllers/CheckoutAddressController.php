<?php

namespace App\Http\Controllers;

use App\Domain\Delivery\Polygon;
use App\Domain\Restaurants\LocateRestaurant;
use Illuminate\Http\Request;

class CheckoutAddressController extends Controller
{
    public function __invoke(Request $request, string $location, PublicOrderingController $public, LocateRestaurant $locator)
    {
        $context = $public->resolve($request, $location);
        abort_unless($context->accepting_orders && $context->fulfillment?->delivery_enabled, 404);
        $point = $request->validate(['latitude' => ['required', 'numeric', 'between:-85,85'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $zones = $context->zones()->where('is_active', true)->whereNotNull('geometry')->get();
        abort_unless($zones->contains(fn ($zone) => Polygon::contains($zone->geometry, (float) $point['latitude'], (float) $point['longitude'])), 422);
        $found = $locator->reverse($point);

        return response()->json([
            'address' => ! empty($found['street']) ? implode(', ', array_filter([$found['street'], $found['house_number'], $found['city']])) : null,
            'incomplete' => ! $found || ! $found['street'] || ! $found['house_number'] || ! $found['city'],
        ])->header('Cache-Control', 'no-store');
    }
}
