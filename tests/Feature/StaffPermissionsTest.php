<?php

use App\Domain\Tenancy\Actions\AcceptStaffInvitation;
use App\Domain\Tenancy\Actions\InviteStaff;
use App\Domain\Tenancy\StaffPermissions;
use App\Domain\Tenancy\TenantAccess;
use App\Models\LocationStaff;
use App\Models\OrganizationMembership;
use App\Models\RestaurantLocation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function featureStaff($location, array $permissions, string $role = 'staff'): User
{
    $user = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $user->id, 'role' => $role, 'permissions' => $permissions]);
    LocationStaff::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $user->id]);

    return $user;
}

it('separates view and edit permissions and denies every disabled section on web and API', function () {
    [, $location, $item] = tenantFixture('feature-views');
    $matrix = array_fill_keys(StaffPermissions::FEATURES, 'none');
    $matrix['orders'] = 'view';
    $staff = featureStaff($location, $matrix);
    $order = placeFixture($location, $item)['order'];
    $this->actingAs($staff)->get('/app/locations/'.$location->public_id.'/orders')->assertOk();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/orders')->assertOk();
    $this->patchJson('/api/v1/orders/'.$order->public_id.'/status', ['status' => 'accepted', 'version' => 1])->assertForbidden();
    foreach (['reservations', 'tables', 'kitchen', 'menu'] as $section) {
        $this->get('/app/locations/'.$location->public_id.'/'.$section)->assertForbidden();
    }
    $this->getJson('/api/v1/locations/'.$location->public_id.'/kitchen-orders')->assertForbidden();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/menu')->assertForbidden();
    expect($order->fresh()->version)->toBe(1);
});

it('allows kitchen-only work through its own active queue without granting order history access', function () {
    [, $location, $item] = tenantFixture('feature-kitchen');
    $matrix = array_fill_keys(StaffPermissions::FEATURES, 'none');
    $matrix['kitchen'] = 'manage';
    $staff = featureStaff($location, $matrix);
    $order = placeFixture($location, $item)['order'];
    $finished = placeFixture($location, $item)['order'];
    $finished->forceFill(['status' => 'completed'])->save();
    $this->actingAs($staff)->get('/app/locations/'.$location->public_id.'/kitchen')->assertOk();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/orders?feature=kitchen')->assertForbidden();
    $this->getJson('/api/v1/locations/'.$location->public_id.'/kitchen-orders?active=0')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson('/api/v1/orders/'.$order->public_id.'/status', ['status' => 'accepted', 'version' => 1])->assertForbidden();
    $this->patchJson('/api/v1/kitchen-orders/'.$order->public_id.'/status', ['status' => 'accepted', 'version' => 1])->assertOk()->assertJsonPath('data.status', 'accepted');
    $this->get('/app/locations/'.$location->public_id)->assertInertia(fn (Assert $page) => $page->where('metrics', null)->where('permissions.orders', 'none'));
});

