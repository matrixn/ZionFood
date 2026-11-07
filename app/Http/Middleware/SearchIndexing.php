<?php

namespace App\Http\Middleware;

use App\Domain\Localization\UiLocale;
use App\Domain\Marketing\LandingSeo;
use Closure;
use Illuminate\Http\Request;

class SearchIndexing
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('app', 'app/*', 'admin', 'admin/*', 'kitchen', 'kitchen/*', 'horizon*', 'login', 'register', 'register/*', 'onboarding', 'forgot-password', 'reset-password/*', 'email/*', 'two-factor-challenge', 'interest', 'test-email', 'invitations/*', 'reserve/*', 'reservations/*', 'user/*', 'track/*', 'rewards/*', 'table/*', 'api/*', 'up')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($request->is('/', 'en', 'it', 'es', 'nl') && ! $request->attributes->has('storefront_location') && $response->isSuccessful()) {
            $response->headers->set('X-Robots-Tag', app(LandingSeo::class)->meta(UiLocale::normalize($request->segment(1)), (bool) $request->user())['robots']);
        }

        return $response;
    }
}
