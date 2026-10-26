<?php

namespace App\Domain\Mail;

use App\Jobs\DeliverEmail;
use Illuminate\Mail\Events\MessageSending;
use InvalidArgumentException;

class QueueOutgoingEmail
{
    public function handle(MessageSending $event): ?bool
    {
        $mailer = $event->data['mailer'] ?? config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (in_array($transport, ['array', 'log'], true)) {
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

        DeliverEmail::dispatch(clone $event->message, $mailer)->onConnection($connection)->afterCommit();

        return false;
    }
}
