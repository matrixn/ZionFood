# ZionFood progress — 0.0.1

## Completed

Consolidated the accessible prior architecture discussion and attached master prompt, saved the requested local memory note and project conventions, and initialized the existing README-only Git checkout without changing its remote. Installed compatible locked dependencies and configured version 0.0.1 in Composer, npm, application config and OpenAPI.

Phase 1 foundation is implemented: Laravel/Fortify authentication, PrimeVue design tokens and layouts, MariaDB/Redis configuration, onboarding, hierarchy, memberships, location assignment authorization, Policies, factories and tests.

Phases 2–4 have working core flows: categories, products, images, variants, modifiers, hours/fulfillment, public menu/cache/cart/checkout/tracking, server pricing, snapshots, idempotency, status workflow, transactional outbox and a private realtime KDS with authoritative reconnect/poll recovery. Pages use actual persistence and Actions, rather than decorative buttons.

Phase 5 is partially implemented: trial entitlements, platform overview, admin setup, scoped integration tokens, API v1, hostname mapping, OpenAPI, CI configuration, load fixtures and measured k6 tests. Deferred work is explicit in MVP_SCOPE.md.

## Verification executed

| Check | Result |
| --- | --- |
| Fresh SQLite migration and full schema | Passed |
| Fresh MariaDB 10.11 migration, including composite tenant foreign keys and token scope migration | Passed |
| Pest/PHP backend suite on isolated SQLite memory | 33 tests passed, 101 assertions |
| Same backend suite on dedicated MariaDB `zionfood_test` | 33 tests passed, 101 assertions |
| Independent simultaneous PHP checkout processes on MariaDB | One order for two identical requests |
| Independent simultaneous PHP status updates on MariaDB | One success, one 409 conflict, version/history preserved |
| Explicit transaction/queue integration check | Delivery queued only after commit |
| Outbox delivery failure, retry and duplicate job test | Passed |
| TypeScript strict check | Passed |
| ESLint | Passed without warnings after formatting-rule alignment |
| Vitest cart tests | Two passed |
| Vite production build | Passed |
| Laravel Pint | Passed |
| Composer schema validation | Passed; advisory only for explicit package version requested by user |
| Playwright Chromium | Three scenarios passed: private Reverb event receipt; landing/auth/mobile rendering; checkout/tracking/KDS reload/workflow/menu save |
| Local k6 with 100 isolated restaurant fixtures | Correctness passed; initial development runtime missed latency targets; Linux OPcache/cache runs passed defined latency thresholds |

The final fixed-arrival local benchmark used five orders/second for 20 seconds plus ten kitchen polling sessions: menu p95 34.78 ms, checkout p95 102.84 ms, 493/493 checks, zero observed duplicate orders. Read BENCHMARKS.md for the small dataset, queue/broadcast isolation and runtime conditions. Production capacity and real notification p95 remain unverified.

The local MariaDB demos contain three organizations, five categories and twenty products each, variants, modifiers and sample orders. Demo passwords are random and only in ignored private storage. Horizon processed real outbox jobs, and Playwright subscribed to the private Reverb channel and received a committed checkout event. The menu screenshot was visually inspected at desktop size; the public menu passed a 390-pixel viewport overflow assertion.

## Failures encountered and resolved

- Windows Composer BAT handling stripped caret constraints; requirements were written explicitly and installed with Linux Composer.
- Native Windows `artisan test` subprocess creation exceeded the inherited environment-size limit. `composer test` invokes Pest through a small runner that also provides a writable upload temp directory in restricted Composer subprocess environments; direct Pest checks and the standard Composer command passed.
- Removed unused Laravel JavaScript scaffold/config after Vite selected its old config and attempted to import Axios.
- Fixed initial TypeScript page resolver, nullable-prop and template-global issues.
- Corrected new-order model default version, discovered by the MariaDB demo seed.
- Made multipart image edits retain explicit empty variant/modifier arrays.
- Isolated benchmark Redis cache DB/prefix after shared development rate counters caused temporary browser 429s. The subsequent browser suite passed.
- The Windows-mounted benchmark missed latency targets and an intermediate cache warmup timed out. A Linux filesystem runtime with OPcache and warm menu cache passed; both valid results are retained.
- Pint introduced a short-name import used before its declaration in the standalone concurrency script. Imports were moved before execution and the integration check rerun.

## Files modified

The application skeleton and dependency lockfiles; `app/Domain/`, `app/Models/`, `app/Policies/`, `app/Actions/Fortify/`, `app/Events/`, `app/Jobs/`, controllers/Requests/Resources/middleware/providers; migrations/factories/seeders; routes/config/bootstrap; Vue pages/layouts/components/stores/types; CSS/theme/Vite/TypeScript/ESLint configuration; backend/frontend/browser/concurrency/load tests; setup and benchmark scripts; Compose/CI; README, AGENTS and the documents in `docs/`. `docs/IMPLEMENTED_FILES.txt` lists the exact repository file paths. Generated vendor/node_modules/builds, private credentials, local logs and test traces are excluded from Git.

## Remaining work and limitations

Webhook configuration/delivery, the WordPress plugin, automatic billing, custom-domain DNS/SSL provisioning, staff invitation/management UI and multi-location creation UI are not implemented. Restaurant branding editing is limited to onboarding; location settings are editable. Overnight hours and geocoded delivery are not supported. Cart contents are memory-only; tracking stays in the placing browser tab. Retention/archival and backup/restore procedures need implementation and staging verification.

No live Forge deployment, DNS/TLS or SMTP verification was performed. The GitHub workflow was added but has not run remotely. Sound/fullscreen behavior on physical kitchen devices, full accessibility, email reset/verification delivery, large menus, prolonged outages and sustained production capacity remain unverified.

## Next implementation step

Complete staff administration and restaurant branding, then configure staging on Forge and run real mail, subdomain/TLS, backup restoration, longer load/soak and WebSocket latency measurements before launch. Implement webhook delivery and the separate WordPress plugin after those P0 operational checks.

## Git and release boundaries

Small local implementation/documentation commits are authorized by the attached prompt. No push, tag, GitHub release or production deployment was requested. Local-only version 0.0.1 is the delivery artifact; all secrets and demo credentials remain outside Git.

Application baseline commit: `91ba816`. Documentation, original specification and benchmark evidence are recorded in the following local documentation commit. Temporary benchmark servers were stopped and their private Linux runtime copy was removed; the local application, MariaDB/Redis, Reverb and Horizon remain available for review.
