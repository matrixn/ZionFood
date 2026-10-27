<?php

namespace App\Jobs;

use App\Domain\Mail\EmailDeliveryLog;
use App\Domain\Mail\EmailLedger;
use App\Domain\Mail\EmailQuota;
use App\Domain\Mail\ErrorAlertCircuit;
use App\Domain\Mail\ResendQuota;
use App\Models\EmailDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
        $ledger = app(EmailLedger::class);
        $record = $ledger->message($this->deliveryId, $this->email, $this->mailer, legacy: true);
        $claim = $ledger->claim($record);
        if ($claim === 'skip') {
            return;
        }
        if ($claim === 'busy') {
            $this->release(15);

            return;
        }
        $context = ['delivery_id' => $this->deliveryId, 'mailer' => $this->mailer, 'attempt' => $this->job ? $this->attempts() : 1];
        $providerLock = null;
        $accepted = false;
        $isResend = config('mail.mailers.'.$this->mailer.'.transport') === 'resend';
        $resend = app(ResendQuota::class);
        try {
            EmailDeliveryLog::write('processing', $context);
            $isAlert = $this->email->getHeaders()->has('X-ZionFood-Error-Alert');
            $generation = $this->email->getHeaders()->get('X-ZionFood-Alert-Generation')?->getBodyAsString();
            $generation = ctype_digit($generation ?? '') ? (int) $generation : null;
            $circuit = app(ErrorAlertCircuit::class);
            if ($isAlert && ! $circuit->canDeliver($generation)) {
                $record->update(['state' => 'suppressed', 'reason' => 'alert_circuit', 'lease_until' => null]);
                EmailDeliveryLog::write('alert_suppressed', $context);

                return;
            }
            if ($isResend) {
                $providerLock = Cache::store(config('email_delivery.cache_store'))->lock('mail:resend:send', 90);
                if (! $providerLock->get()) {
                    $ledger->defer($record, 'provider_busy', 5);
                    $this->release(5);

                    return;
                }
                $wait = $resend->delay();
                if ($wait['seconds']) {
                    $ledger->defer($record, $wait['reason'], $wait['seconds']);
                    $this->release($wait['seconds'] + random_int(1, 30));

                    return;
                }
            }
            $envelope = Envelope::create($this->email);
            $recipients = count($envelope->getRecipients());
            $wait = $quota->reserve($recipients);
            if ($wait > 0) {
                $ledger->defer($record, 'application_quota', $wait);
                EmailDeliveryLog::write('quota_deferred', [...$context, 'retry_after_seconds' => $wait], 'warning');
                $this->release($wait + random_int(1, 30));

                return;
            }
            if ($isResend) {
                $wait = $resend->reserve($recipients);
                if ($wait['reason'] === 'message_exceeds_provider_limit') {
                    $record->update(['state' => 'failed', 'reason' => $wait['reason'], 'lease_until' => null]);

                    return;
                }
                if ($wait['seconds']) {
                    $ledger->defer($record, $wait['reason'], $wait['seconds']);
                    $this->release($wait['seconds'] + random_int(1, 30));

                    return;
                }
            }
            $slot = $isAlert ? $circuit->reserveDelivery($generation) : null;
            if ($isAlert && $slot === null) {
                $record->update(['state' => 'suppressed', 'reason' => 'alert_circuit', 'lease_until' => null]);
                EmailDeliveryLog::write('alert_suppressed', $context);

                return;
            }
            $record->increment('attempts');
            // The message is already rendered and queued: do not emit MessageSending again.
            $sent = Mail::mailer($this->mailer)->getSymfonyTransport()->send($this->email, $envelope);
            if (! $sent) {
                throw new \RuntimeException('Mail transport did not accept the message.');
            }
            $accepted = true;
            if ($slot !== null) {
                try {
                    $circuit->accepted($slot);
                } catch (Throwable) {
                    EmailDeliveryLog::write('alert_acceptance_record_failed', $context, 'warning');
                }
            }
            $original = $sent->getOriginalMessage();
            $providerId = $original instanceof Email ? $original->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString() : null;
            $record->update(['state' => 'accepted', 'provider_email_id' => $providerId, 'accepted_at' => now(), 'lease_until' => null, 'reason' => null, 'available_at' => null]);
            EmailDeliveryLog::write('provider_accepted', [...$context, 'recipient_count' => $recipients, 'provider_email_id' => $providerId]);
        } catch (Throwable $error) {
            if ($accepted) {
                // A ledger/log failure after acceptance must not retry a submitted email.
                EmailDeliveryLog::write('acceptance_record_failed', $context, 'warning');

                return;
            }
            $reason = $isResend ? $resend->rejection($error) : null;
            if ($reason) {
                $wait = $resend->pause($reason);
                $ledger->defer($record, $reason, $wait['seconds']);
                EmailDeliveryLog::write('provider_quota_deferred', [...$context, 'reason' => $reason, 'retry_after_seconds' => $wait['seconds']], 'warning');
                $this->release($wait['seconds'] + random_int(1, 30));

                return;
            }
            $backoffs = $this->backoff();
            $delay = $this->email->getHeaders()->has('X-ZionFood-Error-Alert') ? 3600 : $backoffs[min(max(0, ($this->job ? $this->attempts() : 1) - 1), count($backoffs) - 1)];
            $ledger->defer($record, 'transport_failure', $delay);
            EmailDeliveryLog::write('attempt_failed', [...$context, ...EmailDeliveryLog::failure($error)], 'error');
            if ($this->email->getHeaders()->has('X-ZionFood-Error-Alert')) {
                Log::warning('Error alert delivery failed; inspect provider and queue health.');
                $this->release(3600);

                return;
            }
            throw $error;
        } finally {
            $providerLock?->release();
        }
    }

    public function failed(?Throwable $error): void
    {
        EmailDelivery::where('public_id', $this->deliveryId)->whereNotIn('state', EmailLedger::TERMINAL)->update(['state' => 'failed', 'reason' => 'queue_failed', 'lease_until' => null]);
        EmailDeliveryLog::write('permanently_failed', ['delivery_id' => $this->deliveryId, 'mailer' => $this->mailer, ...($error ? EmailDeliveryLog::failure($error) : [])], 'error');
    }
}
