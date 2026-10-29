<?php

return [
    'limit' => 140,
    'resend_daily_limit' => (int) env('RESEND_DAILY_LIMIT', 100),
    'account_daily_limit' => (int) env('MAIL_ACCOUNT_DAILY_LIMIT', 30),
    'account_requests_per_hour' => (int) env('MAIL_ACCOUNT_REQUESTS_PER_HOUR', 10),
    'ledger_retention_days' => 90,
    'window_seconds' => 86400,
    'cache_store' => env('MAIL_RATE_LIMIT_STORE', 'redis'),
    'queue_connection' => env('MAIL_QUEUE_CONNECTION', 'redis'),
    'admin_email' => env('ADMIN_EMAIL'),
    'alerts_enabled' => (bool) env('MAIL_ERROR_ALERTS', true),
    'alert_dedup_seconds' => 600,
];
