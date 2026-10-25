<?php

namespace App\Domain\Integrations;

use App\Models\OutboxMessage;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class QueueWebhooks
{
    public function execute(OutboxMessage $message): void
    {
        WebhookEndpoint::where('organization_id', $message->organization_id)->where('location_id', $message->location_id)->where('enabled', true)->get()->each(function ($endpoint) use ($message) {
            if (! in_array($message->type, $endpoint->events ?? [], true)) {
                return;
            }
            WebhookDelivery::firstOrCreate(['endpoint_id' => $endpoint->id, 'event_id' => $message->event_id], ['outbox_message_id' => $message->id, 'status' => 'pending', 'payload' => ['id' => $message->event_id, 'type' => $message->type, 'occurred_at' => $message->created_at->toIso8601String(), 'data' => $message->payload], 'next_attempt_at' => now()]);
        });
    }
}
