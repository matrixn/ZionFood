# ZionFood progress — 0.0.4

## Version 0.0.4 - public presentation page (October 26)

Rebuilt the central-host Welcome page in RO/EN with editorial food imagery, clear registration/dashboard calls to action, persistent language selection, mobile navigation, feature tabs, an interactive local kitchen-order sequence, an illustrated delivery-zone preview, FAQ and closing CTA. The mapped storefront root still serves its actual menu. Demo controls do not call order, reservation or notification APIs. Copy describes available services and explicitly keeps online payments unavailable. No new service, billing or backend mutation was introduced.

Two AI images were generated with the built-in image_gen tool and imagegen skill, inspected and copied unchanged to public/images/marketing. Prompts/provenance are in docs/assets/landing-images.prompt.md; the footer and alternate text disclose AI imagery. Graphic previews are native SVG/CSS/Vue, not substituted application screenshots.

Animations include hover/float/path effects, viewport reveals, tab transitions and scroll progress, with reduced-motion handling and lifecycle cleanup. Tested browser flows cover localized content/persistence, actual CTA destinations, feature keyboard navigation, demo state changes without ordering mutations, decoded images, native FAQ, mobile menu, 320px/390px overflow and reduced motion. Desktop/mobile/feature/kitchen screenshots were inspected; a mobile heading wrap and screenshot timing during a tab transition were corrected. Vite initially tried to import the public PNG as a module; runtime-bound public asset URLs resolved the production build.

Frontend TypeScript/Vite build, ESLint, eight Vitest tests and both dedicated Chromium flows passed before the version-only metadata update. Final checks passed: full SQLite suite 138 tests / 712 assertions (including central Welcome vs mapped storefront Menu contracts), Composer validation, version/image/link/catalogue consistency and exact Linux Pint across 182 PHP files. The prior MariaDB results belong to earlier increments; no new MariaDB/production run is claimed for this frontend-focused change. The app bundle remains above Vite's 500 kB warning (approximately 523 kB); images retain generated PNG encoding and the secondary image is lazy-loaded. No production performance claim or live deployment is made. Local commit is allowed; this request does not authorize an additional push or manual deployment.


## Version 0.0.3 - README history and AI banner (October 26)

At the user's explicit request, rebuilt README.md in Romanian with a checked chronological Git history, implemented feature inventory, unfinished product/production checklists, historical validation scope, local setup and documentation links. Checked the catalogue, QR/reservation/delivery boundaries, prior commit log and recorded Horizon/Resend incident. Prepared preferences and the payments placeholder remain distinct from operational features. No new production or performance guarantee is made.

Generated one ZionFood landscape brand illustration with the built-in image_gen tool using the imagegen skill. Copied the selected PNG unchanged to docs/assets/zionfood-hero-ai.png and stored the final prompt/provenance alongside it. The README labels it as an AI illustration, not an application screenshot. The banner is not wired into the application's interface.

Bumped the current version to 0.0.3 for this single commit, preserving historical release notes and dependency versions. Local checks passed: 25 README links, 67 implemented checkboxes, 20 remaining checkboxes, a valid 1672 x 941 PNG copied byte-for-byte, synchronized 0.0.3 metadata, Composer validation and exact Linux Pint across 182 PHP files. Composer lock changes only its content hash; package versions are unchanged. No full application test rerun or live deployment is claimed for this documentation/image change. Commit and push are explicitly authorized.


## Version 0.0.2 - October 26

At the user's request, synchronized current application, Composer/npm package, lock-root, API and documentation versions to 0.0.2. All earlier feature commits were already pushed; no history rewrite was performed. Each future commit must increment the patch version. Dependency versions, historical notes and the original master specification remain unchanged. This version-only commit is authorized for push, with no tag or manual deployment requested. Validation checks version consistency, Composer metadata and the Linux Pint CI command; the prior 138-test feature results remain historical rather than being claimed as rerun.


## Current increment - cuisine catalogue and selection (October 26)

Removed the editable free-text cuisine field from Basics and added a separate Cuisine step immediately after Name and address. Owners/managers select multiple illustrated cards, search bilingual names, see a separate selected list, a sticky count/save bar, unsaved feedback and saved activation dates. Selection timestamps survive unchanged saves. Legacy private free-text values remain for continuity, without automatic mapping. Cuisine is descriptive and optional; it does not activate products, payments or operational services.

Added the shared platform-admin catalogue with bilingual names/descriptions, safe photo upload/replacement, attribution links, display order and availability. Tenant roles cannot edit it. Inactive entries cannot be newly selected; existing selections remain visible with warnings and may be retained/removed. The revision-checked location pivot enforces organization/location ownership. Migration 000600 creates the schema and imports all 27 requested options from bundled JSON, without deployment-time network calls.

All 27 real Wikimedia Commons photos are bundled locally (about 5.7 MB), with author/source/license metadata in the cards and CUISINE_PHOTO_CREDITS.md. Images were checked in a contact sheet and loaded successfully in browser tests. Public storefront data and online payment behavior are unchanged. Deployment needs the additive migration and frontend build; uploaded photos retain the existing public storage link.

