<?php

use Illuminate\Notifications\Messages\MailMessage;

it('serves the supplied square logo sizes through the application icon links', function () {
    foreach ([16, 20, 24, 32, 40, 48, 64, 96, 128, 256] as $size) {
        $image = getimagesize(public_path("images/logo/food.zion3d.ro_icons-{$size}x{$size}.png"));
        expect([$image[0], $image[1], $image['mime']])->toBe([$size, $size, 'image/png']);
    }
    $html = $this->get('/login')->assertOk()->getContent();
    expect($html)->toContain('rel="apple-touch-icon"', 'sizes="16x16"', 'sizes="32x32"', '/images/logo/food.zion3d.ro_icons-256x256.png')->not->toContain('/favicon.svg');
});

it('renders the platform logo in notification email headers without sending mail', function () {
    config(['app.url' => 'https://food.zion3d.ro', 'app.name' => 'ZionFood']);
    $html = (string) (new MailMessage)->line('Logo preview')->render();
    expect($html)->toContain('https://food.zion3d.ro/images/logo/food.zion3d.ro_icons-128x128.png', 'width="64"', 'height="64"', 'ZionFood')->not->toContain('laravel.com/img/notification-logo');
});
