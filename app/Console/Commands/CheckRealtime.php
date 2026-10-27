<?php

namespace App\Console\Commands;

use App\Domain\Integrations\PublicDnsResolver;
use Illuminate\Console\Command;
use Throwable;

class CheckRealtime extends Command
{
    protected $signature = 'zionfood:realtime-check';

    protected $description = 'Check configured Reverb endpoint DNS without publishing an event or exposing credentials';

    public function handle(PublicDnsResolver $dns): int
    {
        if (config('broadcasting.default') !== 'reverb') {
            $this->error('Reverb is not the selected broadcaster. Check BROADCAST_CONNECTION.');

            return self::FAILURE;
        }
        $options = config('broadcasting.connections.reverb.options', []);
        $ok = $this->checkHost('Publisher (REVERB_PUBLISH_HOST or REVERB_HOST)', $options['host'] ?? '', $dns);
        $port = (int) ($options['port'] ?? 0);
        $scheme = $options['scheme'] ?? '';
        if (! in_array($scheme, ['http', 'https'], true) || $port < 1 || $port > 65535) {
            $this->error('Invalid publisher port or scheme.');
            $ok = false;
        } else {
            $this->line('Publisher port: '.$port.'; scheme: '.$scheme);
        }
        $public = $this->checkHost('Public Reverb host (REVERB_HOST)', config('reverb.apps.apps.0.options.host', ''), $dns);
        foreach (['app_id' => 'REVERB_APP_ID', 'key' => 'REVERB_APP_KEY', 'secret' => 'REVERB_APP_SECRET'] as $key => $name) {
            if (! filled(config('broadcasting.connections.reverb.'.$key))) {
                $this->error('Missing '.$name.'.');
                $ok = false;
            }
        }
        $this->comment('DNS/configuration check only: listener, TLS, matching credentials and browser VITE overrides are not verified. No event was published.');

        return $ok && $public ? self::SUCCESS : self::FAILURE;
    }

    private function checkHost(string $label, ?string $host, PublicDnsResolver $dns): bool
    {
        $host ??= '';
        // Reject URL/userinfo/query syntax before printing a misconfigured value.
        if (! filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $this->error($label.': invalid or missing hostname.');

            return false;
        }
        try {
            $resolved = filter_var($host, FILTER_VALIDATE_IP) || strtolower($host) === 'localhost' || count($dns->addresses($host)) > 0;
        } catch (Throwable) {
            $resolved = false;
        }
        if (! $resolved) {
            $this->error($label.': '.$host.' — DNS lookup failed.');

            return false;
        }
        $this->info($label.': '.$host.' — resolves.');

        return true;
    }
}
