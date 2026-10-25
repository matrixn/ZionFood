# ZionFood

**Version 0.0.1** · Multi-tenant restaurant ordering SaaS.

Laravel 12 / PHP 8.4 / MariaDB / Redis / Horizon / Reverb / Sanctum, with Vue 3, Inertia 3, strict TypeScript, PrimeVue 4 and Tailwind 4.

Implemented flows include onboarding, tenant-aware menus and staff invitations/access, full restaurant branding, public ordering, server pricing, idempotent checkout, secure tracking, realtime kitchen operations, organization-scoped tokens and signed webhooks with delivery history/retries. Romanian is the default interface language; English is available through a flag/name selector on every layout. Billing automation, the WordPress plugin and custom-domain provisioning remain outside this release.

Start with [local setup and deployment](docs/DEPLOYMENT.md). In a fresh environment:

```sh
php scripts/configure-local.php
composer install
npm ci
docker compose up -d --wait
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
php artisan serve
```

Use Linux/WSL for Composer and Horizon. Run Horizon, Reverb and the scheduler as described in deployment documentation. Demo passwords are generated locally and saved only in ignored private storage.

- [Architecture and decisions](docs/ARCHITECTURE.md)
- [Original implementation specification](docs/MASTER_PROMPT.md)
- [Database schema](docs/DATABASE_SCHEMA.md)
- [API contracts](docs/API.md) and [OpenAPI](docs/openapi.json)
- [Security boundaries](docs/SECURITY.md)
- [Staff, branding, languages and webhook operations](docs/STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md)
- [Implemented scope](docs/MVP_SCOPE.md)
- [Verification and progress](docs/CODEX_PROGRESS.md)
- [Measured benchmarks](docs/BENCHMARKS.md)

Local tests and benchmark scripts do not imply production capacity or a live deployment.
