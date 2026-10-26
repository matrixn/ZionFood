# Reservations, table QR orders and mapped delivery — 0.0.3

## Activation

Each location independently enables pickup, delivery, online reservations and table QR orders in **Pregătește restaurantul > Servicii oferite**. Existing locations retain their pickup/delivery flags; the two new services default to disabled. Prepare seating and reservation rules first. Scheduled orders and catering remain optional preparation; payments remain an informational placeholder, with no provider or charges.

## Reservations

Guests use `/reserve/{locationULID}`, select a local date, party size and available time, and submit their name/phone and optional email/notes. The server rechecks opening hours, notice, horizon, duration, party bounds, subscription entitlement and capacity when saving. A preview does not reserve capacity. One same-day opening interval per weekday is supported; split, overnight and holiday schedules remain deferred.

Capacity mode pools seat counts. Table mode assigns the smallest available single table that fits; tables are not combined. Blocking reservations are confirmed/seated bookings and unexpired pending holds. Capacity uses the peak concurrent occupancy across half-open time intervals, so adjoining bookings do not overlap. Location row locks serialize booking creation, status changes, setup seating changes, checkout and table operations. Idempotency is scoped to location and a validated canonical request; identical retries return the same booking and private token, changed requests return 409.

Confirmation is automatic or manual. Manual requests hold capacity for a configurable 5–120 minutes (default 15). Staff use **Rezervări** to confirm, reject, seat, complete, cancel or mark no-show using optimistic versions. Seating/no-show/completion cannot precede arrival. Expired holds stop blocking immediately; `zionfood:reservations-expire` records their expiry/history each minute. Guests may withdraw a pending request or cancel a confirmed booking before its snapshotted cutoff. Contact the restaurant after the cutoff.

The receipt requires a 64-character bearer capability. It is kept in session storage or received in an email URL fragment, removed from the address bar before API calls. Public responses omit guest contact data; foreign mapped storefronts cannot retrieve the booking. Tokens are hashed for lookup and separately encrypted for replay/email. Keep APP_KEY recoverable. Optional guest notifications are localized, encrypted, queued after commit and share the existing 140-recipient/24h quota. Superseded versions and expired pending notifications are skipped. This increment does not send SMS, staff booking emails, reservation webhooks or collect deposits.

## Tables and QR

Saving a table plan creates stable relational table identities and QR credentials; codes are unique per location. **Mese și QR** lets authorized location staff open/close the table menu; connected sessions have an eight-hour lifetime. Owners/managers can download QR images, preview links and rotate credentials. Rotation closes sessions and invalidates old links. Closing a menu persistently pauses the table and closes its sessions; scanning again cannot reopen it. Staff reopen the same menu without changing the permanent QR. Accepted orders are retained.

`qr_requires_staff` defaults to false: the permanent QR connects directly while the table menu is open. Staff closing it persists until they explicitly reopen it. Optional staff activation can still be selected in Services. Both policies rely on possession of the QR link; they do not prove physical presence. Anyone retaining a link can access an open session, so staff can pause the menu, require explicit activation or rotate leaked codes as appropriate. A shared table session supports several guests/orders and is not a bill/payment session.

QR credentials travel in URL fragments and are verified under the same location lock used by rotation. The public menu exchanges the proof for a live session token. Checkout validates service, tenant, active table, session expiry/closure and current menu prices. Orders snapshot table code/area and display them in the existing order drawer and kitchen queue. Dine-in orders use the existing non-delivery completion path. No payment collection, reservation check-in linkage, table combining or automatic occupancy inference from QR sessions is implemented.

Changing seating is blocked while future blocking reservations or live table sessions exist, preserving allocation integrity. Cancel/complete affected bookings and close sessions before restructuring. Disabling a service stops new activity while retaining existing records and staff management.

**QR closing cutoff:** Settings > Stabilește ritmul configures `dine_in_cutoff_minutes` per location, 0–240, default 30; 30 and 60 minutes are typical choices. The server checks venue timezone/business hours and refuses connection/new orders at or after closing minus the cutoff, including sessions connected earlier. Setting 0 permits orders until the normal closing time. Pickup/delivery and existing orders are unaffected. Staff may prepare/reopen menus outside hours, but this cannot bypass the public ordering window. Service hours remain location-specific for multi-location restaurants.

## Delivery map

**Setări > Stabilește ritmul > Zone de livrare** offers one editable polygon per zone. Choose a zone, draw its outline, finish at the first vertex or toolbar action, edit/remove vertices, then save the settings. Fees/minimums are integer bani; lower numeric priority wins overlapping areas, with ID as a deterministic tie-breaker. The editor starts at the location's configured coordinates, or Bucharest if none exist.

The customer selects a delivery pin. When at least one active polygon exists, all deliveries require a point inside a polygon; postal prefixes and client-selected zone IDs cannot bypass it. The server selects the zone, minimum and fee; edges are included. The order snapshots the coordinates. Without polygons, the existing postal-prefix flow continues. The address is descriptive text: there is no automatic geocoding or guarantee that text matches the pin.

Geometry is portable GeoJSON `[longitude, latitude]`, a closed simple Polygon with one ring, at most 200 vertices. Holes, MultiPolygon, antimeridian crossings, degenerate or intersecting polygons are rejected. Maximum 30 zones per location. Browser rendering is a preview; the PHP check is authoritative. Deleting/replacing zones preserves existing immutable order totals and coordinates.

### Provider choices

| Option | Rendering and drawing | Operation |
| --- | --- | --- |
| Implemented default | Leaflet + Geoman Free, configurable raster tiles | No provider account required locally; standard OSM tiles are best-effort and subject to their usage policy |
| Alternative | MapLibre GL JS + Terra Draw | Vector styling; select a tile/style provider and its pricing/limits |
| Alternative | Google Maps JS + Terra Draw | Requires Google Cloud configuration/billing; do not depend on the retired Drawing Library |

The user selected Leaflet/Geoman; no paid service was activated. Configure `ZF_MAP_TILE_URL` and `ZF_MAP_ATTRIBUTION` for an appropriate production tile service. Preserve required attribution; only public browser tile credentials belong in a tile URL. Tile failures display a warning and do not erase saved geometry. The map sends tile requests to the selected provider; consider that provider in the deployment's privacy policy. Address search/geocoding is a separate future provider choice.

Primary references: [Leaflet](https://leafletjs.com/), [Geoman Free](https://geoman.io/docs/leaflet/), [OSM tile usage policy](https://operations.osmfoundation.org/policies/tiles/), [MapLibre](https://maplibre.org/maplibre-gl-js/docs/), [Terra Draw](https://terradraw.io/), [Google Drawing Library deprecation](https://developers.google.com/maps/documentation/javascript/drawinglayer).

## Deployment and validation

Run additive migrations `2026_10_26_000300`, `000400` and `000500`, build frontend assets, rebuild config cache and restart Horizon gracefully. The existing scheduler must run every minute. Activate new services per location after reviewing seating/rules. This local increment does not change Forge or deploy automatically. Real guest email delivery, paid tile providers, physical printed-QR/device scanning, live reservation traffic and production load remain unverified.

Meaningful checks include allocation/hold expiry, overlapping capacity, tenancy, token privacy, stale versions, service flags, QR rotation/session closure, authoritative polygon priority/fees/minimums, malformed geometry, dedicated MariaDB concurrency and actual browser reservation/QR/map flows. See CODEX_PROGRESS.md for executed results.
