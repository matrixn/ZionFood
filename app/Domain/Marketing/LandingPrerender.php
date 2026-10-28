<?php

namespace App\Domain\Marketing;

class LandingPrerender
{
    public function markup(string $locale, bool $signedIn): ?string
    {
        $path = config('seo.prerender_path');
        $manifest = is_file($path.'/manifest.json') ? json_decode(file_get_contents($path.'/manifest.json'), true) : null;
        if (($manifest['version'] ?? null) !== config('zionfood.version')) {
            return null;
        }
        $file = $path.'/'.($locale === 'en' ? 'en' : 'ro').'-'.($signedIn ? 'member' : 'guest').'.html';

        return is_file($file) ? file_get_contents($file) : null;
    }
}
