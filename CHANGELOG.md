# Changelog

## Unreleased — 2026-10-26 (version remains 0.0.1)

- Native Resend email transport SDK, RESEND_API_KEY configuration, Forge instructions and a validated Artisan mail test command.
- Staff invitations/acceptance/revocation, protected owner/manager roles, location assignments and scoped token removal.
- Restaurant branding, asset uploads/removal, managed slug, contacts, live preview and public menu/tracking theme.
- Romanian-default and English interfaces with persistent flag/name selectors, localized PrimeVue/validation/status labels and stable API field names.
- Signed webhook configuration, tenant/location fanout, cURL DNS pinning and SSRF restrictions, leases, bounded retry/history and scheduler reconciliation.
- Fixed stale CSRF headers after Inertia login, applied confirmed kitchen responses before background reconciliation and corrected the reported Pint style issue with Linux CI verification.


## 0.0.1 — 2026-10-25

Initial local MVP implementation: tenant-aware onboarding and authorization, PrimeVue restaurant workspace and storefront, menu configuration, transactional checkout and order snapshots, idempotency, private realtime kitchen operations with outbox recovery, trial entitlements, API v1 and organization-scoped integration tokens.

Includes MariaDB/Redis development setup, local demo restaurants, automated backend/frontend/browser/concurrency checks, OpenAPI and measured k6 scenarios. See docs/MVP_SCOPE.md and docs/CODEX_PROGRESS.md for remaining features and verification limits. No production deployment is included.
