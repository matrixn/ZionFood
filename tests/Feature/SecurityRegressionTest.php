<?php

use App\Domain\Imports\GloriaFoodAdapter;
use App\Domain\Imports\ImportComplexityException;
use App\Domain\Imports\ImportVerification;
use App\Domain\Imports\ProcessImport;
use App\Domain\Mail\AccountMailBudget;
use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\ResendQuota;
use App\Domain\Restaurants\Actions\SaveLocationSettings;
use App\Jobs\DeliverEmail;
use App\Jobs\FetchGloriaFoodMenu;
use App\Models\EmailDelivery;
use App\Models\ImportBatch;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

function securitySettings($location): array
{
    $location->refresh()->load('fulfillment');

    return ['name' => $location->name, 'address' => $location->address, 'accepting_orders' => $location->accepting_orders, 'pickup_enabled' => $location->fulfillment->pickup_enabled, 'delivery_enabled' => $location->fulfillment->delivery_enabled, 'hours' => [], 'zones' => [], 'dine_in_cutoff_minutes' => $location->fulfillment->dine_in_cutoff_minutes];
}

function securityManager($location, string $orders = 'none'): User
{
    $user = User::factory()->create();
    OrganizationMembership::create(['organization_id' => $location->organization_id, 'user_id' => $user->id, 'role' => 'manager', 'permissions' => ['orders' => $orders, 'tables' => 'none']]);

    return $user;
}

it('guards every operational switch in both directions for restricted managers', function (string $field, bool $initial, string $permission) {
    [, $location] = tenantFixture('security-switches');
    if ($field === 'accepting_orders') {
        $location->update([$field => $initial]);
    } else {
        $location->fulfillment()->update([$field => $initial]);
    }
    $before = securitySettings($location);
    $this->actingAs(securityManager($location, $permission))->putJson('/app/locations/'.$location->public_id.'/settings', [...$before, 'name' => 'Forbidden overwrite', $field => ! $initial])->assertForbidden();
    expect(securitySettings($location))->toBe($before)->and($location->hours()->count())->toBe(7);
})->with(['accepting_orders', 'pickup_enabled', 'delivery_enabled'])->with([true, false])->with(['none', 'view']);

it('preserves ordinary manager settings edits with unchanged boolean form values', function () {
    [, $location] = tenantFixture('security-settings-compatible');
    $data = securitySettings($location);
    foreach (['accepting_orders', 'pickup_enabled', 'delivery_enabled'] as $field) {
        $data[$field] = (string) (int) $data[$field];
    }
    $this->actingAs(securityManager($location))->putJson('/app/locations/'.$location->public_id.'/settings', [...$data, 'name' => 'Allowed rename', 'dine_in_cutoff_minutes' => (string) $data['dine_in_cutoff_minutes'], 'hours' => [['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']], 'zones' => [['name' => 'Allowed zone', 'postal_prefix' => '24', 'fee' => 500, 'minimum_order' => 1000]]])->assertRedirect();
    expect($location->fresh()->name)->toBe('Allowed rename')->and($location->hours()->count())->toBe(1)->and($location->zones()->count())->toBe(1);
    $this->putJson('/app/locations/'.$location->public_id.'/settings', [...securitySettings($location), 'dine_in_cutoff_minutes' => 90])->assertForbidden();
});

it('rechecks the locked location instead of trusting an earlier model instance', function () {
    [, $location] = tenantFixture('security-stale-settings');
    $data = securitySettings($location);
    $manager = securityManager($location);
    DB::table('restaurant_locations')->where('id', $location->id)->update(['accepting_orders' => false]);
    expect(fn () => app(SaveLocationSettings::class)->execute($manager, $location, $data))->toThrow(AuthorizationException::class);
    expect($location->fresh()->accepting_orders)->toBeFalse();
});

