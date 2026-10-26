<?php

namespace App\Domain\Restaurants;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorefrontShareImage
{
    public function path(Restaurant $restaurant, string $logo): string
    {
        return 'branding/'.$restaurant->organization_id.'/'.$restaurant->public_id.'/shares/'.hash('sha256', 'square-v1|'.$logo).'.png';
    }

    public function url(Restaurant $restaurant): ?string
    {
        if (! $restaurant->logo_path) {
            return null;
        }
        $disk = Storage::disk('public');
        $path = $this->path($restaurant, $restaurant->logo_path);
        try {
            if ($disk->exists($path)) {
                return url($disk->url($path));
            }
            if (! $disk->exists($restaurant->logo_path) || $disk->size($restaurant->logo_path) > 4194304) {
                return null;
            }
            $bytes = $disk->get($restaurant->logo_path);
            $size = getimagesizefromstring($bytes);
            if (! $size || $size[0] > 3000 || $size[1] > 3000 || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }
            $source = imagecreatefromstring($bytes);
            $canvas = imagecreatetruecolor(600, 600);
            try {
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                // Contain the entire logo within a square safe area; never stretch or crop its lettering.
                $scale = min(480 / $size[0], 480 / $size[1]);
                $width = max(1, (int) round($size[0] * $scale));
                $height = max(1, (int) round($size[1] * $scale));
                imagecopyresampled($canvas, $source, (int) ((600 - $width) / 2), (int) ((600 - $height) / 2), 0, 0, $width, $height, $size[0], $size[1]);
                ob_start();
                try {
                    imagepng($canvas);
                    $png = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                if (! $disk->put($path, $png)) {
                    return null;
                }
            } finally {
                imagedestroy($source);
                imagedestroy($canvas);
            }

            return url($disk->url($path));
        } catch (\Throwable $e) {
            Log::warning('Storefront share image unavailable.', ['exception_class' => $e::class]);

            return null;
        }
    }
}
