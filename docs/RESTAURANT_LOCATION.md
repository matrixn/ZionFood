# Restaurant address lookup and map pin

In **Pregătește restaurantul > Nume și adresă**, the numeric coordinate inputs are replaced by **Localizează restaurantul / Locate restaurant** and an editable map. The button reads the current, possibly unsaved street address, city, country and optional postcode. Street address (minimum five characters), city (minimum two) and a two-letter country code are required for searching. Missing data is listed in the interface; the protected server endpoint also validates it.

A single result places the pin. Multiple matches require an explicit choice. Results without a house number are labelled approximate, with advice to add the number or adjust the pin. Empty results and provider failures have localized feedback and retain manual placement as an alternative. This is an address search, not proof of building ownership or guaranteed entrance accuracy.

Click the map or drag the marker to correct the position, then **Save step**. The existing private setup latitude/longitude pair stores the pin internally; users no longer type coordinates. Changing street, city, country or postcode clears the current pin/results and ignores older in-flight searches. Existing saved pins appear on reload. Searches do not save or alter restaurant data by themselves. The saved position continues to center the existing delivery-zone editor and delivery map; checkout address geocoding is not introduced here.

## Provider and operations

`config/geocoding.php` uses the Photon-compatible HTTPS endpoint configured by `ZF_GEOCODER_URL`, defaulting to `https://photon.komoot.io/api/`. It is private server configuration and is not included with browser map settings. No API key is required for that default. Only address components are forwarded: restaurant name, customer/contact details, phone and email are not sent. The interface explains the address disclosure and attributes Photon/OpenStreetMap.

The [Photon demo-server policy](https://github.com/komoot/photon#demo-server) permits reasonable project use, with throttling/bans for extensive traffic and no availability guarantee. Use a suitable hosted or private Photon-compatible instance for larger/production requirements. The [Photon API](https://github.com/komoot/photon/blob/master/docs/api-v1.md) documents search, country restriction and result fields. This feature does not call the separate public Nominatim API.

Searches run only after the user's button press, never as autocomplete, background loops or bulk migration jobs. The server caches identical provider/address/language results for 24 hours, serializes uncached calls with a shared cache lock and admits at most one new upstream call per two seconds. The web route limits each user to ten attempts/minute. Use shared cache storage across application workers/instances; avoid per-process array/file stores in production.

HTTP transport disables redirects/proxies, verifies TLS, sets an application User-Agent and bounded connection/request/read timeouts, caps the response at 128 KiB and processes at most five results. Coordinates must be valid points within the requested country and the map's supported latitude range. Failures log only a generic warning and exception class, without addresses, response content or credentials. No automatic retries are made. The endpoint is operator-controlled, not a client-supplied URL.

## Routes and checks

`POST /app/locations/{public_id}/setup/geocode` uses verified session authentication, CSRF, owner/manager location authorization and JSON address fields. It returns `{results: [{latitude, longitude, label, approximate}]}` with no-store headers. Staff and foreign tenants cannot perform searches for another location. Provider results are cached server-side but are not a public search API.

Feature tests fake provider responses and prohibit stray HTTP requests. They cover authorization, insufficient fields, using unsaved data, query privacy, cache/global cooldown, approximate/foreign/invalid results, provider errors, oversized responses, private configuration and saving a selected pin. Browser tests cover missing-field feedback, absent numeric inputs, marker placement/adjustment/save/reload, RO/EN, ambiguous/empty/error results, clearing a stale pin, mobile layout and the existing setup regression. A separate local live check queried the public business address Calea Victoriei 49, București, receiving multiple matches; it did not change restaurant data. See CODEX_PROGRESS.md for completed results and limits.
