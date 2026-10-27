<?php

return ['default' => env('BROADCAST_CONNECTION', 'log'), 'connections' => [
    'reverb' => ['driver' => 'reverb', 'key' => env('REVERB_APP_KEY'), 'secret' => env('REVERB_APP_SECRET'), 'app_id' => env('REVERB_APP_ID'), 'options' => ['host' => env('REVERB_PUBLISH_HOST', env('REVERB_HOST', 'localhost')), 'port' => env('REVERB_PUBLISH_PORT', env('REVERB_PORT', 8080)), 'scheme' => env('REVERB_PUBLISH_SCHEME', env('REVERB_SCHEME', 'http')), 'useTLS' => env('REVERB_PUBLISH_SCHEME', env('REVERB_SCHEME', 'http')) === 'https']],
    'log' => ['driver' => 'log'], 'null' => ['driver' => 'null'],
]];
