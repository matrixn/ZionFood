<?php

use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('loads real editors inside setup with scoped data and keeps the standalone editors', function () {
    [$owner, $location] = tenantFixture('inline-editors');
    foreach (['hours', 'delivery', 'menu', 'branding', 'staff', 'locations'] as $step) {
        $this->actingAs($owner)->get('/app/locations/'.$location->public_id.'/setup/'.$step)
            ->assertInertia(fn (Assert $page) => $page->component('restaurant/Setup')->where('step', $step)->where('editor.location.public_id', $location->public_id)->where('editor.canManage', true));
    }
    $this->get('/app/locations/'.$location->public_id.'/setup/staff')->assertInertia(fn (Assert $p) => $p->where('editor.actorRole', 'owner')->has('editor.members', 1));
    foreach (['settings', 'menu', 'branding', 'staff', 'locations'] as $section) {
        $this->get('/app/locations/'.$location->public_id.'/'.$section)->assertInertia(fn (Assert $p) => $p->component('restaurant/'.ucfirst($section)));
    }
});

it('blocks foreign and staff access to every embedded administration editor', function () {
    [$owner, $location] = tenantFixture('inline-private');
    [$foreign] = tenantFixture('inline-foreign');
    $staff = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $staff->id, 'role' => 'staff']);
    foreach ([$foreign, $staff] as $actor) {
        foreach (['hours', 'delivery', 'menu', 'branding', 'staff', 'locations'] as $step) {
            $this->actingAs($actor)->get('/app/locations/'.$location->public_id.'/setup/'.$step)->assertForbidden();
        }
        $this->get('/app/locations/'.$location->public_id.'/locations')->assertForbidden();
    }
});

it('returns wizard editor saves to the same setup step and persists normal menu data', function () {
    [$owner, $location] = tenantFixture('inline-save');
    $url = '/app/locations/'.$location->public_id.'/setup/menu';
    $this->actingAs($owner)->from($url)->post('/app/locations/'.$location->public_id.'/categories', ['name' => 'Wizard category', 'menu_id' => $location->menus()->firstOrFail()->id])->assertRedirect($url)->assertSessionHasNoErrors();
    $this->get($url)->assertInertia(fn (Assert $p) => $p->where('editor.menus.0.categories', fn ($categories) => collect($categories)->pluck('name')->contains('Wizard category')));
    $this->put('/app/locations/'.$location->public_id.'/setup/locations', ['revision' => 0])->assertSessionHasErrors('unsupported_step');
    expect($location->fresh()->setup_revision)->toBe(0);
});

it('lists authorized locations without foreign tenant entries and marks manageable locations', function () {
    [$owner, $location] = tenantFixture('inline-locations');
    [$foreign, $other] = tenantFixture('inline-other');
    $this->actingAs($owner)->get('/app/locations/'.$location->public_id.'/locations')->assertInertia(fn (Assert $p) => $p->has('locations', 1)->where('locations.0.public_id', $location->public_id)->where('manageableLocationIds', [$location->public_id]));
    $this->get('/app/locations/'.$other->public_id.'/setup/locations')->assertForbidden();
});
