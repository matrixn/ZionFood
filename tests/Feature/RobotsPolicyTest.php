<?php

use App\Models\StorefrontDomain;

beforeEach(function () {
    config(['seo.public_url' => 'https://food.zion3d.ro', 'seo.indexable' => true, 'zionfood.app_hosts' => ['localhost', 'food.zion3d.ro', 'alias.example.com'], 'zionfood.api_hosts' => ['api.example.com']]);
});

it('allows public content and rendering resources while discouraging private operational crawling', function () {
    $response = $this->get('https://alias.example.com/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeader('Cache-Control', 'no-store, private');
    $text = $response->getContent();
    foreach (['Allow: /', 'Allow: /build/', 'Allow: /images/', 'Allow: /storage/', 'Allow: /flags/', 'Allow: /api/v1/public/locations/*/menu$', 'Allow: /api/v1/public/locations/*/availability$', 'Disallow: /app$', 'Disallow: /admin/', 'Disallow: /horizon/', 'Disallow: /api/'] as $rule) {
        expect(explode("\n", $text))->toContain($rule);
    }
    expect($text)->toContain('Sitemap: https://food.zion3d.ro/sitemap.xml')->not->toContain('Sitemap: https://alias.example.com', 'Crawl-delay:', 'Noindex:', 'Disallow: /login', 'Disallow: /track/', 'Disallow: /table/');
    expect(explode("\n", $text))->not->toContain('Disallow: /');
    $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/track/unknown-order')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('uses the same public resource policy on verified restaurant domains without a marketing sitemap', function () {
    [, $location] = tenantFixture('robots-venue');
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'venue.example.com', 'verification_status' => 'verified']);
    $this->get('https://venue.example.com/robots.txt')->assertOk()->assertSee('Allow: /images/', false)->assertSee('Allow: /api/v1/public/locations/*/menu$', false)->assertDontSee('Sitemap:');
    config(['seo.indexable' => false]);
    expect($this->get('https://venue.example.com/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('blocks dedicated API hosts and disabled or incomplete marketing builds without public allows', function () {
    expect($this->get('https://api.example.com/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
    config(['seo.indexable' => false]);
    expect($this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
    config(['seo.indexable' => true, 'seo.prerender_path' => storage_path('framework/testing/no-robots-build')]);
    expect($this->get('/robots.txt')->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");
});