it('grants scoped menu editing without staff administration and protects shared-location edits', function () {
    [, $location, $item] = tenantFixture('feature-menu');
    $matrix = array_fill_keys(StaffPermissions::FEATURES, 'none');
    $matrix['menu'] = 'manage';
    $staff = featureStaff($location, $matrix);
    $this->actingAs($staff)->get('/app/locations/'.$location->public_id.'/menu')->assertInertia(fn (Assert $page) => $page->where('menuWritable', true)->where('canManage', false));
    $this->get('/app/locations/'.$location->public_id.'/staff')->assertForbidden();
    $data = ['category_id' => $item->category_id, 'name' => 'Updated by menu staff', 'price' => 1234, 'is_available' => true];
    $this->putJson('/api/v1/items/'.$item->public_id, $data)->assertOk();
    $this->post('/app/locations/'.$location->public_id.'/modifiers', ['name' => 'Local extras', 'min_choices' => 0, 'max_choices' => 1, 'options' => [['name' => 'Extra', 'price' => 100]]])->assertSessionHasNoErrors();
    $second = RestaurantLocation::factory()->create(['organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id]);
    $second->menus()->attach($item->category->menu_id, ['organization_id' => $location->organization_id]);
    $this->get('/app/locations/'.$location->public_id.'/menu')->assertInertia(fn (Assert $page) => $page->where('menuWritable', false));
    $this->putJson('/api/v1/items/'.$item->public_id, [...$data, 'price' => 9999])->assertForbidden();
    $this->postJson('/app/locations/'.$location->public_id.'/modifiers', ['name' => 'Shared extras', 'min_choices' => 0, 'max_choices' => 1, 'options' => [['name' => 'Extra', 'price' => 100]]])->assertForbidden();
    expect($item->fresh()->price)->toBe(1234);
});

it('keeps owner access protected and stops managers granting capabilities they do not hold', function () {
    [$owner, $location] = tenantFixture('feature-delegation');
    $none = array_fill_keys(StaffPermissions::FEATURES, 'none');
    OrganizationMembership::where('user_id', $owner->id)->update(['permissions' => $none]);
    expect(app(TenantAccess::class)->canFeature($owner, $location, 'menu', true))->toBeTrue();
    $manager = featureStaff($location, [...$none, 'orders' => 'view'], 'manager');
    $staff = featureStaff($location, $none);
    $membership = OrganizationMembership::where('user_id', $staff->id)->firstOrFail();
    $this->actingAs($manager)->putJson('/app/locations/'.$location->public_id.'/staff/'.$membership->id, ['role' => 'staff', 'location_ids' => [$location->id], 'permissions' => [...$none, 'orders' => 'manage']])->assertForbidden();
    $this->actingAs($owner)->putJson('/app/locations/'.$location->public_id.'/staff/'.$membership->id, ['role' => 'staff', 'location_ids' => [$location->id], 'permissions' => [...$none, 'orders' => 'view']])->assertRedirect();
    expect($membership->fresh()->permissions['orders'])->toBe('view');
    $ownerMembership = OrganizationMembership::where('user_id', $owner->id)->firstOrFail();
    $this->putJson('/app/locations/'.$location->public_id.'/staff/'.$ownerMembership->id, ['role' => 'staff', 'location_ids' => [$location->id], 'permissions' => $none])->assertForbidden();
});

it('validates complete matrices and carries their exact access through invitation acceptance', function () {
    Notification::fake();
    [$owner, $location] = tenantFixture('feature-invitation');
    $none = array_fill_keys(StaffPermissions::FEATURES, 'none');
    $url = '/app/locations/'.$location->public_id.'/staff/invitations';
    $this->actingAs($owner)->postJson($url, ['email' => 'features@example.test', 'role' => 'staff', 'location_ids' => [$location->id], 'permissions' => ['orders' => 'manage', 'admin' => 'manage']])->assertUnprocessable();
    $matrix = [...$none, 'reservations' => 'view', 'tables' => 'manage'];
    $invitation = app(InviteStaff::class)->execute($owner, $location->organization_id, ['email' => 'features@example.test', 'role' => 'staff', 'location_ids' => [$location->id], 'permissions' => $matrix]);
    $token = '';
    Notification::assertSentOnDemand(StaffInvitationNotification::class, function ($notice) use (&$token) {
        $token = $notice->token;

        return true;
    });
    $user = User::factory()->unverified()->create(['email' => 'features@example.test']);
    app(AcceptStaffInvitation::class)->execute($user, $invitation->public_id, $token);
    expect(app(TenantAccess::class)->permissions($user, $location->organization_id))->toBe($matrix);
});

it('rechecks a manager invitation against current capabilities before granting access', function () {
    Notification::fake();
    [, $location] = tenantFixture('feature-stale-inviter');
    $manager = featureStaff($location, StaffPermissions::defaults('manager'), 'manager');
    $invitation = app(InviteStaff::class)->execute($manager, $location->organization_id, ['email' => 'stale-capability@example.test', 'role' => 'staff', 'location_ids' => [$location->id], 'permissions' => StaffPermissions::defaults('staff')]);
    $token = '';
    Notification::assertSentOnDemand(StaffInvitationNotification::class, function ($notice) use (&$token) {
        $token = $notice->token;

        return true;
    });
    OrganizationMembership::where('user_id', $manager->id)->update(['permissions' => ['orders' => 'view']]);
    $user = User::factory()->unverified()->create(['email' => 'stale-capability@example.test']);
    $this->actingAs($user)->post('/invitations/'.$invitation->public_id, ['token' => $token])->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()->and(OrganizationMembership::where('user_id', $user->id)->exists())->toBeFalse()->and($invitation->fresh()->accepted_at)->toBeNull();
});
