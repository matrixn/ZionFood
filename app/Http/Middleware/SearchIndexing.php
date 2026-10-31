<?php

namespace App\Http\Middleware;

use App\Domain\Marketing\LandingSeo;
use Closure;
use Illuminate\Http\Request;

class SearchIndexing
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('app', 'app/*', 'admin', 'admin/*', 'kitchen', 'kitchen/*', 'horizon*', 'login', 'register', 'register/*', 'onboarding', 'forgot-password', 'reset-password/*', 'email/*', 'two-factor-challenge', 'interest', 'test-email', 'invitations/*', 'reserve/*', 'reservations/*', 'user/*', 'track/*', 'table/*', 'api/*', 'up')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif (($request->is('/') || $request->is('en')) && ! $request->attributes->has('storefront_location') && $response->isSuccessful()) {
            $response->headers->set('X-Robots-Tag', app(LandingSeo::class)->meta($request->is('en') ? 'en' : 'ro', (bool) $request->user())['robots']);
        }

        return $response;
    }
}
