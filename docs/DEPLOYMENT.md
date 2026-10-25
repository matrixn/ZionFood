# Local setup and Laravel Forge deployment

## Prerequisites

PHP 8.4 with PDO MySQL, mbstring, intl, fileinfo, XML, curl, zip, bcmath, plus pcntl/posix for Horizon on Linux. Composer 2, Node 24, npm, Docker Compose. Use WSL Ubuntu/Linux for Composer installation and Horizon; Horizon does not support native Windows. The frontend build can run on Windows.

In a fresh checkout:

```sh
php scripts/configure-local.php
composer install
npm ci
docker compose up -d --wait
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
php artisan serve
```

`configure-local.php` refuses to overwrite `.env` and generates random local DB/Reverb credentials and APP_KEY. Compose exposes MariaDB on loopback 13316 and Redis on loopback 16379, with named project volumes. Credentials are local only; no production passwords are supplied. Demo credentials are written to `storage/app/private/demo-accounts.json`. Do not print or commit this file.

In other terminals run `php artisan horizon`, `php artisan reverb:start`, `php artisan schedule:work`; run `npm run dev` for frontend development. `composer run dev` provides an equivalent combined development process using the ordinary queue worker. Do not run two competing worker managers accidentally. Keep at least one WSL process active when using WSL Docker on hosts that stop idle distributions.

Tests:

```sh
composer test
php vendor/bin/pint --test
npm run typecheck
npm run lint
npm test -- --config vitest.config.ts
npm run build
npx playwright install chromium
npm run test:e2e
```

Playwright requires the seeded local app, Redis, Reverb and queue worker running; it reads the private demo credentials without printing them. A fresh test run creates additional orders. Backend tests default to isolated SQLite memory. For MariaDB, provision the dedicated databases with `bash scripts/provision-test-databases.sh`, then run `DB_CONNECTION=mysql DB_DATABASE=zionfood_test php vendor/bin/pest`. Tests refuse a non-memory database without the `_test` suffix.

For real parallel-process checks: `DB_DATABASE=zionfood_test QUEUE_CONNECTION=database php tests/integration/order-concurrency.php`. This creates isolated fixture records and checks duplicate checkout, concurrent transitions and after-commit scheduling. It never operates against the application database.

## Load tests

See [BENCHMARKS.md](BENCHMARKS.md). Prepare fixtures with `DB_DATABASE=zionfood_load_test QUEUE_CONNECTION=database php artisan zionfood:load-fixtures --restaurants=100` after migrating that dedicated database. Private fixture tokens expire in four hours. Run the benchmark server against that database, with separate Redis DB/cache DB/prefix, `BROADCAST_CONNECTION=null` and database queues. Never let its jobs run against the main application database or share its broadcast channels.

`tests/load/ordering.js` accepts RESTAURANTS, ORDER_VUS, ORDER_ITERATIONS, ORDER_RATE, MAX_ORDER_VUS, KITCHEN_SESSIONS, DURATION, BASE_URL and WARM_CACHE. Without ORDER_RATE it uses a fixed iteration count; setting ORDER_RATE uses constant arrival rate. Each checkout is repeated with the same key. Kitchen sessions measure HTTP reconciliation, not WebSocket delivery latency.

## Forge checklist

Create a PHP 8.4 site, root `public`, MariaDB 10.11+ database and Redis. Set APP_ENV=production, APP_DEBUG=false, strong APP_KEY, DB credentials and isolated Redis prefix. Configure `ZF_APP_HOSTS=app.zionfood.ro`, `ZF_API_HOSTS=api.zionfood.ro`, `ZF_STOREFRONT_DOMAIN=zionfood.ro`, APP_URL, SESSION_SECURE_COOKIE=true, SANCTUM_STATEFUL_DOMAINS and SMTP. Limit Reverb allowed origins to the app host, use WSS on 443 and configure its private server credentials. Only VITE_REVERB_APP_KEY/host/port/scheme are public build values; never expose REVERB_APP_SECRET in VITE variables.

Use separate Nginx server blocks for central/API hosts and storefront wildcard domains, with a rejecting default host. Obtain TLS for the central/API/wildcard names, proxy Reverb's WebSocket endpoint, and preserve Origin/Upgrade headers. Set trusted proxies explicitly for the actual load balancer. Restrict MariaDB/Redis to private networking and do not execute files beneath storage.

Deploy script: `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`, `php artisan horizon:terminate`, and restart the supervised Reverb process. Horizon must be managed by a Forge daemon/Supervisor so termination restarts it. Add Laravel's scheduler to cron every minute. Monitor queue failures, undelivered outbox rows, DB latency and disk/Redis capacity.

Create an administrator only after registering the real account: `php artisan zionfood:admin owner@example.com`. Do not seed demos in production. Test registration mail, tenant isolation, checkout/replay, private broadcasts/reconnect, trial expiry and backup restoration against staging before launch. No live Forge deployment has been performed by this implementation task.
