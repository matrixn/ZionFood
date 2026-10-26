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

Run `php artisan zionfood:browser-fixtures` before Playwright to create a separate random local-only account in ignored private storage; existing accounts are unchanged. Playwright requires the seeded local app, Redis, Reverb and queue worker running; it reads the private demo credentials without printing them. A fresh test run creates additional orders. Backend tests default to isolated SQLite memory. For MariaDB, provision the dedicated databases with `bash scripts/provision-test-databases.sh`, then run `DB_CONNECTION=mysql DB_DATABASE=zionfood_test php vendor/bin/pest`. Tests refuse a non-memory database without the `_test` suffix.

For real parallel-process checks: `DB_DATABASE=zionfood_test QUEUE_CONNECTION=database php tests/integration/order-concurrency.php`. This creates isolated fixture records and checks duplicate checkout, concurrent transitions and after-commit scheduling. It never operates against the application database.

## Load tests

See [BENCHMARKS.md](BENCHMARKS.md). Prepare fixtures with `DB_DATABASE=zionfood_load_test QUEUE_CONNECTION=database php artisan zionfood:load-fixtures --restaurants=100` after migrating that dedicated database. Private fixture tokens expire in four hours. Run the benchmark server against that database, with separate Redis DB/cache DB/prefix, `BROADCAST_CONNECTION=null` and database queues. Never let its jobs run against the main application database or share its broadcast channels.

`tests/load/ordering.js` accepts RESTAURANTS, ORDER_VUS, ORDER_ITERATIONS, ORDER_RATE, MAX_ORDER_VUS, KITCHEN_SESSIONS, DURATION, BASE_URL and WARM_CACHE. Without ORDER_RATE it uses a fixed iteration count; setting ORDER_RATE uses constant arrival rate. Each checkout is repeated with the same key. Kitchen sessions measure HTTP reconciliation, not WebSocket delivery latency.

## Forge checklist

Create a PHP 8.4 site, root `public`, MariaDB 10.11+ database and Redis. Set APP_ENV=production, APP_DEBUG=false, strong APP_KEY, DB credentials and isolated Redis prefix. Configure `ZF_APP_HOSTS=app.zionfood.ro`, `ZF_API_HOSTS=api.zionfood.ro`, `ZF_STOREFRONT_DOMAIN=zionfood.ro`, APP_URL, SESSION_SECURE_COOKIE=true, SANCTUM_STATEFUL_DOMAINS and Resend email (see below). Limit Reverb allowed origins to the app host, use WSS on 443 and configure its private server credentials. Only VITE_REVERB_APP_KEY/host/port/scheme are public build values; never expose REVERB_APP_SECRET in VITE variables.

Use separate Nginx server blocks for central/API hosts and storefront wildcard domains, with a rejecting default host. Obtain TLS for the central/API/wildcard names, proxy Reverb's WebSocket endpoint, and preserve Origin/Upgrade headers. Set trusted proxies explicitly for the actual load balancer. Restrict MariaDB/Redis to private networking and do not execute files beneath storage.

Deploy script: `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`, `php artisan horizon:terminate`, and restart the supervised Reverb process. Horizon must be managed by a Forge daemon/Supervisor so termination restarts it. Add Laravel's scheduler to cron every minute. It reconciles both outbox publication and due/stale webhook deliveries. Workers need the cURL extension for HTTPS destination pinning; preserve outbound firewall restrictions. Signing keys and encrypted invitation jobs require the application encryption key during recovery. Set APP_LOCALE=ro and APP_FALLBACK_LOCALE=en for the default interface. Configure Resend or SMTP for staff invitations and account verification; never use the development log mailer in production. Monitor queue failures, undelivered outbox rows, DB latency and disk/Redis capacity.

Create an administrator only after registering the real account: `php artisan zionfood:admin owner@example.com`. Do not seed demos in production. Test registration mail, tenant isolation, checkout/replay, private broadcasts/reconnect, trial expiry and backup restoration against staging before launch. No live Forge deployment has been performed by this implementation task.

## Resend transactional email (Forge)

The native Laravel Resend transport is installed through `resend/resend-php`. Account verification, password resets and staff invitations use the configured default mailer; no separate SMTP credentials or Resend Laravel plugin are needed.

