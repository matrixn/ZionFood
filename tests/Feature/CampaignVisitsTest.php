<?php

use App\Domain\Marketing\Actions\RecordCampaignVisit;
use App\Models\CampaignVisit;
use App\Models\ServiceLead;
use App\Models\StorefrontDomain;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

function campaignVisit(array $extra = []): CampaignVisit
{
    $tags = array_fill_keys(CampaignVisit::FIELDS, null);
    $tags = [...$tags, 'utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'Launch', ...array_intersect_key($extra, $tags)];
    $key = hash('sha256', json_encode(array_intersect_key($tags, array_flip(CampaignVisit::GROUP_FIELDS))));

    return CampaignVisit::create([...$tags, 'campaign_key' => $key, 'locale' => 'ro', 'landing_path' => '/', ...$extra]);
}

function campaignLead(?CampaignVisit $visit = null, string $status = 'new'): ServiceLead
{
    $email = 'contact-'.ServiceLead::count().'@example.test';

    return ServiceLead::create(['email_hash' => ServiceLead::emailHash($email), 'name' => 'Campaign contact', 'email' => $email, 'phone' => '0770123456', 'locale' => 'ro', 'status' => $status, 'campaign_visit_id' => $visit?->id]);
}

function campaignContact(array $extra = []): array
{
    return ['name' => 'Campaign contact', 'email' => 'campaign@example.test', 'phone' => '0770123456', 'locale' => 'ro', ...$extra];
}

beforeEach(function () {
    Cache::flush();
});

it('records only bounded UTM labels and the clean entry path without identifiable visitor data', function () {
    $response = $this->get('/en?utm_source=FaceBook&utm_medium=CPC&utm_campaign=Launch&utm_content=ad-a&utm_term=menu&utm_id=123&fbclid=secret-click&email=private@example.test')->assertOk();
    $visit = CampaignVisit::sole();
    expect($visit->utm_source)->toBe('facebook')->and($visit->utm_medium)->toBe('cpc')->and($visit->utm_campaign)->toBe('Launch')->and($visit->utm_content)->toBe('ad-a')->and($visit->utm_term)->toBe('menu')->and($visit->utm_id)->toBe('123')->and($visit->landing_path)->toBe('/en')->and($visit->locale)->toBe('en');
    $columns = DB::getSchemaBuilder()->getColumnListing('campaign_visits');
    expect($columns)->not->toContain('ip', 'user_agent', 'visitor_id', 'session_id', 'url', 'referrer', 'fbclid', 'email');
    expect(json_encode($visit->toArray()))->not->toContain('secret-click', 'private@example.test')->not->toContain('campaign_key');
    $response->assertSessionHas('marketing.campaign_visit.public_id', $visit->public_id);
});

it('deduplicates same-session refreshes for thirty minutes including a locale change', function () {
    $url = '?utm_source=facebook&utm_campaign=Launch';
    $first = $this->get('/'.$url)->assertOk();
    $this->withCookie(config('session.cookie'), $first->getCookie(config('session.cookie'))->getValue());
    $this->get('/en'.$url)->assertOk();
    expect(CampaignVisit::count())->toBe(1);
    $this->travel(31)->minutes();
    $this->get('/'.$url)->assertOk();
    expect(CampaignVisit::count())->toBe(2);
});

it('keeps different sessions separate without claiming they are different people', function () {
    $first = $this->get('/?utm_campaign=Launch')->assertOk();
    $this->withCookie(config('session.cookie'), str_repeat('a', 40));
    $this->get('/?utm_campaign=Launch')->assertOk();
    expect(CampaignVisit::count())->toBe(2);
});

it('does not create campaign visits without usable tags', function ($query) {
    $this->get('/'.$query)->assertOk();
    expect(CampaignVisit::count())->toBe(0);
})->with(['', '?ref=other', '?utm_source=', '?utm_source[]=array', '?utm_campaign=%3Cscript%3E', '?utm_campaign='.str_repeat('a', 201), '?utm_source=%FF']);

it('retains zero-valued campaign identifiers as legitimate labels', function () {
    $this->get('/?utm_id=0')->assertOk();
    expect(CampaignVisit::sole()->utm_id)->toBe('0');
});

it('excludes known bots speculative fetches and privacy opt-outs', function ($headers) {
    $this->withHeaders($headers)->get('/?utm_campaign=Launch')->assertOk();
    expect(CampaignVisit::count())->toBe(0);
})->with([[['User-Agent' => 'Googlebot']], [['User-Agent' => 'WhatsApp preview']], [['Purpose' => 'prefetch']], [['Sec-Purpose' => 'prefetch;prerender']], [['DNT' => '1']], [['Sec-GPC' => '1']]]);

it('excludes HEAD requests and platform-admin visits', function () {
    $this->head('/?utm_campaign=Launch')->assertOk();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get('/?utm_campaign=Launch')->assertOk();
    expect(CampaignVisit::count())->toBe(0);
});

it('does not mix restaurant menu traffic into platform campaign visits', function () {
    [, $location] = tenantFixture('campaign-restaurant');
    StorefrontDomain::create(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'hostname' => 'restaurant.example.test', 'verification_status' => 'verified']);
    $this->get('https://restaurant.example.test/?utm_campaign=Launch')->assertOk();
    expect(CampaignVisit::count())->toBe(0);
});

