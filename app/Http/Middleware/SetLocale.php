<?php

namespace App\Http\Middleware;

use App\Domain\Localization\UiLocale;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = ($request->is('api/*') && in_array($request->header('Accept-Language'), UiLocale::CODES, true)) ? $request->header('Accept-Language') : ($request->cookie('zf_locale') ?? $request->user()?->locale ?? 'ro');
        $admin = $request->is('admin', 'admin/*', 'horizon*');
        $storefront = $request->attributes->has('storefront_location') || $request->is('restaurant/*', 'store/*', 'table/*', 'track/*', 'reserve/*', 'reservations/*', 'api/v1/public/*');
        if ($admin) {
            $locale = $request->cookie('zf_admin_locale') ?? $locale;
        }
        app()->setLocale(UiLocale::normalize($locale, $admin || $storefront ? UiLocale::ADMIN : UiLocale::CODES));
        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