Validation: 138 backend tests / 698 assertions passed on isolated SQLite and the dedicated MariaDB database through WSL (174.31 seconds). Six new catalogue tests cover the default assets, platform/tenant boundaries, upload validation/replacement, selection dates, deactivation, invalid IDs, duplicates and revision conflicts. Eight Vitest checks, TypeScript/Vite build, ESLint and exact Linux Pint (182 PHP files) passed. Three Chromium flows passed: cuisine selection/search/save/reload/RO-EN/mobile/all 27 images; platform photo create/edit/deactivation; existing setup regression. The two cuisine flows passed again after the sticky-save and translated-hint improvements, including successful decoding of an uploaded photo. Desktop/mobile/admin screenshots were inspected. Two catalogue entries and uploaded files created solely for browser tests were removed from the local application; local tenant test accounts remain isolated fixtures.

Initial browser fixture-path and shared-account locale assumptions were corrected. Visual inspection found a missing dynamic hint key; both translations were added. No production/live Forge test, manual deploy or CI result is claimed. The existing approximately 507 kB app bundle warning remains; the photos use native lazy loading. Version stays 0.0.1. The user explicitly authorized commit and push for this increment, including the preceding local reservation/QR/map commit.



## Previous increment - reservations, table QR and delivery polygons (October 26)

Implemented location-scoped reservations, local arrival slots, peak concurrent capacity/single-table allocation, automatic/manual confirmation with expiring holds, private guest receipts/cancellation and versioned staff operations. Optional encrypted/localized guest emails reuse the shared queue/quota. Stable table QR identities support permanent direct QR sessions by default, with optional staff activation; dine-in checkout snapshots table identity into existing KDS/orders/outbox. Seating restructuring is blocked while future bookings or live sessions exist. All new services default off and are independently selected per location.

Settings now supports drawn/edited delivery polygons using Leaflet + Geoman Free. Portable GeoJSON is bounded/validated server-side. Checkout requires a map pin when polygons exist; the server selects zone/priority/minimum/fee and prevents postal/zone-ID bypass. Postal delivery remains when no polygons exist. Tile URL/attribution are configurable; no paid provider or geocoder was activated. MapLibre/Terra Draw and Google/Terra Draw alternatives are documented. The user confirmed Leaflet + Geoman and permanent QR with staff-controlled menu closure and a configurable closing cutoff.

Payments remain a menu placeholder. Scheduled/catering fulfillment, table combining, automatic reservation-to-QR occupancy linkage, split/overnight/holiday schedules, SMS/staff booking alerts and reservation webhooks remain future work. Capacity previews are advisory and acceptance rechecks under the database lock. Printed/device QR scanning, real guest mail, paid tile providers and production load remain unverified. See [runtime behavior and boundaries](RESERVATIONS_DINING_DELIVERY.md).

Local additive migrations passed without resetting application data. No Forge change, push, tag or deploy is included. Version remains 0.0.1.

Validation before the final QR controls: 129 backend tests / 497 assertions passed on isolated SQLite. The same 129 tests / 497 assertions passed on dedicated MariaDB through WSL (187.64 seconds). Separate simultaneous MariaDB processes verified no capacity overbooking and identical-request replay to one booking. Eight Vitest checks passed. TypeScript/Vite build, ESLint and exact Linux Pint passed (174 PHP files). Four Chromium flows passed: RO/EN/mobile setup; guest reservation/reload/cancellation; real QR order in KDS/session closure; polygon drawing/save/reload/mobile layout plus outside-pin rejection and inside-pin delivery checkout. Reservation receipt, QR kitchen and delivery-zone mobile screenshots were inspected. Build reports a roughly 501 kB application chunk; npm audit retains three existing Vitest 3 development-tool findings whose offered fix requires a major upgrade, outside this feature change. No paid account or real guest email was used.

Initial browser failures exposed a stale slot-search response, which is now ignored; API previews batch-load bookings/tables rather than issuing queries for each slot. QR tests follow the visible manager preview link after encrypted Inertia history. Polygon gestures avoid toolbar flyouts. Linux formatter could not write a Windows-locked route file, so formatting ran natively, followed by the required full Linux Pint test. Concurrency verification refreshes DB-default timezone fields before comparing UTC times.

Final QR policy follow-up: permanent links with direct activation default; staff close/reopen menu state persists separately from expiring session credentials, so scanning cannot undo a staff closure. Added per-location settings cutoff (0-240 minutes, default 30), shared by QR connection and checkout in the venue timezone. Settings allow 30/60-minute values or another interval; staff opening a menu cannot bypass the cutoff. Added migration 000500, pause/rescan/reopen, stale-session checkout, exact cutoff boundary and settings tests. Final SQLite suite: 132 tests / 506 assertions. Final MariaDB full run had 131 passes and one homepage failure caused by a concurrent build temporarily removing the Vite manifest. With build finished, the homepage and all 16 reservation/QR tests passed together on MariaDB (17 tests / 59 assertions); independent booking concurrency passed again. Final SQLite reservation/QR checks also passed (16 / 58). Three feature browser flows passed after menu pause/cutoff changes; the two affected QR/map flows passed again after final labels and the 60-minute setting/reload assertion. Eight frontend tests, build/TypeScript, ESLint and Linux Pint (176 files) passed. All current backend tests have passed; no full MariaDB rerun after the isolated manifest retry is claimed. No payments or deployment were added.


