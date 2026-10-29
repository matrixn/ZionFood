<?php

namespace App\Domain\Marketing\Actions;

class BuildRobotsTxt
{
    public function execute(bool $crawlable, ?string $sitemap = null): string
    {
        if (! $crawlable) {
            return "User-agent: *\nDisallow: /\n";
        }

        $text = rtrim(file_get_contents(resource_path('seo/robots.txt')))."\n";

        return $sitemap ? $text."\nSitemap: ".$sitemap."\n" : $text;
    }
}
