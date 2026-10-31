# Scope at 0.0.42

0.0.42 adds a default-off logo import choice with current/proposed previews, private staging, explicit approved replacement and guarded rollback. A distinct typed logo must be exposed by the public response; no theme/product fallback is inferred. An absent/unsupported logo or failed download is optional and keeps current branding intact. The captured real response used locally has no distinct logo; positive logo cases use controlled fixtures pending validation against a real provider account that exposes one.

0.0.41 adds opt-in current/proposed restaurant-setting comparisons to public-menu imports: shared name/phone, location address/pin, weekly hours/time zone and append-only delivery zones. All choices default off. Approval applies only selected valid fields transactionally, preserves existing zones and local edits, guards stale previews and records encrypted rollback evidence. Unsupported source structures are unavailable individually. Existing imports without a public link or selected settings keep their data.

0.0.40 optionally enriches Fetch Menu imports with photos from a public FoodBooking link. It retains every source product/category, verifies menu/restaurant/currency, matches exact product IDs with available-name checks and stages bounded queued downloads in private storage. Preview/progress updates automatically; images become public only with approved publication. An empty link preserves the previous flow. Public restaurant name/address are informational and do not overwrite location settings.

0.0.39 accepts GloriaFood menus without restaurant_id, retaining stable organization/location-scoped source lineage and explicit local-edit decisions across reimports. Ambiguous source histories and stale identity aliases block publication. Validation diagnostics have translated titles and separate field/rule labels. Real production import acceptance remains pending a new authorized attempt.

0.0.38 applies direct field-to-field Tab navigation across shared and inline forms, retaining hover/screen-reader/F1 help and non-field keyboard explanations. Registration uses friendly quick-verification copy. GloriaFood validation failures expose bounded sanitized field/rule diagnostics for new imports; the specific cause of older production failures requires a fresh attempt because the prior payload and credential were not retained.

0.0.37 improves registration retry and keyboard navigation, with external press-and-hold password controls across the shared auth form. Registration errors retain both password fields in current Vue state; passwords remain excluded from server old-input sessions. Mouse/touch/F2 revelation is temporary, with RO/EN guidance and automatic remasking.

0.0.19 introduces a dish-first automatic generation workspace, combined AI tasks, approximate one-portion nutrition and durable live review notifications. It replaces the earlier manual reference calculator and retires the manual GloriaFood JSON metadata workflow. Published data remains local and requires review. Whole-menu GloriaFood migration, approved publication, reimport decisions and guarded rollback are implemented in 0.0.22; see MIGRATION_CENTER.md. Provider fidelity and estimation quality still require validation with a real authorized restaurant.

## Implemented

- Fortify registration/login/logout, verification and password-reset pages with PrimeVue.
- Onboarding creates organization, owner membership, restaurant, location, hours, fulfillment defaults, main menu, subdomain mapping and 14-day trial.
- Explicit organization/location authorization, Policies and composite tenant foreign keys.
- Restaurant dashboard, location switcher, orders/history/detail drawer including saved rejection reasons in status tooltips and details, categories, item create/edit/soft deletion, image uploads, variants and modifier groups.
- Same-day opening hours, pickup and polygon/postal-prefix delivery zones with authoritative fees and minimums.
- Public menu, product customization, cart, checkout, server pricing, snapshots, idempotency and secure polling-based tracking.
- KDS accept/reject/prepare/ready/dispatch/complete/cancel workflow, optimistic concurrency, private realtime invalidations, sound after user activation, polling/reconnect recovery and fullscreen control.
- Transactional outbox, after-commit dispatch, retries and scheduler reconciliation.
- Organization/trial platform overview, admin setup command and protected Horizon access.
- Versioned public/integration APIs; organization-scoped tokens with abilities, expiry, listing and revocation.
- Staff administration with protected roles, location assignments, expiring/revocable invitations and verified-email acceptance.
- Guided RO/EN owner/manager setup with private location profiles, capacity/table-area planning, reservation preferences, publication readiness and existing-editor navigation; payments placeholder only.
- Restaurant identity, managed slug, tagline, logo/cover, action colors, public contacts, preview and branded tracking.
- Romanian-default and English interfaces with persistent flag/name selectors across all layouts.
- Signed per-location webhooks for order/menu/item events, secure destination resolution/pinning, durable delivery history, retries and reconciliation.
- MariaDB/Redis local Compose services, three restaurant demos, factories, automated tests, OpenAPI and configurable k6 scenarios.

