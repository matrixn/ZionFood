# CODEX MASTER PROMPT — ZionFood SaaS MVP

## Your role

You are the lead software architect and senior full-stack engineer responsible for implementing ZionFood, a multi-tenant restaurant ordering and management SaaS.

Your responsibility is to implement working, tested software, not merely generate documentation, mock screens, or architectural proposals.

**MVP target: November 1, 2026.**

Work incrementally, preserve a functioning application after each phase, and prioritize correctness, maintainability, security and delivery speed.

## 1. Product overview

ZionFood is a SaaS platform inspired by the functional scope of GloriaFood, but designed as an independent product with a modern UI, API-first architecture, WordPress integration capabilities and future native desktop applications.

A restaurant owner must be able to:

- Register an account and create an organization.
- Create and configure a restaurant with an initial location.
- Manage menus, categories, products, prices, variants and modifier groups.
- Configure opening hours, pickup and basic delivery settings.
- Publish a public ordering storefront.
- Receive customer orders in real time.
- Accept, reject and process orders.
- Use a dedicated Kitchen Display System.
- Review previous orders.
- Access the platform through an organization-aware SaaS account.

Customers must be able to:

- Browse a restaurant's public menu.
- Configure products with variants and modifiers.
- Add products to a cart.
- Submit an order without creating an account.
- Select pickup or delivery.
- Track their order using a secure, unguessable tracking token.

The architecture must support at least 100 restaurants as a design target. Production capacity must be verified through load testing, not assumed.

## 2. Required technology stack

Backend:
- Laravel 12
- PHP 8.4
- MariaDB 10.11 or later
- Redis
- Laravel Horizon
- Laravel Reverb
- Laravel Sanctum
- Laravel Policies and Form Requests
- Pest

Frontend:
- Vue 3 Composition API
- TypeScript with strict typing
- Inertia.js 3
- PrimeVue 4
- Tailwind CSS 4
- PrimeIcons or a consistent icon library
- Pinia for the shopping cart and kitchen-specific client state
- Laravel Echo
- Vite
- Vitest and Playwright

Additional:
- OpenAPI documentation for external API contracts
- k6 smoke/load-test scenarios
- Laravel Pint
- ESLint / TypeScript checks

Use compatible, verified package versions. Inspect installed dependencies before modifying them. Do not blindly upgrade major versions.

Start with the official Laravel Vue/Inertia starter kit where appropriate. Replace starter UI components with PrimeVue cleanly; do not preserve unused shadcn-vue dependencies merely because they came with the starter.

Use PrimeVue styled mode with a customized theme for MVP speed. Keep a ZionFood-specific wrapper layer. Volt components may be introduced selectively when necessary; avoid inconsistent mixed component styling.

## 3. Architectural principles

Implement a modular monolith in one Laravel application.

Separate these domains:

- Tenancy
- Restaurants
- Menus
- Orders
- Delivery
- Subscriptions
- Domains
- Integrations

Use:

- Thin controllers
- Form Requests
- Policies
- Domain Actions
- Domain Services where necessary
- Typed data transfer objects where useful
- Eloquent models and relationships
- Events and queued jobs
- API Resources

Do not duplicate business logic between Inertia controllers and REST API controllers.

Important operations, including PlaceOrder, TransitionOrderStatus and UpdateMenuItem, must be implemented as reusable Actions.

Avoid speculative abstractions, microservices, unnecessary repository patterns and excessive third-party packages.

## 4. Multi-tenancy model

Use a single MariaDB database with shared-schema multi-tenancy.

Hierarchy:

Organization -> Restaurant -> Location

An organization can own multiple restaurants.

A restaurant can have multiple locations.

The MVP onboarding creates one organization, one restaurant and one location, but the data model must support future expansion.

Implement:

- Organization memberships
- Organization-scoped roles
- Location-scoped staff access
- Active organization resolution
- Tenant-aware authorization
- Tenant-aware database queries
- Tenant-aware jobs and events
- Tenant-aware broadcasting channels

Use platform-wide administrator privileges separately from organization membership.

