<?php

namespace App\Jobs;

use App\Domain\Mail\EmailLedger;
use App\Models\EmailDelivery;
use App\Models\PlatformActivityEmail;
use App\Models\Restaurant;
use App\Models\ServiceLead;
use App\Models\User;
use App\Notifications\PlatformActivityNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class QueuePlatformActivityEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $activityId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $notice = PlatformActivityEmail::whereKey($this->activityId)->lockForUpdate()->first();
            if (! $notice || ($notice->queued_at && $notice->queued_at->gt(now()->subMinutes(5)))) {
                return;
            }
            $delivery = EmailDelivery::where('public_id', $notice->public_id)->first();
            if (! $delivery || in_array($delivery->state, EmailLedger::TERMINAL, true)) {
                $notice->update(['queued_at' => now(), 'details' => null]);

                return;
            }
            $recipient = User::find($notice->recipient_id);
            $model = match ($notice->subject_type) {
                'lead' => ServiceLead::class, 'user' => User::class, 'restaurant' => Restaurant::class,
            };
            if (! $recipient?->is_platform_admin || ! $recipient->hasVerifiedEmail() || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL) || ! $model::whereKey($notice->subject_id)->exists()) {
                $delivery->update(['state' => 'suppressed', 'reason' => 'platform_activity_unavailable']);
            } elseif ($delivery->expires_at?->isPast()) {
                $delivery->update(['state' => 'expired', 'reason' => 'platform_activity_expired']);
            } else {
                $notification = new PlatformActivityNotification($notice);
                $notification->id = $notice->public_id;
                $recipient->notify($notification);
            }
            $notice->update(['queued_at' => now(), 'details' => in_array($delivery->fresh()->state, EmailLedger::TERMINAL, true) ? null : $notice->details]);
        });
    }
}
