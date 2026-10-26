<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function anafCompany(): array
{
    return ['found' => [[
        'date_generale' => ['cui' => 14837428, 'denumire' => 'Firma Test SRL', 'adresa' => 'Domiciliu fiscal diferit', 'nrRegCom' => 'J40/123/2000', 'telefon' => '0211234567', 'cod_CAEN' => '5611', 'forma_juridica' => 'SRL', 'stare_inregistrare' => 'INREGISTRAT', 'iban' => null, 'statusRO_e_Factura' => true],
        'adresa_sediu_social' => ['sdenumire_Strada' => 'Strada Test', 'snumar_Strada' => '10', 'sdenumire_Localitate' => 'București', 'sdenumire_Judet' => 'București', 'stara' => 'ROMANIA', 'scod_Postal' => '010101'],
        'inregistrare_scop_Tva' => ['scpTVA' => true], 'stare_inactiv' => ['statusInactivi' => false],
    ]], 'notFound' => []];
}

it('normalizes CUI and resolves bounded editable company fields without saving or forwarding tenant data', function () {
    Cache::store('array')->flush();
    Http::preventStrayRequests();
    Http::fake(['webservicesp.anaf.ro/*' => Http::response(anafCompany())]);
    [$owner, $location] = tenantFixture('company-lookup');
    $this->actingAs($owner)->postJson('/app/locations/'.$location->public_id.'/setup/company', ['tax_id' => 'RO 14837428', 'customer_phone' => 'secret'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('company.company_name', 'Firma Test SRL')->assertJsonPath('company.company_address', 'Strada Test, 10, București, București, ROMANIA')
        ->assertJsonPath('company.company_vat', true)->assertJsonPath('company.company_inactive', false)->assertJsonPath('company.company_iban', '')->assertJsonPath('company.tax_id', '14837428');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request[0]['cui'] === 14837428 && count($request->data()) === 1 && ! str_contains(json_encode($request->data()), 'secret'));
    expect($location->fresh()->setup_revision)->toBe(0)->and($location->fresh()->setup_profile)->toBeNull();
    $this->postJson('/app/locations/'.$location->public_id.'/setup/company', ['tax_id' => '14837428'])->assertOk();
    Http::assertSentCount(1);
});

it('requires management authorization and valid CUI before making any upstream request', function () {
    Http::preventStrayRequests();
    [$owner, $location] = tenantFixture('company-auth');
    [$foreign] = tenantFixture('company-other');
    $url = '/app/locations/'.$location->public_id.'/setup/company';
    $this->postJson($url, ['tax_id' => '14837428'])->assertUnauthorized();
    $this->actingAs($foreign)->postJson($url, ['tax_id' => '14837428'])->assertForbidden();
    $this->actingAs($owner)->postJson($url, ['tax_id' => 'https://private.test'])->assertUnprocessable();
    Http::assertNothingSent();
});

it('distinguishes not found from malformed or unavailable ANAF replies and limits provider rate', function () {
    Cache::store('array')->flush();
    Http::preventStrayRequests();
    [$owner, $location] = tenantFixture('company-failure');
    $url = '/app/locations/'.$location->public_id.'/setup/company';
    Http::fake(['webservicesp.anaf.ro/*' => Http::response(['found' => [], 'notFound' => [14837428]])]);
    $this->actingAs($owner)->postJson($url, ['tax_id' => '14837428'])->assertOk()->assertJsonPath('company', null);
    $this->postJson($url, ['tax_id' => '12345678'])->assertUnprocessable();
    Http::assertSentCount(1);
    Cache::store('array')->flush();
    Http::fake(['webservicesp.anaf.ro/*' => Http::response(['found' => [['date_generale' => ['cui' => 11111111]]], 'notFound' => []])]);
    $this->postJson($url, ['tax_id' => '14837428'])->assertUnprocessable();
    Cache::store('array')->flush();
    Http::fake(['webservicesp.anaf.ro/*' => Http::response(str_repeat('x', 131073))]);
    $this->postJson($url, ['tax_id' => '14837428'])->assertUnprocessable();
});

it('saves optional company details in the private revision protected legal profile', function () {
    [$owner, $location] = tenantFixture('company-save');
    $this->actingAs($owner)->put('/app/locations/'.$location->public_id.'/setup/legal', ['revision' => 0, 'company_name' => 'Test SRL', 'company_address' => 'Street 10', 'tax_id' => '14837428', 'company_city' => 'București', 'company_vat' => true, 'company_inactive' => false])->assertSessionHasNoErrors();
    expect($location->fresh()->setup_profile['legal']['company_city'])->toBe('București')->and($location->fresh()->setup_profile['legal']['company_vat'])->toBeTrue();
    $this->get('/restaurant/company-save')->assertInertia(fn (Assert $page) => $page->missing('profile')->where('restaurant.name', 'company-save'));
    $this->put('/app/locations/'.$location->public_id.'/setup/legal', ['revision' => 0, 'company_name' => 'Test', 'company_address' => 'Street'])->assertSessionHasErrors();
});
