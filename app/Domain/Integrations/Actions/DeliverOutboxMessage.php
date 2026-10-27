<?php

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\BroadcastDiagnostics;
use App\Domain\Integrations\QueueWebhooks;
use App\Events\OrderChanged;
use App\Jobs\DeliverOutbox;
use App\Models\OutboxMessage;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverOutboxMessage
{
    public const MAX_ATTEMPTS = 5;

    public const DELAYS = [5, 15, 60, 120];

    public function due(): Builder
    {
        return OutboxMessage::whereNull('delivered_at')->whereNull('failed_at')
            ->where('delivery_attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
    }

    public function execute(int $id): void
    {
        $error = DB::transaction(function () use ($id) {
            $message = OutboxMessage::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($message->delivered_at || $message->failed_at || $message->delivery_attempts >= self::MAX_ATTEMPTS || $message->next_attempt_at?->isFuture()) {
                return null;
            }
            $attempt = $message->delivery_attempts + 1;
            $message->update(['attempts' => $message->attempts + 1, 'delivery_attempts' => $attempt]);
            try {
                app(QueueWebhooks::class)->execute($message);
                if (str_starts_with($message->type, 'order.')) {
                    event(new OrderChanged($message->organization_id, $message->location_id, ['event_id' => $message->event_id, 'type' => $message->type, ...$message->payload]));
                }
                $message->update(['delivered_at' => now(), 'last_error' => null, 'next_attempt_at' => null]);

                return null;
            } catch (Throwable $error) {
                $exhausted = $attempt >= self::MAX_ATTEMPTS;
                $details = app(BroadcastDiagnostics::class)->failure($error);
                $message->update(['last_error' => get_class($error), 'failed_at' => $exhausted ? now() : null, 'next_attempt_at' => $exhausted ? null : now()->addSeconds(self::DELAYS[$attempt - 1])]);
                try {
                    Log::warning('ZionFood outbox delivery failed', ['event_id' => $message->event_id, 'attempt' => $message->attempts, 'delivery_attempt' => $attempt, 'exhausted' => $exhausted, ...$details]);
                } catch (Throwable) {
                    // Diagnostics must not replace the delivery failure or undo durable retry state.
                }

                // Pusher's exception message embeds signed URLs. Do not propagate that text
                // (or chain it as previous) into framework logs, Horizon or failed-job records.
                return $error instanceof BroadcastException
                    ? new BroadcastException('Realtime publication failed: '.$details['reason'].'. '.$details['hint'])
                    : $error;
            }
        }, 3);
        if ($error) {
            throw $error;
        }
    }

    public function retry(string $event): bool
    {
        return DB::transaction(function () use ($event) {
            $message = OutboxMessage::where('event_id', $event)->lockForUpdate()->firstOrFail();
            if ($message->delivered_at || ! $message->failed_at) {
                return false;
            }
            $message->update(['delivery_attempts' => 0, 'failed_at' => null, 'next_attempt_at' => null]);
            DB::afterCommit(fn () => DeliverOutbox::dispatch($message->id));

            return true;
        });
    }
}