Do not rely exclusively on Eloquent global scopes for security.

Explicitly verify organization ownership and location access in Policies, Actions and API controllers.

Never trust an organization ID, location ID or product price submitted by a client without server-side verification.

## 5. Database schema

Implement migrations, models, factories and relevant relationships for:

Identity:
- users
- organizations
- organization_memberships
- location_staff

Restaurants:
- restaurants
- restaurant_locations
- location_business_hours
- storefront_domains

Menus:
- menus
- menu_categories
- menu_items
- menu_item_variants
- modifier_groups
- modifier_options
- menu_item_modifier_groups
- location_menus

Orders:
- orders
- order_items
- order_item_modifiers
- order_status_history
- checkout_idempotency_keys
- outbox_messages

Fulfillment:
- location_fulfillment_settings
- delivery_zones

SaaS and integrations:
- plans
- organization_subscriptions
- api_integrations
- webhook_endpoints
- webhook_deliveries

Use BIGINT internal primary keys and ULID public identifiers where needed.

Store money as integer minor units, not float.

Use RON as the initial currency, but retain currency fields for future expansion.

Store product, variant, modifier, tax and price snapshots in order lines so historical orders remain accurate after menu changes.

Add foreign keys, uniqueness constraints and composite indexes.

Prevent cross-organization relationships from being accepted.

Model product deletion carefully so existing orders remain valid.

Use explicit status enums/casts and timestamps.

## 6. Order processing requirements

Implement a PlaceOrderAction with these responsibilities:

1. Resolve the restaurant and location using a trusted context.
2. Verify that the location accepts orders.
3. Validate opening hours and fulfillment availability.
4. Validate requested products, variants and modifier choices.
5. Recalculate every price and total server-side.
6. Validate the delivery zone, fee and minimum order.
7. Enforce checkout idempotency using the Idempotency-Key header.
8. Create the order and order item snapshots inside a database transaction.
9. Persist an outbox event in the same transaction.
10. Return the created order's public identifier and secure tracking token.

Implement these order statuses:

- pending
- accepted
- preparing
- ready
- out_for_delivery
- completed
- rejected
- cancelled

Define allowed transitions explicitly.

Prevent unauthorized or conflicting transitions using database-level concurrency protection.

Maintain an immutable status history with timestamps and actor information.

Implement real-time notifications using Laravel Reverb and Echo.

Broadcast only after the database transaction commits.

Persist events for retry and reconciliation using an outbox pattern.

Handle duplicate jobs and repeated event delivery safely.

The Kitchen Display must recover missed updates after reconnecting by fetching authoritative state from the API.

Do not depend on WebSocket messages as the source of truth.

## 7. Required frontend layouts

Create:

- AuthLayout.vue
- AdminLayout.vue
- RestaurantLayout.vue
- StorefrontLayout.vue
- KitchenLayout.vue

Implement a cohesive ZionFood design system with reusable components.

Design goals:

- Premium modern SaaS appearance
- Responsive desktop/mobile layout
- Consistent spacing and typography
- Clear loading, validation and empty states
- Accessible dialogs and forms
- Light/dark compatibility
- Touch-friendly Kitchen Display
- Minimal unnecessary animations

Suggested palette:

Primary: #635BFF
Dark ink: #171C2C
Success: #0EA87A
Warning: #F3A642
Light surface: #F6F7FB

Use centralized design tokens rather than scattering hardcoded values.

## 8. Required MVP pages

Authentication:
- Login
- Registration
- Email verification
- Password reset

Onboarding:
- Organization creation
- Restaurant details
- Initial location setup

Platform admin:
- Organization list
- Organization details
- Subscription/trial overview

Restaurant dashboard:
- Dashboard overview
- Live orders and history
- Order detail
- Menu management
- Product create/edit
- Categories
- Modifier groups
- Opening hours
- Fulfillment configuration
- Restaurant settings

Public storefront:
- Restaurant menu
- Product customization dialog
- Cart drawer
- Checkout
- Order tracking

