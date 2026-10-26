# Administration and localization — 2026-10-26

The application remains version 0.0.1. This increment implements staff administration, restaurant branding, webhooks and Romanian/English interfaces. It does not introduce billing, custom-domain provisioning or a WordPress plugin.

## Staff

The restaurant sidebar exposes **Personal / Staff** to owners and managers. Owners can invite and manage managers or staff. Managers can invite and manage staff; they cannot edit themselves or another manager, assign a manager role, or change owner access. Ownership transfer is deliberately a separate future operation. Owners and managers operate all organization locations; staff operate only their assigned locations.

Invitation location IDs are validated against the organization. Invitations expire after seven days and grant no membership until the emailed token is accepted by its matching address. The email-bound token can establish verification for a new or existing unverified account. Resending rotates the invitation ULID and token; revoking prevents subsequent acceptance. Queued invitation emails are encrypted and obsolete/revoked invitations are skipped before sending.

Invitation links carry the random 64-character token in a URL fragment. The invitation page moves it into tab session storage and removes the fragment. The server receives it in CSRF-protected prepare/accept POSTs, never in the initial URL or referrer. Prepare validates the live token before exposing the email, stores an encrypted pending proof in the server session and pre-fills/locks the registration email. Registration revalidates the invitation inside the membership transaction, verifies that exact email and joins the assigned organization/locations before Fortify dispatches Registered. No separate VerifyEmail notification is sent for that proven address. The intended destination becomes /app, so employees enter their scoped workspace rather than owner onboarding. Existing accounts may sign in and accept with the emailed proof without a separate verification round trip. Ordinary registration still requires verification. Revoked, expired, substituted, reissued or invalid proofs cannot grant membership/verification; no partial user is retained after a failed invited registration. Users opening the link in another tab should open the complete original email link there.

Membership changes lock the organization. Removing a member deletes only that organization's membership, location assignments and scoped tokens; the global account and memberships elsewhere remain. Tenant authorization checks current membership on API requests and broadcast subscriptions. An already subscribed WebSocket may retain its minimal identifier-only subscription until reconnection; authoritative API reads deny access immediately. Immediate forced disconnection of existing sockets is not implemented.

Invitations are limited to 30 per hour per actor. Local mail uses the configured development mailer; live SMTP delivery and cross-device onboarding still need deployment verification.

## Restaurant branding

**Identitatea restaurantului / Restaurant branding** edits the restaurant name, managed slug, description, tagline, logo, cover image, primary color and public contact phone/email/HTTPS website. The form includes a live preview. Managed ZionFood subdomains follow slug changes; custom domains are not provisioned or modified. Reserved/colliding slugs are rejected.

Logo/cover uploads accept JPEG, PNG and WebP up to 4 MB. Logos are bounded to 3000×3000 pixels and covers to 6000×6000. SVG and user-controlled storage paths are rejected. Uploaded assets are stored under randomized organization/restaurant paths. Users can replace or remove images. Product/order snapshots remain independent of brand changes.

The public menu and token-protected tracking page apply the logo, favicon, cover/contact identity and primary action colors. Theme variables are restored on leaving the storefront, keeping platform branding separate. The workspace sidebar displays the restaurant identity. A color contrast choice is applied to primary action text. Images are not automatically translated, cropped or re-encoded.

## Romanian and English

Romanian is the default even when a browser advertises English. Every application layout, authentication/onboarding, platform administration, restaurant workspace, kitchen, storefront, tracking and invitation page provides a top-right language selector with locally hosted Romanian/UK flag icons and **Română / English** labels.

Selection posts to `/locale`, validates `ro` or `en`, sets an encrypted HttpOnly SameSite=Lax preference cookie for that host and updates a signed-in user's `locale`. The interface switches reactively without a full reload. On subsequent browser requests preference order is cookie, account locale, then Romanian. Stateless API callers may explicitly send `Accept-Language: ro` or `en`; schema keys, IDs, enums and API event names never change language.

