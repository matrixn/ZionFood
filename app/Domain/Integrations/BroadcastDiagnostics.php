<?php

namespace App\Domain\Integrations;

use Throwable;

class BroadcastDiagnostics
{
    public function failure(Throwable $error): array
    {
        $type = get_class($error);
        $reason = 'broadcast_failed';
        $hint = 'Inspect the broadcast worker and Reverb application configuration.';
        for ($i = 0; $error && $i < 8; $i++, $error = $error->getPrevious()) {
            // Inspect locally; never return raw messages, request URLs, credentials or payloads.
            $text = strtolower($error->getMessage());
            if (str_contains($text, 'curl error 7') || str_contains($text, 'connection refused')) {
                $reason = 'connection_refused';
                $hint = 'Check the Reverb process, internal publish host and port, and firewall.';
            } elseif (preg_match('/curl error 6\b/', $text) || str_contains($text, 'could not resolve')) {
                $reason = 'dns_failure';
                $hint = 'Check the broadcast publish hostname and server DNS.';
            } elseif (str_contains($text, 'curl error 60') || str_contains($text, 'certificate')) {
                $reason = 'tls_verification_failed';
                $hint = 'Check the publish scheme, hostname and trusted certificate chain; keep TLS verification enabled.';
            } elseif (str_contains($text, 'curl error 28') || str_contains($text, 'timed out')) {
                $reason = 'timeout';
                $hint = 'Check Reverb reachability, proxy routing and process health.';
            } elseif (str_contains($text, '401') || str_contains($text, '403') || str_contains($text, 'authentication')) {
                $reason = 'authentication_rejected';
                $hint = 'Check matching Reverb app ID, key and secret, and the cached configuration.';
            } elseif (str_contains($text, '404')) {
                $reason = 'endpoint_not_found';
                $hint = 'Check Reverb publish host, port, scheme and reverse-proxy routing.';
            }
        }

        return ['exception_class' => $type, 'reason' => $reason, 'hint' => $hint];
    }
}
