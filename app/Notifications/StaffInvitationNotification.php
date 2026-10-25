<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $invitationId, public string $token, public int $organizationId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invitation = StaffInvitation::where('public_id', $this->invitationId)->where('organization_id', $this->organizationId)->first();

        return $invitation && ! $invitation->revoked_at && ! $invitation->accepted_at && $invitation->expires_at->isFuture() && hash_equals($invitation->token_hash, hash('sha256', $this->token));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('Invitation to ZionFood'))->line(__('You have been invited to join :organization.', ['organization' => Organization::findOrFail($this->organizationId)->name]))->line(__('Sign in with the invited email address. This invitation expires in seven days.'))->action(__('Accept invitation'), rtrim(config('app.url'), '/').'/invitations/'.$this->invitationId.'#token='.$this->token);
    }
}
