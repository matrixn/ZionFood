<?php

namespace App\Domain\Mail;

use App\Models\EmailDelivery;
use App\Models\Reservation;
use App\Models\StaffInvitation;
use App\Notifications\ReservationNotification;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class EmailLedger
{
    public const TERMINAL = ['accepted', 'failed', 'suppressed', 'expired', 'superseded', 'local'];

    public function hashRecipient(string $address): string
    {
        return hash_hmac('sha256', strtolower(trim($address)), config('app.key'));
    }

    private function recipients(Email $email): array
    {
        return ['to' => array_map(fn ($a) => $a->getAddress(), $email->getTo()), 'cc' => array_map(fn ($a) => $a->getAddress(), $email->getCc()), 'bcc' => array_map(fn ($a) => $a->getAddress(), $email->getBcc())];
    }

    public function queuedNotification(SendQueuedNotifications $job, bool $legacy = false): void
    {
        if (! in_array('mail', $job->channels ?? [], true) || ! $job->notification->id) {
            return;
        }
        $to = [];
        foreach ($job->notifiables as $notifiable) {
            $route = $notifiable->routeNotificationFor('mail', $job->notification);
            $to = [...$to, ...(is_array($route) ? array_map(fn ($k, $v) => is_numeric($k) ? $v : $k, array_keys($route), $route) : [$route])];
        }
        $to = array_values(array_filter($to, fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)));
        if (! $to) {
            return;
        }
        $source = app(MailSource::class)->notification(get_class($job->notification));
        $context = [];
        $expires = null;
        if ($job->notification instanceof ReservationNotification) {
            $context = ['reservation_id' => $job->notification->reservationId, 'version' => $job->notification->version];
        }
        if ($job->notification instanceof StaffInvitationNotification) {
            $context = ['invitation_id' => $job->notification->invitationId, 'organization_id' => $job->notification->organizationId];
            $expires = StaffInvitation::where('public_id', $context['invitation_id'])->where('organization_id', $context['organization_id'])->value('expires_at');
        }
        EmailDelivery::firstOrCreate(['notification_id' => $job->notification->id], ['public_id' => (string) Str::ulid(), 'source' => $source, 'state' => 'notification_queued', 'recipients' => ['to' => $to, 'cc' => [], 'bcc' => []], 'recipient_hashes' => array_map($this->hashRecipient(...), $to), 'context' => $context, 'expires_at' => $expires, ...app(MailSource::class)->initiator($source, $legacy)]);
    }

    public function message(string $id, Email $email, string $mailer, array $data = [], bool $legacy = false): EmailDelivery
    {
        $recipients = $this->recipients($email);
        $notification = $data['__laravel_notification_id'] ?? null;
        $existing = $notification ? EmailDelivery::where('notification_id', $notification)->first() : EmailDelivery::where('public_id', $id)->first();
        $source = $existing?->source ?? app(MailSource::class)->message($email, $data, $legacy);
        $expires = in_array($source, ['account_registration', 'account_verification', 'password_reset'], true) ? now()->addMinutes($source === 'password_reset' ? config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60) : config('auth.verification.expire', 60)) : null;
        $values = ['public_id' => $existing?->public_id ?? $id, 'notification_id' => $notification, 'source' => $source, 'state' => 'queued', 'mailer' => $mailer, 'recipients' => $recipients, 'recipient_hashes' => array_map($this->hashRecipient(...), array_merge(...array_values($recipients))), 'subject' => $email->getSubject(), 'expires_at' => $existing?->expires_at ?? $expires, ...app(MailSource::class)->initiator($source, $legacy)];
        if ($existing) {
            // A queue indexer must never overwrite a live/terminal delivery or its original initiator.
            if ($existing->state === 'notification_queued') {
                unset($values['initiator_id'], $values['initiator_kind']);
                $existing->fill($values)->save();
            }

            return $existing;
        }

        return EmailDelivery::firstOrCreate(['public_id' => $id], $values);
    }

    public function claim(EmailDelivery $context): string
    {
        return DB::transaction(function () use ($context) {
            $record = EmailDelivery::whereKey($context->id)->lockForUpdate()->firstOrFail();
            if (in_array($record->state, self::TERMINAL, true)) {
                return 'skip';
            }
            if ($record->lease_until?->isFuture()) {
                return 'busy';
            }
            if ($record->expires_at?->isPast()) {
                $record->update(['state' => 'expired', 'reason' => 'link_expired', 'available_at' => null]);

                return 'skip';
            }
            $data = $record->context ?? [];
            if (isset($data['reservation_id'])) {
                $booking = Reservation::find($data['reservation_id']);
                if (! $booking || $booking->version !== $data['version'] || ($booking->status === 'pending' && $booking->hold_expires_at?->isPast())) {
                    $record->update(['state' => 'superseded', 'reason' => 'notification_outdated']);

                    return 'skip';
                }
            }
            if (isset($data['invitation_id'])) {
                $invitation = StaffInvitation::where('public_id', $data['invitation_id'])->where('organization_id', $data['organization_id'])->first();
                if (! $invitation || $invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast()) {
                    $record->update(['state' => 'superseded', 'reason' => 'notification_outdated']);

                    return 'skip';
                }
            }
            $record->update(['state' => 'processing', 'lease_until' => now()->addSeconds(120), 'available_at' => null]);

            return 'claimed';
        });
    }

    public function defer(EmailDelivery $record, string $reason, int $seconds): void
    {
        $record->update(['state' => 'deferred', 'reason' => $reason, 'lease_until' => null, 'available_at' => now()->addSeconds($seconds)]);
    }

    public function notificationFinished(?string $id, string $state): void
    {
        try {
            if ($id) {
                EmailDelivery::where('notification_id', $id)->where('state', 'notification_queued')->update(['state' => $state, 'reason' => $state === 'failed' ? 'notification_failed' : 'notification_outdated']);
            }
        } catch (\Throwable) {
            EmailDeliveryLog::write('notification_index_failed', [], 'warning');
        }
    }
}
