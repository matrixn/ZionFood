<?php

// Deterministic encoding only: preserve the existing AI artwork and its provenance.
foreach (['restaurant-table', 'kitchen-service'] as $name) {
    $root = dirname(__DIR__).'/public/images/marketing/';
    $source = imagecreatefrompng($root.$name.'-ai.png');
    foreach ([640, 960, 1440] as $width) {
        $height = (int) round(imagesy($source) * $width / imagesx($source));
        $target = imagescale($source, $width, $height, IMG_BICUBIC_FIXED);
        imagewebp($target, $root.$name.'-'.$width.'.webp', 82);
        imagedestroy($target);
    }
    imagedestroy($source);
}
