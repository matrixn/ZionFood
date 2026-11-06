<?php

use App\Domain\Localization\GeoIpDatabase;
use App\Domain\Localization\UpdateGeoIp;
use App\Domain\Localization\VisitorPreferences;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['geoip.database_path' => base_path('tests/Fixtures/geoip/GeoIP2-Country-Test.mmdb'), 'trustedproxy.proxies' => []]);
});

it('reads public IPv4 IPv6 and mapped addresses locally while rejecting private or invalid addresses', function () {
    Http::preventStrayRequests();
    $db = app(GeoIpDatabase::class);
    expect($db->country('81.2.69.160'))->toBe('GB')
        ->and($db->country('::ffff:81.2.69.160'))->toBe('GB')
        ->and($db->country('2001:218::'))->toBe('JP');
    foreach (['127.0.0.1', '10.0.0.1', '192.168.1.1', '::1', 'fc00::1', '::ffff:127.0.0.1', '2001:db8::1', 'not-ip', null] as $ip) {
        expect($db->country($ip))->toBeNull();
    }
    Http::assertNothingSent();
});

it('uses English EUR for unknown unsupported missing or corrupt geolocation data', function () {
    foreach (['US', 'FR', 'ZZ', null] as $country) {
        expect(VisitorPreferences::forCountry($country))->toBe(['locale' => 'en', 'currency' => 'EUR']);
    }
    config(['geoip.database_path' => base_path('composer.json')]);
    expect(app(GeoIpDatabase::class)->country('81.2.69.160'))->toBeNull();
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.redirectLocale', 'en')->where('pricing.requested_currency', 'EUR'));
});

it('maps IP countries to server defaults without using browser languages timezone or caller supplied country', function ($country, $locale, $currency) {
    $this->mock(GeoIpDatabase::class)->shouldReceive('country')->with('81.2.69.160')->andReturn($country);
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])->withHeader('Accept-Language', 'ro-RO')->withHeader('CF-IPCountry', 'RO')->get('/?country=RO&timezone=Europe/Bucharest')->assertOk()->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.source', 'ip')->where('visitorDefaults.country', $country)->where('visitorDefaults.redirectLocale', $locale === 'ro' ? null : $locale)->where('pricing.requested_currency', $currency));
})->with([['RO', 'ro', 'RON'], ['IT', 'it', 'EUR'], ['ES', 'es', 'EUR'], ['NL', 'nl', 'EUR'], ['GB', 'en', 'GBP'], ['US', 'en', 'EUR']]);

it('ignores spoofed forwarding and country headers but accepts client IP from an explicit trusted proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])->withHeaders(['X-Forwarded-For' => '81.2.69.160', 'CF-IPCountry' => 'GB'])->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.country', null)->where('pricing.requested_currency', 'EUR'));
    config(['trustedproxy.proxies' => ['10.0.0.10']]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.country', 'GB')->where('pricing.requested_currency', 'GBP'));
});

it('prioritizes separate manual and legacy choices over IP while refreshing automatic defaults after an IP change', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160']);
    $this->withCookie('zf_locale', 'it')->withCookie('zf_locale_source', 'ip')->withCookie('zf_currency', 'EUR')->withCookie('zf_currency_source', 'ip')->get('/')->assertCookie('zf_currency_source', 'ip')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.redirectLocale', 'en')->where('pricing.requested_currency', 'GBP'));
    $this->withCookie('zf_locale_source', 'manual')->withCookie('zf_currency_source', 'manual')->get('/')->assertCookie('zf_locale', 'it')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.redirectLocale', 'it')->where('pricing.requested_currency', 'EUR'));
    $this->withCookie('zf_locale_source', 'legacy')->withCookie('zf_currency_source', 'legacy')->get('/')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.redirectLocale', 'it')->where('pricing.requested_currency', 'EUR'));
});

it('marks selector changes as manual and keeps platform admin preference independent', function () {
    $this->postJson('/locale', ['locale' => 'nl'])->assertCookie('zf_locale_source', 'manual');
    $this->postJson('/pricing/preference', ['currency' => 'RON'])->assertCookie('zf_currency_source', 'manual');
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->postJson('/locale', ['locale' => 'en', 'scope' => 'admin'])->assertCookieMissing('zf_locale_source')->assertCookie('zf_admin_locale', 'en');
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])->get('/it')->assertHeader('Content-Language', 'it')->assertInertia(fn (Assert $page) => $page->where('visitorDefaults.redirectLocale', null));
});