1. In Resend, verify your sending domain using the DNS records shown in its Domains screen. For example, verify `zion3d.ro` before using `noreply@zion3d.ro`. The sending domain can differ from the application's web hostname.
2. Create a sending-only API key, restricted to that domain where possible. Store it in the Forge environment, never in Git or a VITE variable.
3. Configure:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_replace_in_forge_only
MAIL_FROM_ADDRESS=noreply@zion3d.ro
MAIL_FROM_NAME="ZionFood"
```

`RESEND_API_KEY` is the primary variable. The previous `RESEND_KEY` is accepted as a fallback when the primary variable is absent. Local development stays on `MAIL_MAILER=log` by default to avoid accidental delivery. Production must select `resend` explicitly. Keep `APP_URL` set to the public HTTPS application address so verification, reset and invitation links are correct.

After deployment/environment changes, run `php artisan config:cache` and `php artisan horizon:terminate`; the Forge supervisor must restart Horizon. All external email is queued for quota-controlled delivery and needs a worker; staff invitation notifications are also queued before rendering. Existing Romanian/English notification localization continues to apply.

Submit a real smoke test only to an address you control:

```sh
php artisan zionfood:mail-test your-address@example.com
```

The command submits to the configured default mailer and queues external delivery, rejects invalid recipients, log/array mailers and a missing Resend key, and returns a failing exit code on provider errors. A successful exit confirms queue acceptance, not provider submission or inbox delivery. Check Resend's email dashboard and the inbox/spam folder. An authenticated platform-admin test page is available at /test-email as documented below. Provider error details and API keys are not printed by this command.

No live Resend account, DNS verification or real email delivery was exercised during implementation. Automated tests use an in-memory HTTP transport and no external recipient. Resend delivery-event webhooks are not included; the existing restaurant webhooks remain a separate feature.

References: [Laravel mail / Resend driver](https://laravel.com/docs/12.x/mail), [Resend Laravel](https://resend.com/laravel).

## Global email quota and error alerts

Every delivery email submitted through Laravel's mailer, including named mailers, verification, password resets, invitations and the test command, is now rendered and queued as an encrypted `DeliverEmail` job before transport submission. Local array/log transports remain local and do not consume external-delivery quota. Direct calls to a provider SDK or Symfony transport outside this delivery job are not supported application sending paths. Composite failover/roundrobin mailers are rejected to avoid a fallback-to-log being mistaken for delivery.

```dotenv
ADMIN_EMAIL=dummy@dummy.com
MAIL_ERROR_ALERTS=true
MAIL_RATE_LIMIT_STORE=redis
MAIL_QUEUE_CONNECTION=redis
```

Replace the dummy ADMIN_EMAIL with an address you control before enabling production delivery. MAIL_MAILER=resend, RESEND_API_KEY and the verified MAIL_FROM_ADDRESS from the section above are still required. No key or real recipient is configured by this implementation.

The shared quota is **140 recipient submissions in any rolling 86,400 seconds**, across all tenants, mailers, web processes and workers in this application's Redis namespace. To/CC/BCC recipients count toward the same quota; a single email cannot contain more than 140 recipients. Each attempt reserves quota atomically under a Redis lock before calling the provider. Failed or ambiguous submissions retain their slots conservatively. There is no midnight reset. Redis must be shared by all application instances, durable and not evict these keys; clearing/replacing the quota cache resets its history. Separate applications sending through the same Resend account are outside this application quota.

When no slots are available, jobs are released until enough slots expire, with small jitter, rather than discarded or sent above quota. Cache/lock failure prevents sending. Production requires a Redis cache store; an in-memory store is accepted only by automated tests. Quota waiting does not consume the exception retry budget. Delivery uses five exception attempts with backoff and a fixed 30-day job deadline; inspect/retry failed jobs after resolving outages. Provider submission has at-least-once semantics; a crash/uncertain response can cause duplicates on retry. This is an application recipient quota, not a guarantee of provider billing or inbox delivery.

MAIL_QUEUE_CONNECTION supports persistent Redis or database queues, not sync/null or SQS with its shorter maximum delay. The default Redis `default` queue is already served by Horizon. If using database instead, supervise `php artisan queue:work database --queue=default --tries=0` separately. Restart Horizon after config/code changes. All transactional mail now requires workers, including verification and password resets; the test command confirms queue submission rather than provider delivery. Very long delays can outlive verification/reset/invitation token validity; request a fresh message after backlog recovery. Revoked invitations remain unusable even if an already-rendered invitation email is eventually delivered.

Error alerts subscribe to Laravel's MessageLogged event and cover `error`, `critical`, `alert`, `emergency`, including normal HTTP/console/worker exceptions that Laravel reports to its logger. Warnings and debug/info logs, normal Laravel ignored 404/419/validation errors, caught-and-unreported exceptions, failures before Laravel boots, and failures that prevent all queue/cache/mail infrastructure from functioning cannot produce these emails. Keep APP_DEBUG=false in production; alerting does not require exposing debug output to visitors.

Each alert contains a reference, UTC time, environment, severity, exception class and up to 20 stack frames without arguments. Raw exception/log messages, request URLs/query/body/headers, context values, SQL/bindings and user/customer data are intentionally excluded; inspect the server logs at the indicated time for full details. Identical fingerprints are grouped for ten minutes. Alerts use the same 140-recipient quota as account emails and may be delayed by the shared backlog. Alert delivery failures are retried with warning-only local logging, preventing recursive email storms; an unavailable alert pipeline never replaces the original exception.

To exercise the alert path on staging after replacing ADMIN_EMAIL and starting workers, report a deliberately generic exception from a controlled Artisan/Tinker session, then inspect the queue/provider/inbox. No public exception-test endpoint is provided. No live alert recipient or external provider request was exercised during implementation.

## Browser email test page

Visit `https://your-application-domain/test-email`, sign in with a verified **platform administrator** account, and click the send-test button. The page displays ADMIN_EMAIL and supports Romanian/English. GET only displays the form; POST performs the action with session authentication, platform-admin authorization, CSRF protection and a shared maximum of three test submissions per hour. The recipient cannot be overridden by form/query data and always comes from ADMIN_EMAIL. Tests also consume the common 140-recipient/24h delivery quota. The platform organization overview links to this page. Log/array mailers, missing Resend credentials and invalid ADMIN_EMAIL produce a form error rather than a delivery claim.

The dummy recipient is deliberately non-operational: replace ADMIN_EMAIL with your own email for a real test. Queue acceptance is shown separately from actual provider/inbox delivery. If needed, grant platform access only to an existing trusted account with `php artisan zionfood:admin your-account@example.com`.

Local browser QA uses `php artisan zionfood:browser-fixtures --admin` to generate a new isolated admin account in ignored private storage; it never changes an existing user's privileges or password. This fixture command remains restricted to APP_ENV=local.
