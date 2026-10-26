# ZionFood 0.0.1

Implementation specification: [original ZionFood SaaS MVP master prompt](MASTER_PROMPT.md), consolidated with **Techstack ZionFood MVP**, conversation `6ade5f72-3784-83ed-b9bf-0f23844f3826`. The substantive Vue/PrimeVue architecture discussion was retrieved; some older answers were available only as content-reference markers. Target MVP date: November 1, 2026.

## Stack and boundaries

One Laravel 12 modular monolith on PHP 8.4. MariaDB 10.11 shared schema, Redis cache/queues, Horizon workers, Reverb private broadcasts and Sanctum integration tokens. Fortify provides authentication; the UI is Vue 3 Composition API, Inertia 3, strict TypeScript, PrimeVue 4 styled mode, Tailwind 4 and Vite. Dependency lockfiles are authoritative; newer major versions are deliberately excluded. Fortify was integrated directly into the official Laravel application skeleton to avoid importing unused starter-kit shadcn dependencies.

`app/Domain/Tenancy`, `Menus`, `Orders`, `Restaurants` and `Integrations` hold access rules and reusable Actions. `PlaceOrderAction`, `TransitionOrderStatus` and `UpdateMenuItem` are shared by dashboard interactions and API calls. Other domains are represented by their models and schema; separate empty domain layers are deliberately absent. No repository abstraction, microservices, React, Filament, Nova or MoonShine.

## Tenant resolution

Organization → restaurant → location. Membership roles are `owner`, `manager`, `staff`; staff also need an explicit location assignment. `TenantAccess` is checked in Actions and controllers; Policies support the same rules. No global scope is treated as the security boundary. The dashboard location selector contains only permitted locations. Each public ID resolves to a model, followed by authorization. Platform administration grants access to the organization overview and Horizon, and does not grant tenant operations.

Issued Sanctum tokens have an organization ID, selected abilities and a 90-day expiry. A token cannot escape its organization even when its user owns several organizations. Integration administration requires a browser session. Tokens retain the user's current membership restrictions and may be revoked.

## Ordering and delivery

Public menu retrieval caches the menu tree in Redis for 30 seconds by location public ULID. Menu/category/item/group model changes invalidate organization menu caches after commit. Location availability, fulfillment and hours are fetched fresh; checkout always validates current database state. Direct SQL writes bypass model invalidation and may take up to 30 seconds to appear publicly.

Checkout locks the location row, checks idempotency, verifies availability, opening hours and trial entitlement, then validates assigned menus, products, variants, modifier bounds and delivery zone. It ignores client prices and calculates integer totals. Delivery uses server-selected GeoJSON polygons when configured, otherwise postal prefixes; it does not geocode addresses. Same-day opening intervals are supported; overnight intervals are rejected. Tax basis points are recorded as snapshots, without fiscal compliance claims.

The order, item/modifier snapshots, initial history, idempotency key and outbox message are committed together. Requests with the same key and canonical validated payload return the same order and tracking token; changed payloads return 409. Idempotency records currently have no automatic expiry. Location serialization prioritizes correctness over maximum per-location throughput. Database deadlock retries are enabled.

Status changes lock the order and require the expected version. Allowed transitions are defined in `OrderStatus`; a stale version returns 409. Rejection requires a reason. History cannot be updated or deleted through its Eloquent model. A database administrator can still modify rows directly; this is not cryptographic or database-trigger immutability.

## Realtime and recovery

An outbox row schedules `DeliverOutbox` only after commit. The job publishes a small `order.changed` invalidation over `organization.{organization}.location.{location}.orders`, then marks delivery. Private channel authorization checks both organization and location. Payloads contain event/order public IDs, event type and version, without customer data. Delivery is at least once: if publication succeeds but recording delivery fails, an event can be repeated. KDS refetches authoritative state, so duplicates are harmless.

Failures retain the outbox row, increment attempts, and record only the exception class. Queue backoff and the scheduled `zionfood:outbox` command provide retry/reconciliation. KDS also synchronizes after reconnect, focus, visibility changes, network restoration, manual refresh, and every 15 seconds. Active order pagination is fully traversed. Customer tracking polls every five seconds using a token in the Authorization header; tokens stay in session storage, outside URLs.

## Hostnames and future work

Trusted central hosts, API hosts and verified storefront mappings are separate. `app.zionfood.ro`, `api.zionfood.ro` and verified restaurant subdomains are supported logically. Storefront hosts expose the storefront, tracking shell, public API and CSRF-protected locale preference endpoint. Reserved subdomains cannot be registered. Custom-domain DNS/SSL provisioning, the WordPress plugin and a Tauri wrapper are deferred. Signed webhook delivery is implemented with durable fanout, per-attempt DNS validation, cURL address pinning, response bounds, leases, retries and retained delivery history.

Design tokens and the `ZF*` wrappers provide a shared appearance across Auth, Admin, Restaurant, Storefront and Kitchen layouts. Pinia is limited to cart and kitchen state. The cart is currently held in memory and resets on a full browser refresh; tracking tokens survive refresh within the same browser tab.

References: [Laravel 12 installation](https://laravel.com/docs/12.x/installation), [official starter kits](https://laravel.com/starter-kits), [k6 executors](https://grafana.com/docs/k6/latest/using-k6/scenarios/executors/).

## Administration and language increment

The October 26 increment adds protected owner/manager/staff administration, expiring accepted-email invitations, restaurant identity/assets/contacts, Romanian-first Vue I18n and Laravel messages, and signed per-location webhooks. See [operational details and limits](STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md). Kitchen transitions apply the confirmed API response immediately and reconcile the authoritative queue in the background; older in-flight list responses cannot overwrite a newer confirmed version. Locale changes retain API keys/status enums unchanged. The API/Echo clients read the latest XSRF cookie after session rotation.

## Guided setup

The owner/manager-only setup controller delegates writes to SaveRestaurantSetup and live readiness calculation to SetupProgress. Location setup JSON is private and revision-checked under a row lock. No arbitrary completion flag or requested payment/provider state is persisted. The UI reuses the existing Vue/PrimeVue editors for operational data. Scheduled/catering preferences remain preparation only until their dedicated fulfillment engines exist; payments is a placeholder by explicit user choice. New onboarding opens setup; existing restaurants are not forcibly paused or redirected.

## Reservations and dining

Availability validates local-time rules and allocates either pooled peak concurrent capacity or a single fitting table. Creation, transitions, seating edits and QR operations take the location lock, shared with checkout. Slot previews batch-load a day of bookings/tables and have no capacity guarantee. Booking idempotency, private bearer receipts, hold expiry and optimistic staff versions are independent of order status. Optional encrypted guest notifications reuse the existing after-commit quota-controlled queue.

QR links prove possession through a fragment token exchanged under the rotation lock for an expiring session. Dine-in checkout snapshots the table while preserving server pricing, idempotency, history and outbox. Service flags default off and are location-scoped. Delivery polygons are portable GeoJSON; the browser map previews the PHP zone priority/containment check. See RESERVATIONS_DINING_DELIVERY.md for operational boundaries.

Permanent QR credentials outlive eight-hour sessions. A separate persisted table pause blocks all guest reconnects until staff reopen the menu. OrderingWindow checks same-day venue hours and a configurable per-location closing cutoff on both connection and checkout; staff menu controls do not override time eligibility.
