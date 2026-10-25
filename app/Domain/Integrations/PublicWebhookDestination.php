<?php

namespace App\Domain\Integrations;

use Symfony\Component\HttpFoundation\IpUtils;

class PublicWebhookDestination
{
    public function __construct(private PublicDnsResolver $dns) {}

    public function resolve(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || ! preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,63}$/', $host) || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/\.(?:localhost|local|internal|test|lan|invalid)$/', $host)) {
            throw new UnsafeWebhookDestination('Invalid HTTPS destination.');
        }
        $addresses = $this->dns->addresses($host);
        if (! $addresses) {
            throw new UnsafeWebhookDestination('Destination has no public addresses.');
        }
        $blocked = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '2001:db8::/32', '2001::/23', '2002::/16', '3fff::/20'];
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || IpUtils::checkIp($ip, $blocked) || (str_contains($ip, ':') && ! IpUtils::checkIp($ip, '2000::/3'))) {
                throw new UnsafeWebhookDestination('Destination address is not globally routable.');
            }
        }

        return ['host' => $host, 'ip' => $addresses[0]];
    }
}