it('attributes new leads to the last eligible campaign and keeps attribution across untagged navigation', function () {
    $first = $this->get('/?utm_source=facebook&utm_campaign=First')->assertOk();
    $this->withCookie(config('session.cookie'), $first->getCookie(config('session.cookie'))->getValue());
    $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=Last')->assertOk();
    $last = CampaignVisit::latest('id')->first();
    $this->get('/en')->assertOk();
    $this->postJson('/interest', campaignContact())->assertOk();
    expect(ServiceLead::sole()->campaign_visit_id)->toBe($last->id);
    $this->get('/?utm_campaign=Another')->assertOk();
    $this->postJson('/interest', campaignContact(['phone' => '0722222222']))->assertOk();
    expect(ServiceLead::count())->toBe(1)->and(ServiceLead::sole()->campaign_visit_id)->toBe($last->id);
});

it('never trusts a client supplied visit identifier', function () {
    $visit = campaignVisit();
    $this->postJson('/interest', campaignContact(['campaign_visit_id' => $visit->id, 'campaign_visit' => $visit->public_id, 'utm_source' => 'forged']))->assertOk();
    expect(ServiceLead::sole()->campaign_visit_id)->toBeNull();
});

it('expires attribution after thirty minutes and respects a later privacy opt-out', function ($mode) {
    $first = $this->get('/?utm_campaign=Launch')->assertOk();
    $this->withCookie(config('session.cookie'), $first->getCookie(config('session.cookie'))->getValue());
    if ($mode === 'expired') {
        $this->travel(31)->minutes();
    } else {
        $this->withHeader('Sec-GPC', '1');
    }
    $this->postJson('/interest', campaignContact())->assertOk();
    expect(ServiceLead::sole()->campaign_visit_id)->toBeNull();
})->with(['expired', 'opt-out']);

it('does not break the landing or retain older attribution when analytics storage is unavailable', function () {
    $visit = campaignVisit();
    Log::spy();
    Cache::shouldReceive('lock')->once()->andThrow(new RuntimeException('cache unavailable'));
    $this->withSession(['marketing.campaign_visit' => ['public_id' => $visit->public_id, 'expires_at' => now()->addMinutes(20)->timestamp]])->get('/?utm_campaign=New')->assertOk()->assertSessionMissing('marketing.campaign_visit');
    expect(CampaignVisit::count())->toBe(1);
    Log::shouldHaveReceived('warning')->once()->with('Campaign visit unavailable', ['exception_class' => RuntimeException::class]);
});

it('caps analytics admission without preventing the public page from rendering', function () {
    $session = app('session')->driver();
    $session->start();
    foreach (range(1, 61) as $i) {
        $request = Request::create('http://localhost/?utm_campaign=Entry'.$i);
        $request->setLaravelSession($session);
        app(RecordCampaignVisit::class)->execute($request, 'ro');
    }
    expect(CampaignVisit::count())->toBe(60);
    $this->get('/?utm_campaign=Another')->assertOk();
    expect(CampaignVisit::count())->toBe(60);
});

it('restricts campaign reporting and related lead filters to verified platform administrators', function () {
    $visit = campaignVisit();
    campaignLead($visit);
    $this->get('/admin/campaigns')->assertRedirect('/login');
    [$owner] = tenantFixture('campaign-report-owner');
    $this->actingAs($owner)->getJson('/admin/campaigns')->assertForbidden()->assertDontSee('Launch');
    $this->getJson('/admin/leads?campaign_visit='.$visit->public_id)->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create(['is_platform_admin' => true]))->get('/admin/campaigns')->assertRedirect(route('verification.notice'));
});

it('reports campaign visits and retained leads without double-counting visits with several leads', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $first = campaignVisit(['utm_content' => 'ad-a']);
    campaignVisit(['utm_content' => 'ad-b']);
    campaignVisit(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'Search']);
    campaignLead($first);
    campaignLead($first, 'qualified');
    $this->actingAs($admin)->get('/admin/campaigns')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('admin/Campaigns')->where('summary.visits', 3)->where('summary.campaigns', 2)->where('summary.leads', 2)->where('summary.qualified', 1)->has('topCampaigns', 2)->where('topCampaigns.0.visits', 2)->where('topCampaigns.0.leads', 2)->has('visits.data', 3)->missing('visits.data.0.campaign_key'));
    $this->get('/admin/campaigns?utm_source=google')->assertInertia(fn (Assert $page) => $page->where('summary.visits', 1)->where('summary.leads', 0));
    $this->get('/admin/leads?campaign_visit='.$first->public_id)->assertInertia(fn (Assert $page) => $page->has('leads.data', 2)->where('leads.data.0.campaign.utm_campaign', 'Launch'));
});

it('uses local day boundaries across the daylight-saving transition and validates report ranges', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    $admin = User::factory()->create(['is_platform_admin' => true]);
    campaignVisit(['created_at' => '2026-10-24 20:59:59']);
    campaignVisit(['created_at' => '2026-10-24 21:00:00']);
    $this->actingAs($admin)->get('/admin/campaigns?days=7')->assertInertia(fn (Assert $page) => $page->where('summary.visits', 1));
    $this->getJson('/admin/campaigns?days=365')->assertUnprocessable();
    $this->getJson('/admin/campaigns?utm_source[]=array')->assertUnprocessable();
});

it('preserves case-sensitive campaign identities and ID-only campaign groups on either database', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    campaignVisit(['utm_campaign' => 'Launch']);
    campaignVisit(['utm_campaign' => 'launch']);
    campaignVisit(['utm_campaign' => null, 'utm_id' => '1']);
    campaignVisit(['utm_campaign' => null, 'utm_id' => '2']);
    $this->actingAs($admin)->get('/admin/campaigns')->assertInertia(fn (Assert $page) => $page->where('summary.campaigns', 4)->has('options.utm_campaign', 2));
    $this->get('/admin/campaigns?utm_source=FACEBOOK&utm_campaign=Launch')->assertInertia(fn (Assert $page) => $page->where('summary.visits', 1)->where('filters.utm_source', 'facebook'));
});
