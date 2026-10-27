<?php

use App\Domain\Integrations\PublicDnsResolver;
use App\Events\OrderChanged;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http'], 'broadcasting.connections.reverb.app_id' => 'not-printed-id', 'broadcasting.connections.reverb.key' => 'not-printed-key', 'broadcasting.connections.reverb.secret' => 'not-printed-secret', 'reverb.apps.apps.0.options.host' => 'ws.example.test']);
});

it('detects missing public DNS even with a valid internal publish address', function () {
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->once()->with('ws.example.test')->andReturn([]);
    $this->artisan('zionfood:realtime-check')->expectsOutputToContain('127.0.0.1')->expectsOutputToContain('ws.example.test — DNS lookup failed.')->assertFailed();
});

it('accepts resolved host configuration without publishing an event', function () {
    $this->mock(PublicDnsResolver::class)->shouldReceive('addresses')->once()->with('ws.example.test')->andReturn(['203.0.113.10']);
    Event::fake([OrderChanged::class]);
    $this->artisan('zionfood:realtime-check')->expectsOutputToContain('No event was published.')->assertSuccessful();
    Event::assertNotDispatched(OrderChanged::class);
});

it('rejects malformed endpoint values without echoing credentials or querying their DNS', function () {
    config(['broadcasting.connections.reverb.options.host' => 'https://secret-user:secret-password@example.test/private?key=secret-value', 'broadcasting.connections.reverb.options.port' => 0, 'reverb.apps.apps.0.options.host' => '127.0.0.1']);
    $this->mock(PublicDnsResolver::class)->shouldNotReceive('addresses');
    $this->artisan('zionfood:realtime-check')->expectsOutputToContain('invalid or missing hostname')->expectsOutputToContain('Invalid publisher port or scheme.')->assertFailed();
});
