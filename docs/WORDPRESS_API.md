# WordPress API — ZionFood 0.0.44

Backend contract for the future WordPress plugin. The plugin itself, its blocks, shortcodes and public proxy are a separate deliverable. See [capability report](WORDPRESS_CAPABILITIES.md) and [OpenAPI](openapi.json).

## Connect

In Restaurant administration → Connections → Integrations, generate a WordPress connection. Select only locations in that restaurant and the required abilities. Choose 30, 90, 180 or 365 days, or **Nedeterminat / No expiry**. The no-expiry choice is stored as `expires_in_days=0` and issues a Sanctum token with `expires_at=null`; no database migration is required. Existing finite validity choices remain unchanged. The API key and webhook verification secret appear only on creation/rotation; lists never return them. Configure the future plugin **server-side** with the returned API URL, key, location public ID and signing secret. Do not put keys in HTML, JavaScript, browser storage, URLs or WordPress frontend settings responses.

Use the configured central/API host, for example `https://food.zion3d.ro/api/v1/wordpress`. A custom tenant storefront domain accepts only public storefront endpoints. HTTPS is mandatory; loopback HTTP is allowed only in local/testing. Sanctum hashes incoming bearer tokens; callback secrets and retained idempotent response bodies are encrypted using Laravel's application key. No extra dependency is necessary: Sanctum, Laravel HTTP/cURL, Redis/Horizon and the transactional outbox are already installed.

```http
GET /api/v1/wordpress/bootstrap
Authorization: Bearer {api_key}
Accept: application/json
Accept-Language: ro
```

Menu image/brand `image_path`, `logo_path` and `cover_path` values are public-disk-relative paths. Resolve them against the configured ZionFood API origin plus `/storage/`, never the WordPress host. A null path means no image; no API key is needed for public image requests. Storefront paths likewise resolve against the ZionFood origin.

Bootstrap returns API/app versions, granted abilities, permitted locations, service settings and platform capabilities. A capability means the API implements it; the restaurant must also enable the service and the token must have its ability. Payments are explicitly unavailable. UI language defaults to Romanian; `Accept-Language: en` is optional. Machine values, keys and event names are never translated.

## Endpoints

All paths below are relative to `/api/v1/wordpress/locations/{location}`; `{location}` is a public ULID, never a numeric tenant selector.

| Method and path | Ability | Behavior |
| --- | --- | --- |
| `GET /menu` | `storefront:read` | Public branding, menu/photos, variants, inherited/variant modifiers, defaults/quantities, food metadata/translations, hours, delivery zones, availability and automatic promotions |
| `GET /availability` | `storefront:read` | Fresh effective service availability, schedule/cutoff and manual/table restrictions |
| `GET /schedule-slots?date=YYYY-MM-DD&fulfillment=pickup` | `storefront:read` | ISO 8601 slots with explicit offsets; fulfillment is pickup/delivery |
| `POST /quote` | `checkout:write` | Non-binding quote with server subtotal, fee, discount, total and currency; no redemption/order creation |
| `POST /checkout` | `checkout:write` | Immediate/scheduled pickup or delivery, QR dine-in or reservation preorder; returns private tracking token |
| `POST /orders/{order}/track` | `checkout:write` | Customer receipt with `tracking_token` in body; no customer contact fields |
| `GET /orders?active=1` | `orders:read` | Paginated operational order snapshots/history; omit active for scheduled/history orders too |
| `GET /kitchen` | `orders:read` + current kitchen permission | Active kitchen queue; future preparation stays hidden |
| `GET /orders/{order}`, `GET /reservations/{reservation}`, `GET /promotions/{promotion}` | Corresponding module read/manage ability | Scoped detail for callback reconciliation; staff contacts only with staff permission |
| `PATCH /orders/{order}/status` | `orders:manage` | `status`, optimistic `version`, required rejection `reason` |
| `GET /reservation-slots?date=YYYY-MM-DD&party_size=2` | `storefront:read` | Capacity/table-aware local reservation slots |
| `POST /reservations` | `reservations:create` | Creates guest booking; returns private reservation token |
| `POST /reservations/{reservation}/track` | `reservations:create` | Guest status with `reservation_token` in body |
| `POST /reservations/{reservation}/cancel` | `reservations:create` | Same proof plus `version`; cancellation deadline enforced |
| `GET /reservations?date=YYYY-MM-DD` | `reservations:read` | Staff booking list, 50/page, including authorized guest contacts |
| `PATCH /reservations/{reservation}/status` | `reservations:manage` | Versioned confirm/reject/seat/complete/cancel/no-show |
| `GET /tables` | `tables:read` | Active table/room/capacity/manual state and effective availability; permanent QR URLs only with tables:manage + current management permission |
| `POST /tables/{table}/connect` | `checkout:write` | Public QR proof `qr_token`; creates/joins an eligible guest session |
| `POST /tables/{table}/active-orders` | `checkout:write` | Read-only shared guest receipt with `table_session_token`; terminal orders disappear |
| `GET /tables/{table}/orders` | `tables:read`, `orders:read` | Staff order details and daily totals using existing permissions |
| `POST /tables/{table}/orders/{order}/served` | `tables:manage`, `orders:manage` | Completes a ready dine-in order using `version` |
| `POST /tables/{table}/{open,close,rotate}` | `tables:manage` | Staff QR lifecycle; business hours/cutoff remain authoritative |
| `GET /editable-menu` | `menu:read` | Authorized editing IDs/structure for menu/category/item/variant/modifier data |
| `POST /categories` | `menu:write` | Create/update category with `menu_id`, optional `id`, `name`, optional `modifier_group_ids` |
| `POST /modifiers` | `menu:write` | Create/update group/options; same conservative organization-wide authorization as menu studio |
| `POST /items`, `PUT /items/{item}`, `DELETE /items/{item}` | `menu:write` | Existing full product validator/action, image upload, prices/variants/options/food metadata/service restrictions and soft deletion |
| `GET /promotions`, `POST /promotions`, `PUT /promotions/{promotion}` | `promotions:manage` | Location promotions, coupon/automatic rules, usage limits and activation |
| `GET /analytics?days=7` | `analytics:read` + individual read abilities | Current feature/location authorization; 7/30/90 days; excludes ungranted modules and nested table rankings. Product ranking values are gross line values before order-level discounts, completed order values are discounted totals |
| `GET /events?after={event_ulid}&limit=100` | `events:read` + individual module abilities | Ordered durable event cursor for reconciliation after callback loss |
| `PUT /callback` | `webhooks:manage` + each subscribed module read ability | One callback per connection/location; `{url,enabled,events}`; HTTPS safe transport |

