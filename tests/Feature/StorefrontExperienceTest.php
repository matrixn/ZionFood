<?php

use App\Models\StorefrontDomain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function checkoutMapFixture(string $slug): array
{
    [$owner, $location] = tenantFixture($slug);
    $location->fulfillment()->update(['delivery_enabled' => true]);
    $location->zones()->create(['name' => 'Test zone', 'postal_prefix' => '', 'fee' => 500, 'minimum_order' => 0, 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[26, 44], [27, 44], [27, 45], [26, 45], [26, 44]]]]]);

    return [$owner, $location];
}

function checkoutGeoFeature(): array
{
    return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [26.1, 44.4]], 'properties' => ['street' => 'Strada Unirii', 'housenumber' => '7', 'city' => 'București', 'countrycode' => 'RO']];
}

it('renders restaurant sharing metadata in raw html without requiring javascript', function () {
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png', 120, 53);
    Storage::disk('public')->put('branding/logo-test.png', file_get_contents($logo->getPathname()));
    [, $location] = tenantFixture('share-raw');
    $location->restaurant->update(['name' => 'Queen & Friends', 'description' => 'Mâncare <b>bună</b>', 'logo_path' => 'branding/logo-test.png']);
    $html = $this->get('/store/'.$location->public_id.'?utm_source=test')->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    expect($xpath->evaluate('string(//meta[@property="og:title"]/@content)'))->toBe('Queen & Friends Meniu Digital')
        ->and($xpath->evaluate('string(//meta[@property="og:site_name"]/@content)'))->toBe('Queen & Friends')
        ->and($xpath->evaluate('string(//meta[@property="og:image"]/@content)'))->toStartWith('http://')
        ->and($xpath->evaluate('string(//meta[@property="og:image"]/@content)'))->toContain('/shares/')
        ->and($xpath->evaluate('string(//meta[@property="og:image:width"]/@content)'))->toBe('600')
        ->and($xpath->evaluate('string(//meta[@property="og:image:height"]/@content)'))->toBe('600')
        ->and($xpath->evaluate('string(//meta[@property="og:url"]/@content)'))->toBe(url('/store/'.$location->public_id))
        ->and($xpath->evaluate('string(//meta[@property="og:description"]/@content)'))->toBe('Mâncare bună');
});

it('uses the mapped storefront brand at its root and never borrows another restaurant logo', function () {
    [, $a] = tenantFixture('share-a');
    [, $b] = tenantFixture('share-b');
    $domain = StorefrontDomain::where('location_id', $a->id)->firstOrFail();
    $domain->update(['verification_status' => 'verified']);
    $this->get('http://'.$domain->hostname.'/')->assertOk()->assertSee('content="share-a Meniu Digital"', false)->assertDontSee('property="og:image"', false);
    $this->postJson('http://'.$domain->hostname.'/api/v1/public/locations/'.$b->public_id.'/address-from-pin', ['latitude' => 44.4, 'longitude' => 26.1])->assertNotFound();
});

it('limits anonymous address lookup to active delivery polygons and validates points first', function () {
    Cache::store('array')->flush();
    Http::preventStrayRequests();
    [, $location] = checkoutMapFixture('public-address-scope');
    $url = '/api/v1/public/locations/'.$location->public_id.'/address-from-pin';
    $this->postJson($url, ['latitude' => 90, 'longitude' => 26])->assertUnprocessable();
    $this->postJson($url, ['latitude' => 40, 'longitude' => 20])->assertUnprocessable();
    $location->fulfillment()->update(['delivery_enabled' => false]);
    $this->postJson($url, ['latitude' => 44.4, 'longitude' => 26.1])->assertNotFound();
    Http::assertNothingSent();
});

it('returns only a formatted editable street number city address without sending contact details', function () {
    Cache::store('array')->flush();
    config(['geocoding.reverse_url' => 'https://photon.komoot.io/reverse']);
    Http::preventStrayRequests();
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [checkoutGeoFeature()]])]);
    [, $location] = checkoutMapFixture('public-address-format');
    $this->postJson('/api/v1/public/locations/'.$location->public_id.'/address-from-pin', ['latitude' => 44.4, 'longitude' => 26.1, 'customer_phone' => 'private-phone'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['address' => 'Strada Unirii, 7, București', 'incomplete' => false]);
    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'private-phone') && $request['lat'] == 44.4 && $request['lon'] == 26.1);
    expect($location->fresh()->setup_revision)->toBe(0);
});

it('throttles repeated public pin searches even when provider responses are cached', function () {
    Cache::store('array')->flush();
    config(['geocoding.reverse_url' => 'https://photon.komoot.io/reverse']);
    Http::preventStrayRequests();
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [checkoutGeoFeature()]])]);
    [, $location] = checkoutMapFixture('public-address-limit');
    $url = '/api/v1/public/locations/'.$location->public_id.'/address-from-pin';
    for ($i = 0; $i < 10; $i++) {
        $this->postJson($url, ['latitude' => 44.4, 'longitude' => 26.1])->assertOk();
    }
    $this->postJson($url, ['latitude' => 44.4, 'longitude' => 26.1])->assertTooManyRequests();
    Http::assertSentCount(1);
});
