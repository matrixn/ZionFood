<?php

namespace App\Jobs;

use App\Domain\Mail\EmailQuota;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Throwable;

class DeliverEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 5;

    public int $expiresAt;

    public function __construct(public Email $email, public string $mailer)
    {
        $this->expiresAt = now()->addDays(30)->timestamp;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return CarbonImmutable::createFromTimestamp($this->expiresAt);
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(EmailQuota $quota): void
    {
        try {
            $envelope = Envelope::create($this->email);
            $wait = $quota->reserve(count($envelope->getRecipients()));
            if ($wait > 0) {
                $this->release($wait + random_int(1, 30));

                return;
            }
            // Bypass MessageSending here: the message is already rendered and queued.
            Mail::mailer($this->mailer)->getSymfonyTransport()->send($this->email, $envelope);
        } catch (Throwable $error) {
            if ($this->email->getHeaders()->has('X-ZionFood-Error-Alert')) {
                // Alert delivery failures must never produce another email alert.
                Log::warning('Error alert delivery failed; inspect provider and queue health.');
                $this->release(3600);

                return;
            }

            throw $error;
        }
    }
}
