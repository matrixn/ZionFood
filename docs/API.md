# API v1

Machine-readable contract: [openapi.json](openapi.json). API host in production: `https://api.zionfood.ro`; browser clients normally use the same-origin `/api/v1` routes on the application/storefront host. No permissive CORS policy is configured. WordPress integrations should call from their backend and retain tokens server-side.

| Method | Endpoint under /api/v1 | Access |
| --- | --- | --- |
| GET | /public/locations/{location}/menu | Public location ULID; verified storefront host cannot access another location |
| POST | /public/locations/{location}/checkout | Public; Idempotency-Key required, 16–100 letters/digits/hyphens/underscores |
| POST | /public/locations/{location}/address-from-pin | Public, delivery enabled, point inside active polygon; scoped by storefront host and throttled |
| GET | /public/orders/{order} | Bearer tracking token; public order ULID; returns no customer contact details |
| GET | /locations/{location}/orders | Session or Sanctum orders:read; organization and location authorization |
| PATCH | /orders/{order}/status | Session or Sanctum orders:manage; status, expected version, optional reason |
| GET | /locations/{location}/menu | Session or Sanctum menu:read; tenant/location authorization |
| POST | /locations/{location}/items | Owner/manager; session or Sanctum menu:write |
| PUT | /items/{item} | Owner/manager; session or Sanctum menu:write |
| GET, POST | /locations/{location}/integration-tokens | Verified browser session, owner/manager; GET excludes token values |
| DELETE | /locations/{location}/integration-tokens/{token} | Verified issuing user's browser session, owner/manager |
| GET, POST | /locations/{location}/webhooks | Verified browser session, owner/manager; signing key only in create input |
| PUT, DELETE | /locations/{location}/webhooks/{endpoint} | Same location/organization owner/manager browser session |
| POST | /locations/{location}/webhook-deliveries/{delivery}/retry | Owner/manager browser session, enabled endpoint, retryable delivery |

Locations/items/orders in URLs use public ULIDs. Category, variant, modifier-option and delivery-zone IDs in payloads are internal numeric references checked against the resolved tenant and menu. Authenticated order results use API Resources with pagination; `?active=1` excludes terminal statuses. Follow `links.next` until null to recover the complete active queue.

Checkout input contains `customer_name`, `customer_phone`, `fulfillment` (`pickup`, `delivery` or `dine_in`), optional notes, and items containing `product_id`, `quantity`, optional `variant_id`, optional `modifier_ids`. Delivery requires `delivery_address`. With active polygons it also requires `delivery_latitude`/`delivery_longitude`; the server ignores the selected zone and calculates eligibility/fee/minimum by polygon priority. Without polygons, `postal_code` and `delivery_zone_id` are required. Dine-in requires a live `table_session_token` and the enabled service. Prices/totals from clients have no effect. The server returns 201 and `order_id`, `tracking_token`, `replayed:false`; an identical replay returns 200 and `replayed:true`. A changed payload with an existing key returns 409.

Status transitions require the current integer `version`. A successful operation increments it. Invalid transitions return 422; stale versions return 409; forbidden tenant/ability access returns 403. Invalid or mismatched tracking tokens return 404. Public tracking responses use `Cache-Control: no-store`.

Product images: multipart POST, `_method=PUT` for updates, JSON string in `payload` for the product fields and an `image` file. Only JPEG/PNG/WebP, maximum 4 MB and 6000×6000 pixels. Ordinary JSON requests also support item create/update. Product fields include integer price, tax basis points, variants and modifier group IDs. Updating variants replaces their IDs; existing carts may require reconfiguration. Orders retain independent snapshots.

Browser CRUD retains CSRF protection. Personal access tokens have the selected abilities from `menu:read`, `menu:write`, `orders:read`, `orders:manage`, are scoped to one organization, and expire in 90 days. Only the creation response reveals the plaintext token once; it must be stored by the integration backend. Existing secrets are never sent in token lists or broadcast events.

Signed webhook configuration and delivery are implemented. Events are order.created, order.updated, menu.updated and item.updated. The configuration schema, signing algorithm, receiver verification and retry semantics are documented in [administration and webhooks](STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md). Existing signing secrets and response bodies are never returned. Removed endpoints retain their organization-scoped delivery history. No WordPress plugin has been implemented.

Browser routes under /app/locations/{location} include /staff, /branding and /webhooks. Staff invitation/member management and multipart branding use CSRF-protected session routes and reusable domain Actions. /invitations/{public_id} accepts a token from the email fragment only in its verified-email acceptance POST. /locale accepts only ro/en and changes the encrypted preference cookie plus the signed-in user preference. Integration clients may explicitly request Accept-Language: ro or en; field names/status values are stable across languages. Tracking responses additionally contain public restaurant branding.

