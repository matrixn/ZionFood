<?php

namespace App\Domain\Analytics;

use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecordMenuVisit
{
    public function execute(Request $request, RestaurantLocation $location): void
    {
        // Aggregate visits, not identifiable people. No raw IP, UA or visitor ID is stored in the DB.
        if (preg_match('/bot|crawler|spider|preview|facebookexternalhit|WhatsApp/i', $request->userAgent() ?? '')) {
            return;
        }
        $day = now($location->timezone)->toDateString();
        $fingerprint = hash_hmac('sha256', $day.'|'.$location->id.'|'.$request->ip().'|'.($request->userAgent() ?? ''), config('app.key'));
        $key = 'menu-visit:'.$fingerprint;
        if (! Cache::add($key, true, now()->addMinutes(30))) {
            return;
        }
        try {
            DB::transaction(function () use ($location, $day) {
                DB::table('menu_visit_stats')->insertOrIgnore(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'day' => $day, 'visits' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('menu_visit_stats')->where('location_id', $location->id)->where('day', $day)->increment('visits', 1, ['updated_at' => now()]);
            }, 3);
        } catch (\Throwable $error) {
            Cache::forget($key);
            throw $error;
        }
    }
}
