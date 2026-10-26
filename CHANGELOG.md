# Changelog

## 0.0.8 — 2026-10-26

- Added a Romanian README usage guide for address-from-pin, saving the location, delivery-map zoom/recentering, ambiguous addresses and polygon saving.
- Linked the 0.0.7 implementation commit and synchronized current version metadata without changing runtime behavior or dependencies.

## 0.0.7 — 2026-10-26

- Added address-from-pin lookup with structured address filling, partial-address feedback, stale-response protection and preservation of the chosen pin.
- Delivery zones now zoom to the saved restaurant pin, show a restaurant reference marker and a recenter button, and resolve saved addresses without a pin once per settings visit.
- Shared bounded Photon transport, cache/cooldown and tenant authorization cover both lookup directions; no schema or dependencies changed.

## 0.0.6 — 2026-10-26

- Expanded the README checked inventory for restaurant address lookup, required-field feedback, result selection, map adjustment/saving and stale-pin invalidation.
- Synchronized current version metadata for the documentation commit; runtime behavior and dependency versions are unchanged.

## 0.0.5 — 2026-10-26

- Replaced numeric coordinate fields in restaurant setup with a Locate restaurant button, required-address feedback, address candidates and an adjustable saved map pin.
- Added tenant-protected, cached and rate-limited Photon address lookup with bounded transport and localized provider-error/approximate-match feedback.

## 0.0.4 — 2026-10-26

- Replaced the public central-host welcome page with a responsive Romanian/English marketing presentation, generated food/kitchen visuals, clear CTAs, feature previews and an interactive kitchen demo.
- Added motion-aware scroll/hover effects, keyboard tabs, mobile navigation, FAQ and generated-asset provenance; restaurant storefront routing remains unchanged.

## 0.0.3 — 2026-10-26

- Rebuilt the Romanian README with a checked implementation history, feature inventory, outstanding work and explicit verification boundaries.
- Added a generated ZionFood brand illustration, embedded project-local image and its final prompt/provenance.
- Synchronized package/application/API/documentation versions without changing dependencies.

## 0.0.2 — 2026-10-26

- Synchronized application/package/API versions and established a patch increment for each new commit.

- Dedicated cuisine selection step after name/address, with 27 illustrated bilingual categories, multiple selections, saved activation dates and platform catalogue/photo/availability management.

- Actual per-location reservations with capacity/table allocation, expiring holds, private guest receipts/cancellation and versioned staff management.
- Permanent table QR with persistent staff menu pause/reopen, optional activation policy and configurable closing cutoff; authoritative dine-in checkout and table snapshots in the kitchen.
- Leaflet/Geoman delivery polygon editor, customer delivery pins and server-selected zones/fees/minimums; configurable tile provider. Payments remain placeholder.

- Guided RO/EN restaurant setup with private location profiles, table/capacity planning, draft reservation rules, optional catering preparation, live service controls, readiness checks and a payments placeholder.
- Fixed Horizon worker provisioning for custom Forge environments; added mail-status diagnostics and safe PHP/file lifecycle logs with Resend acceptance confirmation.
- Temporary public RO/EN /test-email page targeting ADMIN_EMAIL with CSRF protection and three-per-hour test throttling; authentication/admin restrictions removed at the user's explicit request.
- Global encrypted mail queue, atomic rolling 140-recipient/24h Redis quota with deferred overflow, and deduplicated error/exception email alerts via ADMIN_EMAIL.
- Native Resend email transport SDK, RESEND_API_KEY configuration, Forge instructions and a validated Artisan mail test command.
- Staff invitations/acceptance/revocation, protected owner/manager roles, location assignments and scoped token removal.
- Restaurant branding, asset uploads/removal, managed slug, contacts, live preview and public menu/tracking theme.
- Romanian-default and English interfaces with persistent flag/name selectors, localized PrimeVue/validation/status labels and stable API field names.
- Signed webhook configuration, tenant/location fanout, cURL DNS pinning and SSRF restrictions, leases, bounded retry/history and scheduler reconciliation.
- Fixed stale CSRF headers after Inertia login, applied confirmed kitchen responses before background reconciliation and corrected the reported Pint style issue with Linux CI verification.


## 0.0.1 — 2026-10-25

Initial local MVP implementation: tenant-aware onboarding and authorization, PrimeVue restaurant workspace and storefront, menu configuration, transactional checkout and order snapshots, idempotency, private realtime kitchen operations with outbox recovery, trial entitlements, API v1 and organization-scoped integration tokens.

Includes MariaDB/Redis development setup, local demo restaurants, automated backend/frontend/browser/concurrency checks, OpenAPI and measured k6 scenarios. See docs/MVP_SCOPE.md and docs/CODEX_PROGRESS.md for remaining features and verification limits. No production deployment is included.
