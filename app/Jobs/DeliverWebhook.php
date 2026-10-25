<?php

namespace App\Jobs;

use App\Domain\Integrations\PublicWebhookDestination;
use App\Domain\Integrations\UnsafeWebhookDestination;
use App\Models\OutboxMessage;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    public function handle(PublicWebhookDestination $destination): void
    {
        $claim = DB::transaction(function () {
            $delivery = WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || in_array($delivery->status, ['delivered', 'failed', 'blocked', 'cancelled'], true) || $delivery->next_attempt_at?->isFuture() || ($delivery->status === 'delivering' && $delivery->locked_until?->isFuture())) {
                return null;
            }
            $endpoint = WebhookEndpoint::find($delivery->endpoint_id);
            $message = OutboxMessage::find($delivery->outbox_message_id);
            if (! $endpoint || ! $endpoint->enabled || ! $message || $endpoint->organization_id !== $message->organization_id || $endpoint->location_id !== $message->location_id || ! in_array($message->type, $endpoint->events ?? [], true)) {
                $delivery->update(['status' => 'cancelled', 'next_attempt_at' => null]);

                return null;
            }
            if ($delivery->attempts >= 6) {
                $delivery->update(['status' => 'failed', 'next_attempt_at' => null]);

                return null;
            }
            $token = (string) Str::uuid();
            $delivery->update(['status' => 'delivering', 'attempts' => $delivery->attempts + 1, 'locked_until' => now()->addSeconds(45), 'claim_token' => $token]);

            return ['delivery' => $delivery, 'endpoint' => $endpoint, 'token' => $token];
        }, 3);
        if (! $claim) {
            return;
        }
        $delivery = $claim['delivery'];
        $endpoint = $claim['endpoint'];
        $status = null;
        $failure = null;
        $retry = false;
        try {
            // Resolve again on EVERY attempt, then pin cURL to that checked address. Environment proxies are disabled.
            $target = $destination->resolve($endpoint->url);
            $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
            $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) now()->timestamp;
            $response = Http::connectTimeout(3)->timeout(8)->setHandler(new CurlHandler)->withOptions(['allow_redirects' => false, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$target['host'].':443:'.$ip]], 'verify' => true, 'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 65536) {
                    throw new \RuntimeException('Response too large.');
                }
            }, 'progress' => function ($total, $downloaded) {
                if ($downloaded > 65536) {
                    throw new \RuntimeException('Response too large.');
                }
            }])->withHeaders(['X-ZionFood-Event' => $delivery->payload['type'], 'X-ZionFood-Delivery' => $delivery->event_id, 'X-ZionFood-Timestamp' => $timestamp, 'X-ZionFood-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, Crypt::decryptString($endpoint->secret_encrypted))])->withBody($body, 'application/json')->post($endpoint->url);
            $status = $response->status();
            $response->toPsrResponse()->getBody()->close();
            if (! $response->successful()) {
                $failure = 'http_'.$status;
                $retry = $status >= 500 || in_array($status, [408, 429], true);
            }
        } catch (UnsafeWebhookDestination) {
            $failure = 'destination_blocked';
        } catch (\Throwable) {
            $failure = 'transport_error';
            $retry = true;
        }
        $next = $failure === null ? 'delivered' : ($failure === 'destination_blocked' ? 'blocked' : ($retry && $delivery->attempts < 6 ? 'retrying' : 'failed'));
        WebhookDelivery::whereKey($delivery->id)->where('claim_token', $claim['token'])->where('status', 'delivering')->update(['status' => $next, 'http_status' => $status, 'failure_code' => $failure, 'delivered_at' => $next === 'delivered' ? now() : null, 'next_attempt_at' => $next === 'retrying' ? now()->addSeconds([15, 60, 300, 900, 3600][$delivery->attempts - 1]) : null, 'locked_until' => null, 'claim_token' => null]);
    }
}
