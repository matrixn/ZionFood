<?php

use App\Models\Cuisine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function cuisinePhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1cAAAAASUVORK5CYII='));
}
function cuisineData(array $overrides = []): array
{
    return ['slug' => 'romanian', 'name_ro' => 'Românească', 'name_en' => 'Romanian', 'description_ro' => 'Preparate tradiționale.', 'description_en' => 'Traditional dishes.', 'is_active' => true, 'sort_order' => 300, ...$overrides];
}

it('ships all 27 requested cuisines with local photos and documented sources', function () {
    expect(Cuisine::count())->toBe(27);
    foreach (Cuisine::all() as $cuisine) {
        expect(is_file(public_path(ltrim($cuisine->image_path, '/'))))->toBeTrue()->and($cuisine->image_source_url)->toStartWith('https://commons.wikimedia.org/')->and($cuisine->image_license)->not->toBeEmpty()->and($cuisine->description_ro)->not->toBeEmpty()->and($cuisine->description_en)->not->toBeEmpty();
    }
});

it('limits catalogue editing to platform administrators and rejects unsafe images', function () {
    [$owner] = tenantFixture('catalog-tenant');
    $this->actingAs($owner)->get('/admin/cuisines')->assertForbidden();
    $this->post('/admin/cuisines', cuisineData(['image' => cuisinePhoto()]))->assertForbidden();
    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($admin)->post('/admin/cuisines', cuisineData(['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')]))->assertSessionHasErrors('image');
    expect(Cuisine::count())->toBe(27);
});

it('creates and updates catalogue entries without overwriting bundled photos', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($admin)->post('/admin/cuisines', cuisineData(['image' => cuisinePhoto()]))->assertSessionHasNoErrors();
    $record = Cuisine::where('slug', 'romanian')->firstOrFail();
    $path = substr($record->image_path, strlen('/storage/'));
    Storage::disk('public')->assertExists($path);
    $this->post('/admin/cuisines/'.$record->id, cuisineData(['is_active' => false, 'description_ro' => 'Descriere nouă.']))->assertSessionHasNoErrors();
    expect($record->fresh()->is_active)->toBeFalse()->and($record->fresh()->image_path)->toBe('/storage/'.$path);
    $this->post('/admin/cuisines/'.$record->id, cuisineData(['image' => cuisinePhoto()]))->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($path);
    expect($record->fresh()->image_path)->not->toBe('/storage/'.$path);
});

it('keeps cuisine selection separate from basics and preserves activation dates', function () {
    [$owner, $location] = tenantFixture('cuisine-choice');
    $ids = Cuisine::whereIn('slug', ['pizza', 'burger'])->pluck('id')->all();
    $url = '/app/locations/'.$location->public_id.'/setup/cuisines';
    $this->actingAs($owner)->get($url)->assertInertia(fn (Assert $page) => $page->where('step', 'cuisines')->has('cuisines', 27)->has('selectedCuisines', 0));
    $this->put($url, ['revision' => 0, 'cuisine_ids' => $ids])->assertSessionHasNoErrors();
    $date = $location->cuisines()->first()->pivot->created_at->toIso8601String();
    $this->travel(1)->days();
    $this->put($url, ['revision' => 1, 'cuisine_ids' => $ids])->assertSessionHasNoErrors();
    expect($location->cuisines()->count())->toBe(2)->and($location->cuisines()->first()->pivot->created_at->toIso8601String())->toBe($date);
    $this->put($url, ['revision' => 2, 'cuisine_ids' => [$ids[0]]])->assertSessionHasNoErrors();
    expect($location->cuisines()->count())->toBe(1)->and($location->fresh()->setup_revision)->toBe(3);
});

it('rejects new inactive choices while keeping existing inactive selections visible and removable', function () {
    [$owner, $location] = tenantFixture('inactive-cuisine');
    $pizza = Cuisine::where('slug', 'pizza')->first();
    $burger = Cuisine::where('slug', 'burger')->first();
    $url = '/app/locations/'.$location->public_id.'/setup/cuisines';
    $this->actingAs($owner)->put($url, ['revision' => 0, 'cuisine_ids' => [$pizza->id]])->assertSessionHasNoErrors();
    $pizza->update(['is_active' => false]);
    $burger->update(['is_active' => false]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page->has('cuisines', 26)->has('selectedCuisines', 1));
    $this->put($url, ['revision' => 1, 'cuisine_ids' => [$pizza->id, $burger->id]])->assertSessionHasErrors('cuisine_ids');
    $this->put($url, ['revision' => 1, 'cuisine_ids' => [$pizza->id]])->assertSessionHasNoErrors();
    $this->put($url, ['revision' => 2, 'cuisine_ids' => []])->assertSessionHasNoErrors();
    expect($location->cuisines()->count())->toBe(0);
});

it('enforces tenant access, catalogue ids, distinct choices and setup revision', function () {
    [$owner, $location] = tenantFixture('cuisine-integrity');
    [$foreign] = tenantFixture('cuisine-foreign');
    $id = Cuisine::first()->id;
    $url = '/app/locations/'.$location->public_id.'/setup/cuisines';
    $this->actingAs($foreign)->put($url, ['revision' => 0, 'cuisine_ids' => [$id]])->assertForbidden();
    $this->actingAs($owner)->put($url, ['revision' => 0, 'cuisine_ids' => [999999]])->assertSessionHasErrors('cuisine_ids');
    $this->put($url, ['revision' => 0, 'cuisine_ids' => [$id, $id]])->assertSessionHasErrors();
    $this->put($url, ['revision' => 0, 'cuisine_ids' => [$id]])->assertSessionHasNoErrors();
    $this->put($url, ['revision' => 0, 'cuisine_ids' => []])->assertSessionHasErrors('revision');
    expect($location->cuisines()->first()->pivot->organization_id)->toBe($location->organization_id);
});
