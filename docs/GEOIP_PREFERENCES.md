# IP-country preferences — 0.0.57

Marketing defaults now come from the visitor's **IP country on the server**, not browser languages, region, timezone, GPS or a caller-supplied country header. Country lookups use the locally installed DB-IP Lite MMDB through `maxmind-db/reader`. No visitor IP is sent to a geolocation service, stored in new tables/cookies, returned in page props or included in GeoIP logs. Only the country code and effective locale/currency hints are exposed. IP geolocation remains approximate and may describe a VPN/proxy exit rather than physical residence.

| IP country | Language | Display currency |
| --- | --- | --- |
| RO | Romanian | RON |
| IT | Italian | EUR |
| ES | Spanish | EUR |
| NL | Dutch | EUR |
| GB | English | GBP |
| Other / unavailable | English | EUR |

Currency conversion, RON price authority and missing-rate safeguards remain as documented in PRICING_CURRENCIES.md. Missing rates show actual RON amounts with a notice even when the desired currency is EUR/GBP; no rate is invented.

## Priority and persistence

Language and currency are independent. A successful selector action stores the value plus its encrypted, httponly `zf_locale_source=manual` or `zf_currency_source=manual` cookie. Selecting the currently suggested language also locks that preference without reloading an unchanged language URL. Footer language links use the same protected manual-preference endpoint when JavaScript is enabled. Platform-admin language remains independently RO/EN.

Automatic values have source `ip` and are recalculated when the visitor IP changes. Explicit manual values always win; a saved account language also wins over automatic hints. Legacy cookies without a source marker are conservatively preserved as manual because old automatic/manual choices cannot be distinguished reliably. Invalid cookie values do not override the allowed locale/currency lists.

Canonical language pages remain stable for search engines: / is Romanian and /en, /it, /es, /nl retain their explicit languages. On the marketing entry /, client enhancement navigates to the selected/detected canonical language when needed, preserving query/UTM and fragment. Explicit non-root language URLs are never automatically redirected. Manual preference cookies are not overwritten simply because a different explicit language URL was visited. Raw no-JavaScript language content remains the explicit URL language; prices/currency already use the server's current preference. Responses stay private/no-store.

## Database maintenance

[DB-IP Lite](https://db-ip.com/db/lite.php) provides monthly country snapshots under CC BY 4.0. The public pricing section and the admin maintenance section include the required **IP Geolocation by DB-IP** link. Production data is private generated operational storage, not committed to Git. A small MIT-licensed upstream MaxMind test fixture is committed for offline CI and is never a production fallback.

- Initial/manual update: `php artisan zionfood:geoip-update`, or **Platform administration → Configuration → Plans and exchange rates → Visitor country by IP → Update GeoIP database**.
- The existing scheduler runs `zionfood:geoip-update --scheduled` daily at **03:45 Europe/Bucharest**, installing a new monthly snapshot only when needed. If the current month is not published yet, it can use the preceding month without repeatedly downloading an already installed prior release.
- Default file: `storage/app/private/geoip/country.mmdb`; use `ZF_GEOIP_DATABASE` only to point to private persistent/shared storage if the normal Forge storage symlink is not sufficient. Keep this path outside public web roots.
- Downloads use a fixed HTTPS host/monthly filename, TLS validation, no redirects/proxies, connection/read/total bounds, a shared lock, 16MiB compressed and 32MiB expanded limits. The country reader/type/sample are validated before atomic replacement. Failed downloads/archives preserve the current database. Temporary files are removed; release-marker failure is logged separately if the valid new database was already installed.
- Missing/unreadable data and private/invalid/reserved visitor addresses use English/EUR defaults. IPv4, IPv6 and IPv4-mapped IPv6 are supported. Admin shows readiness/build date and a warning after 62 days. No public request downloads the database.

## Reverse proxies

Laravel resolves the client address from `REMOTE_ADDR` by default. `X-Forwarded-For` is accepted only from explicitly configured trusted proxies. `CF-IPCountry`, arbitrary country query parameters and custom test headers are never country authorities in application code.

If Nginx already restores the real client address, no proxy change is needed. Otherwise set **`ZF_TRUSTED_PROXIES` to the exact trusted proxy IPs/CIDRs** (comma separated) and rebuild config cache through the normal deployment. Do not list visitor networks or the whole Internet. Configuration rejects wildcards, `private_ranges`, malformed ranges and /0. Only forwarded-for/proto/port are enabled; forwarded host is not trusted. See [Laravel trusted-proxy documentation](https://laravel.com/docs/12.x/requests#configuring-trusted-proxies).

No production proxy, Forge configuration or deployment was changed in this implementation. Confirm the real proxy chain before setting its allowlist. See CODEX_PROGRESS.md for actual checks and remaining deployment steps.
