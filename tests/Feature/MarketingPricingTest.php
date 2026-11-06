<?php

use App\Domain\Marketing\PricingCatalog;
use App\Domain\Marketing\SyncExchangeRates;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function validMarketingRates(): array
{
    return [
        ['base' => 'RON', 'quote' => 'EUR', 'rate' => 0.2, 'date' => now('UTC')->toDateString()],
        ['base' => 'RON', 'quote' => 'GBP', 'rate' => 0.16, 'date' => now('UTC')->toDateString()],
    ];
}

function marketingPriceEdit(array $changes = []): array
{
    return [...['version' => 1, 'weekly_enabled' => true, 'plans' => [
        ['key' => 'menu', 'name' => 'Menu Starter', 'price_ron' => '59.95'],
        ['key' => 'orders', 'name' => 'Orders', 'price_ron' => '149'],
        ['key' => 'business', 'name' => 'Business', 'price_ron' => '249.50'],
    ]], ...$changes];
}

it('restricts pricing administration to verified platform administrators', function () {
    $this->get('/admin/pricing')->assertRedirect('/login');
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/admin/pricing')->assertForbidden();
    $this->putJson('/admin/pricing', marketingPriceEdit())->assertForbidden();
    Http::fake();
    $this->postJson('/admin/pricing/rates')->assertForbidden();
    Http::assertNothingSent();
    $owner->forceFill(['is_platform_admin' => true, 'email_verified_at' => null])->save();
    $this->get('/admin/pricing')->assertRedirect('/email/verify');
});

it('saves integer RON base prices with conflict detection and updates static HTML immediately without a rebuild', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin)->putJson('/admin/pricing', marketingPriceEdit())->assertOk()->assertJsonPath('settings.version', 2);
    expect(DB::table('marketing_plans')->where('key', 'menu')->value('price_minor'))->toBe(5995);
    $html = $this->get('/it')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//article[@data-plan="menu"]//h3')->item(0)->textContent)->toBe('Menu Starter')
        ->and($xpath->query('//article[@data-plan="menu"]//data')->item(0)->getAttribute('value'))->toBe('59.95');
    $this->putJson('/admin/pricing', marketingPriceEdit())->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->get('/admin/pricing')->assertInertia(fn (Assert $page) => $page->component('admin/Pricing')->where('pricing.plans.0.price_minor', 5995));
});

it('rejects unknown plans duplicate keys malformed amounts and markup-like numeric inputs', function ($field, $value) {
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]));
    $data = marketingPriceEdit();
    data_set($data, $field, $value);
    $this->putJson('/admin/pricing', $data)->assertUnprocessable();
    expect(DB::table('marketing_plans')->where('key', 'menu')->value('price_minor'))->toBe(4900);
})->with([
    ['plans.0.key', 'other'], ['plans.0.key', 'orders'], ['plans.0.price_ron', '-1'],
    ['plans.0.price_ron', '4.999'], ['plans.0.price_ron', '1e4'], ['plans.0.price_ron', '1000000'],
    ['plans.0.name', '   '], ['plans.0.price_ron', 5995],
]);

it('fetches bounded fixed-origin quotes and converts displays while retaining exact RON billing authority', function () {
    Http::fake([SyncExchangeRates::ENDPOINT.'*' => Http::response(validMarketingRates())]);
    expect(app(SyncExchangeRates::class)->execute())->toBeTrue();
    $this->postJson('/pricing/preference', ['currency' => 'EUR', 'price_minor' => 1])->assertOk()->assertCookie('zf_currency', 'EUR')->assertJsonPath('plans.0.display_minor', 980)->assertJsonPath('plans.0.price_minor', 4900)->assertJsonPath('base_currency', 'RON');
    $this->postJson('/pricing/preference', ['currency' => 'GBP'])->assertJsonPath('plans.0.display_minor', 784);
    $this->postJson('/pricing/preference', ['currency' => 'USD'])->assertUnprocessable();
    $this->withCookie('zf_currency', 'EUR')->get('/es')->assertInertia(fn (Assert $page) => $page->where('pricing.currency', 'EUR')->where('pricing.plans.0.price_minor', 4900));
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), SyncExchangeRates::ENDPOINT.'?') && $request['base'] === 'RON' && $request['quotes'] === 'EUR,GBP');
    DB::table('marketing_plans')->where('key', 'menu')->update(['price_minor' => 4999]);
    DB::table('marketing_exchange_rates')->where('currency', 'EUR')->update(['rate_scaled' => 50000000]);
    $quote = app(PricingCatalog::class)->publicData('EUR');
    expect($quote['plans'][0]['display_minor'])->toBe(2500)->and($quote['plans'][0]['price_minor'])->toBe(4999);
});