it('retains owner access and permits an orders manager while denying foreign managers', function () {
    [$owner, $location] = tenantFixture('security-legitimate-settings');
    OrganizationMembership::where('user_id', $owner->id)->update(['permissions' => ['orders' => 'none']]);
    $this->actingAs($owner)->putJson('/app/locations/'.$location->public_id.'/settings', [...securitySettings($location), 'accepting_orders' => false])->assertRedirect();
    $this->actingAs(securityManager($location, 'manage'))->putJson('/app/locations/'.$location->public_id.'/settings', [...securitySettings($location), 'accepting_orders' => true])->assertRedirect();
    [, $foreign] = tenantFixture('security-foreign-settings');
    $this->actingAs(securityManager($foreign, 'manage'))->putJson('/app/locations/'.$location->public_id.'/settings', securitySettings($location))->assertForbidden();
});

function securityAmplifiedMenu(int $products = 150): array
{
    $options = array_map(fn ($id) => ['id' => $id, 'name' => 'o', 'price' => 0, 'default' => true], range(1, 20));
    $groups = array_map(fn ($id) => ['id' => $id, 'name' => 'g', 'required' => true, 'allow_quantity' => false, 'force_min' => 20, 'force_max' => 20, 'options' => $options], range(1, 20));
    $sizes = array_map(fn ($id) => ['id' => $id, 'name' => 'v', 'price' => 0], range(1, 20));

    return ['id' => 1, 'restaurant_id' => 1, 'currency' => 'RON', 'categories' => [['id' => 1, 'name' => 'c', 'groups' => $groups, 'items' => array_map(fn ($id) => ['id' => $id, 'name' => 'p', 'price' => 1, 'sizes' => $sizes], range(1, $products))]]];
}

it('rejects multiplicative import evidence before writing snapshots or touching live menus', function () {
    [$owner, $location, $item] = tenantFixture('security-import-budget');
    $batch = ImportBatch::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'user_id' => $owner->id, 'settings' => ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]]);
    expect(fn () => app(ProcessImport::class)->execute($batch, securityAmplifiedMenu()))->toThrow(ImportComplexityException::class);
    expect(DB::table('import_snapshots')->where('batch_id', $batch->id)->count())->toBe(0)->and(DB::table('import_reports')->where('batch_id', $batch->id)->count())->toBe(0)->and($item->fresh()->price)->toBe(3500);
    $response = $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/migration-center', ['source' => 'file', 'file' => UploadedFile::fake()->createWithContent('complex.json', json_encode(securityAmplifiedMenu(20))), 'authorized' => true, 'size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0]);
    $response->assertCreated();
    expect(ImportBatch::where('public_id', $response->json('batch_id'))->value('failure_code'))->toBe('import_complexity_limit');
    $batch->update(['credential' => 'local-fake-key']);
    Http::fake(['pos.globalfoodsoft.com/*' => Http::response(securityAmplifiedMenu())]);
    (new FetchGloriaFoodMenu($batch->id))->handle();
    expect($batch->fresh()->failure_code)->toBe('import_complexity_limit')->and($batch->fresh()->credential)->toBeNull();
});

it('bounds basket count and escaped-name output while retaining complete ordinary evidence', function () {
    $menu = app(GloriaFoodAdapter::class)->normalize(securityAmplifiedMenu(1), ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0])['menu'];
    $evidence = app(ImportVerification::class)->evidence($menu);
    expect($evidence)->toHaveCount(20)->and($evidence[0]['choices'])->toHaveCount(400);
    $item = $menu['categories'][0]['items'][0];
    $menu['categories'][0]['groups'] = [];
    $menu['categories'][0]['items'] = array_fill(0, 501, $item);
    expect(fn () => app(ImportVerification::class)->evidence($menu))->toThrow(ImportComplexityException::class);
    $item['name'] = str_repeat('"', 150);
    $item['variants'] = [null];
    $menu['categories'][0]['groups'] = array_slice(app(GloriaFoodAdapter::class)->normalize(securityAmplifiedMenu(1), ['size_prices' => 'base_plus_size', 'nutrition_basis' => 'per_100g', 'tax_basis_points' => 0])['menu']['categories'][0]['groups'], 0, 1);
    foreach ($menu['categories'][0]['groups'][0]['options'] as &$option) {
        $option['name'] = str_repeat('"', 100);
    }
    unset($option);
    $menu['categories'][0]['items'] = array_fill(0, 2000, $item);
    expect(fn () => app(ImportVerification::class)->evidence($menu))->toThrow(ImportComplexityException::class);
});

