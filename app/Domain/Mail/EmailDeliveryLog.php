<?php

namespace App\Domain\Mail;

use Illuminate\Support\Facades\Log;
use Resend\Exceptions\ErrorException;
use Throwable;

class EmailDeliveryLog
{
    public static function write(string $stage, array $context = [], string $level = 'info'): void
    {
        $context = ['mail_delivery' => true, 'stage' => $stage, ...$context];
        try {
            Log::channel('mail_delivery')->log($level, 'ZionFood email '.$stage, $context);
        } catch (Throwable) {
            // Logging must not turn an accepted provider submission into a retry.
            error_log('ZionFood email '.$stage.' '.json_encode($context));
        }
    }

    public static function failure(Throwable $error): array
    {
        $details = ['exception_class' => get_class($error), 'reason' => 'transport_or_infrastructure_failure', 'hint' => 'Check queue, Redis, DNS/TLS and provider configuration.'];
        for ($i = 0; $error && $i < 8; $i++, $error = $error->getPrevious()) {
            if ($error instanceof ErrorException) {
                $status = $error->getErrorCode();
                $details['http_status'] = $status;
                $details['reason'] = 'provider_rejected';
                $details['hint'] = match ($status) {
                    401 => 'Check RESEND_API_KEY and rebuild the configuration cache.',
                    403 => 'Check the verified sender domain, API key domain permissions and Resend test-recipient restrictions.',
                    400, 422 => 'Check sender, recipient and message format in the Resend dashboard.',
                    429 => 'Provider rate limit reached; delivery will retry.',
                    default => 'Check the Resend dashboard and provider status.',
                };
                break;
            }
        }

        return $details;
    }
}