it('keeps the saved account language above an automatic cookie after login while explicit manual cookies still win', function () {
    [$owner, $location] = tenantFixture('ip-account-preference');
    $owner->forceFill(['locale' => 'nl'])->save();
    $this->actingAs($owner)->withCookie('zf_locale', 'en')->withCookie('zf_locale_source', 'ip')->get('/app/locations/'.$location->public_id.'/setup/basics')->assertOk()->assertHeader('Content-Language', 'nl')->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));
    $this->withCookie('zf_locale_source', 'manual')->get('/app/locations/'.$location->public_id.'/setup/basics')->assertOk()->assertHeader('Content-Language', 'en')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('atomically updates valid country data and skips scheduled downloads for the current release', function () {
    $path = storage_path('framework/testing/geoip-'.Str::uuid().'/country.mmdb');
    config(['geoip.database_path' => $path]);
    Http::fake([UpdateGeoIp::ORIGIN.'*' => Http::response(gzencode(file_get_contents(base_path('tests/Fixtures/geoip/GeoIP2-Country-Test.mmdb'))))]);
    try {
        expect(app(UpdateGeoIp::class)->execute())->toBeTrue()->and(app(GeoIpDatabase::class)->country('81.2.69.160'))->toBe('GB');
        expect(app(UpdateGeoIp::class)->execute(true))->toBeTrue();
        Http::assertSentCount(1);
        expect(glob(dirname($path).'/geoip-*'))->toBe([]);
    } finally {
        foreach (glob(dirname($path).'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir(dirname($path));
    }
});

it('retains the previous database when a new gzip or country database is invalid', function ($invalid) {
    $path = storage_path('framework/testing/geoip-'.Str::uuid().'/country.mmdb');
    mkdir(dirname($path), 0750, true);
    copy(base_path('tests/Fixtures/geoip/GeoIP2-Country-Test.mmdb'), $path);
    config(['geoip.database_path' => $path]);
    $before = hash_file('sha256', $path);
    $body = match ($invalid) {
        'gzip' => 'not-gzip',
        'compressed bound' => str_repeat('x', 17 * 1024 * 1024),
        'expanded bound' => gzencode(str_repeat('x', 33 * 1024 * 1024)),
        default => gzencode('not-a-database'),
    };
    Http::fake([UpdateGeoIp::ORIGIN.'*' => Http::response($body)]);
    try {
        expect(app(UpdateGeoIp::class)->execute())->toBeFalse()->and(hash_file('sha256', $path))->toBe($before)->and(app(GeoIpDatabase::class)->country('81.2.69.160'))->toBe('GB');
        expect(glob(dirname($path).'/geoip-*'))->toBe([]);
    } finally {
        unlink($path);
        rmdir(dirname($path));
    }
})->with(['gzip', 'database', 'compressed bound', 'expanded bound']);

it('restricts database downloads to verified platform administrators and never discloses visitor IPs', function () {
    Http::fake();
    $this->actingAs(User::factory()->create())->postJson('/admin/pricing/geoip')->assertForbidden();
    Http::assertNothingSent();
    $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])->get('/en')->assertOk()->assertDontSee('81.2.69.160');
    $this->mock(UpdateGeoIp::class)->shouldReceive('execute')->once()->andReturn(true);
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->postJson('/admin/pricing/geoip')->assertOk()->assertJsonPath('updated', true)->assertJsonPath('geoip.ready', true);
});

it('falls back to the previous published monthly release when the current download is not available', function () {
    $path = storage_path('framework/testing/geoip-'.Str::uuid().'/country.mmdb');
    config(['geoip.database_path' => $path]);
    Http::fake([UpdateGeoIp::ORIGIN.'*' => Http::sequence()->push('', 404)->push(gzencode(file_get_contents(base_path('tests/Fixtures/geoip/GeoIP2-Country-Test.mmdb'))))->push('', 404)]);
    try {
        expect(app(UpdateGeoIp::class)->execute())->toBeTrue()->and(app(GeoIpDatabase::class)->country('81.2.69.160'))->toBe('GB');
        expect(app(UpdateGeoIp::class)->execute(true))->toBeTrue();
        Http::assertSentCount(3); // current-month retry does not download the same previous release again
    } finally {
        foreach (glob(dirname($path).'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir(dirname($path));
    }
});
