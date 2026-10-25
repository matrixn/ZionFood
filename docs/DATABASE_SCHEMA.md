# Database schema

The migrations are authoritative. Internal identifiers use BIGINT; organizations, restaurants, locations, menus, menu items and orders additionally use ULID public identifiers. Money is unsigned integer minor units; initial currency is RON. Order subtotal is capped at 100,000,000 bani. Configured product prices and quantities are bounded independently.

| Area | Tables | Relationships and invariants |
| --- | --- | --- |
| Identity | users, organizations, organization_memberships, location_staff | Unique organization/user membership and location/user assignment; staff organization must match both membership and location |
| Restaurants | restaurants, restaurant_locations, location_business_hours, storefront_domains | Restaurant/location organization uses a composite foreign key; one opening interval per weekday; unique verified hostname mapping |
| Menus | menus, menu_categories, menu_items, menu_item_variants | Menu/restaurant, category/menu and item/category organization pairs use composite foreign keys; products are soft deleted |
| Modifiers | modifier_groups, modifier_options, menu_item_modifier_groups | Item/group pivot verifies the same organization on both sides; min/max selection rules enforced by validation/Action |
| Publication | location_menus | Location and menu share an organization via composite foreign keys; the initial onboarding attaches the restaurant's main menu |
| Orders | orders, order_items, order_item_modifiers, order_status_history | Order/location organization composite foreign key; product/variant/modifier names and integer prices are copied into snapshots; no snapshot FK to deletable products |
| Reliability | checkout_idempotency_keys, outbox_messages | Unique location/key and event ULID; outbox retains attempts and delivery state |
| Fulfillment | location_fulfillment_settings, delivery_zones | One configuration per location; postal prefix, delivery fee, minimum order and active flag per zone |
| SaaS | plans, organization_subscriptions | One subscription per organization; plan feature JSON, status and trial expiry |
| Integrations | personal_access_tokens, api_integrations, webhook_endpoints, webhook_deliveries | Sanctum stores hashed tokens with optional organization scope; webhook endpoints/deliveries now support an enabled signed delivery service; api_integrations remains reserved for future integration metadata |

Order indexes cover organization/location/status/creation time. Idempotency and history remain retained indefinitely in this MVP; a production retention policy and maintenance jobs remain to be designed. The tracking token is hashed for lookup and separately encrypted with APP_KEY for safe idempotent replay. Back up APP_KEY together with database recovery procedures, without placing it in Git.

Factories cover users, organizations, restaurants, locations, menus, categories, items and modifier groups. DemoSeeder creates three isolated organizations with five categories and twenty products per restaurant, variants, modifier groups, opening hours, delivery settings and sample order states. It is restricted to local/testing, and writes random demo credentials into ignored private storage. Platform-admin creation is an explicit Artisan command.

## October 26 expansion

`users.locale` defaults to ro. `staff_invitations` stores organization, inviter/acceptor, normalized email, role, validated location IDs, hashed token and expiry/acceptance/revocation timestamps; organization/email is unique. Restaurant branding stores tagline, validated primary color, randomized logo/cover paths and public contact fields. Webhook endpoints add active location, name, event selection and soft deletion. Deliveries link to the durable outbox message and retain a minimal event envelope, attempt/next-attempt state, lease/claim token, HTTP status and bounded failure category. Endpoint/event uniqueness makes fanout idempotent. Soft-deleted endpoints retain delivery history; signing secrets and delivery envelopes/claim tokens are hidden from serialized metadata.
