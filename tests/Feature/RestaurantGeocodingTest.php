<?php

use App\Domain\Restaurants\LocateRestaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['cache.default' => 'array', 'geocoding.url' => 'https://photon.komoot.io/api/']);
    Cache::store('array')->flush();
    Http::preventStrayRequests();
});
function geoAddress(): array
{
    return ['address' => 'Strada Libertății 12', 'city' => 'București', 'country' => 'RO', 'postal_code' => '010001'];
}
function geoFeature(array $changes = []): array
{
    return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [26.1, 44.4]], 'properties' => ['name' => 'Restaurant', 'street' => 'Strada Libertății', 'housenumber' => '12', 'city' => 'București', 'country' => 'România', 'countrycode' => 'RO'], ...$changes];
}

it('requires tenant management and sufficient address fields before contacting a provider', function () {
    [$owner, $location] = tenantFixture('geo-owner');
    [$foreign] = tenantFixture('geo-foreign');
    $url = '/app/locations/'.$location->public_id.'/setup/geocode';
    $this->actingAs($foreign)->postJson($url, geoAddress())->assertForbidden();
    $this->actingAs($owner)->postJson($url, ['address' => '', 'city' => '', 'country' => ''])->assertUnprocessable()->assertJsonValidationErrors(['address', 'city', 'country']);
    Http::assertNothingSent();
});

it('locates current unsaved address fields without changing the saved restaurant', function () {
    [$owner, $location] = tenantFixture('geo-unsaved');
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [geoFeature()]])]);
    $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/setup/geocode', [...geoAddress(), 'phone' => 'private-phone', 'name' => 'Private Name'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('results.0.latitude', 44.4)->assertJsonPath('results.0.approximate', false);
    expect($location->fresh()->setup_revision)->toBe(0)->and($location->fresh()->setup_profile)->toBeNull();
    Http::assertSent(fn ($request) => $request['q'] === 'Strada Libertății 12, București, 010001' && $request['countrycode'] === 'RO' && ! str_contains($request->url(), 'private-phone') && ! str_contains($request->url(), 'Private'));
});

it('caches address results and limits uncached provider requests across users', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [geoFeature()]])]);
    $first = app(LocateRestaurant::class)->execute(geoAddress());
    expect(app(LocateRestaurant::class)->execute(geoAddress()))->toBe($first);
    expect(fn () => app(LocateRestaurant::class)->execute([...geoAddress(), 'address' => 'Another street 20']))->toThrow(ValidationException::class);
    Http::assertSentCount(1);
});

it('returns safe approximate matches and excludes foreign or invalid coordinates', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => [geoFeature(['properties' => ['street' => 'Libertății', 'city' => 'București', 'countrycode' => 'RO']]), geoFeature(['geometry' => ['type' => 'Point', 'coordinates' => [26, 95]]]), geoFeature(['properties' => ['name' => 'Foreign', 'countrycode' => 'FR']])]])]);
    $results = app(LocateRestaurant::class)->execute(geoAddress());
    expect($results)->toHaveCount(1)->and($results[0]['approximate'])->toBeTrue()->and($results[0]['label'])->toBe('Libertății, București');
});

it('handles provider errors and oversized results without retaining false matches', function () {
    Http::fake(['photon.komoot.io/*' => Http::sequence()->pushStatus(503)->push(str_repeat('x', 131073))]);
    expect(fn () => app(LocateRestaurant::class)->execute(geoAddress()))->toThrow(ValidationException::class);
    Cache::forget('geocoding:provider-cooldown');
    expect(fn () => app(LocateRestaurant::class)->execute(geoAddress()))->toThrow(ValidationException::class);
});

it('keeps private provider configuration out of setup props and stores selected pins through existing setup', function () {
    [$owner, $location] = tenantFixture('geo-save');
    config(['geocoding.url' => 'https://private-geocoder.example/api/']);
    $this->actingAs($owner)->get('/app/locations/'.$location->public_id.'/setup/basics')->assertDontSee('private-geocoder.example');
    $this->put('/app/locations/'.$location->public_id.'/setup/basics', ['revision' => 0, 'restaurant_name' => 'New name', 'name' => $location->name, ...geoAddress(), 'phone' => '0712345678', 'timezone' => 'Europe/Bucharest', 'latitude' => 44.4, 'longitude' => 26.1])->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['basics']['latitude'])->toBe(44.4);
});
