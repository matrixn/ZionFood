<?php

return [
    'limit' => 140,
    'window_seconds' => 86400,
    'cache_store' => env('MAIL_RATE_LIMIT_STORE', 'redis'),
    'queue_connection' => env('MAIL_QUEUE_CONNECTION', 'redis'),
    'admin_email' => env('ADMIN_EMAIL'),
    'alerts_enabled' => (bool) env('MAIL_ERROR_ALERTS', true),
    'alert_dedup_seconds' => 600,
];
