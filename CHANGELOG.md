# Changelog

## 0.0.42 — 2026-10-31

- Optional restaurant logo choice in public-link migration previews, default off, with existing/proposed images and shared-location disclosure.
- Bounded private staging reuses verified CDN transport and WebP decoding, preserving transparency and proportions. Explicit approval alone publishes the logo; previous files remain for guarded rollback.
- Missing/unsupported logos and failed downloads leave existing branding unchanged. Older staged settings remain compatible; product-photo counters exclude logos.

## 0.0.41 — 2026-10-31

- Add opt-in restaurant name, address/map position, phone, opening hours/time zone and delivery-zone proposals from the verified public menu. Compare current/proposed data and zone outlines before explicit publication; defaults remain unchecked.
- Append delivery zones only, preserving existing rows and local edits. Persist external zone mappings and deduplicate equivalent entries on reimport. Reject unsupported schedules, polygons or rates per field without silently approximating them.
- Apply selected settings with the menu transaction, repeat permissions and stale-preview checks, archive before/after evidence and support guarded rollback. Add an encrypted nullable publication-settings snapshot migration. No dependency changes.

## 0.0.40 — 2026-10-31

- Add an optional public FoodBooking menu URL to Migration Center. Keep Fetch Menu products/prices authoritative; verify source identity and attach photos by exact product IDs, additionally checking names when available. An empty URL keeps the previous import flow.
- Stage photos privately in bounded queued chunks, with automatic preview progress and explicit matched/downloaded/missing/failed/limited counters. Validate raster images, use density-aware source variants, re-encode WebP and publish only on approval while preserving existing photos on reimport.
- Use fixed provider/CDN destinations with public DNS validation, cURL address pinning, verified TLS, disabled proxies/redirects and response/image/traffic limits. Add guarded tenant-only previews, failed-job recovery, temporary-file pruning and regression coverage. No dependency or database-schema change.

## 0.0.39 — 2026-10-31

- Accept missing, null or empty GloriaFood restaurant IDs, resolving a menu-based namespace against published import history scoped to organization/location. Keep source snapshots unchanged and reject malformed nonempty IDs.
- Preserve source lineage, nutrient mappings and local-edit conflict handling across optional-ID changes and legacy imports. Block ambiguous histories and stale identity aliases at publication.
- Translate source-validation issue titles and separate field/rule diagnostics in Romanian and English. Add provider-fetch, reimport, isolation and browser regressions; no dependency or database-schema changes.

## 0.0.38 — 2026-10-31

- Replace access-receipt wording with friendly quick-verification copy in Romanian/English, including backend messages.
- Skip field-help Tab stops across all forms while preserving hover, screen-reader descriptions, F1/Escape help and keyboard access to non-field explanations.
- Persist bounded GloriaFood validation paths/rules for failed API/file imports and expose them in authorized previews/logs without source values or credentials. Existing failed batches require a new import to collect diagnostics.

## 0.0.37 — 2026-10-31

- Keep authentication form help available without adding Tab stops before each field; associate persistent screen-reader descriptions with auth and CAPTCHA inputs.
- Preserve registration passwords after validation errors and clear them after successful registration, retaining existing login/reset error cleanup and server-side password flash exclusions.
- Add adjacent external press-and-hold password reveal controls for mouse, touch and F2 keyboard use, with immediate masking on release, pointer leave/cancellation, blur, visibility changes and submission.

## 0.0.36 — 2026-10-30

- Add Romanian/English No expiry selection for WordPress credentials using expires_in_days=0 and a null Sanctum expires_at.
- Preserve bounded rotation grace and revocation for non-expiring keys; existing finite options and schema remain unchanged.

## 0.0.35 — 2026-10-30

- Dedicated restaurant/location-scoped WordPress credentials with selectable abilities, expiry, safe rotation and revocation.
- Versioned WordPress API for menu management, authoritative quotes/checkout, reservations, tables/QR, operations, promotions, analytics and event reconciliation.
- Opt-in scheduled orders and confirmed-reservation preorders, slot capacity/notice/horizon and timed kitchen admission.
- Four server-priced coupon/automatic promotion kinds with transactional usage limits and immutable discount snapshots.
- Sanitized inbound/outbound communication journals, restaurant visibility setting and platform-wide filters; callback correlation and fresh credential/permission checks.
- RO/EN management screens, FieldHelp, OpenAPI and explicit capability report. WordPress plugin, payments, marketing/mobile/printing remain separate.

