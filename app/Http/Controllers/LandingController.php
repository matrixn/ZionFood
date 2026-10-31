<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\BuildRobotsTxt;
use App\Domain\Marketing\Actions\RecordCampaignVisit;
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

        if ($response->isSuccessful()) {
            app(RecordCampaignVisit::class)->execute($request, $locale);
        }

        return $response->cookie('zf_locale', $locale, 525600, '/', null, $request->isSecure(), true, false, 'lax');
    }

    public function robots(Request $request, LandingSeo $seo, BuildRobotsTxt $robots)
    {
        $central = ! $request->attributes->has('storefront_location') && in_array($request->getHost(), config('zionfood.app_hosts'), true);
        $crawlable = config('seo.indexable') && ($request->attributes->has('storefront_location') || ($central && str_starts_with($seo->meta('ro')['robots'], 'index')));
        $text = $robots->execute($crawlable, $crawlable && $central ? $seo->baseUrl().'/sitemap.xml' : null);

        return response($text)->header('Content-Type', 'text/plain; charset=UTF-8')->header('Cache-Control', 'no-store, private');
    }

    public function sitemap(Request $request, LandingSeo $seo)
    {
        abort_if($request->attributes->has('storefront_location'), 404);
        $meta = $seo->meta('ro');

        return response()->view('marketing.sitemap', ['urls' => str_starts_with($meta['robots'], 'index') ? $meta['alternates'] : []])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
