<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class TestEmailController extends Controller
{
    public function show()
    {
        return Inertia::render('admin/TestEmail', ['configured' => (bool) filter_var(config('email_delivery.admin_email'), FILTER_VALIDATE_EMAIL)]);
    }

    public function send()
    {
        if (! filter_var(config('email_delivery.admin_email'), FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => __('Configure a valid ADMIN_EMAIL in the environment.')]);
        }
        if (in_array(config('mail.default'), ['array', 'log'], true) ||
            (config('mail.default') === 'resend' && blank(config('services.resend.key')))) {
            throw ValidationException::withMessages(['email' => __('Configure a delivery mailer and its credentials first.')]);
        }
        try {
            Mail::raw(__('ZionFood test email. The configured mail service is working.'), function ($message) {
                $message->to(config('email_delivery.admin_email'))->subject('ZionFood — Email de test / Test email');
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-ZionFood-Mail-Source', 'email_test');
            });
        } catch (Throwable) {
            throw ValidationException::withMessages(['email' => __('Email could not be queued. Check delivery configuration and queue health.')]);
        }

        return back()->with('success', __('Test email queued for the administrator. Check the recipient inbox after processing.'));
    }
}
