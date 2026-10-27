# Local setup and Laravel Forge deployment

## 0.0.19 menu-generation upgrade

The normal release must run migration `2026_10_27_001000_add_workspace_notifications`, build assets and gracefully restart Horizon workers. This creates the durable notification inbox and retires only GloriaFood-import drafts, preserving published product metadata. Migration rollback cannot restore those removed drafts. No new credentials are required: existing encrypted AI settings and Reverb configuration remain valid, with automatic visible-page polling when realtime is unavailable. See [MENU_CONTENT.md](MENU_CONTENT.md). No manual Forge deployment was performed for this change.

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

The command submits to the configured default mailer and queues external delivery, rejects invalid recipients, log/array mailers and a missing Resend key, and returns a failing exit code on provider errors. A successful exit confirms queue acceptance, not provider submission or inbox delivery. Check Resend's email dashboard and the inbox/spam folder. A temporary public test page is available at /test-email as documented below. Provider error details and API keys are not printed by this command.

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

Visit `https://your-application-domain/test-email` and click the send-test button. At the user's explicit request this page is temporarily public: no authentication, email verification or administrator role is required. Remove both GET and POST routes after completing email diagnostics. The page displays ADMIN_EMAIL and supports Romanian/English. GET only displays the form; POST performs the action with CSRF protection and a shared maximum of three test submissions per hour, across guests and signed-in users. The recipient cannot be overridden by form/query data and always comes from ADMIN_EMAIL. Tests also consume the common 140-recipient/24h delivery quota. The platform organization overview links to this page. Log/array mailers, missing Resend credentials and invalid ADMIN_EMAIL produce a form error rather than a delivery claim.

The dummy recipient is deliberately non-operational: replace ADMIN_EMAIL with your own email for a real test. Queue acceptance is shown separately from actual provider/inbox delivery. No account or platform access is needed for this temporary diagnostic route.

The public-page browser test runs as a guest and needs no credentials. The existing optional local admin fixture command remains restricted to APP_ENV=local and is unnecessary for this page.

## Mail delivery diagnostics and worker recovery

Run `php artisan zionfood:mail-status` in the deployed `current` directory. It does not send mail or print secrets. It checks the configured mailer/key, queue counts, shared quota and whether a running Horizon supervisor actually has workers assigned to the email connection/queue. A live Horizon master alone is insufficient. Inspect `php artisan horizon:status` together with this command. Database queues still require a separate worker and are outside Horizon.

The October 26 live incident was caused by APP_ENV=test while Horizon only had production/local plans. The master stayed running with zero supervisors; three email jobs waited without any provider attempts. A site-scoped three-worker recovery plan drained the queue, and the Resend dashboard confirmed all three ZionFood messages as Delivered. The persistent fix adds a conservative wildcard Horizon plan after the named production/local entries, retaining their intended worker counts. Keep shared APP_KEY, Redis prefix/DB and queue configuration consistent between PHP-FPM and workers.

All actual delivery jobs now log lifecycle events to both `storage/logs/mail-delivery-YYYY-MM-DD.log` (14-day rotation) and PHP's error_log handler at info level or higher, independently of LOG_LEVEL. Web enqueue records go to the PHP-FPM error destination; worker records go to the Horizon PHP process error destination, often the Forge process/Supervisor log. They are not necessarily the same file. Configure PHP's error_log destination if a single shared PHP log is required.

Log stages: `queued` (persisted to queue), `processing`, `quota_deferred` (includes wait seconds), `provider_accepted` (includes the Resend email ID when available), `attempt_failed`, `queue_failed` and `permanently_failed`. Search by `delivery_id` to correlate the stages. Existing queued jobs without a correlation ID remain compatible and receive one when processed. A logging failure cannot turn successful provider acceptance into a duplicate retry. Mail diagnostic errors are excluded from the error-alert email listener to avoid feedback loops.

