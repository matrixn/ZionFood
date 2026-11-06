<?php

return [
    // Explicit IPs/CIDRs only. Never trust arbitrary forwarded headers from direct visitors.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', env('ZF_TRUSTED_PROXIES', ''))), function ($range) {
        $parts = explode('/', $range);
        if (! filter_var($parts[0], FILTER_VALIDATE_IP) || count($parts) > 2) {
            return false;
        }

        return count($parts) === 1 || (ctype_digit($parts[1]) && (int) $parts[1] > 0 && (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32));
    })),
];
