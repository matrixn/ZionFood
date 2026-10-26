<?php

use App\Domain\Restaurants\Actions\UpdateBranding;
use App\Domain\Restaurants\StorefrontShareImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('contains the complete wide transparent logo in a white square without distorting it', function () {
    Storage::fake('public');
    [, $location] = tenantFixture('share-padding');
    $source = imagecreatetruecolor(120, 53);
    imagealphablending($source, false);
    imagesavealpha($source, true);
    imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
    imagefilledrectangle($source, 0, 1, 119, 51, imagecolorallocate($source, 255, 0, 0));
    imagefilledrectangle($source, 0, 1, 9, 51, imagecolorallocate($source, 0, 0, 255));
    imagefilledrectangle($source, 110, 1, 119, 51, imagecolorallocate($source, 0, 255, 0));
    ob_start();
    imagepng($source);
    $bytes = ob_get_clean();
    imagedestroy($source);
    Storage::disk('public')->put('branding/wide.png', $bytes);
    $restaurant = $location->restaurant;
    $restaurant->update(['logo_path' => 'branding/wide.png']);
    $service = app(StorefrontShareImage::class);
    $url = $service->url($restaurant);
    $path = $service->path($restaurant, $restaurant->logo_path);
    $png = Storage::disk('public')->get($path);
    expect($url)->toEndWith($path)->and(getimagesizefromstring($png))->toMatchArray([0 => 600, 1 => 600, 'mime' => 'image/png']);
    $image = imagecreatefromstring($png);
    expect(imagecolorat($image, 0, 0) & 0xFFFFFF)->toBe(0xFFFFFF)
        ->and(imagecolorat($image, 300, 190) & 0xFFFFFF)->toBe(0xFFFFFF)
        ->and(imagecolorat($image, 300, 410) & 0xFFFFFF)->toBe(0xFFFFFF)
        ->and(imagecolorat($image, 65, 300) & 0xFFFFFF)->toBe(0x0000FF)
        ->and(imagecolorat($image, 300, 300) & 0xFFFFFF)->toBe(0xFF0000)
        ->and(imagecolorat($image, 535, 300) & 0xFFFFFF)->toBe(0x00FF00);
    imagedestroy($image);
    expect(Storage::disk('public')->get('branding/wide.png'))->toBe($bytes);
    // Existing derivatives are reusable without decoding the source for each menu view.
    Storage::disk('public')->delete('branding/wide.png');
    expect($service->url($restaurant))->toBe($url);
});

it('changes the share url with a new upload and removes the old derivative when branding is removed', function () {
    Storage::fake('public');
    [$owner, $location] = tenantFixture('share-replace');
    $restaurant = $location->restaurant;
    foreach (['first', 'second'] as $name) {
        $file = UploadedFile::fake()->image($name.'.png', 53, 120);
        Storage::disk('public')->put('branding/'.$name.'.png', file_get_contents($file->getPathname()));
    }
    $service = app(StorefrontShareImage::class);
    $restaurant->update(['logo_path' => 'branding/first.png']);
    $first = $service->url($restaurant);
    $restaurant->update(['logo_path' => 'branding/second.png']);
    expect($service->url($restaurant))->not->toBe($first);
    $path = $service->path($restaurant, $restaurant->logo_path);
    app(UpdateBranding::class)->execute($owner, $restaurant, ['name' => $restaurant->name, 'slug' => $restaurant->slug, 'primary_color' => '#176943', 'remove_logo' => true]);
    Storage::disk('public')->assertMissing($path);
    expect($service->url($restaurant->fresh()))->toBeNull();
});

it('keeps the menu available when a logo is missing or corrupt', function () {
    Storage::fake('public');
    [, $location] = tenantFixture('share-invalid');
    $restaurant = $location->restaurant;
    $service = app(StorefrontShareImage::class);
    expect($service->url($restaurant))->toBeNull();
    $restaurant->update(['logo_path' => 'branding/missing.png']);
    expect($service->url($restaurant))->toBeNull();
    Storage::disk('public')->put('branding/missing.png', 'invalid image');
    expect($service->url($restaurant))->toBeNull();
    $this->get($location->storefront_path)->assertOk()->assertDontSee('property="og:image"', false);
});
