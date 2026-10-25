<?php

// Give test uploads a writable temp directory even in restricted Composer subprocess environments.
$directory = dirname(__DIR__).'/storage/framework/testing/tmp';
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
putenv('TMPDIR='.$directory);
putenv('TMP='.$directory);
putenv('TEMP='.$directory);
require dirname(__DIR__).'/vendor/pestphp/pest/bin/pest';
