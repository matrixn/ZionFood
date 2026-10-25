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
- MariaDB/Redis local Compose services, three restaurant demos, factories, automated tests, OpenAPI and configurable k6 scenarios.

## Remaining before production launch

- Complete Forge deployment and external DNS/TLS/mail configuration, including verified live subdomains.
- Expand staff/role administration and invitations; roles and location assignments currently exist in models and authorization, without a staff-management screen.
- Complete restaurant brand editing and multi-restaurant/multi-location creation screens. Onboarding creates one of each; the schema and location switcher support multiple records.
- Add webhook configuration/delivery and the separate WordPress plugin; provision custom domains and SSL only after critical ordering work is stable.
- Harden billing/subscription lifecycle beyond local trial entitlement. No payment collection.
- Define privacy retention, idempotency cleanup, outbox archival, backup/restore and incident procedures.
- Persist carts across full refresh if desired, add a portable tracking flow and improve regional language/content coverage.
- Extend browser coverage of reset/verification emails, delivery flows, dark mode, touch sound/fullscreen and accessibility; real SMTP remains unverified.
- Validate capacity on the intended production runtime. Local load results must be read with their precise test conditions; notification p95 and sustained outage recovery are not production guarantees.

No inert P0 buttons are intentionally exposed. Future integrations are described as unavailable rather than presented as functioning services. See CODEX_PROGRESS.md for exact measured verification and outstanding work.