## 0.0.34 — 2026-10-29

- Added a local receipt-themed registration CAPTCHA with numeric keypad, visual confirmation and an accessible text alternative in RO/EN.
- Enforced short-lived session-bound one-use challenges/proofs and Redis locks before registration effects; preserved proven staff-invitation onboarding.
- Added challenge/submission throttling, private nonindexable responses and sensitive-input flash exclusions; invalid registration/captcha attempts no longer consume account-mail admission capacity.
- Added security, localization, expiry/replay and isolated browser regression coverage; documented the limits of a proprietary anti-automation layer.

## 0.0.33 — 2026-10-29

- Fixed restricted-manager operational-switch authorization in settings/setup against the locked location state.
- Bounded aggregate GloriaFood verification evidence before allocation/persistence, with explicit failure and complete ordinary reports.
- Added shared account-workflow admission and account-email delivery budgets, respecting known provider caps and preserving other transactional capacity.
- Hid the administrator recipient on the deliberately public, fixed-recipient, globally rate-limited email-test page.
- Added isolated local security regression/browser checks and a scoped audit/remediation report.

## 0.0.32 — 2026-10-29

- Linked landing-page CSS in the initial server-rendered head using current build-owned stylesheet metadata.
- Bounded decorative SVG/flag sizes, hid decorations until styled and gated icon glyphs on successful font loading without hiding readable page content.
- Added blocked CSS/JavaScript/font and no-JavaScript styled-page regression coverage.

## 0.0.31 — 2026-10-29

- Added a reviewable robots.txt source and host-aware policy for public pages, render assets, public-menu API exceptions and protected operations; noindex public pages remain crawlable.
- Preserved canonical sitemap/readiness controls, blocked dedicated API/disabled-indexing hosts and prevented shared caching of host-specific policies.
- Regenerated the README banner with the repository logo and Romanian CTA, linked the banner to ZionFood and recorded image-generation provenance.

## 0.0.30 — 2026-10-29

- Integrated the supplied ZionFood PNG icon set through a shared responsive logo across presentation, authentication, restaurant/platform/kitchen shells and storefront attribution.
- Replaced the legacy favicon, added touch icons, Organization logo metadata, notification-email branding and migration-report branding; restaurant logos and public menu share previews retain restaurant identity.

## 0.0.29 — 2026-10-29

- Display saved kitchen rejection reasons beside the orders status in an accessible tooltip and directly below the status in order details.
- Reuse authorized order history and existing RO/EN labels, with no schema or API changes.

## 0.0.28 — 2026-10-29

- Added public opening/closure/next-opening banners and final-hour ordering notices for online and QR menus, using the location timezone and actual QR cutoff.
- Added default-on location ordering protection with authorized/revisioned settings; disabling protection permits cart preparation only; submission retains schedule/table checks, as do QR and other checkout rules.
- Added fresh scoped availability polling, live cart/configurator/checkout guards and explanations without discarding carts or submitted orders.

## 0.0.27 — 2026-10-28

- Added build-rendered public landing content, deterministic Romanian/English URLs, canonical/hreflang metadata, JSON-LD and social sharing tags available without JavaScript.
- Added canonical-host sitemap/robots routing, private-route noindex headers, SEO readiness checks and safeguards for local/disabled/missing-build indexing.
- Added GloriaFood migration content, verification/publication steps and explicit transfer limitations, plus responsive WebP presentation assets and ZionFood favicon.

## 0.0.26 — 2026-10-27

- Added a platform-admin outgoing-email ledger with source/initiator/status/recipient/date filters, live polling, metadata details and encrypted private fields.
- Added UTC Resend daily guards, typed provider daily/monthly/rate pauses and read-only account usage synchronization; retained mail stays queued without repeated provider quota exceptions.
- Indexed existing encrypted jobs without sending or modifying queues; protected expired new auth links and outdated invitation/reservation messages, with 90-day terminal metadata retention.
- Documented the separately confirmed Reverb DNS cause and internal-publisher recovery, keeping public browser WSS verification open.

