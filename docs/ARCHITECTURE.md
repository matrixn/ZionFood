# ZionFood 0.0.30

Menu metadata uses validated v2 content, encrypted combined AI proposals and source-hash publication guards. Automatic one-portion nutrition is explicitly estimated, normalized server-side and reviewed before publication. Durable recipient notifications plus private ID-only Reverb invalidations/background polling deliver results across workspace pages. Platform provider settings, shared-menu authorization and the global quota remain authoritative. The manual GloriaFood metadata adapter and manual recipe calculator were retired; whole-menu Migration Center is implemented separately with encrypted staging, approved version publication and guarded rollback (see MIGRATION_CENTER.md). See [MENU_CONTENT.md](MENU_CONTENT.md).

The restaurant shell groups operations, administration and connection links, with Setup below the location selector. Setup embeds existing editors and loads selected editor props through WorkspaceController's shared authorization/data builder. Standalone routes and save endpoints remain; saves return to the wizard referer. See ADMIN_NAVIGATION.md for scope, unsaved guards and location selection.

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

Status changes lock the order and require the expected version. Allowed transitions are defined in `OrderStatus`; a stale version returns 409. Rejection requires a reason. Authorized order lists already include chronological history. The frontend derives the current rejection reason from its latest rejected history entry; the orders table exposes it through the shared accessible help tooltip, and order details show it below the status. Only currently rejected orders show this reason; missing/blank reasons produce no invented fallback. Plain text interpolation preserves escaping. History cannot be updated or deleted through its Eloquent model. A database administrator can still modify rows directly; this is not cryptographic or database-trigger immutability.

## Realtime and recovery

An outbox row schedules `DeliverOutbox` only after commit. The job publishes a small `order.changed` invalidation over `organization.{organization}.location.{location}.orders`, then marks delivery. Private channel authorization checks both organization and location. Payloads contain event/order public IDs, event type and version, without customer data. Delivery is at least once: if publication succeeds but recording delivery fails, an event can be repeated. KDS refetches authoritative state, so duplicates are harmless.

Failures retain the outbox row, increment attempts, and record only the exception class. Queue backoff and the scheduled `zionfood:outbox` command provide retry/reconciliation, subject to the persistent per-event budget and due time described below. KDS also synchronizes after reconnect, focus, visibility changes, network restoration, manual refresh, and every 15 seconds. Active order pagination is fully traversed. Customer tracking polls every five seconds using a token in the Authorization header; tokens stay in session storage, outside URLs.

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

## Cuisine catalogue

The platform owns the shared bilingual cuisine/photo catalogue. Owners/managers choose location-scoped entries through the separate revision-checked setup step. Composite location/organization pivot ownership and locked catalogue availability checks preserve tenant boundaries and activation dates. Uploads are platform-admin-only and local/public-disk managed; attribution links are never server download targets. See CUISINES.md.

## Public presentation

Welcome.vue provides the bilingual central-host marketing surface; mapped storefront roots retain PublicOrderingController menu rendering. Illustrative feature/order previews hold only local Vue state and have no domain mutation or notification side effects. Responsive CSS/IntersectionObserver effects respect reduced-motion preferences. Image generation provenance is bundled in docs/assets/landing-images.prompt.md. See LANDING_PAGE.md.

## Restaurant address location

LocateRestaurant resolves current unsaved address inputs through a configured Photon-compatible service after tenant management authorization. Cached results and a global provider lock/cooldown bound upstream traffic; results are read-only until the existing setup save persists the chosen pin. Private provider configuration is separate from public map tile settings. No checkout address geocoding or new database schema is introduced. See RESTAURANT_LOCATION.md.

The same transport resolves a pin to structured address fields without moving it; setup saves address and pin together. Delivery Settings prefers a saved pin and zooms new polygon editors to it, with a reference marker/recenter action. A complete saved address without a pin is resolved once for viewport use only, requiring an explicit choice when ambiguous. Both directions share transport throttles and per-user rate limits.

Ingredients are a shared platform catalogue plus restaurant-owned records. Product recipes use an ordered pivot and transactional scope/availability validation in UpdateMenuItem. Portion labels and explicit flags are additive metadata; public flags combine recipe and product information. Integer money and checkout behavior remain unchanged. The automatically seeded catalogue is bundled with import-count evidence and preserves administrator changes on reseeding. See INGREDIENTS.md.


## Storefront experience (0.0.12)

Public-menu HTML supplies restaurant-specific sharing metadata before JavaScript runs; Vue Head maintains the matching hydrated values without duplicate tags. Product images in cart state are presentation metadata only and are never submitted as checkout authority. Dialogs use a bounded flex shell with separate header/body/footer; native form IDs retain submission/validation for footer buttons. Customer reverse lookup reuses LocateRestaurant bounded transport and rejects disabled locations, foreign storefront scope and points outside active polygons before calling the provider.


