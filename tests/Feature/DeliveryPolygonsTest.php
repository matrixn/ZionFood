<?php

use App\Domain\Delivery\Polygon;
use App\Domain\Orders\Actions\PlaceOrderAction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function zoneSquare(): array
{
    return ['type' => 'Polygon', 'coordinates' => [[[24, 45], [25, 45], [25, 46], [24, 46], [24, 45]]]];
}

it('validates bounded simple GeoJSON polygons and includes their boundary', function () {
    expect(Polygon::valid(zoneSquare()))->toBeTrue()->and(Polygon::contains(zoneSquare(), 45.5, 24.5))->toBeTrue()->and(Polygon::contains(zoneSquare(), 45, 24.5))->toBeTrue()->and(Polygon::contains(zoneSquare(), 45.5, 23))->toBeFalse();
    foreach ([null, ['type' => 'Polygon', 'coordinates' => ['ring' => [[0, 0]]]], ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 1], [0, 1], [1, 0], [0, 0]]]], ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [2, 0], [0, 0]]]], ['type' => 'Polygon', 'coordinates' => [[['24', 45], [25, 45], [25, 46], ['24', 45]]]]] as $shape) {
        expect(Polygon::valid($shape))->toBeFalse();
    }
});

it('persists mapped zones in settings while keeping old postal zones supported', function () {
    [$owner,$location] = tenantFixture('drawn-zones');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/settings', ['name' => $location->name, 'address' => $location->address, 'accepting_orders' => true, 'pickup_enabled' => true, 'delivery_enabled' => true, 'hours' => [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']], 'zones' => [['name' => 'Centru', 'postal_prefix' => '', 'fee' => 900, 'minimum_order' => 1000, 'geometry' => zoneSquare(), 'priority' => 2]]])->assertSessionHasNoErrors();
    expect($location->zones()->first()->geometry)->toBe(zoneSquare());
});

it('cannot bypass geographic delivery with a forged zone id or a matching postal prefix', function () {
    [, $location,$item] = tenantFixture('geo-checkout');
    $location->fulfillment()->update(['delivery_enabled' => true]);
    $location->zones()->create(['name' => 'Polygon', 'postal_prefix' => '', 'fee' => 900, 'minimum_order' => 0, 'geometry' => zoneSquare(), 'priority' => 0]);
    $legacy = $location->zones()->create(['name' => 'Legacy', 'postal_prefix' => '24', 'fee' => 1, 'minimum_order' => 0]);
    $data = [...checkoutData($item), 'fulfillment' => 'delivery', 'delivery_address' => 'Pin location', 'postal_code' => '240000', 'delivery_zone_id' => $legacy->id, 'delivery_latitude' => 45.5, 'delivery_longitude' => 24.5];
    $result = app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid());
    expect($result['order']->delivery_fee)->toBe(900)->and($result['order']->delivery_latitude)->toBe(45.5)->and($result['order']->total)->toBe(7900);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, [...$data, 'delivery_longitude' => 23], (string) Str::uuid()))->toThrow(ValidationException::class);
    unset($data['delivery_latitude'],$data['delivery_longitude']);
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
});

it('uses configured overlap priority and the server-side zone minimum', function () {
    [, $location,$item] = tenantFixture('zone-priority');
    $location->fulfillment()->update(['delivery_enabled' => true]);
    $first = $location->zones()->create(['name' => 'First', 'postal_prefix' => '', 'fee' => 900, 'minimum_order' => 9000, 'geometry' => zoneSquare(), 'priority' => 0]);
    $cheap = $location->zones()->create(['name' => 'Cheap', 'postal_prefix' => '', 'fee' => 100, 'minimum_order' => 0, 'geometry' => zoneSquare(), 'priority' => 1]);
    $data = [...checkoutData($item), 'fulfillment' => 'delivery', 'delivery_address' => 'Pin', 'delivery_zone_id' => $cheap->id, 'delivery_latitude' => 45.5, 'delivery_longitude' => 24.5];
    expect(fn () => app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $first->update(['minimum_order' => 0]);
    expect(app(PlaceOrderAction::class)->execute($location, $data, (string) Str::uuid())['order']->delivery_fee)->toBe(900);
});

it('rejects malformed settings geometry without replacing existing zones', function () {
    [$owner,$location] = tenantFixture('bad-geometry');
    $location->zones()->create(['name' => 'Existing', 'postal_prefix' => '24', 'fee' => 0, 'minimum_order' => 0]);
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/settings', ['name' => $location->name, 'address' => $location->address, 'accepting_orders' => false, 'pickup_enabled' => true, 'delivery_enabled' => true, 'hours' => [], 'zones' => [['name' => 'Bad', 'postal_prefix' => '', 'fee' => 0, 'minimum_order' => 0, 'geometry' => ['type' => 'Polygon', 'coordinates' => []]]]])->assertSessionHasErrors('zones.0.geometry');
    expect($location->zones()->count())->toBe(1)->and($location->zones()->first()->name)->toBe('Existing');
});
