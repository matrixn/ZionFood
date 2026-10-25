<?php

namespace App\Jobs;

use App\Domain\Integrations\QueueWebhooks;
use App\Events\OrderChanged;
use App\Models\OutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $messageId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [5, 15, 60, 120];
    }

    public function handle(): void
    {
        $error = DB::transaction(function () {
            $message = OutboxMessage::whereKey($this->messageId)->lockForUpdate()->firstOrFail();
            if ($message->delivered_at) {
                return null;
            }
            $message->increment('attempts');
            app(QueueWebhooks::class)->execute($message);
            try {
                if (str_starts_with($message->type, 'order.')) {
                    event(new OrderChanged($message->organization_id, $message->location_id, ['event_id' => $message->event_id, 'type' => $message->type, ...$message->payload]));
                }
                $message->update(['delivered_at' => now(), 'last_error' => null]);

                return null;
            } catch (\Throwable $error) {
                // Never persist transport exception messages: they can include credentials or URLs.
                $message->update(['last_error' => get_class($error)]);

                return $error;
            }
        });
        if ($error) {
            throw $error;
        }
    }
}
