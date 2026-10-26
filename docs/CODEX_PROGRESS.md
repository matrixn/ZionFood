# ZionFood progress — 0.0.1

Current increment: **October 26, 2026**. The user requested staff administration, full restaurant branding, webhooks, Romanian-default/optional-English interfaces, updated documentation, tests and local commits, with **no push, tag or deploy**. The subsequent GitHub Actions Pint failure is included in this increment.

## Completed tasks

The previous ordering foundation remains: authentication/onboarding, tenant-aware organization/restaurant/location hierarchy, menus/products/variants/modifiers, public ordering, integer server pricing, snapshots, idempotency, protected tracking, order history, private realtime kitchen, outbox recovery, trial entitlement and scoped API tokens.

This increment adds:

- Staff management with owner/manager boundaries, location assignments, membership removal and organization-scoped token revocation. Verified invitees accept expiring/revocable invitations; existing users in other organizations are preserved. Email jobs are encrypted and obsolete invitations are skipped.
- Restaurant name/slug/tagline/description, logo/cover uploads/removal, primary color, public contacts, live preview and public menu/tracking branding. Managed subdomain collisions/reserved names and unsafe uploads are rejected.
- Romanian as the default, optional English, top-right local flag/name selectors across all layouts, persistent cookie/account preference, reactive catalogues, PrimeVue ARIA/form labels, dates/currency/statuses and Laravel validation/auth/email messages. API keys, role/status values and event names remain language independent.
- Webhook destination/event/secret configuration, signed payloads, tenant/location fanout, idempotent durable delivery rows, response/status history including removed endpoints, leases/claim tokens, six bounded attempts, manual retry, deletion/disable cancellation and scheduler reconciliation. Every attempt checks public DNS answers and pins the checked IP with explicit cURL, verified TLS, no proxy/redirect and bounded timeout/response size.
- Confirmed kitchen transition responses update the queue immediately before background reconciliation; older in-flight lists and known older versions cannot overwrite them.
- Fixed stale CSRF meta headers after Inertia login by reading the current XSRF cookie for API/Echo authentication.
- Corrected formatting in PlaceOrderAction and checked the complete PHP tree with the Linux Pint command used by CI. The GitHub workflow was not pushed or rerun remotely.

Detailed behavior and limits: [STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md](STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md).

## Tests executed

| Check | Result |
| --- | --- |
| Expanded backend suite, isolated SQLite memory | 76 tests passed, 261 assertions |
| Same suite on dedicated MariaDB zionfood_test | 76 tests passed, 261 assertions |
| Additive migration on the local application MariaDB database | Passed without resetting existing data |
| Independent simultaneous checkout processes on MariaDB | One order for two identical submissions |
| Independent simultaneous status transitions on MariaDB | One success and one conflict; version/history preserved |
| Explicit commit/queue integration check | Delivery queued only after commit |
| Frontend Vitest | Six passed: cart, locale/reactivity/catalogue coverage, current CSRF cookie and stale kitchen response protection |
| Playwright Chromium | Four passed: Romanian/English administration flow; private Reverb event receipt; landing/auth/mobile storefront; checkout/tracking/KDS reload/workflow/menu save |
| TypeScript and Vite production build | Passed |
| ESLint | Passed |
| Linux php vendor/bin/pint --test | Passed across 135 PHP files |

Webhook tests use fake HTTP receivers; no webhook POST was sent to a third-party receiver. Browser configuration saves disabled endpoints and tests rejection of a private address. Delivery tests cover exact HMAC bytes, pinned DNS options, unsafe/mixed DNS answers, redirects, transient failures, stale leases, duplicate jobs, endpoint disabling, retries, retained history, reconciliation beyond 100 due deliveries, cross-tenant restrictions and fanout surviving a failed kitchen broadcast. Staff tests cover no access before verified acceptance, invalid/expired/revoked tokens, foreign location IDs, manager escalation, owner protection and organization-only removal. Branding and locale tests cover persistence, host updates, safe images/colors, public data and stable API keys.