## Guided setup web routes

Verified browser sessions for tenant owners/managers may GET /app/locations/{public_id}/setup/{step?} and PUT /app/locations/{public_id}/setup/{step}. CSRF applies to writes. A revision is required; stale revision, invalid fields, capacity/party conflicts and publication blockers return normal validation errors. Read-only steps link to existing editors; payments has no supported write. Setup profile/legal data is not added to the public menu/tracking API. See RESTAURANT_SETUP.md for field groups and current limitations.

## Reservation and table endpoints

| Method | Endpoint under /api/v1 | Contract |
| --- | --- | --- |
| GET | /public/locations/{location}/reservation-slots | date YYYY-MM-DD, party_size; local ISO slots and timezone; no-store; 10/min/IP |
| POST | /public/locations/{location}/reservations | Idempotency-Key; guest_name, guest_phone, optional guest_email/notes, party_size, starts_at YYYY-MM-DDTHH:mm in venue timezone; 201/200 reservation_id/token/replayed |
| GET | /public/reservations/{reservation} | Private 64-character bearer; times/status/version/can_cancel without guest contact data; no-store |
| POST | /public/reservations/{reservation}/cancel | Same bearer, expected version; 409 stale / 422 cutoff or invalid transition |
| POST | /public/tables/{table}/connect | qr_token proof, current enabled location/table; returns session_id/token/expires_at; no-store; 20/min/IP |

Booking mutations use 10/hour/IP and 60/min/location limits. Public endpoints enforce verified storefront location mappings. Browser session writes retain CSRF. Tokens for reservations/table sessions are capabilities separate from Sanctum integration tokens. No reservation integration-token management API is introduced.

Verified staff browser routes: GET /app/locations/{location}/reservations (date/status filter, 50/page), POST /app/reservations/{reservation}/status (status/version), GET /app/locations/{location}/tables and POST /app/tables/{table}/{open|close|rotate}. Tenant location staff may operate; only owners/managers receive or rotate permanent QR credentials. Table operations are throttled 30/min/user. Public web pages are /reserve/{location}, /reservations/{reservation} and /table/{table}; private tokens belong in fragments/session storage, never query parameters. See RESERVATIONS_DINING_DELIVERY.md for state transitions and limitations.

Permanent QR menu pause is enforced independently of session expiry; a connect request cannot undo staff closure. Connection and dine-in checkout enforce the location closing cutoff (default 30 minutes). Settings persist dine_in_cutoff_minutes between 0 and 240. Staff open/close preserve the permanent QR; rotate replaces its credential.

## Cuisine catalogue browser routes

GET /admin/cuisines and POST /admin/cuisines[/{numeric cuisine ID}] require a verified platform-admin session; POST accepts multipart bilingual fields, active flag, sort order, safe photo upload and optional attribution links. Tenant setup GET/PUT /app/locations/{public_id}/setup/cuisines uses a verified owner/manager session, revision and cuisine_ids array (distinct numeric IDs, maximum 100). Inactive previously-selected entries may be kept/removed but cannot be newly selected. Existing activation dates survive unchanged saves. All writes use CSRF. No public/integration cuisine write API is added.

## Restaurant address lookup

POST /app/locations/{public_id}/setup/geocode requires a verified owner/manager session, CSRF and ten requests/minute/user. Body: address (5-255 characters), city (2-100), country (two uppercase letters), optional postal_code (20 maximum). Response: results array containing latitude, longitude, label and approximate, with no-store. Lookup uses unsaved form data and does not modify setup or revision. Existing Basics PUT stores the pin. Private geocoder configuration is not returned.

POST /app/locations/{public_id}/setup/reverse-geocode accepts numeric latitude (-85..85) and longitude (-180..180), under the same management/CSRF/shared throttle. Its no-store response has address null or structured address/city/country/postal_code/missing, without saving. Settings receives a saved mapCenter or null and saved mapAddress components; it may perform one forward lookup when no pin exists. See RESTAURANT_LOCATION.md.

Restaurant administration now includes GET /app/locations/{public_id}/locations and setup/locations plus setup/delivery. Setup hours/delivery/menu/branding/staff load the selected existing editor props under the same owner/manager authorization. Writes reuse existing endpoints and return to the current wizard URL. These supporting steps do not accept arbitrary setup PUT payloads. See ADMIN_NAVIGATION.md.

Product JSON and multipart payloads now accept optional portion_size (50 characters), ingredient_ids (distinct ordered IDs, maximum 100), contains_allergens and from_frozen. Prices remain integer bani. Omitted ingredient_ids preserve an existing recipe. Ingredient selection is restricted to common or same-restaurant entries, with hidden/deleted IDs allowed only when already assigned. Public menu products include portion_size, ordered ingredient metadata and effective combined flags. Web ingredient administration uses verified management sessions and CSRF; global /admin/ingredients requires platform admin and tenant /app/locations/{id}/ingredients manages only restaurant-owned rows. See INGREDIENTS.md.

