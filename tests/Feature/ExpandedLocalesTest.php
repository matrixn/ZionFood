<?php

use App\Domain\Localization\UiLocale;
use App\Models\CampaignVisit;
use App\Models\ServiceLead;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('serves each new landing language with reciprocal hreflang and a self canonical independently of cookies', function ($locale, $heading) {
    config(['seo.indexable' => true, 'seo.public_url' => 'https://food.zion3d.ro', 'zionfood.app_hosts' => ['localhost', 'food.zion3d.ro']]);
    $response = $this->withCookie('zf_locale', 'en')->get('/'.$locale)->assertOk()->assertHeader('Content-Language', $locale)->assertSee('lang="'.$locale.'"', false)->assertSee($heading);
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $dom = new DOMXPath($doc);
    expect($dom->query('//link[@rel="alternate" and @hreflang]'))->toHaveCount(6)
        ->and($dom->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro/'.$locale);
    foreach (UiLocale::CODES as $code) {
        expect($dom->query('//link[@hreflang="'.$code.'"]')->item(0)->getAttribute('href'))->toBe('https://food.zion3d.ro'.UiLocale::path($code));
    }
})->with([['it', 'Il tuo ristorante.'], ['es', 'Tu restaurante.'], ['nl', 'Jouw restaurant.']]);

it('persists new restaurant interface languages with localized validation and private noindex workspace pages', function ($locale) {
    [$owner, $location] = tenantFixture('locale-'.$locale);
    $this->actingAs($owner)->postJson('/locale', ['locale' => $locale])->assertOk()->assertCookie('zf_locale', $locale)->assertHeader('Content-Language', $locale);
    expect($owner->fresh()->locale)->toBe($locale)->and($owner->fresh()->preferredLocale())->toBe($locale);
    $this->withCookie('zf_locale', $locale)->get('/app/locations/'.$location->public_id)->assertOk()->assertHeader('Content-Language', $locale)->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
    $response = $this->postJson('/locale', []); // validation must use the same language on the server
    $response->assertUnprocessable()->assertHeader('Content-Language', $locale);
    expect($response->json('errors.locale.0'))->not->toContain('required', 'obligatoriu');
})->with(['it', 'es', 'nl']);

it('keeps platform administration RO EN and stores its language independently of restaurant preference', function () {
    $admin = User::factory()->create(['is_platform_admin' => true, 'locale' => 'it']);
    $this->actingAs($admin)->withCookie('zf_locale', 'nl')->get('/admin')->assertOk()->assertHeader('Content-Language', 'ro')->assertInertia(fn (Assert $page) => $page->where('locale', 'ro'));
    $this->postJson('/locale', ['locale' => 'es', 'scope' => 'admin'])->assertUnprocessable();
    $this->postJson('/locale', ['locale' => 'en', 'scope' => 'admin'])->assertOk()->assertCookie('zf_admin_locale', 'en')->assertCookieMissing('zf_locale');
    expect($admin->fresh()->locale)->toBe('it');
    $this->withCookie('zf_admin_locale', 'en')->get('/admin')->assertHeader('Content-Language', 'en')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/locale', ['locale' => 'en', 'scope' => 'admin'])->assertForbidden();
});

it('records attribution and accepts contact requests in the new marketing languages', function ($locale) {
    $this->get('/'.$locale.'?utm_source=campaign&utm_campaign=Locale')->assertOk();
    expect(CampaignVisit::sole()->landing_path)->toBe('/'.$locale)->and(CampaignVisit::sole()->locale)->toBe($locale);
    $this->postJson('/interest', ['name' => 'Local Contact', 'email' => 'locale-'.$locale.'@example.test', 'phone_country' => 'RO', 'phone' => '0770123456', 'locale' => $locale])->assertOk()->assertExactJson(['status' => 'received']);
    expect(ServiceLead::sole()->locale)->toBe($locale)->and(ServiceLead::sole()->campaign_visit_id)->toBe(CampaignVisit::sole()->id);
})->with(['it', 'es', 'nl']);

it('does not advertise a partially generated language cluster', function () {
    $path = storage_path('framework/testing/localized-build-'.Str::uuid());
    mkdir($path, 0777, true);
    file_put_contents($path.'/manifest.json', json_encode(['version' => config('zionfood.version')]));
    foreach (['ro', 'en', 'it', 'es'] as $locale) {
        file_put_contents($path.'/'.$locale.'-guest.html', '<main>Static build fixture</main>');
    }
    config(['seo.prerender_path' => $path, 'seo.indexable' => true, 'seo.public_url' => 'https://food.zion3d.ro', 'zionfood.app_hosts' => ['localhost', 'food.zion3d.ro']]);
    try {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>', false);
    } finally {
        foreach (glob($path.'/*') as $file) {
            unlink($file);
        }
        rmdir($path);
    }
});
