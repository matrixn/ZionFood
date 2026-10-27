# Changelog

## 0.0.17 — 2026-10-27

- Add reviewed structured allergens, trace declarations, dietary labels and recipe-based nutrition with explicit units, source references, cooked yield and unknown-value handling; disable AI nutrition estimation.
- Persist editable multilingual menu text locally, including variants/modifiers; hide stale translations and retain authoritative IDs, prices and checkout rules.
- Add encrypted, platform-admin-configured Groq/OpenAI credentials, global queue pacing/budget and guarded AI translation/rewrite/suggestion drafts with explicit publication.
- Stage GloriaFood Fetch Menu v2 food metadata with named mappings, source evidence and unresolved-field reports; preserve the full Migration Center compatibility/reimport/publication plan separately.

## 0.0.16 — 2026-10-27

- Per-employee none/view/manage permissions for orders, reservations, tables/QR, kitchen and menu, with hidden navigation, read-only controls and server/domain/policy checks.
- Permission editing on invitations and memberships, owner protection, delegation ceilings and shared-menu location checks; separate active kitchen API from order history.
- Atomic table/room bulk activation and closure with permanent QR preservation, session invalidation and green/red effective availability borders.
- Explain schedule/cutoff restrictions consistently to staff and QR guests; preserve the configured ordering window and refresh table availability automatically.

## 0.0.15 — 2026-10-27

- Use `/restaurant/{slug}` public menus with legacy-link redirects, scoped location selection for multi-location restaurants and verified-host isolation.
- Explain disabled/closed QR table ordering with a guest page; give disabled staff controls tooltips and a management link to activate the location service.
- Put CUI first in legal setup and resolve public ANAF v9 company details into editable private fields, with bounded cached transport, provider-wide rate protection and manual fallback.
- Link guest registration to a proven emailed staff invitation: prefilled locked email, transactional membership/verification and direct assigned-workspace access. Ordinary registration retains email verification.

## 0.0.14 — 2026-10-26

- Replace repeated restaurant-title share descriptions with a Romanian menu call to action, with an English translation for English pages; retain branding descriptions in the menu itself.
- Restore compact per-dialog desktop widths while retaining viewport bounds and persistent action footers.
- Record the user's standing authorization for automatic version increments, validation, commits and pushes.

## 0.0.13 — 2026-10-26

- Generate a separate cached 600px square PNG for restaurant sharing, containing the full proportionally scaled logo on white with 60px minimum padding. Existing uploads work without re-upload.
- Set Open Graph image dimensions/type, version derivative paths by layout/source upload and remove obsolete derivatives after branding changes.
- Require PHP GD for PNG/JPEG/WebP processing and enable it in CI.

## 0.0.12 — 2026-10-26

- Restaurant-specific server-rendered Open Graph metadata with its logo and localized Digital Menu title.
- Redesigned public product cards/details: large modal photo, labelled serving, no duplicate title or title stars, compact parenthesized ingredients and persistent quantity/total/add controls.
- Compact cart rows with photographs; scrollable dialog bodies and persistent action footers across checkout and restaurant editors.
- Delivery-pin address autofill in street, number, locality format with manual edits, stale-response protection and bounded public lookup.
- Subtle bilingual allergen panel with expandable fourteen groups and current Regulation (EU) 1169/2011 reference.

## 0.0.11 — 2026-10-26

- Added the deduplicated 183-ingredient PDF catalogue, source-count audit, independent allergen/frozen flags and review markers, with positive ties kept enabled by user choice.
- Added protected global and restaurant-owned ingredient management, hidden/deleted selection rules and preservation of existing recipe information.
- Products now support ordered ingredients, portion labels and explicit flags; the editor accepts RON prices while APIs and checkout retain integer minor units.
- Public menus and product details display portions, ingredients and combined flags, with the requested notice and an updated legal-reference note.
- Added an additive, automatically seeded migration and an idempotent seeder preserving administrator changes.

## 0.0.10 — 2026-10-26

- Expanded the Romanian README with the inline-editor table, wizard save/navigation behavior and the 0.0.9 implementation commit.
- Synchronized current version metadata without runtime or dependency changes.

## 0.0.9 — 2026-10-26

- Moved restaurant setup directly below the location selector and grouped navigation into Operations, Restaurant management and Connections, with nested active states and scrollable mobile navigation.
- Embedded hours, delivery zones, menu, branding, staff and accessible locations in the setup guide, reusing protected editor data and save routes. Saves stay on the current step; embedded editors warn on unsaved navigation.
- Added separate Locations navigation for accessible locations and retained standalone editors; no location-creation, payment or schema changes.

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