it('keeps preferences independent and makes automatic detection opt in only for first landing visits', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.detectLocale', true)->where('visitorDefaults.detectCurrency', true));
    $this->get('/it')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.detectLocale', false));
    $this->withCookie('zf_locale', 'nl')->withCookie('zf_currency', 'GBP')->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.detectLocale', false)->where('visitorDefaults.detectCurrency', false));
    $this->actingAs(User::factory()->create(['locale' => 'es']))->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.detectLocale', false));
});

it('falls back safely before the first sync and retains old rates on partial corrupt or unavailable responses', function ($case) {
    $valid = validMarketingRates();
    $bad = match ($case) {
        'partial' => [$valid[0]],
        'duplicate' => [$valid[0], $valid[0]],
        'wrong base' => [[...$valid[0], 'base' => 'USD'], $valid[1]],
        'stale' => [[...$valid[0], 'date' => now('UTC')->subDays(30)->toDateString()], $valid[1]],
        'future' => [[...$valid[0], 'date' => now('UTC')->addDays(7)->toDateString()], $valid[1]],
        'bad date' => [[...$valid[0], 'date' => '2026-02-31'], $valid[1]],
        'negative' => [[...$valid[0], 'rate' => -0.2], $valid[1]],
        'oversized' => str_repeat('x', 70000),
        default => 'unavailable',
    };
    $this->postJson('/pricing/preference', ['currency' => 'EUR'])->assertOk()->assertJsonPath('currency', 'RON')->assertJsonPath('requested_currency', 'EUR')->assertJsonPath('plans.0.display_minor', 4900);
    Http::fake([SyncExchangeRates::ENDPOINT.'*' => Http::sequence()->push(validMarketingRates())->push($bad, $case === 'outage' ? 503 : 200)]);
    expect(app(SyncExchangeRates::class)->execute())->toBeTrue();
    $before = DB::table('marketing_exchange_rates')->orderBy('currency')->get()->toJson();
    expect(app(SyncExchangeRates::class)->execute())->toBeFalse()
        ->and(DB::table('marketing_exchange_rates')->orderBy('currency')->get()->toJson())->toBe($before)
        ->and(DB::table('marketing_pricing_settings')->value('last_error'))->toBe('provider_unavailable');
})->with(['partial', 'duplicate', 'wrong base', 'stale', 'future', 'bad date', 'negative', 'oversized', 'outage']);

it('marks retained stale rates honestly and lets scheduled updates be disabled independently of manual sync', function () {
    DB::table('marketing_exchange_rates')->insert(['currency' => 'EUR', 'rate_scaled' => 20000000, 'rate_date' => now()->subDays(20)->toDateString(), 'fetched_at' => now()->subDays(20)]);
    expect(app(PricingCatalog::class)->publicData('EUR')['stale'])->toBeTrue();
    DB::table('marketing_pricing_settings')->update(['weekly_enabled' => false]);
    Http::fake([SyncExchangeRates::ENDPOINT.'*' => Http::response(validMarketingRates())]);
    $this->artisan('zionfood:exchange-rates --scheduled')->assertSuccessful();
    Http::assertNothingSent();
    $this->artisan('zionfood:exchange-rates')->assertSuccessful();
    expect(app(PricingCatalog::class)->publicData('EUR')['stale'])->toBeFalse();
});

it('escapes edited package names in server-rendered markup', function () {
    DB::table('marketing_plans')->where('key', 'menu')->update(['name' => '<script>alert(1)</script>']);
    $this->get('/en')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});
