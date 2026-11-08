# Marketing packages and currencies — 0.0.58

Platform administrators manage names and monthly per-location base prices at **Administration → Configuration → Plans and exchange rates** (`/admin/pricing`). The three stable package identifiers are `menu`, `orders`, `business`; their public names are shared across languages. Prices are stored as integer RON minor units. Decimal input accepts two places; the browser normalizes commas, while the server converts the validated decimal string without floating-point price arithmetic. Updates are transactional and revision checked. Only verified platform admins can view or change these settings.

The landing selector offers **RON, EUR, GBP**. Server-side IP-country defaults map RO to Romanian/RON, IT/ES/NL to their language/EUR, and GB to English/GBP. Unsupported/unavailable countries fall back to **English and EUR**. Version 0.0.55 replaces browser timezone/region inference with local DB-IP Lite lookups. Manual choices are stored separately, remain independent and take priority. No GPS prompt or visitor IP transmission to a lookup service is used. Explicit language URLs stay stable. Cookies remain host-only, httponly, SameSite=Lax and secure on HTTPS. See GEOIP_PREFERENCES.md for legacy preference handling, trusted proxies and database installation.

Marketing responses are private/no-store because currency preferences affect their content. The server supplies current names, base prices, rates and rounded amounts; an escaped overlay updates the build-owned pricing HTML for no-JavaScript/search readers too. Editing prices does **not** require a rebuild. Canonical URLs/sitemap remain independent of display currency. Tenant menu currencies, order prices, permissions and snapshots are unaffected.

## Exchange rates

The fixed backend endpoint is `https://api.frankfurter.dev/v2/rates?base=RON&quotes=EUR,GBP`. [Frankfurter](https://frankfurter.dev/) is free and open-source, requires no API key and permits local caching. Rates are quote units per 1 RON. They are indicative reference conversions, not a promised payment-provider exchange rate.

The transport verifies TLS, disables proxies/redirects, uses connect/total timeouts, limits responses to 64KiB and validates both expected quotes, dates and finite positive rates before atomic storage. Visitors/admins cannot choose the destination URL. Rates use eight-decimal scaled integers; display conversion uses integer half-up minor-unit rounding. A shared cache lock prevents concurrent fetches. Provider failure retains the last valid quotes and records status plus a structured warning. Admin shows the source, quote dates, last successful sync and failures.

Automatic updates run **Monday at 04:15 Europe/Bucharest** through the existing Laravel scheduler and can be disabled in admin. The throttled admin-only manual button works independently. No provider request occurs when public visitors open the page or change currency.

Before the first successful sync, requests for EUR/GBP display actual RON prices with an unavailable-conversion notice; no rate is invented. Retained rates over 14 days old show a stale notice. Missing/duplicate quotes, invalid base/date or malformed values reject the entire new response.

## Installation

1. Run normal application migrations. The new tables seed existing 49/149/249 RON plans once; later deployments do not reseed edits.
2. Run `php artisan zionfood:exchange-rates` once, or press **Update rates** in platform admin.
3. Keep the existing `php artisan schedule:run` cron active. `php artisan zionfood:exchange-rates --scheduled` respects the weekly toggle; without that option it is a manual refresh.
4. Ensure server HTTPS trust stores and shared cache/locks work. Keep TLS verification enabled; certificate errors require a trust-store fix.

## Billing boundary

This release manages commercial presentation and reference conversion. RON remains the canonical billing currency/amount; rounded EUR/GBP displays must never be converted back to calculate an invoice. Responses retain RON `price_minor` alongside `display_minor`, selected currency, rate and date. Future subscription checkout/invoicing must re-read the current server RON price and snapshot applicable terms at confirmation. Actual subscription charging, invoice issuance, tax rules and plan entitlement enforcement remain separate work. The selector issues no invoice.

See CODEX_PROGRESS.md for executed checks and production limits.
