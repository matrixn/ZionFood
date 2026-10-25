#!/usr/bin/env bash
# Same-source local runtime on Linux storage, avoiding Windows-mounted PHP source overhead.
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
runtime=$(mktemp -d /tmp/zionfood-benchmark.XXXXXX)
rsync -a --exclude=.git --exclude=.bootstrap --exclude=node_modules --exclude=storage/logs --exclude=storage/app --exclude=public/build ./ "$runtime/"
mkdir -p "$runtime/storage/logs" "$runtime/storage/app/private" "$runtime/storage/framework/cache/data"
echo "Benchmark runtime: $runtime (same source, temporary private Linux copy)."
cd "$runtime"
XDEBUG_MODE=off DB_DATABASE=zionfood_load_test QUEUE_CONNECTION=database REDIS_DB=5 REDIS_CACHE_DB=6 REDIS_PREFIX=zionfood_benchmark_ BROADCAST_CONNECTION=null ZF_CHECKOUT_IP_LIMIT=20000 PHP_CLI_SERVER_WORKERS=4 php -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -S 127.0.0.1:8004 -t public