## 0.0.25 — 2026-10-27

- Bounded outbox delivery across scheduler/worker duplicates using persistent per-cycle attempts, due times and exhaustion state; legacy exhausted rows are retained and quarantined.
- Added explicit per-event retry preserving lifetime history, plus a credential-free Reverb DNS/configuration check.
- Removed signed transport URLs from propagated Reverb exceptions while retaining safe diagnostics and webhook fanout.

## 0.0.24 — 2026-10-27

- Added persistent, platform-admin-controlled error-alert suspension at the sixth alert/attempt in a rolling hour; queued old generations are discarded on manual resume, ordinary mail keeps its global quota.
- Added permission-scoped table order summaries, daily value, snapshot details and versioned ready-to-served actions.
- Added location date/time/display preferences with embedded setup editing and public-payload filtering; reservation receipts link back to the menu.
- Added redacted broadcast diagnostics and optional internal Reverb publishing endpoint variables; no live transport fix is claimed.

## 0.0.23 — 2026-10-27

- Added session-authorized active table receipts, snapshot products/options/quantities/totals, automatic polling and refresh recovery.
- Dine-in checkout stays in the QR menu; repeat identical orders receive fresh idempotency keys. Pickup/delivery retain the tracking-page flow.
- Ready orders remain visible until completed; terminal orders disappear individually. Existing receipt access survives stopped ordering while new sessions cannot read previous parties.

## 0.0.22 — 2026-10-27

- Added GloriaFood Migration Center: fixed-endpoint Fetch Menu client, encrypted staging, preview, field/rule issues, nutrient-ID resolution, source diff, reference baskets and JSON/printable PDF reports.
- Added approved location-specific menu publication, source mappings, reimport with local-edit decisions, guarded rollback and stale-secret/draft retention.
- Completed category/size modifier inheritance, optional/required bounds, quantities, default selections, size nutrition, product fulfillment restrictions and kitchen snapshots across management, storefront and authoritative checkout.
- Existing orders, menus, ingredient photos and tenant/location authorization are preserved. Real authorized-provider migration still requires production acceptance.

## 0.0.21 — 2026-10-27

- Fixed Linux Pint method_argument_space failure in RestaurantOverviewTest.
- Required an additional fresh-cache Linux Pint audit before the exact CI check; runtime behavior is unchanged.

## 0.0.20 — 2026-10-27

- Added permission-scoped restaurant analytics for local-calendar 7/30/90-day periods, daily charts/tables, product and table rankings, reservations, capacity and menu reach.
- Added approximate menu-visit aggregation with transient 30-minute deduplication, preview-bot filtering and no raw visitor identifiers in the database.
- Added reusable contextual form help throughout the application, preserving explicit input names, keyboard/touch access and scrollable modal layouts.
- Compacted table controls into room selection plus open/close actions; shortened status copy, moved explanations into help and strengthened state borders/icons.
- Successful AI approval now closes its modal and confirms publication with a toast; failed saves remain open. Performed a real Groq notification/nutrition approval on the existing Forge restaurant without changing provider credentials.

## 0.0.19 — 2026-10-27

- Redesigned automatic generation around a searchable dish list, prefilled one-portion inputs, task cards and a persistent generation action.
- Added combined AI proposals including clearly labelled nutritional estimates, per-serving/per-100g normalization and plausibility checks.
- Added durable recipient-scoped notifications, private ID-only realtime invalidations, automatic background polling and a review modal with editable text/food labels and selective approval.
- Retired manual GloriaFood JSON metadata import and reference-by-reference nutrition calculation. Existing published metadata is retained; retired GloriaFood drafts are removed by migration.
- Kept tenant/shared-menu authorization, encrypted drafts/provider keys, global quotas, source hashes and unchanged customer prices.

## 0.0.18 — 2026-10-27

- Fix Groq strict-schema rejection of empty translation/variant/modifier maps by generating schemas specific to translation, rewrite or food suggestions and omitting empty nested maps.
- Restore omitted known-empty maps after translation so saved proposals, review and public/editor payloads retain their existing structure.
- Add recursive wire-JSON schema regression coverage for both providers, every operation, sparse products and empty option groups.

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
