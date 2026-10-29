<?php

use App\Domain\Marketing\LandingPrerender;
use Illuminate\Support\Str;

it('links landing styles in the initial head and bounds decorative graphics without hiding readable content', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    $dom = new DOMXPath($doc);
    expect($dom->query('//head/link[@data-landing-style and @rel="stylesheet"]')->length)->toBeGreaterThan(0)
        ->and($dom->query('//head/style[@id="landing-first-paint"]'))->toHaveCount(1)
        ->and($dom->query('//h1'))->toHaveCount(1);
    $art = $dom->query('//*[local-name()="svg" and @data-decoration]');
    expect($art->length)->toBeGreaterThan(0);
    foreach ($art as $svg) {
        expect((int) $svg->getAttribute('width'))->toBeGreaterThan(0)
            ->and((int) $svg->getAttribute('height'))->toBeGreaterThan(0)
            ->and(preg_replace('/\s+/', '', $svg->getAttribute('style')))->toContain('visibility:hidden');
    }
    expect($dom->query('//div[@id="app"]')->item(0)->getAttribute('style'))->not->toContain('display: none', 'visibility: hidden');
});

it('accepts only current build-owned CSS paths for the initial landing styles', function () {
    $directory = storage_path('framework/testing/first-paint-'.Str::uuid());
    mkdir($directory, 0777, true);
    config(['seo.prerender_path' => $directory]);
    try {
        file_put_contents($directory.'/manifest.json', json_encode(['version' => config('zionfood.version'), 'styles' => ['assets/Welcome-abc.css', 'https://foreign.example/style.css', '../private.css', 'assets/../private.css', 123]]));
        expect(app(LandingPrerender::class)->styles())->toBe(['assets/Welcome-abc.css']);
        file_put_contents($directory.'/manifest.json', json_encode(['version' => 'older-release', 'styles' => ['assets/Welcome-abc.css']]));
        expect(app(LandingPrerender::class)->styles())->toBe([]);
    } finally {
        unlink($directory.'/manifest.json');
        rmdir($directory);
    }
});
