<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\PricingCatalog;
use App\Domain\Marketing\SyncExchangeRates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function preference(Request $request, PricingCatalog $catalog)
    {
        $data = $request->validate(['currency' => ['required', Rule::in(PricingCatalog::CURRENCIES)]]);

        return response()->json($catalog->publicData($data['currency']))->cookie('zf_currency', $data['currency'], 525600, '/', null, $request->isSecure(), true, false, 'lax')->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, PricingCatalog $catalog)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Pricing', ['pricing' => $catalog->adminData()]);
    }

    public function update(Request $request, PricingCatalog $catalog)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate([
            'version' => 'required|integer|min:1', 'weekly_enabled' => 'required|boolean', 'plans' => 'required|array|size:3',
            'plans.*.key' => ['required', 'distinct', Rule::in(['menu', 'orders', 'business'])],
            'plans.*.name' => 'required|string|max:80|regex:/\S/u',
            'plans.*.price_ron' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/'],
        ]);
        $catalog->update($request->user(), $data);

        return response()->json($catalog->adminData());
    }

    public function sync(Request $request, SyncExchangeRates $sync, PricingCatalog $catalog)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return response()->json(['synced' => $sync->execute(), 'pricing' => $catalog->adminData()]);
    }
}
