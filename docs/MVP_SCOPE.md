# Scope at 0.0.1

## Implemented

- Fortify registration/login/logout, verification and password-reset pages with PrimeVue.
- Onboarding creates organization, owner membership, restaurant, location, hours, fulfillment defaults, main menu, subdomain mapping and 14-day trial.
- Explicit organization/location authorization, Policies and composite tenant foreign keys.
- Restaurant dashboard, location switcher, orders/history/detail drawer, categories, item create/edit/soft deletion, image uploads, variants and modifier groups.
- Same-day opening hours, pickup and postal-prefix delivery zones with fees and minimums.
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

## Remaining before production launch

- Implement the customer reservation/capacity-allocation engine, on-site/QR ordering, scheduled fulfillment, split/overnight/per-service/holiday schedules and payment processing. Setup preferences do not activate these future services.

- Complete Forge deployment and external DNS/TLS/mail configuration, including verified live subdomains.
- Complete multi-restaurant/multi-location creation screens. Onboarding creates one of each; the schema and location switcher support multiple records.
- Implement the separate WordPress plugin; provision custom domains and SSL after operational verification.
- Harden billing/subscription lifecycle beyond local trial entitlement. No payment collection.
- Define privacy retention, idempotency cleanup, outbox archival, backup/restore and incident procedures.
- Persist carts across full refresh if desired and add a portable customer tracking flow. Interface languages are Romanian/English; owner-authored menu content is not automatically translated.
- Extend browser coverage of reset/verification emails, delivery flows, dark mode, touch sound/fullscreen and accessibility; real SMTP remains unverified.
- Validate capacity on the intended production runtime. Local load results must be read with their precise test conditions; notification p95 and sustained outage recovery are not production guarantees.

No inert P0 buttons are intentionally exposed. Future integrations are described as unavailable rather than presented as functioning services. See CODEX_PROGRESS.md for exact measured verification and outstanding work.