Staff permissions are checked again on every request, including the scopes granted to the token. Revoking issuer membership or removing a required module permission stops the connection. Tokens cannot access a different restaurant within the same organization, escape allowed locations through legacy routes, or manage their own credentials. Shared-menu writes require permission over **all** locations using that menu. Modifier-group management retains the existing organization-wide guard; a narrowly scoped connection must not alter another location's shared options.

## Checkout and scheduling

```json
{
  "customer_name": "Client",
  "customer_phone": "0712345678",
  "fulfillment": "pickup",
  "scheduled_for": "2026-11-03T14:00:00+02:00",
  "promotion_code": "BUNVENIT",
  "items": [{"product_id": "01J00000000000000000000000", "quantity": 2}]
}
```

Use the existing line contract: optional `variant_id`, `modifiers:[{id,quantity}]` **or** `modifier_ids`, never both. Client prices/totals are ignored. Delivery supplies address and a permitted polygon pin or selected postal zone. Minimums use the pre-discount subtotal. Public/customer proofs use distinct body fields because the Authorization header belongs to the WordPress connection.

General settings independently enable scheduled orders and reservation preorders; both default off. Configure minimum notice, maximum horizon, preparation lead time and orders per exact slot. Pickup/delivery use quarter-hour slots during existing same-day hours. Pending/accepted/etc. scheduled orders consume capacity; cancelled/rejected do not. Slot discovery is advisory; checkout serializes location writes and verifies capacity again. Manual pause, service and subscription entitlement are still checked. DST slots carry offsets, so repeated local times remain distinct. Split/overnight/holiday/per-service schedules remain separate work.

For a reservation preorder use `fulfillment=dine_in`, `reservation_id` and `reservation_token`. The reservation must belong to that location, be confirmed, meet notice/horizon and have no non-cancelled/non-rejected preorder (including completed orders). Its start time supplies `scheduled_for`; an explicitly supplied time must match. No QR session is required for this path; ordinary dine-in still requires a valid live QR session. This is an unpaid preorder. Reservation and order status are managed separately; cancelling a reservation does not silently cancel an order already sent to the kitchen.

Scheduled orders are recorded immediately and appear in order history with their target and preparation times. Kitchen/active feeds exclude them until `preparation_at`. Acceptance/rejection/cancellation are allowed beforehand; preparing/ready/served transitions are blocked until preparation starts. The scheduler runs `ReleaseScheduledOrders` each minute, increments the version and emits `order.updated` once. Existing polling also recovers due orders if realtime is delayed. Snapshot discounts and item prices never change after placement.

## Promotions