Screenshots of the mobile language selector, Romanian staff page and webhook administration were visually inspected. Mobile overflow assertions passed. Browser fixtures are generated into a separate local-only organization with random private credentials; existing demo account passwords are unchanged.

## Failures found and resolved

- Initial native formatting was insufficient evidence for the reported Linux Pint check. The actual Linux command was run and new-file style issues were fixed without disabling any rules.
- Locale switching after login revealed stale CSRF meta values; the current cookie now takes precedence and Echo uses a per-request header provider.
- Reviewed Guzzle transport selection and explicitly pinned delivery to its cURL handler, avoiding a streaming fallback that would ignore cURL DNS options.
- Prevented translation of the technical `options` API key/relationship and added a Romanian API schema regression test.
- Fixed reactive branding image-removal controls after saving new assets.
- Updated browser locators for alertdialog semantics and decorative icons; retained a realistic timeout for the multi-step checkout test.
- Applied the authoritative transition response immediately in KDS to avoid delayed card removal while a slower full-list request completes.
- Retained delivery history for soft-deleted endpoints and paused history auto-refresh while older pages are expanded.

## Files modified

New staff/restaurant/integration domain Actions/services, controllers/Form Requests, invitation notification/model and webhook delivery job; additive migration and webhook relationships/casts; outbox/menu publication, locale middleware/provider/routes/authentication client; Vue layouts, language selector/flag SVGs, English/Romanian catalogues, PrimeVue configuration, staff/branding/webhook/invitation pages and kitchen store/components; backend/frontend/browser tests and a local browser-fixture command; dependency lockfiles and project documentation/OpenAPI. The complete repository file list is in IMPLEMENTED_FILES.txt; the local commit diff provides the exact increment.

Private environment files, signing keys, demo/browser/load credentials, generated vendor/node_modules/builds, traces and logs remain excluded from Git.

## Remaining tasks and known limits

The WordPress plugin, automatic billing, custom-domain DNS/SSL provisioning, multi-restaurant/location creation screens, ownership transfer, retention/archival automation and production Forge deployment remain separate work. Overnight opening and geocoded delivery are not implemented. The cart is in memory and portable customer tracking is not implemented. Owner-authored menu content is not automatically translated.

Live SMTP, external HTTPS webhook receiver interoperability, forced disconnection of existing identifier-only WebSocket subscriptions after staff removal, physical-device sound/fullscreen behavior, complete accessibility certification and sustained production/webhook throughput are unverified. Authoritative API access is denied immediately after membership removal; an already in-flight webhook cannot be recalled.

The October 25 k6 measurements remain historical baseline evidence in BENCHMARKS.md. They were not rerun for this expanded code, and no new production-capacity claim is made.

## Next implementation step

Use staging to verify actual mail delivery/acceptance across browsers, external receiver TLS/signature interoperability, longer queue/outage recovery and production deployment/backup restoration. Then implement the separate WordPress plugin, subscription lifecycle and custom-domain provisioning as prioritized by the user.

## Git and release boundaries

The baseline commits 91ba816 and ee24976 were pushed in the preceding user-authorized turn. This increment is local only: no push, tag, GitHub release or deployment is authorized. Version remains 0.0.1. Local application services remain available for review.

## Resend email increment — October 26, 2026

Added the official Resend PHP SDK for Laravel's existing native mail transport. `RESEND_API_KEY` is now the primary credential (`RESEND_KEY` fallback retained), with an empty example variable and explicit production setup in DEPLOYMENT.md. Existing verification/reset/invitation notifications use the default mailer. Local development remains on log; no real environment key was changed.

Added `zionfood:mail-test {email}` for synchronous provider submission checks with input/configuration validation and redacted failure output. Automated coverage verifies native transport resolution, configuration/input rejection, actual SDK request payload/authentication through an in-memory HTTP handler, and provider failure handling. No live Resend request, DNS verification, real recipient, push, tag or deployment is included.