A provider_accepted record confirms submission, not delivery or inbox placement. Use the logged provider_email_id in Resend to inspect Delivered/Bounced/Delayed status. Failures log exception class, safe HTTP status and a bounded configuration hint, without raw exception/provider messages, addresses, email content, tokens, headers or API keys. 401 points to API credentials; 403 to verified sender/domain/key permissions or test-recipient restrictions; 429 to provider throttling. Logs cannot be produced for jobs that no worker ever starts, so use the status command when a queue stays pending.

After updating code/configuration, rebuild `php artisan config:cache` and gracefully restart the supervised Horizon process (`php artisan horizon:terminate`). Do not stop another application's Horizon process or flush shared Redis. No quota reset is needed to recover this incident.

## Reservations, QR and delivery polygons

Apply additive migrations 2026_10_26_000300, 000400 and 000500, rebuild frontend/config and gracefully restart Horizon. Keep the existing minute scheduler running: zionfood:reservations-expire records timed-out pending holds. New reservation/QR flags default off; enable each location after seating/rules review. Keep APP_KEY stable for encrypted booking/QR/session credentials and queued guest notifications. No payments are enabled.

ZF_MAP_TILE_URL and ZF_MAP_ATTRIBUTION configure the browser raster tile provider. Default standard OpenStreetMap tiles are for light best-effort use under their usage policy; choose a suitable production service and preserve attribution. Only public browser tile credentials belong here. No geocoder or paid provider account is provisioned. See RESERVATIONS_DINING_DELIVERY.md for provider alternatives, pin/address semantics and operational limits.

New QR menus use permanent codes with persistent staff pause/reopen. Set the dine-in ordering cutoff in each location Settings page (default 30 minutes). Both new connections and orders from existing sessions stop at closing minus that interval. No payment settings are enabled.

## Cuisine catalogue

Run additive migration 000600 with database/data/cuisines.json and public/images/cuisines deployed from Git. It creates the initial 27 catalogue options without external requests. Build assets; retain storage:link for platform-admin photo uploads. Existing live catalogue edits are not reset by ordinary seeding. No payment or service activation is implied by cuisine selection. See CUISINES.md and CUISINE_PHOTO_CREDITS.md.

## Restaurant address search

