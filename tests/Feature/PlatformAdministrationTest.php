<?php

use App\Domain\Platform\Actions\BuildPlatformOverview;
use App\Models\ImportBatch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\ServiceLead;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('requires verified platform access for every new administrative view', function ($path) {
    $this->get($path)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->getJson($path)->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create(['is_platform_admin' => true]))->get($path)->assertRedirect(route('verification.notice'));
})->with(['/admin', '/admin/organizations', '/admin/users', '/admin/operations', '/admin/organizations/01AAAAAAAAAAAAAAAAAAAAAAAA']);

it('provides zero filled analytics without initializing mail settings or contacting providers', function () {
    Http::preventStrayRequests();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $before = [DB::table('mail_provider_limits')->count(), DB::table('email_alert_settings')->count()];
    $this->actingAs($admin)->get('/admin?days=7')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('admin/Dashboard')->where('overview.totals.organizations', 0)->where('overview.totals.users', 1)->has('overview.timeline', 7)->where('overview.timeline.0.orders', 0)->has('overview.values', 0));
    $this->get('/admin/operations')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/Operations')->where('mail', null)->where('alerts', null)->has('imports.data', 0));
    expect([DB::table('mail_provider_limits')->count(), DB::table('email_alert_settings')->count()])->toBe($before);
    Http::assertNothingSent();
});

it('aggregates completed orders by currency and UTC creation date without exposing shopper data', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    [$owner, $location] = tenantFixture('platform-one');
    [, $other] = tenantFixture('platform-two');
    foreach ([[$location, 'RON', 'completed', 1200, '2026-10-25 00:00:00'], [$other, 'EUR', 'completed', 400, '2026-10-31 10:00:00'], [$location, 'RON', 'cancelled', 90000, '2026-10-31 10:00:00'], [$location, 'RON', 'completed', 999, '2026-10-24 23:59:59'], [$location, 'RON', 'pending', 200, '2026-10-31 10:00:00']] as [$place, $currency, $status, $total, $created]) {
        Order::create(['organization_id' => $place->organization_id, 'location_id' => $place->id, 'tracking_token_hash' => hash('sha256', uniqid()), 'tracking_token_encrypted' => encrypt('private-tracking'), 'fulfillment' => 'pickup', 'customer_name' => 'Private guest', 'customer_phone' => '0712345678', 'currency' => $currency, 'subtotal' => $total, 'delivery_fee' => 0, 'total' => $total, 'status' => $status, 'created_at' => $created]);
    }
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $data = app(BuildPlatformOverview::class)->execute($admin, 7);
    expect($data['totals']['completed'])->toBe(2)->and($data['totals']['active_orders'])->toBe(1)->and($data['totals']['organizations'])->toBe(2)->and($data['values']->all())->toBe([['currency' => 'EUR', 'value' => 400, 'count' => 1], ['currency' => 'RON', 'value' => 1200, 'count' => 1]])->and($data['timeline'][0]['orders'])->toBe(1)->and($data['timeline'][6]['orders'])->toBe(1);
    expect(json_encode($data))->not->toContain('Private guest', '0712345678', 'private-tracking');
    expect(fn () => app(BuildPlatformOverview::class)->execute($owner, 7))->toThrow(HttpException::class);
});

it('lists current lead backlog and trial expirations independently of the selected reporting period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    [, $location] = tenantFixture('platform-trial');
    OrganizationSubscription::where('organization_id', $location->organization_id)->update(['trial_ends_at' => now()->addDays(2)]);
    ServiceLead::create(['name' => 'Private lead', 'email' => 'lead@example.test', 'email_hash' => ServiceLead::emailHash('lead@example.test'), 'phone' => '0770123456', 'locale' => 'ro', 'status' => 'new', 'created_at' => now()->subDays(40)]);
    $data = app(BuildPlatformOverview::class)->execute(User::factory()->create(['is_platform_admin' => true]), 7);
    expect($data['totals']['leads'])->toBe(0)->and($data['totals']['new_leads'])->toBe(1)->and($data['totals']['expiring'])->toBe(1)->and($data['subscriptions'])->toHaveCount(1);
    expect(json_encode($data))->not->toContain('Private lead', 'lead@example.test', '0770123456');
});