Earlier increment: **October 26, 2026**. The user requested staff administration, full restaurant branding, webhooks, Romanian-default/optional-English interfaces, updated documentation, tests and local commits, with **no push, tag or deploy**. The subsequent GitHub Actions Pint failure is included in this increment.

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

## Forge email-delivery incident and lifecycle logging — October 26, 2026

Live browser SSH inspection on food.zion3d.ro found APP_ENV=test, a running Horizon master but no supervisors, three pending email jobs, no delayed/reserved jobs and zero quota reservations. Configuration had the Resend mailer/key. The missing Horizon environment plan was the cause; no Resend request had been attempted. Applied a site-scoped local three-worker recovery plan to the current Horizon master without modifying the environment or another site's processes. Supervisor count became one, queue size became zero, quota used became three. The Resend dashboard showed all three recovered ZionFood test emails as Delivered. This establishes provider-reported delivery, not independent inbox inspection.

Persistent source fix: wildcard Horizon environment plan after explicit production/local plans. Added zionfood:mail-status to distinguish a running master from matching active workers. Added safe delivery lifecycle logs to daily mail-delivery files and PHP error_log, including provider acceptance ID, queue failures, retry/defer/final failure and correlation ID. Existing encrypted queued jobs stay compatible. Diagnostic logging cannot recursively create alert emails or cause an accepted message to retry. Detailed worker/FPM log destinations, safe failure classifications and recovery steps are in DEPLOYMENT.md.
Current verification: 103 backend tests / 364 assertions, six frontend tests, ESLint, TypeScript/Vite build and Linux Pint across 148 PHP files passed. Added regressions for custom-environment worker provisioning, production-plan precedence, actual Resend acceptance ID logging, safe provider failure classification, missing workers and logging failures after acceptance. Local status inspection correctly reports the development log mailer as non-delivering. No browser UI source changed in this increment.

## Guided setup implementation — October 26, 2026

The user requested live GloriaFood setup analysis and direct implementation. The authenticated reference was inspected read-only: basics, pickup, delivery/advanced options, reservations, on-premise services, operating hours, scheduled orders, taxation/payment methods, order-taking app/alert calls, menu editor, publishing/legal and reservation deposit/provider explanations. No reference settings, subscriptions, legal forms or payments were submitted. The user chose future payment policies per restaurant among all three models (fixed reservation deposit, per-guest deposit, full menu/package prepayment), then explicitly postponed actual payments to a menu placeholder.

Added an additive private location setup profile/revision, management-authorized setup Form Request/controller/domain Action, live-data progress/readiness, grouped RO/EN wizard and compact mobile navigation. New onboarding enters the wizard; existing users retain their dashboard and acceptance state. Setup saves basic location details, capacity/table-area plans, live pickup/delivery switches, draft on-site/scheduled/reservation preferences and private legal/policy data. Existing menu/branding/hours/delivery/staff editors are reused. Publish checks actual required data; the payments step is only a visible coming-soon placeholder. No customer reservation engine, QR ordering or payment collection is claimed. See RESTAURANT_SETUP.md for the inspected/reference matrix, decisions and precise boundaries.

The local MariaDB migration ran without resetting application data. Browser fixtures create isolated local test restaurants and preserve existing users. New backend tests cover management/tenant isolation, private profile visibility, revision conflicts, unique table codes/capacity, reservation bounds, unsafe/invalid fields, payment writes being unavailable and publication blockers. An initial browser check exposed duplicate success messages; the redundant local status was removed in favor of the shared workspace flash. Selectors were made explicit for the seating mode and reservation switch. Native Windows MariaDB test subprocesses could not connect, so MariaDB verification was moved to WSL rather than claiming native coverage.

Verification: 110 backend tests passed (419 assertions) on SQLite and the dedicated MariaDB database through WSL; six frontend tests, ESLint, TypeScript/Vite build and Linux Pint across 154 PHP files passed. The dedicated Playwright setup flow verifies persistence/reload, table totals, planned reservation saving, unavailable payment controls, RO/EN and desktop/mobile overflow. The final screenshots were visually inspected; input sizing was corrected after the initial desktop screenshot exposed a numeric-field overflow. No production migration, push or deploy is included in this setup increment.

Follow-up scope decision: the user confirmed off-site should also include optional catering/events at another address, chosen by each restaurant. Added an off-by-default per-location catering-planned switch, persistence/toggle/tenant tests and browser reload verification. No event checkout or payment collection is enabled. This follow-up remains local only.
Final optional-catering checks: 111 backend tests / 426 assertions passed on SQLite; eight current setup tests / 62 assertions passed on MariaDB through WSL after the earlier full 110-test MariaDB pass. Six frontend tests, ESLint, TypeScript/Vite build and Linux Pint (154 PHP files) passed. The complete setup browser flow also verifies off-by-default catering opt-in and reload persistence. No push or deploy was performed.