- Transactional capacity/table reservations, expiring holds, private guest receipts/cancellation, staff booking management, QR sessions/dine-in orders and drawn delivery areas. See RESERVATIONS_DINING_DELIVERY.md.

- Global bilingual cuisine/photo catalogue and separate restaurant selection step, with availability management, activation-date feedback and bundled image attribution.

- Bilingual central-host presentation page with AI visuals, responsive CTAs, keyboard feature previews, local order demo and reduced-motion animation support.

- Restaurant setup address lookup with missing-field feedback, candidate selection and adjustable saved pin; no numeric coordinate inputs.

## Remaining before production launch

- Implement catering/event fulfillment, split/overnight/per-service/holiday schedules and payment processing. Reservations and QR ordering are implemented and independently enabled per location.

- Complete Forge deployment and external DNS/TLS/mail configuration, including verified live subdomains.
- Complete multi-restaurant/multi-location creation screens. Onboarding creates one of each; the schema and location switcher support multiple records.
- Implement the separate WordPress plugin; provision custom domains and SSL after operational verification.
- Harden billing/subscription lifecycle beyond local trial entitlement. No payment collection.
- Define privacy retention, idempotency cleanup, outbox archival, backup/restore and incident procedures.
- Persist carts across full refresh if desired and add a portable customer tracking flow. Interface languages remain Romanian/English; optional menu-content translations are persisted and reviewed through the 0.0.17 editor.
- Extend browser coverage of reset/verification emails, delivery flows, dark mode, touch sound/fullscreen and accessibility; real SMTP remains unverified.
- Validate capacity on the intended production runtime. Local load results must be read with their precise test conditions; notification p95 and sustained outage recovery are not production guarantees.

No inert P0 buttons are intentionally exposed. Future integrations are described as unavailable rather than presented as functioning services. See CODEX_PROGRESS.md for exact measured verification and outstanding work.

Restaurant navigation groups Operations, Restaurant management and Connections, with Setup first below the location selector. Real hours, delivery, menu, branding and staff editors stay within the wizard; existing accessible locations can be selected/configured there. Location creation/deletion and organization transfer are not part of this change. See ADMIN_NAVIGATION.md.

Products support ordered catalogue ingredients, optional portion labels and explicit allergen/frozen metadata, with RON entry and integer minor-unit persistence. Shared and restaurant-owned ingredient management is implemented, with source-count evidence and automatic idempotent seeding. Nutrition computation, ingredient quantities, supplier traceability, complete PDF-product import and legal compliance certification are outside this increment. See INGREDIENTS.md.


## Storefront experience (0.0.12)

Implemented storefront polish: restaurant-specific share metadata, product/serving/ingredient redesign, compact image cart, persistent modal action footers, and editable delivery-pin reverse lookup. The allergen notice now references Regulation 1169/2011 with an expandable fourteen-group summary. Actual messaging-platform preview/cache behavior and provider geographic coverage remain operational checks.


0.0.15 adds slug-based restaurant entry points, branch selection, explicit guest QR unavailability pages, disabled-control guidance, ANAF company autofill in legal setup, and invitation-aware employee registration. Payments remain placeholders. ANAF has no required API credentials, so no platform API-key settings screen is introduced. Provider availability and complete data coverage are not guaranteed.


0.0.16 adds individual five-module feature permissions, invitation carry-through, view-only interfaces, independent active kitchen access, order status controls, scoped bulk table/room operations and effective QR availability with shared schedule/cutoff explanations. Owners remain full; existing null permission matrices retain legacy behavior. Time-based restrictions remain active per the user's explicit choice. Payments remain placeholders.

0.0.20 adds feature/location-scoped RestaurantOverview analytics, approximate daily menu-visit aggregates, compact effective table-state controls and reusable FieldHelp across forms. Definitions deliberately distinguish order value, reservations, QR sessions and approximate visits from receipts, physical occupancy and unique people. Successful generation review closes automatically; failed writes remain open. See [RESTAURANT_ANALYTICS.md](RESTAURANT_ANALYTICS.md).

0.0.22 completes the documented compatibility matrix: category/variant groups, quantity/default semantics, structured food metadata and product service restrictions. The Migration Center imports full menus, not the retired single-product manual food JSON workflow. Currency conversion, XML/CSV/XLSX/PDF extraction, customer/order-history import and remote photo downloads are outside this GloriaFood API-focused increment.