## Customer delivery address from a pin

POST `/api/v1/public/locations/{location}/address-from-pin` accepts numeric latitude (-85..85) and longitude (-180..180). The location must accept orders and have delivery enabled; the point must lie inside an active delivery polygon. Response: `{address: "street, number, locality" | null, incomplete: boolean}`, no-store. Missing number/locality may produce a partial address for manual completion. Public limits are 10/min/IP, 100/hour/IP and 60/min/location, in addition to the shared provider cache/lock/cooldown. The route stores no customer data and forwards only coordinates. Map-only lookup does not replace authoritative checkout polygon/fee validation.


## Company data resolver and public menu URLs (0.0.15)

`POST /app/locations/{location}/setup/company` requires a verified management session, CSRF and location authorization. Body: `{tax_id: "RO14837428"}`. Response: `{company: {...}|null, source: "ANAF", checked_at: ISO8601}` with no-store. Fields cover normalized tax ID, company name/address, registry number, phone, registered-office postal code/city/county/country, CAEN, legal form/status, optional IBAN, VAT/inactivity/e-Factura flags. Missing text is empty; unknown flags are null. No lookup writes setup data. Format, busy/provider failure and oversized/malformed/mismatched responses return localized validation errors. Limits: five/minute and fifty/hour/user, plus the shared ANAF cooldown. Save reviewed values through the existing legal PUT/revision contract.

`GET /restaurant/{slug}` opens the sole location or a public branch chooser. `/restaurant/{slug}/{location-public-id}` selects a branch within that restaurant. `/store/{location-public-id}` redirects to the new location path. Verified storefront host roots remain valid and their scope also applies to slug routes. Valid disabled/paused `/table/{id}` links render a helpful no-store/noindex page; unknown/inactive/foreign tables retain 404. Disabled-service table connect returns a validation error.

`POST /invitations/{id}/prepare` accepts the 64-character token from the original email fragment, validates it and returns the invited email/role/organization only after proof. It stores encrypted pending proof in the session; ten requests/minute/IP applies to prepare/accept. Registration ignores client role/scope/verification claims and binds to this server-validated context. Authenticated `POST /invitations/{id}` may verify a matching unverified account through its valid emailed invitation.


## Staff capabilities, kitchen and table batches (0.0.16)

Optional `permissions` on invitation and membership create/update payloads is a complete object with `orders`, `reservations`, `tables`, `kitchen`, `menu`, each `none|view|manage`. Unknown keys/levels and incomplete explicit maps are rejected. Omission retains legacy defaults for invitations; same-role membership edits preserve the current matrix. Owner records cannot be edited and managers cannot grant beyond their own current feature levels. Read/manage checks apply in addition to existing location/organization scope and Sanctum abilities.

GET `/api/v1/locations/{location}/kitchen-orders` requires kitchen view plus `orders:read` for real tokens. It always returns active orders; `active=0` cannot expose terminal history. PATCH `/api/v1/kitchen-orders/{order}/status` requires kitchen manage plus `orders:manage`, using the existing version/reason/state machine. Order history/transition endpoints require their own orders capabilities. Public order endpoints remain unchanged.

POST `/app/locations/{location}/tables/bulk` requires verified web session, CSRF, tables manage and the existing table-operation throttle. Body: `{operation: "open"|"close", scope: "all"|"area", area?: string(max80)}`. Area is required for area scope. The response redirects with flash success or validation/authorization errors. Only active tables in that location match. Closing pauses menus/closes sessions; opening respects global service state and does not override hours. The manage page includes `orderingWindow` and current permissions. Known QR links blocked by program/cutoff render the explanatory guest page with times; invalid/foreign tables retain 404.
## Menu-content web workflow (0.0.17)

Verified sessions use GET `/app/locations/{location}/menu-content`, GET `.../items/{item}` for current editor data, PUT `.../items/{item}` with `{content,hash}`, POST `.../generate` with `{kind,item,languages,options}` (analyze/nutrition/translate/suggest/rewrite; null item only for all-location translation), GET `.../drafts/{draft}` for a freshly authorized review and POST the same URL with `{action,sections?,changes?}`. Review changes are restricted to text/food/translation fields originally present in the proposal; nutrition is generated and labelled estimated server-side. All mutations require CSRF, menu-manage/shared-menu rights and the existing throttle; reads require current menu-view/location access. Stale writes/publication return 409; invalid inputs/output/configuration/quota return 422. Batch creation remains transactional.