it('paginates organizations filters trial state and treats search wildcards as literals', function () {
    [, $location] = tenantFixture('platform-filter');
    $organization = Organization::findOrFail($location->organization_id);
    $organization->update(['name' => 'One 100%_ Bistro']);
    Organization::factory()->count(23)->create();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->get('/admin/organizations')->assertInertia(fn (Assert $page) => $page->component('admin/Organizations')->has('organizations.data', 20)->where('organizations.total', 24)->missing('subscriptions'));
    $this->get('/admin/organizations?search='.urlencode('100%_'))->assertInertia(fn (Assert $page) => $page->has('organizations.data', 1)->where('organizations.data.0.public_id', $organization->public_id));
    $this->get('/admin/organizations?search=0')->assertInertia(fn (Assert $page) => $page->has('organizations.data', 1)->where('organizations.data.0.public_id', $organization->public_id));
    $this->get('/admin/organizations?subscription=trial')->assertInertia(fn (Assert $page) => $page->where('organizations.total', 1));
    OrganizationSubscription::where('organization_id', $organization->id)->update(['trial_ends_at' => now()->subDay()]);
    $this->get('/admin/organizations?subscription=expired')->assertInertia(fn (Assert $page) => $page->where('organizations.total', 1));
    $this->get('/admin/organizations?subscription=trial')->assertInertia(fn (Assert $page) => $page->where('organizations.total', 0));
});

it('shows a client support record and preserves legacy links without granting tenant operation rights', function () {
    [$owner, $location] = tenantFixture('platform-detail');
    $organization = Organization::findOrFail($location->organization_id);
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->get('/admin/'.$organization->public_id)->assertRedirect('/admin/organizations/'.$organization->public_id);
    $this->get('/admin/organizations/'.$organization->public_id)->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('admin/Organization')->where('organization.name', $organization->name)->has('organization.restaurants.0.locations', 1)->where('organization.members.0.email', $owner->email)->missing('organization.members.0.password')->missing('organization.restaurants.0.locations.0.setup_profile'));
    $this->getJson('/app/locations/'.$location->public_id)->assertForbidden();
    $this->get('/app')->assertRedirect('/admin');
    $this->actingAs($owner)->get('/app')->assertRedirect('/app/locations/'.$location->public_id);
    $this->getJson('/admin/organizations/'.$organization->public_id)->assertForbidden();
});

it('filters account access without returning secrets and lists membership counts', function () {
    [$owner] = tenantFixture('platform-users');
    $admin = User::factory()->create(['is_platform_admin' => true]);
    User::factory()->unverified()->create(['email' => 'pending@example.test']);
    $this->actingAs($admin)->get('/admin/users?access=admin')->assertInertia(fn (Assert $page) => $page->component('admin/Users')->has('users.data', 1)->where('users.data.0.email', $admin->email)->missing('users.data.0.password')->missing('users.data.0.remember_token'));
    $this->get('/admin/users?access=unverified')->assertInertia(fn (Assert $page) => $page->where('users.data.0.email', 'pending@example.test'));
    $this->get('/admin/users?search='.urlencode($owner->email))->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.memberships_count', 1));
});

it('returns only safe import diagnostics and respects the status filter', function () {
    [$owner, $location] = tenantFixture('platform-import');
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $owner->id, 'status' => 'failed', 'credential' => 'private-provider-key', 'settings' => ['private' => 'do-not-serialize']]);
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get('/admin/operations?status=failed')->assertInertia(fn (Assert $page) => $page->component('admin/Operations')->where('attention.imports', 1)->has('imports.data', 1)->where('imports.data.0.public_id', $batch->public_id)->missing('imports.data.0.credential')->missing('imports.data.0.settings'));
    $this->get('/admin/operations?status=published')->assertInertia(fn (Assert $page) => $page->has('imports.data', 0)->where('attention.imports', 1));
});

it('rejects unsupported filters instead of applying arbitrary queries', function ($path) {
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->getJson($path)->assertUnprocessable();
})->with(['/admin?days=9999', '/admin/organizations?subscription=invalid', '/admin/users?access=invalid', '/admin/operations?status=invalid', '/admin/users?search[]=array']);
