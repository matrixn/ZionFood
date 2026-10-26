# Guided restaurant setup

Implemented locally on October 26, 2026. Entry: **Pregătește restaurantul / Restaurant setup** in the owner/manager workspace, or `/app/locations/{public_id}/setup`. New onboarding returns directly to this guide. Existing restaurants remain on their dashboard and retain their current order-acceptance state.

## Reference analysis

The user-provided GloriaFood / restaurantlogin.com account was inspected through its existing authenticated browser tab, read-only. Sidebar navigation and explanatory panels were opened; no fields, service toggles, restaurant data, subscriptions, legal agreements or payment settings were saved or activated. The reference account's personal/contact details are not copied into ZionFood.

| GloriaFood setup group | Observed submenus and functionality | ZionFood treatment |
| --- | --- | --- |
| Restaurant basics | Name/address/phone, country/time zone, city/postal address; location; website; cuisine; account confirmation | Structured location details and optional coordinates; existing Fortify verification; branding editor retained |
| Services & opening hours | Pickup; delivery zones and advanced outside-area/address options; table reservation; on premise and anonymous ordering; opening hours per service; special days/holidays and service pause; scheduled orders | Live pickup/delivery switches; existing hours/postal-zone editor; capacity/table plan and explicit draft preferences for future on-site/scheduled ordering/reservations |
| Payment methods & taxes | Currency, tax-inclusive/exclusive pricing, tax categories and delivery-fee tax; cash and in-person card by service; legacy phone card-details option | Existing integer product prices/tax basis points; no copied tax percentages or legal assumptions; payment processing postponed by user instruction; no phone collection/storage of card details |
| Taking orders | Mobile order-taking app/device requirements and missed-order phone alert | Existing browser KDS, authorized staff and realtime notifications; no claim of a native mobile app or automated phone calls |
| Menu Setup | Category editor, reusable choices/add-on groups and Preview & Test Ordering | Existing category/product/variant/modifier editors linked directly, plus public menu preview |
| Publishing | Legal company details, policies, Facebook, smart links, sales website, legacy website, shared/branded mobile apps | Private company/policy setup, existing hosted public menu and service-readiness checklist; external publishing/app products are not implied |
| Payments | Online providers and reservation deposit; deposit explanation explicitly covers per-reservation/per-guest amounts, refundability and applying a deposit to the guest bill, requiring active online payments | A visible **coming soon** placeholder only, per explicit user instruction |

Inspection limits: disabled/gated services were not enabled merely to reveal deeper settings. Specific provider lists, premium capabilities, actual booking availability algorithms and individual populated menu-item dialogs were not verified. The visible reference tax values are account data, not authoritative current tax guidance. The reference UI also displays a retirement notice; its branding or business lifecycle is not adopted by ZionFood.

## Navigation and implementation

| Group | Step | What saving does |
| --- | --- | --- |
| Your restaurant | Name and address | Saves restaurant name, location name/address/time zone, city/country/postal code, phone/email/website/cuisine and optional coordinate pair |
| Your restaurant | Areas, tables and seats | Saves either total capacity or a table plan with unique table codes, area/terrace labels and seat counts; computes the total server-side |
| How you operate | Services | Updates pickup, delivery, QR ordering and reservation flags independently; retains scheduled/catering preparation |
| How you operate | Hours and delivery | Opens the existing location editor for business hours and drawn polygon or postal-prefix delivery zones/fees/minimums |
| How you operate | Menu | Opens the existing category, item, variant, modifier and product-tax editor |
| How you operate | Branding | Opens the existing logo/cover/colors/contact editor |
| Team and guests | Reservation preparation | Saves intended confirmation mode, seating duration, slot interval, notice/horizon, party-size bounds, cancellation notice and guest instructions |
| Team and guests | Staff and access | Opens the existing protected staff/invitation editor; the owner already has access, so invitations are optional |
| Team and guests | Company and policies | Stores legal entity/address, tax/registration identifiers, policy URLs and cancellation text as private setup data |
| Launch | Review and publish | Calculates blockers from current data, previews the public menu and changes the existing order-acceptance flag |
| Launch | Payments | Informational placeholder; no form, keys, provider connection, payment policy persistence or charges |

