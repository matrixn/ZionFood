<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = ($request->is('api/*') && in_array($request->header('Accept-Language'), ['ro', 'en'], true)) ? $request->header('Accept-Language') : ($request->cookie('zf_locale') ?? $request->user()?->locale ?? 'ro');
        app()->setLocale(in_array($locale, ['ro', 'en'], true) ? $locale : 'ro');
        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