0.0.23 adds shared active table-session receipts inside the QR menu, automatic status refresh, reload recovery and stay-on-menu dine-in checkout. Ready orders stay visible until completed; terminal orders are removed individually. Existing read-only receipt capabilities survive stopped ordering without allowing new orders or exposing earlier sessions to new guests.

0.0.24 adds staff table active counts/daily order value, paginated snapshot details and authorized serving of ready orders; per-location date/time/public display controls with embedded setup; reservation return navigation; and persistent manual-reset error-alert suspension after five admissions/delivery attempts per rolling hour. Per-item serving, actual receipts/payment accounting and a verified live Reverb transport repair remain outside the verified delivery.

0.0.25 bounds outbox delivery retries across jobs/scheduler, retains exhausted events for explicit recovery, sanitizes propagated Reverb errors and adds a read-only DNS/configuration command. Live Forge DNS/listener/proxy correction and end-to-end websocket verification require the actual server topology and are not implied by the source release.


0.0.26 adds platform-admin outgoing-email metadata, source/initiator/status filters, automatic refresh, existing encrypted configured-queue indexing and provider UTC quotas/usage controls. It retains the 140/24h rolling guard and independent error-alert suspension. Sent-history reconstruction, Resend delivery/bounce webhooks and exactly-once delivery remain outside this increment. See EMAIL_DELIVERY.md.


0.0.27 adds raw-HTML marketing content without a runtime Node service, deterministic RO/EN URLs, canonical/hreflang/social/structured metadata, sitemap/robots routes, private noindex headers, WebP assets and documented GloriaFood migration marketing. Public-host Nginx forwarding, DNS/TLS checks, webmaster-property verification/submission and indexation/ranking remain external launch work.

## Implemented in 0.0.28

Public online and table menus show venue open/closed state, configured closing/next opening and last-hour notice. General settings, including embedded setup, expose default-on online schedule/table protection. The table condition applies only where QR ordering is enabled; disabling protection permits cart preparation only outside hours; submission, manual stop and QR rules remain enforced. Live availability, cart/footer explanations and preserved active receipts are included. Overnight schedules remain outside the existing same-day schedule model.

## Platform identity at 0.0.30

Supplied ZionFood multi-resolution PNG mark across public presentation, account flows, platform/tenant/kitchen shells, storefront attribution, browser/touch icons, HTML email headers, migration reports and Organization SEO metadata. Restaurant-uploaded identity is preserved.

## Search crawl policy and documentation branding at 0.0.31

Professional host-aware robots rules permit public pages and render assets with specific read-only menu-data exceptions, preserve crawlable public noindex pages, append the marketing sitemap only where applicable and block dedicated API/disabled-indexing hosts. The README promotional illustration now uses the supplied ZionFood logo and the Romanian CTA “Dă gust afacerii tale!”, with a working banner link. Production robots accessibility and actual indexation require post-deployment verification.

## Initial landing rendering at 0.0.32

Initial HTML links the actual landing stylesheet, with bounded hidden-until-styled SVG decorations, intrinsic flag sizes and font-ready icons. The public text and links remain available when external CSS/JS/fonts fail. Browser coverage includes styled no-JavaScript, blocked CSS+JS and failed icon fonts.

## Security assessment at0.0.33

Implemented local regression-tested repairs for restricted-manager operational switches, multiplicative import verification work and public account email admission/shared delivery capacity. Kept the requested public test-email diagnostic while removing private recipient disclosure. See security/2026-10-29-local-audit.md. Full production/infrastructure/provider and exhaustive third-party package security certification remain outside this local assessment.

## Registration verification at 0.0.34

Implemented a local receipt CAPTCHA, numeric keypad, RO/EN feedback, accessible text alternative and server-bound expiring one-use proof. Ordinary signup requires it; proven staff invitations keep their direct flow. Existing email verification/rate/quota controls remain. This is a lightweight anti-automation layer, not proof against sophisticated bots; see REGISTRATION_CAPTCHA.md.


0.0.35 prepares a dedicated WordPress API and restaurant-scoped credentials/logs. It adds opt-in scheduled pickup/delivery, confirmed-reservation preorders and four coupon/automatic promotion types with server pricing and capacity checks. The separate WordPress plugin, all-promotion-template parity, marketing/mobile/printing, real payments and production WordPress/network validation remain outstanding. See [WORDPRESS_CAPABILITIES.md](WORDPRESS_CAPABILITIES.md).