it('limits mixed account requests before account or email side effects', function () {
    Queue::fake();
    config(['mail.default' => 'smtp', 'email_delivery.account_requests_per_hour' => 2]);
    $data = ['name' => 'Security signup', 'email' => 'safe-account@example.test', 'password' => 'StrongLocalTest!2026', 'password_confirmation' => 'StrongLocalTest!2026'];
    $this->post('/register', [...$data, ...registrationCaptchaData($this)])->assertSessionHasNoErrors();
    auth('web')->logout();
    session()->flush();
    $this->post('/forgot-password', ['email' => $data['email']])->assertSessionHasNoErrors();
    $this->post('/register', [...$data, ...registrationCaptchaData($this), 'email' => 'denied-account@example.test'])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(User::where('email', 'denied-account@example.test')->exists())->toBeFalse();
    $this->actingAs(User::where('email', $data['email'])->sole())->post('/email/verification-notification')->assertTooManyRequests();
    Queue::assertPushed(DeliverEmail::class, 2);
    expect(EmailDelivery::count())->toBe(2);
});

it('bounds distributed account admission and resets rolling limits without blocking other sources', function () {
    config(['email_delivery.account_daily_limit' => 2, 'email_delivery.account_requests_per_hour' => 1]);
    $budget = app(AccountMailBudget::class);
    expect($budget->admitRequest('192.0.2.1'))->toBe(0)->and($budget->admitRequest('192.0.2.1'))->toBe(3600)->and($budget->admitRequest('192.0.2.2'))->toBe(0)->and($budget->admitRequest('192.0.2.3'))->toBe(86400);
    expect($budget->reserveDelivery('account_registration', 2))->toBe(0)->and($budget->reserveDelivery('password_reset', 1))->toBe(86400)->and($budget->reserveDelivery('account_verification', 1))->toBe(86400)->and($budget->reserveDelivery('legacy', 1))->toBe(86400)->and($budget->reserveDelivery('staff_invitation', 1))->toBe(0)->and($budget->reserveDelivery('reservation', 1))->toBe(0);
    $this->travel(24)->hours();
    expect($budget->admitRequest('192.0.2.1'))->toBe(0)->and($budget->reserveDelivery('password_reset', 1))->toBe(0);
});

it('respects the administrator provider cap and keeps at least half outside the account budget', function () {
    app(ResendQuota::class)->configure(20, 1);
    config(['email_delivery.account_daily_limit' => 1000]);
    expect(app(AccountMailBudget::class)->limit())->toBe(10);
});

it('also honors a smaller provider-reported daily cap including a zero allowance', function () {
    app(ResendQuota::class)->status();
    DB::table('mail_provider_limits')->where('provider', 'resend')->update(['usage' => json_encode(['daily' => ['limit' => 20]])]);
    $budget = app(AccountMailBudget::class);
    expect($budget->limit())->toBe(10)->and($budget->reserveDelivery('account_registration', 10))->toBe(0)->and($budget->reserveDelivery('password_reset', 1))->toBe(86400);
    DB::table('mail_provider_limits')->where('provider', 'resend')->update(['usage' => json_encode(['daily' => ['limit' => 0]])]);
    expect($budget->limit())->toBe(0)->and($budget->admitRequest('192.0.2.1'))->toBe(86400);
});

it('defers legacy account work before shared quota reservation or any provider call', function () {
    config(['email_delivery.cache_store' => 'array', 'email_delivery.account_daily_limit' => 1]);
    app(AccountMailBudget::class)->reserveDelivery('account_registration', 1);
    $email = (new Email)->from('app@example.test')->to('local@example.test')->text('Private local test');
    $job = new DeliverEmail($email, 'smtp');
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(Mockery::on(fn ($delay) => $delay >= 86400 && $delay <= 86430));
    $job->setJob($queueJob);
    Mail::shouldReceive('mailer')->never();
    $quota = Mockery::mock(EmailQuota::class);
    $quota->shouldNotReceive('reserve');
    $job->handle($quota);
    expect(EmailDelivery::where('public_id', $job->deliveryId)->value('reason'))->toBe('account_mail_quota');
});