ZF_REVERSE_GEOCODER_URL configures the private Photon-compatible reverse endpoint (default https://photon.komoot.io/reverse). Set it alongside ZF_GEOCODER_URL for a custom provider. Both directions share the existing provider lock/cooldown and per-user throttle. No migration or dependency install is needed; build assets and refresh cached configuration as usual. See RESTAURANT_LOCATION.md for address completeness and delivery centering.

ZF_GEOCODER_URL configures a Photon-compatible HTTPS search endpoint, default https://photon.komoot.io/api/. No key is needed for the default public demo; its reasonable-use and availability limits make a suitable hosted/private service preferable for larger deployments. Configure shared cache storage across workers/instances: lookups cache results for 24 hours, serialize upstream requests and use a global two-second cooldown. No database migration is needed. Rebuild frontend/config after rollout. See RESTAURANT_LOCATION.md for attribution, privacy and failure handling.

The 0.0.9 restaurant navigation/inline editor change requires the usual code/frontend asset update, without new migrations or dependencies. Existing standalone URLs and save routes remain available. ADMIN_NAVIGATION.md describes editor scope and saved-data behavior.

## Ingredient catalogue deployment

Run the normal php artisan migrate --force for migration 000700, then build frontend assets. The migration seeds database/data/ingredients.json automatically in every environment; DatabaseSeeder also invokes IngredientSeeder. An explicit php artisan db:seed --class=IngredientSeeder --force may be repeated without overwriting admin edits, hiding state or removal tombstones. No PDF, Python extraction package or external lookup service is required on Forge. Ensure the bundled JSON is deployed. Review source-conflict entries (including hidden Mai) through /admin/ingredients. See INGREDIENTS.md for ownership, majority/tie rules and the distinction between source flags and verified recipe information.


## Storefront experience (0.0.12)

Storefront experience 0.0.12 requires an asset build and normal code/config refresh, with no new migration or dependencies. Public pin lookup reuses ZF_REVERSE_GEOCODER_URL and the shared production cache; assess provider capacity for anonymous use. Social metadata is in initial HTML and uses an absolute public logo URL. Serve public storage over the correct HTTPS origin and ensure crawlers can fetch the logo. Actual WhatsApp cached previews are not verified locally.


## Share-logo images (0.0.13)

PHP GD (`ext-gd`) with PNG, JPEG and WebP support is required by Composer; enable it for the site's CLI and FPM PHP version on Forge before deployment. CI installs GD explicitly. No new database migration or JavaScript dependency is required. Public storage must be writable and publicly served via the existing storage link.

A menu view generates a missing 600 × 600 PNG once under `branding/{organization}/{restaurant}/shares/`, then reuses it. The whole logo fits proportionally inside a 480 × 480 area on opaque white, protecting horizontal lettering in square thumbnails and flattening transparent backgrounds. No trimming of logo content or stretching is used. The source file and in-app header logo stay unchanged. Source path/layout version define the immutable derivative filename; normal replacement uploads have unique source paths and therefore fresh URLs. Old derivatives are removed after successful branding replacement/removal. Missing, oversized or corrupt inputs omit the image gracefully and log safe generation failures rather than breaking menus.

This also supports existing valid logo uploads without re-upload. Very small sources cannot gain detail through resizing. A real messaging-platform preview refresh is not verified locally; existing shared messages may retain their previous preview.


## Company lookup and invitation-aware entry (0.0.15)

No schema migration, API key or paid subscription is required for ANAF v9. The fixed public endpoint is `https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva`; [official request/schema/usage instructions](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/doc_WS_V9.txt) specify at most one request/second/client. Use shared cache/locks across workers and permit outbound HTTPS to that host. Positive cache entries last 24 hours within the reference-date namespace; not-found entries last ten minutes. ANAF may omit fields or be unavailable; manual legal editing remains available. No arbitrary endpoint setting is exposed in the platform admin because the selected provider needs no credentials/configuration.

Build frontend assets and refresh route/config caches normally. Existing ULID menu links and emailed invitation fragment links remain usable. Browser sessions must work across the invitation, register and application routes on the same central host. No live customer invitation email was sent by local fixtures. Actual production routing/provider/invitation behavior must be observed after deployment; this source increment does not manually deploy Forge.


## Staff feature access and table batches (0.0.16)

Run additive migration `2026_10_27_000800_add_staff_permissions` during the normal deployment: it adds nullable JSON permission maps to memberships and invitations. Existing rows keep legacy defaults until explicitly configured; it does not reset accounts/assignments or QR credentials. Build assets and refresh the normal route/config caches, gracefully restart long-lived workers/Reverb as required by the standard deployment. No new package/provider/environment variable is required.

Use Personal → Editează accesul to select none/view/manage for each feature. Menu writes affecting several locations require access to all affected locations, and shared organization modifier changes require all organization locations. Feature permissions do not turn staff into owners/managers or grant company/admin configuration access. Staff can download QRs only when allowed to view tables; mutation controls need tables manage.

Program/cutoff still use the saved venue timezone and non-overnight business hours. A manually enabled table can remain unavailable to guests outside that window. The table page refreshes availability every 30 seconds and on focus; public connect/checkout revalidate immediately on their requests. No production data/configuration change or manual Forge deploy is performed by local checks.
## Menu-content setup (0.0.17)

Run migration 000900, build assets and perform the usual cache refresh/long-lived worker restart. Preserve APP_KEY for encrypted credentials and drafts. No new dependency or AI environment variable is needed: a platform administrator configures provider/model/key and quota under `/admin/menu-ai`. Groq is the initial free-plan option; actual provider eligibility/quotas/billing must be checked in that account. There is no automatic paid fallback. Permit outbound HTTPS to the explicitly selected provider. Existing Horizon `default` workers run GenerateMenuContent, paced globally at one request/minute with a 40-second provider timeout and 50-second job timeout; keep queue retry_after greater than the Horizon worker timeout. No worker means drafts remain queued. Incomplete/429/interrupted attempts fail without hidden provider retries; the operator can regenerate explicitly. Local tests use fake provider HTTP responses and do not verify a real API key or deploy Forge.

## 0.0.20 overview and help upgrade

Run migration `2026_10_27_001100_add_menu_visit_stats` and build the new assets in the normal release. Existing Redis/cache configuration supplies transient 30-minute visit deduplication. APP_KEY remains unchanged. Counts start with browser visits after this release and cannot be backfilled accurately. This adds no analytics SaaS, client tracking cookie, provider credential or chart dependency. The already configured live Groq workflow was exercised through the authenticated restaurant UI; no manual Forge deployment or server credential extraction was performed.

## Migration Center (0.0.22)

Run additive migration 2026_10_27_001200 and build frontend assets. Horizon/default queue performs Fetch Menu jobs; existing scheduler runs hourly zionfood:imports-prune. ZF_IMPORT_RETENTION_DAYS defaults to 30 for unapproved snapshots. Keys are entered per import and never belong in committed configuration. Allow outbound verified HTTPS to pos.globalfoodsoft.com. Configure upload_max_filesize/post_max_size/web request limits to support the advertised five-MiB file maximum. Published audit/version rows are retained. See MIGRATION_CENTER.md for operational checks and rollback constraints. No real-provider or production deployment is implied by local tests.

## 0.0.24 upgrade and alert suspension

Run additive migrations `2026_10_27_110000_create_email_alert_settings` and `2026_10_27_110100_add_location_preferences`, build assets, refresh normal caches and gracefully reload long-lived workers through the existing deployment process. No data reset or package changes. Platform administrators manage `/admin/email-alerts`; the shared DB lock coordinates all alert producers/workers. Keep MAIL_ERROR_ALERTS enabled and ADMIN_EMAIL configured privately. The sixth distinct alert admission or provider attempt within a rolling hour latches suspension; a clock-hour change/restart does not clear it. Manual resume clears the hour windows and advances a generation, discarding previous-generation/legacy alert jobs when processed. Pausing does not erase the original application logs. Normal invitation/reservation/auth mail remains subject to 140 recipients/24h; deferred messages do not bypass that quota. Transport failure attempts are counted because acceptance can be uncertain. Provider acceptance is logged separately; no inbox-delivery guarantee is implied.

Forge observation during this change showed repeated `email quota_deferred` logs. The original BroadcastException message was absent from the visible log tail, so no specific Reverb cause or live fix is asserted. New `ZionFood broadcast delivery failed` entries contain safe classifications (DNS/refused/TLS/timeout/auth/endpoint) and hints; the original durable retry/error still occurs. If the publisher currently loops through a public proxy, optional `REVERB_PUBLISH_HOST/PORT/SCHEME` may address the internal Reverb listener while `REVERB_*`/`VITE_REVERB_*` browser settings remain public. Determine the actual listener/process topology first; do not disable TLS verification or expose credentials. These optional settings have unchanged fallback behavior when absent. No manual Forge deploy/restart/configuration change was performed for this work.

## Reverb DNS and outbox recovery (0.0.25)

The supplied Forge log records cURL error 6 resolving `ws.food.zion3d.ro` at 2026-10-27 10:31 UTC (12:31 Europe/Bucharest). That is a DNS failure before a Reverb HTTP connection. The same events reached 458–463 lifetime attempts because the old minute reconciler kept creating new jobs after queue retries exhausted. Application orders stay stored; the live websocket invalidation failed. Do not clear Redis queues, delete outbox rows or mark them delivered to hide this error.

During the normal upgrade run `2026_10_27_120000_bound_outbox_retries` and gracefully restart Horizon through the existing deployment process so already-running workers use the new job code. The migration preserves event identity/payload/lifetime attempts, initializes the per-cycle counter from historical attempts and marks undelivered rows with at least five attempts exhausted. Queued duplicates then skip those rows. Fresh attempts use persistent 5/15/60/120-second delays and stop after the fifth failure, independently of queue job identities. The minute reconciler only queues due, non-exhausted rows. Webhook deliveries already fanned out remain independently retryable/idempotent.

Verify the actual topology before changing environment values. If Reverb runs on the SAME server at loopback port 8080, the existing supported internal publishing settings are:

```dotenv
REVERB_PUBLISH_HOST=127.0.0.1
REVERB_PUBLISH_PORT=8080
REVERB_PUBLISH_SCHEME=http
```

For the site inspected during this incident, Forge showed Reverb Running with `--port=8080`, and the owner confirmed it is on the same server. Use the actual supervised Reverb listener port on other installations, which may differ. If Reverb runs on another server, use its reachable authenticated TLS endpoint; do not replace it with local loopback. Internal publishing does not repair public browser DNS. For the current public hostname, create/correct its A/AAAA or CNAME record pointing to the intended server, configure the matching TLS certificate and websocket reverse proxy, and use the appropriate `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` and public `VITE_REVERB_*` build values. Keep the same app ID/key/secret on both sides and keep the secret out of VITE variables. Do not disable TLS verification or change BROADCAST_CONNECTION to null/log as a substitute for fixing realtime.

After an authorized server configuration update, rebuild the configuration cache and gracefully restart Horizon; rebuild frontend assets when VITE values change. Restart the supervised Reverb process when its own listener/app configuration changes. `php artisan zionfood:realtime-check` reads the EFFECTIVE cached configuration, checks publisher and public REVERB_HOST DNS and reports missing configuration without printing credentials. It publishes no event and does not verify TCP reachability, TLS, credential matching or separately overridden VITE assets. Localhost/IP values need no external DNS. A successful DNS check alone does not prove end-to-end delivery.

After resolving the actual endpoint, explicitly retry ONE exhausted event first:

```sh
php artisan zionfood:outbox-retry EVENT_ULID
```

Use an actual event_id from the safe diagnostic line. This resets only the per-cycle attempt budget and exhaustion/due state, preserves lifetime attempts and payload, and schedules after commit. It rejects missing, already-delivered or non-exhausted events. Verify delivery and browser reconciliation before selectively retrying other retained events. No automatic bulk replay is added. Reverb transport exceptions now propagate only a safe classification/hint without chaining the raw signed URL; existing historical logs are not rewritten. The five-per-hour error-email circuit remains separate from the five-attempt per-event publication budget. No Forge environment/service/DNS change is implied by this source release.


## Email ledger and Resend quota (0.0.26)

Apply additive migration 130000, build assets/config and gracefully restart Horizon through the normal deployment. Keep Redis-backed shared locks and the minute scheduler running. RESEND_DAILY_LIMIT=100 initializes the persistent guard once; admin edits then take precedence. In platform Admin → Emails and queue, index the existing configured backlog and check provider usage (no emails sent by either action). New metadata appears on enqueue. See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for reset semantics, key-permission errors, legacy/custom-queue limits, expired auth links and 90-day pruning. No new dependency or Resend webhook configuration is required. No live quota/backlog verification is implied by local tests.

Operational follow-up to the Reverb incident: after the owner corrected their environment values, a graceful Horizon refresh and one explicit event retry were confirmed successful (event delivered with one cycle attempt). This was internal backend publication. Public browser websocket DNS/TLS/VITE configuration remains a separate check; provider email quota never explained the original cURL-6 broadcaster failure.
