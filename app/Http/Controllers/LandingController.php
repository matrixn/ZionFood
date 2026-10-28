<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\LandingSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function show(Request $request, LandingSeo $seo)
    {
        abort_if($request->attributes->has('storefront_location'), 404);
        $locale = $request->is('en') ? 'en' : 'ro';
        app()->setLocale($locale);
        $meta = $seo->meta($locale, (bool) $request->user());
        $response = Inertia::render('Welcome', ['locale' => $locale, 'seo' => $meta])->withViewData('landingMeta', $meta)->toResponse($request);

        return $response->cookie('zf_locale', $locale, 525600, '/', null, $request->isSecure(), true, false, 'lax');
    }

    public function robots(Request $request, LandingSeo $seo)
    {
        $central = ! $request->attributes->has('storefront_location') && in_array($request->getHost(), config('zionfood.app_hosts'), true);
        $text = "User-agent: *\n";
        if ($central && str_starts_with($seo->meta('ro')['robots'], 'index')) {
            $text .= "Allow: /\nSitemap: ".$seo->baseUrl()."/sitemap.xml\n";
        } elseif ($central || in_array($request->getHost(), config('zionfood.api_hosts'), true)) {
            $text .= "Disallow: /\n";
        } else {
            // Restaurant domains keep their existing public-menu indexing policy.
            $text .= "Allow: /\n";
        }

        return response($text)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(Request $request, LandingSeo $seo)
    {
        abort_if($request->attributes->has('storefront_location'), 404);
        $meta = $seo->meta('ro');

        return response()->view('marketing.sitemap', ['urls' => str_starts_with($meta['robots'], 'index') ? $meta['alternates'] : []])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
