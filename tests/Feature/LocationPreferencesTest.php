<?php

use App\Domain\Restaurants\LocationPreferences;
use App\Http\Controllers\PublicOrderingController;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('validates location display preferences and prevents stale or foreign writes', function () {
    [$owner, $location] = tenantFixture();
    [$foreign] = tenantFixture('foreign-preferences');
    $data = [...LocationPreferences::DEFAULTS, 'revision' => 0, 'date_format' => 'Y-m-d', 'datetime_format' => 'd.m.Y H:i:s', 'show_phone' => false];
    $url = '/app/locations/'.$location->public_id.'/general-settings';
    $this->actingAs($foreign)->putJson($url, $data)->assertForbidden();
    $this->actingAs($owner)->put($url, $data)->assertRedirect();
    expect($location->fresh()->display_settings['show_phone'])->toBeFalse();
    $this->putJson($url, $data)->assertConflict();
    $this->putJson($url, [...$data, 'revision' => 1, 'date_format' => '<script>'])->assertUnprocessable();
    $this->get('/app/locations/'.$location->public_id.'/setup/general')->assertInertia(fn (Assert $page) => $page->component('restaurant/Setup')->where('step', 'general')->has('editor.location.display_settings'));
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get($url)->assertForbidden();
});

it('filters public contact fields and photos immediately without changing the saved source or shared menu cache', function () {
    [$owner,$location,$item] = tenantFixture();
    $location->restaurant->update(['contact_phone' => '0712345678', 'contact_email' => 'contact@example.test', 'website' => 'https://example.test', 'description' => 'Description']);
    $item->update(['image_path' => '/storage/product.jpg']);
    $public = app(PublicOrderingController::class);
    expect($public->data($location)['restaurant']['contact_phone'])->toBe('0712345678');
    app(LocationPreferences::class)->save($owner, $location, [...LocationPreferences::DEFAULTS, 'revision' => 0, 'show_phone' => false, 'show_email' => false, 'show_website' => false, 'show_address' => false, 'show_description' => false, 'show_product_images' => false]);
    $data = $public->data($location->fresh());
    expect($data['restaurant']['contact_phone'])->toBeNull()->and($data['restaurant']['contact_email'])->toBeNull()->and($data['restaurant']['website'])->toBeNull()->and($data['restaurant']['description'])->toBeNull()->and($data['location']['address'])->toBeNull()->and($data['menus'][0]['categories'][0]['items'][0]['image_path'])->toBeNull();
    expect($location->fresh()->restaurant->contact_phone)->toBe('0712345678')->and($item->fresh()->image_path)->toBe('/storage/product.jpg');
    app(LocationPreferences::class)->save($owner, $location->fresh(), [...LocationPreferences::DEFAULTS, 'revision' => 1]);
    expect($public->data($location->fresh())['menus'][0]['categories'][0]['items'][0]['image_path'])->toBe('/storage/product.jpg');
});
