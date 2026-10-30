<?php

return [
    'api_url' => rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/api/v1/wordpress',
    'rate_limit' => 120,
    'log_retention_days' => 30,
    'response_retention_days' => 7,
    'request_retention_days' => 90,
];