Restaurant administration → Promotions provides creation/editing/activation. API also accepts a product-public-ID eligibility list. Kinds: `percent` in basis points (1000=10%), `fixed` in integer currency minor units, `free_delivery`, `buy_x_get_y`. BOGO rewards the cheapest eligible units, including their modifier price. Optional minimum subtotal, discount cap, fulfillment list, date range with explicit timezone, shared usage cap and coupon code. Code uniqueness is restaurant-wide. Blank code means automatic. One promotion applies: the best eligible automatic discount, or the entered valid coupon; no stacking with automatic discounts when a coupon is supplied. Eligibility for scheduled orders uses fulfillment time. A usage is consumed transactionally on placement and is **not refunded** on later cancellation; quotes and retries consume none. This release does not implement every GloriaFood promotion template, customer segmentation, stacking or marketing campaigns.

## Reliability and recovery

Every mutating request needs `Idempotency-Key` (16–100 ASCII letters/digits/hyphen/underscore). Exceptions: read-only `POST /quote`, customer tracking and guest active-order reads. Reuse the same key **and payload** after a timeout; do not generate a new key when the outcome is unknown. Keys are namespaced to a connection; canonical payloads include method/path/query data and uploaded file hashes. A connection lock serializes mutation receipts. Failures roll back and are not cached. Conflicting key reuse returns 409. Successful receipts are encrypted for 7 days; hashes remain 90 days. After receipt expiry a retained key returns 409 rather than creating a duplicate. Beyond 90 days, do not retry an old mutation; reconcile first. Checkout and reservations also retain their domain-level idempotency record. Replayed responses carry `Idempotency-Replayed: true`.

Default rate limit is 120 requests/minute per authenticated connection; 429 includes Laravel retry headers. Retry network errors/429/5xx with bounded exponential backoff and jitter; respect `Retry-After`, retain the same mutation key, stop on 401/403/422 and resolve 409 conflicts explicitly. No network can guarantee infallibility. Outbox persistence, immutable snapshots, idempotency, version checks, signed callbacks and cursor reconciliation provide recoverable **at-least-once** delivery, not an exactly-once network guarantee.

Callbacks use `X-ZionFood-Delivery` (stable event ULID), `X-ZionFood-Event`, `X-ZionFood-Timestamp`, `X-ZionFood-Signature: sha256=...` and `X-ZionFood-Request-ID`. Verify `hash_hmac('sha256', timestamp + '.' + raw_body, secret)` with constant-time comparison, enforce a short timestamp tolerance and deduplicate event IDs durably in the future plugin. Acknowledge with 2xx after durable admission. Events contain IDs/versions, not customer contacts. Re-fetch through authorized APIs. A connection revocation disables callbacks; dispatch also rechecks current issuer permissions and unexpired credentials.

Supported events: `order.created`, `order.updated`, `reservation.created`, `reservation.updated`, `menu.updated`, `item.updated`, `promotion.updated`. Callback attempts revalidate **all** DNS answers, pin cURL to a checked public IP, verify TLS, disable redirects/proxies, bound response size/time and retry transient failures at most six times. Use `/events` to reconcile missed events; persist cursor after processing, deduplicate IDs and periodically refresh resource snapshots. The current durable outbox has no automatic archival; introduce an explicit retention/recovery-window contract before archival.

Rotation returns a new API key, preserving the callback secret and validity policy, including no expiry. Previous keys get at most 15 minutes grace, never extended past an earlier expiry. Revocation is immediate for keys and callbacks; historical journals and orders remain. Only the issuer or organization owner can rotate/revoke a connection.

## Logs and operations

General settings → Show restaurant communication logs reveals the restaurant-only section. It is a visibility setting, not a collection switch. Platform admin → Communication logs always sees sanitized technical metadata, including unattributed invalid WordPress credentials. Filters: restaurant (platform only), inbound/outbound, exact HTTP status, outcome, request/event IDs and UTC date range; 30 rows/page. Tenant filters cannot widen restaurant scope. The journal covers requests reaching WordPress API middleware; TLS, malformed HTTP and upstream server admission errors remain in web-server diagnostics. Logs include route template, method, status, duration, callback host, event/attempt/failure and replay flag. No request/response bodies, credentials, raw exception messages, customer addresses, names or telephone numbers are stored. A failed journal insert emits a sanitized application warning without undoing the business result.

Run normal migrations and the existing scheduler/Horizon deployment process. New scheduled tasks release due orders every minute and prune logs/response bodies daily. Configuration in `config/wordpress.php`: 30-day logs, 7-day encrypted receipts, 90-day request hashes and 120/minute default. Ensure Redis, scheduler and workers run in production. No production callback, WordPress installation or external DNS/TLS was exercised by the local release; see CODEX_PROGRESS.md for exact checks.
