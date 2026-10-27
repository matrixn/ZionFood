# Scope at 0.0.20

0.0.19 introduces a dish-first automatic generation workspace, combined AI tasks, approximate one-portion nutrition and durable live review notifications. It replaces the earlier manual reference calculator and retires the manual GloriaFood JSON metadata workflow. Published data remains local and requires review. Full automated whole-menu migration, reimport and rollback remain future work in MENU_CONTENT.md; provider/estimation quality must be validated with a real authorized restaurant.

## Implemented

- Fortify registration/login/logout, verification and password-reset pages with PrimeVue.
- Onboarding creates organization, owner membership, restaurant, location, hours, fulfillment defaults, main menu, subdomain mapping and 14-day trial.
- Explicit organization/location authorization, Policies and composite tenant foreign keys.
- Restaurant dashboard, location switcher, orders/history/detail drawer, categories, item create/edit/soft deletion, image uploads, variants and modifier groups.
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

- Implement catering/event fulfillment, scheduled fulfillment, split/overnight/per-service/holiday schedules and payment processing. Reservations and QR ordering are implemented and independently enabled per location.

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
