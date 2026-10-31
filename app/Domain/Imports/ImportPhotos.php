<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Storage;

class ImportPhotos
{
    public function stage(ImportBatch $batch, string $key, string $bytes, string $url): array
    {
        $size = @getimagesizefromstring($bytes);
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $size[0] * $size[1] > 8000000 || $size[0] > 8192 || $size[1] > 8192) {
            throw new \RuntimeException('public_photo_dimensions');
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw new \RuntimeException('public_photo_decode');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        try {
            if (! imagewebp($image, null, 85)) {
                throw new \RuntimeException('public_photo_encode');
            }
            $encoded = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        if (! $encoded || strlen($encoded) > 3145728) {
            throw new \RuntimeException('public_photo_limit');
        }
        $hash = hash('sha256', $encoded);
        $path = 'imports/'.$batch->public_id.'/photos/'.$key.'-'.$hash.'.webp';
        if (! Storage::disk('local')->put($path, $encoded)) {
            throw new \RuntimeException('public_photo_storage');
        }

        return ['path' => $path, 'sha256' => $hash, 'width' => $size[0], 'height' => $size[1], 'source_url' => $url];
    }

    public function bytes(ImportBatch $batch, string $key, array $photo): string
    {
        $hash = $photo['sha256'] ?? '';
        if ((! ctype_digit($key) && $key !== 'logo') || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || ($photo['path'] ?? '') !== 'imports/'.$batch->public_id.'/photos/'.$key.'-'.$hash.'.webp') {
            throw new \RuntimeException('public_photo_path');
        }
        $bytes = Storage::disk('local')->get($photo['path']);
        if (! is_string($bytes) || ! hash_equals($hash, hash('sha256', $bytes))) {
            throw new \RuntimeException('public_photo_checksum');
        }

        return $bytes;
    }

    public function publicPath(ImportBatch $batch, string $key, array $photo): string
    {
        $prefix = $key === 'logo' ? 'branding/'.$batch->organization_id.'/'.RestaurantLocation::where('organization_id', $batch->organization_id)->findOrFail($batch->location_id)->restaurant->public_id : 'menu/'.$batch->organization_id;

        return $prefix.'/imports/'.$batch->public_id.'/'.$key.'-'.$photo['sha256'].'.webp';
    }

    public function publish(ImportBatch $batch, string $key, array $photo, array &$created): string
    {
        $bytes = $this->bytes($batch, $key, $photo);
        $path = $this->publicPath($batch, $key, $photo);
        if (! Storage::disk('public')->exists($path)) {
            if (! Storage::disk('public')->put($path, $bytes)) {
                throw new \RuntimeException('public_photo_publication');
            }
            $created[] = $path;
        }

        return $path;
    }
}