Verification for this email increment: 80 backend tests passed (275 assertions, SQLite in-memory); Linux `php vendor/bin/pint --test` passed for 137 files; six Vitest tests, ESLint, TypeScript/Vite build and Composer validation passed. Composer reported no vulnerability advisories and only added resend/resend-php v1.17.0 without updating existing packages. Composer's existing explicit-version advisory remains. Initial SDK autoload checks were run before Composer finished and the payload assertion needed Symfony's quoted sender format; both were resolved before the final full pass. Browser/concurrency/MariaDB suites were not rerun for this mail-only change; prior results above belong to the preceding increment. Version remains 0.0.1.

## Global email quota and diagnostic alerts — October 26, 2026

Added a MessageSending listener that queues all external Laravel mail (including named mailers) as encrypted jobs. Each delivery reserves recipient slots with a shared Redis lock and a rolling 24-hour ledger capped at 140; overflow waits in the persistent queue. Failed/uncertain submissions retain their quota. Local log/array mail stays local. New configuration includes ADMIN_EMAIL (dummy@dummy.com as explicitly requested), MAIL_ERROR_ALERTS, MAIL_RATE_LIMIT_STORE and MAIL_QUEUE_CONNECTION. The private local .env ADMIN_EMAIL was set without exposing any other environment values.

Added a guarded MessageLogged listener for error/critical/alert/emergency diagnostics, including normally reported exceptions, with ten-minute fingerprint grouping, argument-free bounded stack frames and no raw message/request/context data. Alert failures cannot recursively email themselves or replace the original failure. All alerts share the same quota as account mail. Full setup, worker requirements, delayed token expiry, failure/recovery and infrastructure limits are in DEPLOYMENT.md. No real email, key, external recipient, push, tag or deployment is included.

Added `/test-email` with a localized platform-admin form and POST action. GET never sends; POST always targets ADMIN_EMAIL, rejects non-delivery configuration, uses CSRF/session/verified-account/platform-admin authorization and a global three-per-hour test throttle on top of the shared daily delivery quota. The platform organization page links to it. Added backend access/configuration/recipient/rate tests and an isolated local-only platform-admin browser fixture option. The latest user explicitly authorized commit and push after verification; no tag or deployment was requested.

Final checks for quota/alerts/admin page: 96 backend tests passed (343 assertions, SQLite); six frontend tests, ESLint and TypeScript/Vite build passed. Isolated eight-process Redis integration admitted exactly 140 of 160 simultaneous recipient attempts with 140 stored slots; temporary keys were removed. The dedicated Playwright admin test passed, covering guest redirect, intended login return, Romanian/English page and mobile layout; its screenshot was visually inspected. An initial browser check was corrected to await authentication and its intended /test-email return. No live provider POST or actual recipient was used. Existing MariaDB/order-concurrency/full browser-suite results above are historical to the prior increment; only relevant current checks were rerun here. Version remains 0.0.1.
Final Linux CI formatting command php vendor/bin/pint --test passed across 145 PHP files.

## Temporary public email test page — October 26, 2026

At the user's explicit request, removed auth/verified/platform-admin middleware from both /test-email routes and the now-unused gate. The page uses the guest-compatible layout with RO/EN selection and queue-success feedback. GET still only renders; POST always targets ADMIN_EMAIL, retains CSRF and the global three-per-hour throttle, and delivery still shares the 140-recipient/24h quota. Updated the current deployment instructions and access/browser regression tests. Remove both temporary diagnostic routes after testing; no automatic removal date was requested.
Verification: 96 backend tests / 344 assertions, six frontend tests, guest Playwright RO/EN/mobile test, ESLint, TypeScript/Vite build and Linux Pint (145 PHP files) passed. The guest-layout screenshot was inspected. No real email was sent.
