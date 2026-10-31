<?php

use App\Domain\Marketing\Actions\ManageServiceLead;
use App\Models\ServiceLead;
use App\Models\StorefrontDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

function leadInput(array $extra = []): array
{
    return ['name' => '  Radu Restaurant  ', 'email' => ' RADU@EXAMPLE.TEST ', 'phone' => '+40 770 123 456', 'locale' => 'ro', ...$extra];
}

function storedLead(string $email = 'lead@example.test', string $status = 'new'): ServiceLead
{
    return ServiceLead::create(['email_hash' => ServiceLead::emailHash($email), 'name' => 'Contact person', 'email' => $email, 'phone' => '+40 770 111 222', 'locale' => 'ro', 'status' => $status]);
}

it('captures only the three contact details with encrypted storage and no account or outgoing message', function () {
    Mail::fake();
    Notification::fake();
    Queue::fake();
    $response = $this->postJson('/interest', leadInput(['status' => 'qualified', 'updated_by' => 999, 'is_platform_admin' => true]));
    $response->assertOk()->assertExactJson(['status' => 'received'])->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $lead = ServiceLead::sole();
    expect($lead->name)->toBe('Radu Restaurant')->and($lead->email)->toBe('radu@example.test')->and($lead->phone)->toBe('+40 770 123 456')->and($lead->status)->toBe('new')->and($lead->updated_by)->toBeNull();
    $raw = DB::table('service_leads')->first();
    expect($raw->name)->not->toContain('Radu')->and($raw->email)->not->toContain('example.test')->and($raw->phone)->not->toContain('770 123');
    expect($lead->toArray())->not->toHaveKeys(['name', 'email', 'phone', 'email_hash']);
    expect(User::count())->toBe(0);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

it('requires all contact fields and rejects malformed or oversized input', function ($extra, $key) {
    $this->postJson('/interest', leadInput($extra))->assertUnprocessable()->assertJsonValidationErrors($key);
    expect(ServiceLead::count())->toBe(0);
})->with([
    [['name' => ''], 'name'], [['name' => str_repeat('a', 121)], 'name'], [['name' => "Unsafe\nName"], 'name'],
    [['email' => 'not-an-email'], 'email'], [['email' => []], 'email'], [['phone' => ''], 'phone'],
    [['phone' => 'call me tomorrow'], 'phone'], [['phone' => str_repeat('1', 31)], 'phone'], [['locale' => 'xx'], 'locale'],
]);

it('normalizes duplicate identities without replacing contact details or reopening a closed request', function () {
    $lead = storedLead('radu@example.test', 'closed');
    $before = $lead->only('name', 'email', 'phone', 'status', 'locale', 'updated_at');
    $this->postJson('/interest', leadInput(['name' => 'Another person', 'phone' => '0722222222', 'locale' => 'en']))->assertOk()->assertExactJson(['status' => 'received']);
    expect(ServiceLead::count())->toBe(1)->and($lead->fresh()->only(array_keys($before)))->toEqual($before);
});

it('silently discards honeypot submissions without disclosing the trap', function ($website) {
    $this->postJson('/interest', leadInput(['website' => $website]))->assertOk()->assertExactJson(['status' => 'received']);
    expect(ServiceLead::count())->toBe(0);
})->with(['https://spam.invalid', '0']);

it('limits submissions per IP without blocking another visitor', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/interest', leadInput(['email' => 'lead'.$i.'@example.test']))->assertOk();
    }
    $this->postJson('/interest', leadInput())->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->postJson('/interest', leadInput())->assertOk();
    expect(ServiceLead::count())->toBe(4);
});

it('also enforces the hourly cap after minute windows reset', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/interest', leadInput(['email' => 'hour'.$i.'@example.test']))->assertOk();
        $this->travel(61)->seconds();
    }
    $this->postJson('/interest', leadInput())->assertTooManyRequests();
    expect(ServiceLead::count())->toBe(10);
});

it('protects lead submission with the normal web CSRF middleware', function () {
    $this->app->instance('env', 'local');
    $this->postJson('/interest', leadInput())->assertStatus(419);
    expect(ServiceLead::count())->toBe(0);
});

it('rejects the lead route on restaurant and API-only domains', function () {
    [, $location] = tenantFixture('lead-restaurant-host');
    config(['zionfood.api_hosts' => ['api.example.test']]);
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'restaurant.example.test', 'verification_status' => 'verified']);
    foreach (['restaurant.example.test', 'api.example.test', 'unknown.example.test'] as $host) {
        $this->postJson('https://'.$host.'/interest', leadInput())->assertNotFound();
    }
    expect(ServiceLead::count())->toBe(0);
});