Share metadata uses a dedicated StorefrontShareImage derivative rather than the original logo. The server bounds input bytes/dimensions before GD decoding, flattens transparency on white, contains the entire image in a 600px square and caches by immutable upload path plus layout version. Metadata declares PNG type and exact dimensions in both initial HTML and Vue Head. Branding cleanup runs after commit; image-generation failure cannot prevent menu rendering.


## Restaurant entry points and company identity (0.0.15)

Public menus use the restaurant's managed slug. Legacy ULID links resolve the current slug; multiple locations have an explicit chooser and a scoped `/{location-public-id}` suffix. Model-derived storefront paths supply workspace links. Verified custom hosts resolve only their own mapped location. Guest table pages distinguish service-disabled and table-paused states before attempting QR connection; authorization/ordering windows remain in domain actions.

ResolveCompany normalizes ANAF v9 data into private legal fields and never saves on lookup. It prefers registered-office address components, falling back to the fiscal address only when the registered-office address is unavailable. Fiscal postal codes are not substituted into an existing registered-office address. Optional flags retain unknown/null states rather than inventing false values. The UI rejects late results after form edits or CUI changes, and existing revision-protected legal saves persist explicit user review.

Invitation tokens move from fragments to a CSRF-protected prepare exchange and encrypted pending server session. Registration uses this capability to prefill the exact email and atomically create user, proven-email verification and invited membership before login/Registered. Normal owner registration keeps the verification flow. See STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md.


## Employee capabilities and effective dining state (0.0.16)

StaffPermissions defines a five-feature none/view/manage matrix on organization memberships and invitations, with legacy role defaults. TenantAccess combines it with membership, token organization scope and assigned locations. Permissions are shared to private Inertia pages and editor props; navigation and action visibility derive from them. Role-based company/identity/integration/staff administration stays separate. Invitation acceptance snapshots configured capabilities and revalidates current inviter authority. Owner access is immutable in this UI.

Operations exposes independent order-history and active-kitchen paths with the existing orders token abilities as additional requirements. Domain order transitions use an explicit server-chosen feature, not a client selector. Reservation/table/menu mutations and policies enforce their module levels. Shared-menu writes check all attached locations and global modifier operations check all organization locations. Read-only menu payloads omit unused ingredient editor catalogue data for staff.

OrderingWindow now supplies a status snapshot with distinct outside-hours/cutoff reasons and program/cutoff times in the location timezone. Both guest QR entry and staff cards use this source; public connect/checkout continue authoritative checks. Bulk room/all table actions serialize on the location row and reuse the existing table-session operations. They do not change QR credentials, services or business hours.

0.0.20 adds feature/location-scoped RestaurantOverview analytics, approximate daily menu-visit aggregates, compact effective table-state controls and reusable FieldHelp across forms. Definitions deliberately distinguish order value, reservations, QR sessions and approximate visits from receipts, physical occupancy and unique people. Successful generation review closes automatically; failed writes remain open. See [RESTAURANT_ANALYTICS.md](RESTAURANT_ANALYTICS.md).

## Migration Center (0.0.22)

The Imports domain adapts fixed-endpoint GloriaFood Fetch Menu v2 into a provider-independent canonical menu array. ProcessImport keeps encrypted source/canonical snapshots and verification evidence; PublishImport serializes on location and batch, replaces only that location’s menu assignment, and preserves previous menu versions. MenuGraph fingerprints protect preview/publication/rollback. Source mappings are namespaced by provider, restaurant, location, type and publication batch. EffectiveModifiers is shared by public serialization and authoritative checkout. See [MIGRATION_CENTER.md](MIGRATION_CENTER.md).

## Guest table receipts (0.0.23)

ActiveTableOrders resolves a read-only bearer capability for the exact organization/location/table/session, selects only nonterminal dine-in orders and serializes snapshot-only guest fields. Receipt access is independent of ordering availability; closed/expired sessions retain active receipts for existing capability holders. Public menu caches remain receipt-free. Vue TableOrders polls the paginated endpoint, handles stale responses and restores per-tab capabilities. See RESERVATIONS_DINING_DELIVERY.md.

## Location preferences, staff table orders and error-alert circuit (0.0.24)

LocationPreferences owns whitelisted location display settings and optimistic revision writes behind owner/manager authorization and a location row lock. Public payloads filter hidden contacts/description/address/photos without mutating shared cached menu data. Vue date rendering uses the venue timezone; date-only and unzoned reservation slots preserve local calendar components. Wire dates, event names and logs remain unchanged. Optional setup/general embeds the same settings page.