GET `/app/notifications` returns the latest 100 owned, currently authorized result records (not full proposals). POST `/app/notifications/{notification}/read` marks an owned, authorized record read. Private Reverb `user.{id}.notifications` events (`workspace.notification`) contain only `notification_id`. Clients fetch current data on invalidation and poll automatically while visible. Foreign IDs are inaccessible. The retired `.../items/{item}/gloriafood` route no longer exists.

GET/POST `/admin/menu-ai` remains platform-admin-only; encrypted keys are accepted privately and never returned. Public menu metadata includes reviewed allergen/label codes, current translations and nutrition basis/numbers/estimated marker with optional portion weight/per-serving values. Recipes, analysis inputs, references, assumptions and review reports stay private. Existing pricing/checkout fields remain unchanged. See MENU_CONTENT.md and OpenAPI MenuFoodContent.

## Restaurant overview and visits (0.0.20)

GET `/app/locations/{location}?days=7|30|90` returns the existing Inertia dashboard with separately permission-gated analytics. Orders use placed-at boundaries, reservations start-at boundaries, and dates use the location time zone. No customer contact details are included in analytics. POST `/api/v1/public/locations/{location}/visit` returns 204 for accepted/deduplicated/known-preview requests, 404 for unknown or host-mismatched locations and 429 on throttling. It returns no visitor identifier. Existing menu responses, order prices and checkout contracts are unchanged. See RESTAURANT_ANALYTICS.md for all count/value definitions.

## Menu compatibility and migration (0.0.22)

Checkout lines accept either legacy modifier_ids (one unit per option) or modifiers: [{id,quantity}], never both non-empty. IDs must belong to inherited category/product/selected-variant groups; counts are distinct options, quantities are bounded and priced server-side. Snapshots include quantities and internal kitchen names. Public variants expose defaults, nutrition and own modifier_groups; products expose order_types. An explicit empty order_types list disallows every service; null is unrestricted.

Migration Center is a verified browser/CSRF surface under /app/locations/{location}/migration-center, not a Sanctum integration API: GET index, POST start, GET {batch}, POST {batch}/resolve, POST {batch}/publish, POST {batch}/revert, GET {batch}/report and GET {batch}/print. See MIGRATION_CENTER.md for source/settings, mandatory review token, blockers, conflicts and version semantics.

## Guest active table orders (0.0.23)

GET /api/v1/public/tables/{table}/orders uses Authorization: Bearer <dining-session-token>, optional cursor, and returns {orders,next_cursor}. Nonterminal dine-in order snapshots include public_id/status/version/currency/total/created_at and items with name/variant/quantity/total/modifiers. Only the exact session's table/location is accessible. Completed/rejected/cancelled orders, contacts, notes, tracking tokens and internal names are excluded. This read-only receipt capability may outlive ordering permission; session closure/expiry still blocks checkout. Responses are private/no-store and throttled.

## Location configuration and staff table receipts (0.0.24)

Verified browser session/CSRF routes (central host, outside /api/v1):
- GET/PUT `/app/locations/{location}/general-settings`: managers only; PUT whitelisted display fields plus `revision`, stale writes 409. GET is Inertia.
- GET `/app/tables/{table}/orders?cursor=…`: tables read and orders/kitchen read. Returns up to 30 active or current-day-completed receipt snapshots, `next_cursor`, `can_serve`; private/no-store.
- POST `/app/tables/{table}/orders/{order}/served`: tables read and orders/kitchen manage, body `version`; returns 204. Wrong table/order 404, denied 403, stale 409, non-ready 422.
- GET/PUT `/admin/email-alerts`: platform administrators only. PUT `enabled`, `resume`, `version`; returns safe settings/counter state, conflicts 409. Tenant managers and platform admins without tenant membership do not gain tenant access.

Reservation tracking now returns a server-derived same-site `menu_url` and safe `display_settings`; the existing bearer/host checks apply to the full response. Public menu/tracking payloads apply hidden contact/display flags. Times remain ISO on the wire and monetary values remain integer minor units.

0.0.25 changes no HTTP schema. Outbox invalidations now have a persistent five-attempt delivery budget and controlled CLI recovery. Kitchen/customer polling continues to fetch authoritative order state when realtime transport is unavailable; failures do not roll back an already-committed order. See DEPLOYMENT.md for DNS/internal publisher remediation.


## Platform email administration (0.0.26)

Verified platform-admin session only; separate from restaurant API and token abilities. GET /admin/emails renders the page; GET /admin/emails/data returns paginated metadata/summary/provider state with source, state, initiator, mailer, exact recipient, from/to and page filters. PUT /admin/emails/provider accepts daily_limit and revision (409 stale). POST /admin/emails/provider/usage queries read-only provider account usage; POST /admin/emails/index-queue indexes existing encrypted jobs. Mutations require CSRF and usage/index share a three/minute admin throttle. No body/token/credential is exposed. See [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md) for states, semantics and historical limits.