it('keeps all lead management private to verified platform administrators', function () {
    $lead = storedLead();
    $this->get('/admin/leads')->assertRedirect('/login');
    [$owner] = tenantFixture('lead-tenant-owner');
    $this->actingAs($owner)->getJson('/admin/leads')->assertForbidden()->assertDontSee($lead->email);
    $this->patchJson('/admin/leads/'.$lead->public_id, ['status' => 'contacted'])->assertForbidden();
    $this->deleteJson('/admin/leads/'.$lead->public_id)->assertForbidden();
    expect(fn () => app(ManageServiceLead::class)->update($owner, $lead, 'contacted'))->toThrow(HttpException::class);
    expect(fn () => app(ManageServiceLead::class)->delete($owner, $lead))->toThrow(HttpException::class);
    expect($lead->fresh()->status)->toBe('new');
    $admin = User::factory()->unverified()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->get('/admin/leads')->assertRedirect(route('verification.notice'));
});

it('paginates and filters platform leads without exposing identity hashes', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    for ($i = 0; $i < 27; $i++) {
        storedLead('admin'.$i.'@example.test', $i === 26 ? 'qualified' : 'new');
    }
    $response = $this->actingAs($admin)->get('/admin/leads')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Leads')->has('leads.data', 25)->where('leads.total', 27)->where('summary.new', 26)->missing('leads.data.0.email_hash')->where('leads.data.0.email', 'admin26@example.test'));
    $this->get('/admin/leads?status=qualified')->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('filters.status', 'qualified'));
    $this->get('/admin/leads?page=2')->assertInertia(fn (Assert $page) => $page->has('leads.data', 2));
    $this->getJson('/admin/leads?status=arbitrary')->assertUnprocessable();
});

it('lets an administrator update status and permanently delete a request without accepting other changes', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $lead = storedLead();
    $this->actingAs($admin)->patchJson('/admin/leads/'.$lead->public_id, ['status' => 'contacted', 'email' => 'injected@example.test'])->assertNoContent();
    expect($lead->fresh()->status)->toBe('contacted')->and($lead->fresh()->updated_by)->toBe($admin->id)->and($lead->fresh()->email)->toBe('lead@example.test');
    $this->patchJson('/admin/leads/'.$lead->public_id, ['status' => 'invalid'])->assertUnprocessable();
    $this->patchJson('/admin/leads/'.$lead->id, ['status' => 'new'])->assertNotFound();
    $this->deleteJson('/admin/leads/'.$lead->public_id)->assertNoContent();
    expect(ServiceLead::count())->toBe(0)->and($admin->fresh())->not->toBeNull();
    $this->postJson('/interest', leadInput(['email' => 'lead@example.test']))->assertOk();
    expect(ServiceLead::count())->toBe(1);
});

it('localizes validation in the chosen landing language without persisting input in a session', function () {
    $this->postJson('/interest', leadInput(['locale' => 'en', 'name' => '']))->assertUnprocessable()->assertHeader('Content-Language', 'en')->assertJsonPath('errors.name.0', 'The contact name field is required.')->assertSessionMissing('_old_input');
});

it('does not flash personal input from unsupported non-JSON submissions', function () {
    $this->post('/interest', leadInput(['name' => '']))->assertStatus(406)->assertSessionMissing('_old_input');
    expect(ServiceLead::count())->toBe(0);
});

it('renders three blank contact fields and localized help in build-owned HTML without caching personal data', function () {
    $lead = storedLead('private-contact@example.test');
    foreach (['/' => 'ro', '/en' => 'en'] as $path => $locale) {
        $response = $this->get($path)->assertOk()->assertSee('id="contact"', false);
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $dom = new DOMXPath($doc);
        expect($dom->query('//section[@id="contact"]//input[not(@type="hidden")]'))->toHaveCount(3)
            ->and($dom->query('//section[@id="contact"]//form[@method="post" and @action="/interest"]'))->toHaveCount(1);
        $markup = file_get_contents(config('seo.prerender_path').'/'.$locale.'-guest.html');
        expect($markup)->not->toContain($lead->email)->not->toContain($lead->phone)->not->toContain('service_leads');
    }
});
