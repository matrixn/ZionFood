<?php

// Run once in a fresh checkout. Existing environments are never overwritten.
$root = dirname(__DIR__);
if (file_exists($root.'/.env')) {
    fwrite(STDERR, ".env already exists; keep it or configure it manually.\n");
    exit(1);
}
$env = file_get_contents($root.'/.env.example');
$values = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_PASSWORD' => bin2hex(random_bytes(24)), 'DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)), 'REVERB_APP_KEY' => bin2hex(random_bytes(16)), 'REVERB_APP_SECRET' => bin2hex(random_bytes(32))];
foreach ($values as $key => $value) {
    $env = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $key.'='.$value, $env);
}
file_put_contents($root.'/.env', $env);
chmod($root.'/.env', 0600);
echo "Created local .env with random credentials. No credentials were printed.\n";
