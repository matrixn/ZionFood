<?php

namespace App\Jobs;

use App\Domain\Integrations\Actions\DeliverOutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = DeliverOutboxMessage::MAX_ATTEMPTS;

    public function __construct(public int $messageId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return DeliverOutboxMessage::DELAYS;
    }

    public function handle(): void
    {
        app(DeliverOutboxMessage::class)->execute($this->messageId);
    }
}