Kitchen:
- Live Kitchen Display
- Accept order
- Start preparation
- Mark ready
- Complete order
- Reject with reason

All P0 pages must have actual persistence and working interactions. Do not implement decorative interfaces with buttons that do nothing.

## 9. PrimeVue component strategy

Use PrimeVue for:

- DataTable
- Dialog
- Drawer
- Select
- InputNumber
- DatePicker
- Tabs
- Toast
- ConfirmDialog
- Form inputs

Create reusable wrappers:

- ZFPageHeader
- ZFStatCard
- ZFStatusBadge
- ZFDataTable
- ZFDrawer
- ZFConfirmDialog
- ZFFormField
- ZFEmptyState

Build domain-specific components:

- MenuItemEditor
- ModifierGroupEditor
- OrderTable
- OrderDetailsDrawer
- KitchenOrderCard
- KitchenStatusColumns
- ProductConfigurator
- CartDrawer
- CheckoutForm
- OrderTrackingTimeline

Do not rebuild generic component functionality already provided by PrimeVue.

## 10. API-first requirements

Expose versioned REST endpoints under /api/v1.

The backend must use the same domain actions for internal dashboards and external API requests.

Prepare endpoints for:

- Public restaurant/menu retrieval
- Public checkout
- Order tracking
- Authenticated menu management
- Authenticated restaurant order retrieval
- Authenticated order status transitions
- Integration tokens
- Webhook configuration

Use Laravel Sanctum for external integrations.

Supported future token abilities:

- menu:read
- menu:write
- orders:read
- orders:manage

Token abilities must not bypass organization and location authorization.

Never expose private integration credentials to frontend JavaScript.

Generate or maintain an OpenAPI specification for the supported endpoints.

## 11. Hostname architecture

Use these logical hostnames:

app.zionfood.ro
api.zionfood.ro
{slug}.zionfood.ro

Central administrative routes must only resolve on trusted application hostnames.

Public storefronts must resolve their location through a verified hostname-to-location mapping.

Prevent collisions with reserved hostnames such as app, api, www and admin.

Do not trust arbitrary Host headers.

Prepare the storefront_domains table for later custom domains, including:

- hostname
- location_id
- type
- verification_status
- ssl_status
- is_primary

Do not implement full Cloudflare for SaaS provisioning during the initial MVP unless all critical P0 work is complete.

## 12. Security and privacy

Implement and test:

- Organization data isolation
- Location-specific authorization
- CSRF protection for browser flows
- Token ability checks
- Server-side price calculation
- Checkout rate limiting
- Idempotency
- Safe upload validation
- Private broadcast authorization
- Secure tracking tokens
- No personal data in unnecessary broadcasts
- No plaintext integration secrets in logs
- Restricted webhook destinations when webhooks are enabled
- Validation of all external inputs
- No hardcoded production credentials

Do not implement payment-card processing or claim fiscal compliance as part of this MVP.

Customer tracking should use token-protected endpoints and safe polling initially. Do not expose public order channels without authorization.

## 13. Seed data

Create demo seeders for three isolated organizations with different restaurants.

Each demo restaurant should have:

- One owner
- One location
- Opening hours
- Pickup and delivery settings
- At least five menu categories
- At least twenty menu products
- Several variants
- Several modifier groups
- Sample orders in different statuses

Seed platform-admin access securely through environment-based configuration or an explicit Artisan setup command.

Never commit demo passwords or secrets intended for production.

## 14. Tests and acceptance criteria

Create automated tests proving:

- Organization A cannot access organization B's orders, menus or locations.
- Staff cannot operate outside assigned locations.
- Restaurant owner can manage their own menu.
- Public customer can place a valid order.
- Invalid modifier selections are rejected.
- Client-supplied prices are ignored.
- Repeated checkout requests do not duplicate orders.
- Orders cannot be placed for unavailable products.
- Closed restaurants reject immediate orders.
- Invalid status transitions are rejected.
- Simultaneous status updates cannot corrupt order state.
- Order item snapshots remain unchanged after menu edits.
- Broadcasting events are dispatched after commit.
- Failed outbox deliveries can be retried.
- Tracking tokens do not expose other orders.
- KDS recovers pending orders after reconnecting.