All pages have RO/EN copy and the existing persistent language selector. The desktop guide has grouped submenus, current-step highlighting and completion checks. Mobile uses a compact step selector. Saving stays on the current step; navigation has explicit previous/next links. Unsaved changes trigger a leave-page confirmation. Progress is recalculated from saved data; it is not a client-supplied completion flag. Use the existing setup navigation to return after editing menu/staff/hours/branding.

## Data ownership and integrity

- The additive migration adds nullable `setup_profile` JSON and `setup_revision` to restaurant_locations. No existing data is rewritten or deleted.
- Public storefront responses continue to expose only their existing explicit public fields. `setup_profile` is hidden from automatic model serialization and returned only by the owner/manager setup controller; legal/private notes do not become public website policies automatically.
- Both Form Request and domain Action enforce tenant management access. Ordinary staff and foreign tenants cannot inspect or edit setup data. Public IDs are resolved before authorization.
- Each setup write locks the location and checks its revision, rejecting stale writes without overwriting newer setup changes. This protects concurrent wizard edits; older standalone editors retain their own existing behavior and do not increment the wizard revision.
- Basics writes keep authoritative location name/address/time zone and restaurant name synchronized; contact/cuisine/legal preferences remain location setup fields. Public restaurant contacts still use the Branding editor, allowing multi-location preparation without silently overwriting shared contact details.
- Table plans synchronize stable relational table identities and QR credentials; the reservation engine uses them as allocation inventory. Codes are unique ignoring case; unknown per-table attributes are rejected; seat counts are positive and total capacity is bounded. Capacity-only mode supports delivery-only venues with zero seats.
- Planned reservation party sizes cannot exceed capacity, and capacity cannot be reduced below an already planned maximum party size. Time ranges, coordinates, party sizes, URLs and time zones are validated server-side.
- Setup publication requires contact/address details, operating hours, an active menu with an available product, at least one viable pickup/delivery/QR service, and delivery zones when delivery is enabled. Pausing remains allowed. Existing checkout remains authoritative for subscriptions, actual opening times and request validation. Existing Settings remains an operational control; this checklist is not a replacement for checkout validation or a legal compliance certification.

## Current boundaries and agreed future decisions

**Working now:** restaurant/location setup, transactional reservations and guest cancellation, staff booking management, table QR sessions/orders, per-location service activation, polygon delivery areas and existing menu/branding/staff editors. See [runtime behavior and boundaries](RESERVATIONS_DINING_DELIVERY.md).

**Preparation only:** scheduled orders and optional off-site catering. Existing hours support one same-day interval per weekday; split/overnight/per-service schedules and holidays remain future work.

**Payments are postponed:** the user explicitly selected a placeholder for now. Future payment policy must be selectable per restaurant among fixed deposit per reservation, deposit per guest, and full payment for a selected menu/package. Exact refund/cancellation/no-show policy, applying a deposit to the final bill, provider/onboarding and multi-merchant settlement must be decided before collecting money. No speculative merchant defaults or live charges are introduced in this increment.

Remaining runtime phases include catering/events, scheduled-order fulfillment and payment integration. Keep the stack, organization/location boundaries, immutable order totals, outbox and existing queue/email safeguards.

### Optional off-site catering decision

The user explicitly included catering/events at a different address, controlled by each restaurant rather than globally enabled. Services now includes `catering_planned`, false by default and independently saved per location alongside on-site/scheduled preferences. It may be turned on or off without enabling delivery or changing any other tenant. This is preparation only; event dates, guest quantities, event address, quotations and eventual fulfillment remain part of the future catering flow, with payments still postponed.
