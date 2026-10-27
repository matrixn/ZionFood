<?php

namespace App\Jobs;

use App\Domain\Mail\EmailDeliveryLog;
use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\ErrorAlertCircuit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Throwable;

class DeliverEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 5;

    public int $expiresAt;

    public ?string $deliveryId = null;

    public function __construct(public Email $email, public string $mailer)
    {
        $this->expiresAt = now()->addDays(30)->timestamp;
        $this->deliveryId = (string) Str::ulid();
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
        $this->deliveryId ??= (string) Str::ulid();
        $context = ['delivery_id' => $this->deliveryId, 'mailer' => $this->mailer, 'attempt' => $this->job ? $this->attempts() : 1];
        try {
            EmailDeliveryLog::write('processing', $context);
            $isAlert = $this->email->getHeaders()->has('X-ZionFood-Error-Alert');
            $generation = $this->email->getHeaders()->get('X-ZionFood-Alert-Generation')?->getBodyAsString();
            $generation = ctype_digit($generation ?? '') ? (int) $generation : null;
            $circuit = app(ErrorAlertCircuit::class);
            if ($isAlert && ! $circuit->canDeliver($generation)) {
                EmailDeliveryLog::write('alert_suppressed', $context);

                return;
            }
            $envelope = Envelope::create($this->email);
            $wait = $quota->reserve(count($envelope->getRecipients()));
            if ($wait > 0) {
                EmailDeliveryLog::write('quota_deferred', [...$context, 'retry_after_seconds' => $wait], 'warning');
                $this->release($wait + random_int(1, 30));

                return;
            }
            $slot = $isAlert ? $circuit->reserveDelivery($generation) : null;
            if ($isAlert && $slot === null) {
                EmailDeliveryLog::write('alert_suppressed', $context);

                return;
            }
            // Bypass MessageSending here: the message is already rendered and queued.
            $sent = Mail::mailer($this->mailer)->getSymfonyTransport()->send($this->email, $envelope);
            if (! $sent) {
                throw new \RuntimeException('Mail transport did not accept the message.');
            }
            if ($slot !== null) {
                try {
                    $circuit->accepted($slot);
                } catch (Throwable) {
                    // Never retry a provider-accepted message because bookkeeping failed.
                    EmailDeliveryLog::write('alert_acceptance_record_failed', $context, 'warning');
                }
            }
            $original = $sent->getOriginalMessage();
            $providerId = $original instanceof Email ? $original->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString() : null;
            EmailDeliveryLog::write('provider_accepted', [...$context, 'recipient_count' => count($envelope->getRecipients()), 'provider_email_id' => $providerId]);
        } catch (Throwable $error) {
            EmailDeliveryLog::write('attempt_failed', [...$context, ...EmailDeliveryLog::failure($error)], 'error');
            if ($this->email->getHeaders()->has('X-ZionFood-Error-Alert')) {
                // Alert delivery failures must never produce another email alert.
                Log::warning('Error alert delivery failed; inspect provider and queue health.');
                $this->release(3600);

                return;
            }

            throw $error;
        }
    }

    public function failed(?Throwable $error): void
    {
        EmailDeliveryLog::write('permanently_failed', ['delivery_id' => $this->deliveryId, 'mailer' => $this->mailer, ...($error ? EmailDeliveryLog::failure($error) : [])], 'error');
    }
}
