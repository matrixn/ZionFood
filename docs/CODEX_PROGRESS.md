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
