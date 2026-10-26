# ZionFood

**Version 0.0.1** · Multi-tenant restaurant ordering SaaS.

Laravel 12 / PHP 8.4 / MariaDB / Redis / Horizon / Reverb / Sanctum, with Vue 3, Inertia 3, strict TypeScript, PrimeVue 4 and Tailwind 4.

Implemented flows include onboarding, tenant-aware menus and staff invitations/access, full restaurant branding, capacity/table reservations, guest cancellation, table QR orders, mapped delivery, public ordering, server pricing, idempotent checkout, secure tracking, realtime kitchen operations, organization-scoped tokens and signed webhooks with delivery history/retries. Romanian is the default interface language; English is available through a flag/name selector on every layout. Billing automation, the WordPress plugin and custom-domain provisioning remain outside this release.

Transactional email supports Resend through Laravel's native driver. Configure `MAIL_MAILER=resend`, `RESEND_API_KEY` and a verified `MAIL_FROM_ADDRESS` in Forge; see [Resend setup and test command](docs/DEPLOYMENT.md#resend-transactional-email-forge). Local development uses the log mailer.

All external email is queued and shares a Redis-backed rolling limit of 140 recipients per 24 hours. Configure `ADMIN_EMAIL` for deduplicated error/exception alerts; see [quota and diagnostic setup](docs/DEPLOYMENT.md#global-email-quota-and-error-alerts).

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
- [Guided restaurant setup and reference analysis](docs/RESTAURANT_SETUP.md)
- [Reservations, table QR orders and mapped delivery](docs/RESERVATIONS_DINING_DELIVERY.md)
- [Implemented scope](docs/MVP_SCOPE.md)
- [Verification and progress](docs/CODEX_PROGRESS.md)
- [Measured benchmarks](docs/BENCHMARKS.md)

Local tests and benchmark scripts do not imply production capacity or a live deployment.