Run migration tests, Laravel tests, TypeScript checks and frontend build.

Provide a k6 load-test script simulating 100 restaurants, configurable concurrent kitchen sessions and order submission rates.

Performance targets for benchmarking, not guaranteed claims:

- Cached menu retrieval p95 below 400 ms under the defined test load.
- Checkout p95 below 800 ms under the defined test load, excluding third-party services.
- Live notification latency typically below 2 seconds under the defined test load.
- Zero duplicate orders from repeated identical idempotent requests.
- Zero confirmed orders lost during recoverable broadcast failures.

Report measured results and test conditions. Do not claim production capacity without successful tests.

## 15. Implementation phases

### PHASE 1 — Foundation

- Inspect the repository.
- Initialize Laravel 12 and the Vue/Inertia starter if the repository is empty.
- Configure MariaDB, Redis and PrimeVue.
- Set up the ZionFood theme and application layouts.
- Implement authentication.
- Create organization, restaurant and location models.
- Implement basic tenancy middleware and authorization.
- Add factories and tests.
- Verify migrations and build.

### PHASE 2 — Menu Management

- Implement menus, categories, products, variants and modifiers.
- Build menu CRUD interfaces.
- Implement image upload and validation.
- Implement opening hours.
- Add public menu rendering.
- Add business rules and tests.

### PHASE 3 — Checkout and Orders

- Implement cart state in Pinia.
- Build the product customization flow.
- Build checkout.
- Implement PlaceOrderAction.
- Implement price snapshots and totals.
- Implement idempotency.
- Implement status transition workflow.
- Add automated tests.

### PHASE 4 — Real-time Kitchen

- Configure Reverb and Echo.
- Implement private organization/location channels.
- Implement transactional outbox and retry handling.
- Build Kitchen Display.
- Implement reconnect and synchronization behavior.
- Add notification sound activated after a user interaction.
- Test concurrent order updates.

### PHASE 5 — SaaS, API and Hardening

- Add trial plans and feature entitlements.
- Add the public and integration API v1.
- Implement subdomain storefront resolution.
- Generate OpenAPI documentation.
- Add integration placeholders for WordPress.
- Finish platform admin.
- Add rate limiting and security tests.
- Run Playwright and k6 tests.
- Document deployment to Laravel Forge.

## 16. Documentation to generate

Maintain these files:

- docs/ARCHITECTURE.md
- docs/DATABASE_SCHEMA.md
- docs/API.md
- docs/SECURITY.md
- docs/MVP_SCOPE.md
- docs/DEPLOYMENT.md
- docs/CODEX_PROGRESS.md

CODEX_PROGRESS.md must include:

- Completed tasks
- Remaining tasks
- Files modified
- Tests executed
- Test failures and blockers
- Known limitations
- Next implementation step

Documentation must describe the actual implementation, not imaginary or planned functionality as if it were already complete.

## 17. Execution rules

- Implement real working code.
- Avoid placeholder methods and TODO-only features for P0 functionality.
- Do not create an unnecessary second framework or backend.
- Do not use Filament, Nova or MoonShine.
- Do not introduce React.
- Do not replace MariaDB with PostgreSQL without justification and explicit approval.
- Preserve existing working code when the repository is not empty.
- Do not perform destructive operations on existing data.
- Favor simple solutions that satisfy current requirements and allow later extension.
- Apply Laravel Pint and frontend formatting.
- Run relevant tests after each meaningful milestone.
- Fix failures before proceeding when reasonably possible.
- Make small, logical Git commits when operating in an appropriate Git repository.
- Do not commit secrets, credentials or generated vendor/node_modules directories.
- Clearly identify anything that cannot be verified in the current environment.

Begin by inspecting the repository and environment.

Produce a concise execution plan, then immediately implement Phase 1. Continue with subsequent phases as the available execution context permits, recording exact progress in CODEX_PROGRESS.md.

The final goal is a functional ZionFood MVP, not a collection of scaffolding files.