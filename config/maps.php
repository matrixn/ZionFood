<?php

return [
    'tile_url' => env('ZF_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('ZF_MAP_ATTRIBUTION', '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'),
];