StaffTableOrders scopes both orders and sessions to organization/location/table. Summaries use grouped aggregates; active orders span sessions and days, daily value uses DST-aware local-midnight UTC boundaries and excludes cancelled/rejected orders. Table read plus orders/kitchen read is required for summaries/details. Serving additionally requires orders/kitchen manage and delegates the existing versioned Ready→Completed transition; there is no direct pending→completed shortcut. Private details omit customer contacts and secrets.

ErrorAlertCircuit stores a singleton locked database row: enable switch, latched suspension, counters, generation and version. Deduplicated admissions and actual provider attempts each have a rolling-hour budget of five. A sixth attempt latches suspension; elapsed time/restart does not clear it. SMTP/Resend alert jobs carry a generation header and check the latch before quota/provider access. Manual admin resume clears windows and advances generation, invalidating backlog; ordinary emails bypass this alert-only gate but still use EmailQuota. Provider acceptance is recorded separately and is not inbox delivery. Failure attempts count conservatively. MAIL_ERROR_ALERTS remains a server master switch.

Broadcast retries retain durable outbox semantics. Additional logs contain only an error classification/hint/event ID/attempt, never raw request URLs or credentials. Optional REVERB_PUBLISH_* values separate server publishing from browser websocket routing.

## Durable outbox attempt budget (0.0.25)

DeliverOutboxMessage owns the locked delivery state. `attempts` remains the lifetime count; `delivery_attempts` counts a manually resettable cycle capped at five. `next_attempt_at` makes both queue retries and scheduler duplicates respect the same 5/15/60/120-second delays. `failed_at` latches exhausted events without deleting payloads or marking them delivered. A successful delivery clears the due/error fields. Due queries exclude delivered, future and exhausted rows; jobs repeat those guards under the row lock. Explicit CLI recovery requires an exhausted undelivered event, retains its identity/lifetime history and dispatches after commit. Publication stays at least once if a process dies after sending and before recording success; consumer invalidations must remain idempotent.

Webhook fanout stays in the transaction and precedes broadcasting, so a broadcast failure preserves already-created idempotent webhook deliveries. Known BroadcastException messages are replaced by a safe classification/hint without a previous exception, preventing signed query strings from reaching default Laravel/Horizon exception serialization. Generic non-broadcast errors retain their exception identity. Reverb endpoint fallback semantics are unchanged; realtime-check is a read-only DNS/configuration aid, not a transport or end-to-end health guarantee.


## Outgoing mail ledger and provider pause (0.0.26)

EmailLedger captures queued notification intent and rendered transport metadata; DeliverEmail correlates by notification ID/public ULID and leases records before provider work. ResendQuota holds persistent UTC counters and shared daily/monthly/rate pauses under database row locks, with a cache provider lock around usage/sending. Existing rolling application quotas and the error-alert circuit remain separate. IndexQueuedEmails reads authenticated encrypted configured queue payloads without executing them. AdminEmailsController is platform-only and returns explicit safe metadata fields. See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md).


## 0.0.27 marketing rendering and search metadata

LandingController/LandingSeo keep canonical-host metadata and language URLs independent of request cookies/Host input. LandingPrerender and LandingGateway serve version-checked build-time Vue HTML with live request page JSON only on Welcome; other Inertia pages retain their existing gateway behavior. The same Vue source is enhanced in the browser. SearchIndexing sets private-route and readiness-aware landing policies without changing tenant authorization. See LANDING_PAGE.md.

## Ordering availability (0.0.28)

LocationSchedule supplies one venue-timezone schedule for checkout and public availability. GetOrderingAvailability combines it with manual acceptance, enabled services and (for online ordering when QR is enabled) an active, manually open table. The default-on location preference `block_unavailable_online_orders` gates pickup/delivery cart additions only. An explicit false permits cart preparation only outside hours or with all tables closed; submission still requires opening hours and an open table where QR is enabled; manual stop, chosen service, subscription, inventory, prices and delivery validation remain authoritative. QR always retains its table/session, schedule and configured cutoff checks.

Availability is computed outside the cached product tree and exposed through read-only scoped public endpoints. Checkout rechecks under the existing location row lock; table control and preference updates share that lock. Idempotent replay precedes the gate. Vue polls every 15 seconds, refreshes on return, uses server time with monotonic elapsed time at closing/cutoff and disables new cart/checkout actions if refresh fails or becomes stale. This is ordering permission, not occupancy or a promise of stock/order acceptance.

## Shared platform identity (0.0.30)

ZFLogo uses the supplied PNG sizes with a responsive source set and reserved square dimensions. Platform surfaces, auth, shells and storefront attribution share it; tenant branding stays independent. The Blade gateway supplies favicon/touch links, notification HTML mail overrides only its shared header, and migration reports/Organization JSON-LD reference the appropriate mark. See PLATFORM_BRANDING.md.
