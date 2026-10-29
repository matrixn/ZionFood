<?php

return [
    'cache_store' => env('REGISTRATION_CAPTCHA_CACHE_STORE', env('MAIL_RATE_LIMIT_STORE', 'redis')),
    'ttl_seconds' => 300,
    'max_outstanding' => 3,
];
