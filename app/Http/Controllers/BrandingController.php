<?php

namespace App\Http\Controllers;

use App\Domain\Restaurants\Actions\UpdateBranding;
use App\Http\Requests\BrandingRequest;
use App\Models\RestaurantLocation;

class BrandingController extends Controller
{
    public function update(BrandingRequest $request, string $location, UpdateBranding $action)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        $action->execute($request->user(), $context->restaurant, $request->validated());

        return back()->with('success', __('Restaurant branding saved.'));
    }
}
