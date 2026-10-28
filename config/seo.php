<?php

return [
    'public_url' => env('ZF_PUBLIC_URL', env('APP_URL', 'http://localhost')),
    'indexable' => (bool) env('ZF_SEO_INDEXABLE', true),
    'prerender_path' => public_path('build/seo'),
];
