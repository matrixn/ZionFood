<?php

use App\Domain\Marketing\LandingSeo;
use App\Models\StorefrontDomain;
use App\Models\User;

function seoDocument(string $html): DOMXPath
{
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($doc);
}

beforeEach(function () {
    config(['seo.public_url' => 'https://food.zion3d.ro', 'seo.indexable' => true, 'zionfood.app_hosts' => ['localhost', '127.0.0.1', 'food.zion3d.ro', 'alias.example.com']]);
});

it('renders localized indexable landing text and metadata in raw HTML independently of cookies or user agents', function () {
    foreach (['/' => 'ro', '/en' => 'en'] as $path => $locale) {
        $response = $this->withCookie('zf_locale', $locale === 'ro' ? 'en' : 'ro')->get($path);
        $response->assertOk()->assertHeader('Content-Language', $locale)->assertCookie('zf_locale', $locale);
        expect($response->headers->get('X-Robots-Tag'))->toStartWith('index, follow');
        $dom = seoDocument($response->getContent());
        expect($dom->query('//h1'))->toHaveCount(1)
            ->and($dom->query('//section[@id="migration"]//h2')->item(0)->textContent)->toContain('GloriaFood')
            ->and($dom->query('//title'))->toHaveCount(1)
            ->and($dom->query('//meta[@name="description"]'))->toHaveCount(1)
            ->and($dom->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro'.$path)
            ->and($dom->query('//link[@hreflang="ro"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro/')
            ->and($dom->query('//link[@hreflang="en"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro/en')
            ->and($dom->query('//a[@href="/register"]')->length)->toBeGreaterThan(0);
        $schema = json_decode($dom->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        expect($schema['@graph'][2]['inLanguage'])->toBe($locale)->and($schema['@graph'][0]['logo']['url'])->toBe('https://food.zion3d.ro/images/logo/food.zion3d.ro_icons-256x256.png')->and(json_encode($schema))->not->toContain('aggregateRating')->not->toContain('price');
    }
    $this->withHeader('User-Agent', 'Googlebot')->get('/')->assertSee('id="migration"', false);
});

it('uses configured canonical URLs instead of host headers or tracking parameters and returns reciprocal sitemap alternates', function () {
    $html = $this->get('https://alias.example.com/en?utm_source=campaign')->assertOk()->getContent();
    $dom = seoDocument($html);
    expect($dom->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro/en');
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($response->getContent());
    expect($xml->url)->toHaveCount(2);
    foreach ($xml->url as $url) {
        expect($url->children('http://www.w3.org/1999/xhtml')->link)->toHaveCount(3);
    }
    expect($response->getContent())->not->toContain('/admin')->not->toContain('lastmod')->not->toContain('utm_source');
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://food.zion3d.ro/sitemap.xml', false);
});

it('prevents indexing staging local and incomplete builds without advertising nonexistent sitemap pages', function () {
    config(['seo.indexable' => false]);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/robots.txt')->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
    $this->get('/sitemap.xml')->assertDontSee('<loc>', false);
    config(['seo.indexable' => true, 'seo.prerender_path' => storage_path('framework/testing/missing-seo-artifacts')]);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    config(['seo.public_url' => 'http://localhost']);
    expect(app(LandingSeo::class)->meta('ro')['robots'])->toBe('noindex, nofollow');
});

it('keeps personalized CTA variants and live user data out of the shared rendered artifacts', function () {
    $user = User::factory()->create(['name' => 'Private owner name']);
    $response = $this->actingAs($user)->get('/')->assertOk();
    $dom = seoDocument($response->getContent());
    expect($dom->query('//section[contains(@class,"hero")]//a[@href="/app"]')->length)->toBeGreaterThan(0)
        ->and($dom->query('//section[contains(@class,"hero")]//a[@href="/register"]'))->toHaveCount(0);
    expect(file_get_contents(config('seo.prerender_path').'/ro-member.html'))->not->toContain($user->email)->not->toContain($user->name);
});

it('does not replace restaurant domains with the marketing landing or leak private routes into the sitemap', function () {
    [, $location] = tenantFixture('seo-venue');
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'venue.example.com', 'verification_status' => 'verified']);
    $this->get('https://venue.example.com/')->assertOk()->assertDontSee('id="migration"', false);
    $this->get('https://venue.example.com/en')->assertNotFound();
    $this->get('https://venue.example.com/sitemap.xml')->assertNotFound();
    $this->get('https://venue.example.com/robots.txt')->assertOk()->assertSee('Allow: /', false)->assertDontSee('Sitemap:');
    $this->get('http://localhost/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('http://localhost/test-email')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('rejects invalid or foreign canonical configuration instead of reflecting untrusted URLs', function () {
    foreach (['https://evil.example/', 'https://food.zion3d.ro/?token=secret', 'https://user@food.zion3d.ro', 'javascript:alert(1)'] as $url) {
        config(['seo.public_url' => $url]);
        expect(fn () => app(LandingSeo::class)->baseUrl())->toThrow(RuntimeException::class);
    }
});
