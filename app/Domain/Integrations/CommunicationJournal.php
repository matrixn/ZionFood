<?php

namespace App\Domain\Integrations;

use App\Models\CommunicationLog;
use App\Models\RestaurantLocation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;

class CommunicationJournal
{
    public function write(array $data): void
    {
        try {
            CommunicationLog::create(array_intersect_key($data, array_flip(['request_id', 'organization_id', 'restaurant_id', 'location_id', 'wordpress_connection_id', 'direction', 'operation', 'method', 'http_status', 'outcome', 'duration_ms', 'destination_host', 'event_id', 'attempt', 'error_code', 'replayed'])));
        } catch (\Throwable $error) {
            // Logging failure must not undo a committed business operation. Never log private bodies/URLs.
            try {
                Log::warning('Communication journal unavailable', ['request_id' => $data['request_id'] ?? null, 'direction' => $data['direction'] ?? null, 'http_status' => $data['http_status'] ?? null, 'exception_class' => $error::class]);
            } catch (\Throwable) {
            }
        }
    }

    public function outbound(WebhookEndpoint $endpoint, WebhookDelivery $delivery, ?int $status, string $outcome, ?string $failure, int $duration, string $requestId): void
    {
        $location = RestaurantLocation::find($endpoint->location_id);
        if (! $location || (int) $location->organization_id !== (int) $endpoint->organization_id) {
            return;
        }
        $this->write(['request_id' => $requestId, 'organization_id' => $location->organization_id, 'restaurant_id' => $location->restaurant_id, 'location_id' => $location->id, 'wordpress_connection_id' => $endpoint->wordpress_connection_id, 'direction' => 'outbound', 'operation' => 'webhook.delivery', 'method' => 'POST', 'http_status' => $status, 'outcome' => $outcome, 'error_code' => $failure, 'duration_ms' => $duration, 'destination_host' => parse_url($endpoint->url, PHP_URL_HOST), 'event_id' => $delivery->event_id, 'attempt' => $delivery->attempts]);
    }
}
