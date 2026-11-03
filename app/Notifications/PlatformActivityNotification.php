<?php

namespace App\Notifications;

use App\Models\PlatformActivityEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformActivityNotification extends Notification
{
    // Rendering runs inside QueuePlatformActivityEmail. Delivery uses the existing quota-controlled queue.
    public function __construct(public PlatformActivityEmail $activity) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = __('platform_activity.'.$this->activity->source);

        return (new MailMessage)->subject('ZionFood — '.$title)->view([
            'html' => 'emails.platform-activity', 'text' => 'emails.platform-activity-text',
        ], [
            'title' => $title, 'fields' => $this->activity->details['fields'],
            'occurredAt' => $this->activity->created_at->timezone('Europe/Bucharest')->format('d.m.Y H:i'),
            'activityReference' => $this->activity->public_id,
            'adminUrl' => rtrim(config('app.url'), '/').$this->activity->details['path'],
        ])->withSymfonyMessage(fn ($email) => $email->getHeaders()->addTextHeader('X-ZionFood-Mail-Source', $this->activity->source));
    }
}
