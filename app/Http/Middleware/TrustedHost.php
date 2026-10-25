<?php

namespace App\Http\Middleware;

use App\Models\StorefrontDomain;
use Closure;
use Illuminate\Http\Request;

class TrustedHost
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        $central = in_array($host, config('zionfood.app_hosts'), true);
        $api = in_array($host, config('zionfood.api_hosts'), true);
        if ($api && ! $central) {
            abort_unless($request->is('api/v1/*') || $request->is('up'), 404);
        }
        if (! $central && ! $api) {
            $domain = StorefrontDomain::where('hostname', $host)->where('verification_status', 'verified')->first();
            abort_unless($domain, 404);
            $request->attributes->set('storefront_location', $domain->location_id);
            abort_unless($request->is('/') || $request->is('track/*') || $request->is('api/v1/public/*'), 404);
        }
        if (! $central && ($request->is('app*') || $request->is('admin*') || $request->is('kitchen*') || $request->is('login') || $request->is('register') || $request->is('onboarding') || $request->is('horizon*') || $request->is('broadcasting/*'))) {
            abort(404);
        }

        return $next($request);
    }
}
