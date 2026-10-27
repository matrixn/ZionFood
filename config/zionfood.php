<?php

return [
    'version' => '0.0.17',
    'checkout_ip_limit' => (int) env('ZF_CHECKOUT_IP_LIMIT', 10),
    'app_hosts' => array_filter(explode(',', env('ZF_APP_HOSTS', 'localhost,127.0.0.1,app.zionfood.ro'))),
    'api_hosts' => array_filter(explode(',', env('ZF_API_HOSTS', 'localhost,127.0.0.1,api.zionfood.ro'))),
    'storefront_base_domain' => env('ZF_STOREFRONT_DOMAIN', 'zionfood.ro'),
    'reserved_slugs' => ['app', 'api', 'www', 'admin', 'mail', 'support', 'status', 'kitchen'],
];
