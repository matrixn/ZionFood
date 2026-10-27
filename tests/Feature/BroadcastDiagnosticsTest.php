<?php

use App\Domain\Integrations\BroadcastDiagnostics;
use App\Events\OrderChanged;
use App\Jobs\DeliverOutbox;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('classifies broadcast failures without leaking credentials urls or raw messages', function () {
    $diagnostics = new BroadcastDiagnostics;
    foreach (['cURL error 7' => 'connection_refused', 'cURL error 6' => 'dns_failure', 'cURL error 60' => 'tls_verification_failed', 'cURL error 28' => 'timeout', '401 unauthorized' => 'authentication_rejected', '404' => 'endpoint_not_found'] as $message => $reason) {
        $result = $diagnostics->failure(new RuntimeException($message.' https://secret-key:secret-password@example.test/private?token=secret-token'));
        expect($result['reason'])->toBe($reason)->and(json_encode($result))->not->toContain('secret-')->not->toContain('https://');
    }
});

it('preserves the original outbox failure and retry state even if diagnostics cannot be logged', function () {
    [, $location, $item] = tenantFixture('broadcast-diagnostics');
    $message = OutboxMessage::withoutEvents(fn () => OutboxMessage::create(['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'order.created', 'payload' => ['version' => 1]]));
    $failure = new RuntimeException('cURL error 7 secret-url');
    Event::listen(OrderChanged::class, function () use ($failure) {
        throw $failure;
    });
    Log::shouldReceive('warning')->once()->andThrow(new RuntimeException('log unavailable'));
    try {
        (new DeliverOutbox($message->id))->handle();
        $this->fail('The original broadcast failure must be retried.');
    } catch (RuntimeException $error) {
        expect($error)->toBe($failure);
    }
    expect($message->fresh()->delivered_at)->toBeNull()->and($message->fresh()->attempts)->toBe(1)->and($message->fresh()->last_error)->toBe(RuntimeException::class);
});
