<?php

namespace App\Domain\Marketing;

class LandingSeo
{
    public function baseUrl(): string
    {
        $url = rtrim((string) config('seo.public_url'), '/');
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true) || ! in_array(strtolower($parts['host'] ?? ''), config('zionfood.app_hosts'), true)) {
            throw new \RuntimeException('ZF_PUBLIC_URL must be a root HTTP(S) URL on a configured central application host.');
        }

        return $url;
    }

    public function meta(string $locale, bool $signedIn = false): array
    {
        $locale = $locale === 'en' ? 'en' : 'ro';
        $strings = json_decode(file_get_contents(resource_path('js/i18n/'.$locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
        $base = $this->baseUrl();
        $alternates = ['ro' => $base.'/', 'en' => $base.'/en'];
        $host = strtolower(parse_url($base, PHP_URL_HOST));
        $public = parse_url($base, PHP_URL_SCHEME) === 'https' && $host !== 'localhost' && ! filter_var($host, FILTER_VALIDATE_IP) && ! preg_match('/\.(test|localhost|invalid)$/', $host);
        $ready = app(LandingPrerender::class)->markup($locale, $signedIn) !== null;
        $indexable = config('seo.indexable') && $public && $ready;
        $description = $strings['landing_meta_description'];
        $url = $alternates[$locale];
        $image = $base.'/images/marketing/restaurant-table-1440.webp';

        return ['title' => $strings['landing_meta_title'].' · ZionFood', 'description' => $description, 'url' => $url, 'home' => $alternates[$locale], 'alternates' => $alternates, 'locale' => $locale === 'en' ? 'en_GB' : 'ro_RO', 'image' => $image, 'image_alt' => $strings['landing_hero_image_alt'], 'robots' => $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, nofollow', 'ready' => $ready, 'schema' => ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'Organization', '@id' => $base.'/#organization', 'name' => 'ZionFood', 'url' => $base.'/', 'logo' => ['@type' => 'ImageObject', 'url' => $base.'/images/logo/food.zion3d.ro_icons-256x256.png', 'width' => 256, 'height' => 256]],
            ['@type' => 'WebSite', '@id' => $base.'/#website', 'name' => 'ZionFood', 'url' => $base.'/', 'inLanguage' => ['ro', 'en'], 'publisher' => ['@id' => $base.'/#organization']],
            ['@type' => 'WebPage', '@id' => $url.'#webpage', 'url' => $url, 'name' => $strings['landing_meta_title'], 'description' => $description, 'inLanguage' => $locale, 'isPartOf' => ['@id' => $base.'/#website'], 'about' => ['@type' => 'Service', 'name' => 'ZionFood', 'serviceType' => $strings['landing_seo_service'], 'provider' => ['@id' => $base.'/#organization']]],
        ]]];
    }
}
