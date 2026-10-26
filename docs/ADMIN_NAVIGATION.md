# Restaurant navigation and inline setup

Setup is the first link below the location selector. Remaining links are always visible in three labelled groups:

| Group | Links |
| --- | --- |
| Operations | Overview, orders, reservations, tables/QR, kitchen |
| Restaurant management | Menu, staff, locations, restaurant identity, hours/delivery |
| Connections | Integrations, webhooks |

Staff without management access see only the operational group; server authorization remains authoritative. Deep wizard routes highlight Setup. Mobile navigation scrolls and closes after link selection. Changing location from the wizard retains its current step, subject to an unsaved-edit confirmation.

## Embedded setup editors

The `hours`, `delivery`, `menu`, `branding`, `staff` and `locations` setup routes contain real editors. Existing Basics, Cuisine, seating, services, reservations, legal, publish and payment-placeholder behavior remains. Cuisine still follows Name and address. Hours and delivery are separate steps; the Services delivery link remains inside the guide. Existing readiness gates and the ten-item progress denominator remain unchanged; supporting locations/delivery pages create no new launch requirements.

Settings, Menu, Branding and Staff pages render through an embedded RestaurantLayout without another shell or document title. Setup loads only the selected editor's props through the same authorized WorkspaceController builder as standalone pages. Existing save endpoints return to the wizard referer. Previous/Next remain available; no iframe or duplicate data store is used.

Hours shows schedules and the QR cutoff; Delivery shows polygons/postal prefixes, fees, minimums and map centering. Both retain the existing full location-settings payload and concurrency behavior. They do not increment setup_revision; dedicated setup forms retain their revision checks. Embedded editors warn before leaving dirty forms; successful saves reset their baseline. The hours-only page does not trigger geocoding.

## Locations

The standalone `/app/locations/{public_id}/locations` page and inline `setup/locations` step list only existing locations accessible to the signed-in user. Configure links appear only where that user can manage the organization and lead to setup/basics. This increment adds no location creation, deletion, organization transfer or access grants. Every destination still enforces tenant authorization.

Backend tests cover editor data, standalone compatibility, foreign/staff denials, menu save return/persistence, unsupported setup writes and scoped location lists. Browser checks cover grouping/order, embedded saves, dirty navigation cancellation, location configuration, mobile and RO/EN. Completed results and limits are in CODEX_PROGRESS.md.

Restaurant management now includes Ingredients. Platform administration also links the shared ingredient catalogue. Menu has an inline Manage ingredients dialog, including within setup/menu, so product and custom ingredient preparation remain inside the guide. Standalone ingredient administration remains available. See INGREDIENTS.md.