Vue I18n catalogues contain over 400 keys per language, with matching-key and encoding checks. PrimeVue labels/ARIA, currency/date presentation, order statuses and primary Laravel validation/auth/password emails are localized. Business content such as restaurant names, menus and product descriptions remains owner-authored; automatic menu-content translation is not implemented.

The API and Echo authorization clients read the current XSRF cookie for each request. They avoid using an obsolete page meta token after an Inertia login rotates the session.

## Webhooks

**Webhooks** configures named destinations for the active location, selects `order.created`, `order.updated`, `menu.updated`, `item.updated`, enables/disables delivery and displays paginated delivery history. Configuration requires an owner/manager browser session; personal integration tokens cannot administer signing keys. Create/update accepts a signing secret of 32–128 ASCII letters/digits/hyphens/underscores. Existing secrets are encrypted with APP_KEY and never returned to JavaScript. Blank secret on edit preserves the current key.

Events are persisted in the transactional outbox. Order and menu mutations record their notifications in the same transaction as their data. Moving an item between assigned menus notifies both affected locations. Fanout creates one durable delivery per endpoint/event, scoped by organization and location. A failed kitchen broadcast does not roll back already recorded webhook fanout. Old outbox rows already marked delivered are not retroactively sent to newly configured endpoints.

Only HTTPS port 443 is allowed, with a DNS hostname, no URL credentials/query/fragment, and globally routable DNS answers. Private, loopback, link-local, metadata, CGNAT, documentation and selected special IPv4/IPv6 ranges are rejected, including mixed public/private DNS answers. Every attempt resolves and validates again. The transport explicitly selects Guzzle's cURL handler, pins the checked IP using CURLOPT_RESOLVE while preserving hostname/TLS verification, disables environment proxies and redirects, and bounds response size to 64 KiB. It does not use Guzzle streaming fallback, which would bypass cURL-specific DNS pinning.

Connection timeout is three seconds and total HTTP timeout eight seconds. A 45-second database lease and claim token protect duplicate jobs and stale workers. A successful 2xx marks delivery; network errors, 408/429 and 5xx retry with delays of 15, 60, 300, 900 and 3600 seconds, for at most six attempts. Redirects/other 4xx fail without following them; unsafe DNS destinations become blocked. `zionfood:webhooks` runs every minute to reconcile due retries and expired leases. Manual retry resets attempts while retaining the same event ID; it is limited to ten requests/minute per actor. Disabled/deleted endpoints cancel queued attempts. A request already in flight cannot be recalled.

Payloads contain public identifiers rather than customer personal data:

```json
{"id":"EVENT_ULID","type":"order.created","occurred_at":"2026-10-26T00:00:00+00:00","data":{"order_id":"ORDER_ULID","version":1}}
```

Headers are `X-ZionFood-Event`, `X-ZionFood-Delivery`, `X-ZionFood-Timestamp` and `X-ZionFood-Signature`. The signature is:

```text
sha256=HMAC_SHA256(secret, timestamp + "." + exact_request_body)
```

Receivers should enforce a short timestamp tolerance, compare signatures in constant time, and deduplicate atomically using the event/delivery ID. At-least-once delivery means a timeout or crash after remote acceptance may cause a repeated event. Fetch full data separately with a tenant-scoped API token. Response bodies, signing secrets and raw transport exception messages are not stored in delivery history; only status codes and bounded failure categories are retained.

## Verification limits

Automated tests use fake HTTP receivers to verify payload bytes, HMAC, DNS pin options, URL/DNS rejection, retries, leases, cancellation, tenant isolation and transaction behavior. No live third-party receiver was contacted with a webhook POST. Browser tests save disabled endpoints and validate a blocked private address. Actual external TLS/receiver interoperability, live SMTP, forced disconnection of already subscribed staff sockets and sustained webhook throughput remain staging work.
