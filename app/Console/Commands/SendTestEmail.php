<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestEmail extends Command
{
    protected $signature = 'zionfood:mail-test {email : Recipient email address}';

    protected $description = 'Send a test email using the configured default mailer';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Provide a valid recipient email address.');

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->error('Select a delivery mailer first (MAIL_MAILER=resend). The log and array mailers do not deliver email.');

            return self::FAILURE;
        }

        if ($mailer === 'resend' && blank(config('services.resend.key'))) {
            $this->error('Configure RESEND_API_KEY before sending.');

            return self::FAILURE;
        }

        try {
            Mail::raw('Acesta este un email de test ZionFood. / This is a ZionFood test email.', function ($message) use ($email) {
                $message->to($email)->subject('ZionFood — Email de test / Test email');
            });
        } catch (Throwable) {
            $this->error('Email submission failed. Check the mailer configuration, verified sender domain and provider dashboard.');

            return self::FAILURE;
        }

        $this->info('Email submitted to the configured mailer. Confirm delivery in the provider dashboard and recipient inbox.');

        return self::SUCCESS;
    }
}
