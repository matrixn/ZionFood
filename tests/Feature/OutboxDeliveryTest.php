<?php

use App\Events\OrderChanged;
use App\Jobs\DeliverOutbox;
use App\Models\OutboxMessage;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function undeliveredEvent(array $data = []): OutboxMessage
{
    [, $location] = tenantFixture('outbox-'.strtolower(Str::random(8)));

    return OutboxMessage::withoutEvents(fn () => OutboxMessage::create([...['event_id' => (string) Str::ulid(), 'organization_id' => $location->organization_id, 'location_id' => $location->id, 'type' => 'order.created', 'payload' => ['order_id' => (string) Str::ulid(), 'version' => 1]], ...$data]));
}

it('bounds failures across fresh jobs scheduler ticks and duplicate queued jobs', function () {
    $message = undeliveredEvent();
    Event::listen(OrderChanged::class, fn () => throw new RuntimeException('Offline'));
    for ($i = 1; $i <= 5; $i++) {
        expect(fn () => (new DeliverOutbox($message->id))->handle())->toThrow(RuntimeException::class);
        expect($message->fresh()->delivery_attempts)->toBe($i)->and($message->fresh()->attempts)->toBe($i);
        if ($i < 5) {
            $next = $message->fresh()->next_attempt_at;
            (new DeliverOutbox($message->id))->handle();
            expect($message->fresh()->attempts)->toBe($i);
            $this->travelTo($next);
        }
    }
    expect($message->fresh()->failed_at)->not->toBeNull()->and($message->fresh()->delivered_at)->toBeNull()->and($message->fresh()->next_attempt_at)->toBeNull();
    $this->travel(2)->days();
    Queue::fake();
    $this->artisan('zionfood:outbox')->assertSuccessful();
    $this->artisan('zionfood:outbox')->assertSuccessful();
    Queue::assertNothingPushed();
    for ($i = 0; $i < 10; $i++) {
        (new DeliverOutbox($message->id))->handle();
    }
    expect($message->fresh()->attempts)->toBe(5);
});

it('queues only due non-exhausted rows and preserves delivered and legacy high-attempt events', function () {
    $due = undeliveredEvent();
    $future = undeliveredEvent(['next_attempt_at' => now()->addMinute()]);
    $paused = undeliveredEvent(['failed_at' => now(), 'delivery_attempts' => 5, 'attempts' => 463]);
    $done = undeliveredEvent(['delivered_at' => now()]);
    Queue::fake();
    $this->artisan('zionfood:outbox')->assertSuccessful();
    Queue::assertPushed(DeliverOutbox::class, 1);
    Queue::assertPushed(DeliverOutbox::class, fn ($job) => $job->messageId === $due->id);
    Event::fake([OrderChanged::class]);
    foreach ([$future, $paused, $done] as $event) {
        (new DeliverOutbox($event->id))->handle();
    }
    Event::assertNotDispatched(OrderChanged::class);
    expect($paused->fresh()->attempts)->toBe(463)->and($paused->fresh()->payload)->toBe($paused->payload);
});

it('requires explicit per-event recovery and retains event identity payload and lifetime attempts', function () {
    $event = undeliveredEvent(['failed_at' => now(), 'delivery_attempts' => 5, 'attempts' => 463]);
    Queue::fake();
    $this->artisan('zionfood:outbox-retry invalid')->assertFailed();
    $this->artisan('zionfood:outbox-retry '.Str::ulid())->assertFailed();
    $this->artisan('zionfood:outbox-retry '.$event->event_id)->assertSuccessful();
    Queue::assertPushed(DeliverOutbox::class, 1);
    expect($event->fresh()->attempts)->toBe(463)->and($event->fresh()->delivery_attempts)->toBe(0)->and($event->fresh()->failed_at)->toBeNull()->and($event->fresh()->payload)->toBe($event->payload);
    $this->artisan('zionfood:outbox-retry '.$event->event_id)->assertFailed();
    Event::fake([OrderChanged::class]);
    (new DeliverOutbox($event->id))->handle();
    (new DeliverOutbox($event->id))->handle();
    Event::assertDispatchedTimes(OrderChanged::class, 1);
    expect($event->fresh()->attempts)->toBe(464)->and($event->fresh()->delivered_at)->not->toBeNull();
    $this->artisan('zionfood:outbox-retry '.$event->event_id)->assertFailed();
});

it('never propagates signed Reverb urls to queue error records or framework logs', function () {
    $event = undeliveredEvent();
    $original = new BroadcastException('cURL error 6: Could not resolve host: example.invalid for https://example.invalid/apps/id/events?auth_key=private-key&auth_signature=private-signature');
    Event::listen(OrderChanged::class, fn () => throw $original);
    try {
        (new DeliverOutbox($event->id))->handle();
        $this->fail('Expected a failed publication.');
    } catch (BroadcastException $safe) {
        expect($safe->getMessage())->toContain('dns_failure')->and((string) $safe)->not->toContain('private-key')->not->toContain('private-signature')->not->toContain('auth_key=')->not->toContain('https://')->and($safe->getPrevious())->toBeNull();
    }
    expect($event->fresh()->last_error)->toBe(BroadcastException::class);
});

it('quarantines exhausted legacy rows when the migration is applied without rewriting their lifetime history', function () {
    $event = undeliveredEvent(['attempts' => 463]);
    $completed = undeliveredEvent(['attempts' => 9, 'delivered_at' => now()]);
    $migration = require database_path('migrations/2026_10_27_120000_bound_outbox_retries.php');
    $migration->backfill();
    expect($event->fresh()->attempts)->toBe(463)->and($event->fresh()->delivery_attempts)->toBe(5)->and($event->fresh()->failed_at)->not->toBeNull()->and($event->fresh()->delivered_at)->toBeNull()->and($completed->fresh()->failed_at)->toBeNull();
});
