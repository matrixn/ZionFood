<?php

namespace App\Domain\Mail;

use App\Jobs\DeliverEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class QueueOutgoingEmail
{
    public function handle(MessageSending $event): ?bool
    {
        $mailer = $event->data['mailer'] ?? config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (in_array($transport, ['array', 'log'], true)) {
            $record = app(EmailLedger::class)->message((string) Str::ulid(), $event->message, $mailer, $event->data);
            $event->message->getHeaders()->addTextHeader('X-ZionFood-Delivery-ID', $record->public_id);

            return null;
        }
        // Do not permit fallback-to-log transports to claim real delivery.
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            throw new InvalidArgumentException('Use a single delivery transport for quota-controlled email.');
        }
        $connection = config('email_delivery.queue_connection');
        if (! in_array(config('queue.connections.'.$connection.'.driver'), ['redis', 'database'], true)) {
            throw new InvalidArgumentException('Email delivery requires a persistent queue connection.');
        }

        $job = (new DeliverEmail(clone $event->message, $mailer))->onConnection($connection)->afterCommit();
        $record = app(EmailLedger::class)->message($job->deliveryId, $event->message, $mailer, $event->data);
        $job->deliveryId = $record->public_id;
        try {
            Bus::dispatch($job);
        } catch (Throwable $error) {
            $record->update(['state' => 'failed', 'reason' => 'queue_failed']);
            EmailDeliveryLog::write('queue_failed', ['delivery_id' => $job->deliveryId, 'mailer' => $mailer, ...EmailDeliveryLog::failure($error)], 'error');
            throw $error;
        }

        return false;
    }
}
