<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

class ReservationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public int $reservationId, public int $version)
    {
        $this->afterCommit();
        $this->locale(Reservation::whereKey($reservationId)->value('locale') ?? 'ro');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $booking = Reservation::find($this->reservationId);

        return $booking && $booking->version === $this->version && ! ($booking->status === 'pending' && $booking->hold_expires_at?->isPast());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = Reservation::with('location.restaurant')->findOrFail($this->reservationId);

        return (new MailMessage)->subject(__('Reservation update').' — '.$booking->location->restaurant->name)->line(__('Reservation status').': '.__('reservation_status_'.$booking->status))->line($booking->starts_at->setTimezone($booking->timezone)->format('d.m.Y H:i').' · '.$booking->party_size.' '.__('guests'))->action(__('View reservation'), rtrim(config('app.url'), '/').'/reservations/'.$booking->public_id.'#token='.Crypt::decryptString($booking->token_encrypted));
    }
}
