# Security boundaries and limits

TenantAccess, Policies, explicit queries, composite foreign keys and Action checks form the isolation boundary. Integration ability checks supplement membership and location access. Staff cannot manage menus or settings; platform admins cannot operate a restaurant without membership.

Fortify handles authentication, email verification and password resets. New passwords require at least 12 characters. Login is limited to five attempts per minute by normalized email/IP. Browser operations use session authentication and CSRF middleware; external integrations use expiring, hashed Sanctum tokens. Real SMTP delivery is not verified locally: the default mailer logs messages to private storage.

Checkout limits are ten requests per minute per IP and 120 per minute per location. `ZF_CHECKOUT_IP_LIMIT` supports deliberate operator tuning; benchmark environments override it because many virtual customers originate from one IP. Tracking is limited to 60 requests per minute per IP. Reverse proxy configuration must preserve the correct client IP without trusting arbitrary forwarded headers.

Checkout recalculates prices from current assigned menus, checks quantities/modifiers/fulfillment/trials and stores snapshots transactionally. It locks the location for idempotency, and status changes use order locks plus optimistic versions. Validation failures cannot create partial orders.

Tracking tokens use 64 random alphanumeric characters. They are hashed for lookup, encrypted for replay, and are not included in order API Resources or logs. The customer page keeps the token in session storage and sends it in Authorization headers. It does not expose a public broadcast channel. Session-storage tracking is limited to the placing browser/tab; a portable customer tracking link is not implemented.

Private broadcasts carry identifiers and versions without names, addresses, phones or notes. Reverb uses an explicit origin allowlist. Horizon authentication requires platform-admin access even in local environments. Unknown Host values are rejected. Storefront/API hosts cannot open central dashboard or authentication routes; production host allowlists must be set explicitly.

Uploads have type, size and dimension limits, randomized storage filenames and organization storage prefixes. SVG is excluded. Configure Nginx so public storage never executes PHP. No arbitrary upload path is accepted. Current validation does not re-encode raster images or run malware scanning.

Webhook delivery is disabled, so this application does not make outbound webhook requests. Enabling it requires public HTTPS destination checks, DNS/address revalidation at connection time, private/link-local/metadata network blocking, redirect restrictions, signing, bounded retries and reviewed secret handling. These defenses must be implemented before enabling a sender.

No payment-card processing, fiscal certification, automatic billing, MFA, staff-management invitation UI, data-retention automation or full audit trail is claimed. Order history is immutable through the model, not against direct SQL writes. Production TLS/DNS, backups/restores, SMTP and sustained capacity require deployment verification. Never commit `.env`, demo credentials, integration tokens or private fixture files.
