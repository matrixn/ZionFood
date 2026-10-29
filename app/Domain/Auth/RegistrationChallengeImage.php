<?php

namespace App\Domain\Auth;

class RegistrationChallengeImage
{
    public function render(string $code): string
    {
        $image = imagecreatetruecolor(640, 168);
        $paper = imagecolorallocate($image, 255, 250, 240);
        imagefill($image, 0, 0, $paper);
        $ink = imagecolorallocate($image, 35, 40, 64);
        $line = imagecolorallocate($image, 226, 220, 207);
        imagestring($image, 3, 24, 12, 'ZIONFOOD', $ink);
        imageline($image, 24, 37, 616, 37, $line);
        foreach (str_split($code) as $index => $digit) {
            $glyph = imagecreatetruecolor(16, 22);
            imagefill($glyph, 0, 0, imagecolorallocate($glyph, 255, 250, 240));
            imagestring($glyph, 5, 3, 3, $digit, imagecolorallocate($glyph, 35, 40, 64));
            imagecopyresampled($image, $glyph, 64 + $index * 145, 51 + random_int(-3, 3), 0, 0, 64, 88, 16, 22);
            imagedestroy($glyph);
            if ($index < 3) {
                imageline($image, 172 + $index * 145, 55, 172 + $index * 145, 135, $line);
            }
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
