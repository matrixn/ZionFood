<?php

namespace App\Domain\Mail;

use App\Notifications\ReservationNotification;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Symfony\Component\Mime\Email;

class MailSource
{
    public const SOURCES = ['system_error', 'account_registration', 'account_verification', 'password_reset', 'staff_invitation', 'reservation', 'email_test', 'other', 'legacy'];

    public function notification(string $class): string
    {
        return match ($class) {
            VerifyEmail::class => request()->route() && request()->is('register') ? 'account_registration' : 'account_verification',
            ResetPassword::class => 'password_reset',
            StaffInvitationNotification::class => 'staff_invitation',
            ReservationNotification::class => 'reservation',
            default => 'other',
        };
    }

    public function message(Email $email, array $data, bool $legacy = false): string
    {
        if ($email->getHeaders()->has('X-ZionFood-Error-Alert')) {
            return 'system_error';
        }
        if ($email->getHeaders()->get('X-ZionFood-Mail-Source')?->getBodyAsString() === 'email_test') {
            return 'email_test';
        }

        return isset($data['__laravel_notification']) ? $this->notification($data['__laravel_notification']) : ($legacy ? 'legacy' : 'other');
    }

    public function initiator(string $source, bool $legacy = false): array
    {
        if ($source === 'system_error') {
            return ['initiator_kind' => 'system', 'initiator_id' => null];
        }
        if ($legacy) {
            return ['initiator_kind' => 'unknown', 'initiator_id' => null];
        }
        $http = request()->route() !== null;
        $user = $http ? auth()->user() : null;

        return ['initiator_kind' => $user ? 'user' : ($legacy ? 'unknown' : ($http ? 'guest' : 'system')), 'initiator_id' => $user?->id];
    }
}
