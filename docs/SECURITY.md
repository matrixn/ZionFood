# Security boundaries and limits

TenantAccess, Policies, explicit queries, composite foreign keys and Action checks form the isolation boundary. Integration ability checks supplement membership and location access. Staff cannot manage menus or settings; platform admins cannot operate a restaurant without membership.

Fortify handles authentication, email verification and password resets. New passwords require at least 12 characters. Login is limited to five attempts per minute by normalized email/IP. Browser operations use session authentication and CSRF middleware; external integrations use expiring, hashed Sanctum tokens. Real SMTP delivery is not verified locally: the default mailer logs messages to private storage.

Checkout limits are ten requests per minute per IP and 120 per minute per location. `ZF_CHECKOUT_IP_LIMIT` supports deliberate operator tuning; benchmark environments override it because many virtual customers originate from one IP. Tracking is limited to 60 requests per minute per IP. Reverse proxy configuration must preserve the correct client IP without trusting arbitrary forwarded headers.

Checkout recalculates prices from current assigned menus, checks quantities/modifiers/fulfillment/trials and stores snapshots transactionally. It locks the location for idempotency, and status changes use order locks plus optimistic versions. Validation failures cannot create partial orders.

Tracking tokens use 64 random alphanumeric characters. They are hashed for lookup, encrypted for replay, and are not included in order API Resources or logs. The customer page keeps the token in session storage and sends it in Authorization headers. It does not expose a public broadcast channel. Session-storage tracking is limited to the placing browser/tab; a portable customer tracking link is not implemented.

Private broadcasts carry identifiers and versions without names, addresses, phones or notes. Reverb uses an explicit origin allowlist. Horizon authentication requires platform-admin access even in local environments. Unknown Host values are rejected. Storefront/API hosts cannot open central dashboard or authentication routes; production host allowlists must be set explicitly.

Uploads have type, size and dimension limits, randomized storage filenames and organization storage prefixes. SVG is excluded. Configure Nginx so public storage never executes PHP. No arbitrary upload path is accepted. Current validation does not re-encode raster images or run malware scanning.

Webhook delivery accepts only public DNS HTTPS destinations on port 443, with no URL credentials/query/fragment. Each attempt rejects unsafe or mixed public/private DNS answers, pins the checked IP through an explicitly selected cURL transport, verifies TLS/hostname, disables redirects and environment proxies, and bounds connect/total timeout and response size. Signing keys are encrypted and never returned in existing configuration. HMAC covers the timestamp and exact body. Delivery leases, stable event IDs, six bounded attempts and the scheduler provide at-least-once recovery; receivers must verify signatures/timestamps and deduplicate. History stores only bounded status/failure metadata, not response bodies or transport exception messages. See STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md.

No payment-card processing, fiscal certification, automatic billing, MFA, data-retention automation or full audit trail is claimed. Order history is immutable through the model, not against direct SQL writes. Production TLS/DNS, backups/restores, SMTP and sustained capacity require deployment verification. Never commit `.env`, demo credentials, integration tokens or private fixture files.

Staff invitation tokens are hashed, expire after seven days, require verified matching email acceptance and remain in URL fragments/tab storage until the acceptance POST. Queued invitation notifications are encrypted and skip revoked/obsolete invitations. Owner access cannot be altered by this management UI; managers cannot create or edit manager/owner roles. Membership removal immediately denies authoritative API access and revokes organization-scoped tokens. Existing identifier-only WebSocket subscriptions may persist until reconnection; forced socket disconnection is not implemented.

Language preferences accept only ro/en and use an encrypted HttpOnly SameSite cookie plus account storage. API schema keys and enums remain locale independent. API and private-channel authentication use the current XSRF cookie after login/session rotation.

Cuisine catalogue editing is platform-admin-only; tenant managers may only select available entries for their own location. Selection retains revision/row-lock checks and a composite tenant pivot FK. Photos are bounded JPEG/PNG/WebP uploads, stored under generated paths; arbitrary image paths and SVG are not supported. Source/license URLs are passive HTTP/HTTPS links, never runtime server fetch targets. The default catalogue and credits are bundled, requiring no network access at migration time.

Restaurant address search is restricted to verified tenant owners/managers for their location. The client supplies address fields, never a destination URL. Only address components are forwarded to the server-configured HTTPS provider, with TLS, no redirects/proxy, bounded timeouts/body/results and shared cache throttling. Warning logs omit the query and response. Private provider configuration is not part of public maps props. Candidate labels are rendered as escaped text; lookups cannot persist changes on their own.

Reverse lookup uses the same authorization and bounded transport, forwarding only the pin coordinates. Nearest usable results are limited to 200 metres; structured strings are bounded and missing fields explicit. Stale responses after pin/address changes cannot apply. A new feature-cache namespace isolates incompatible older formatted results.

Inline setup editors reuse the same authorized WorkspaceController data builder as standalone pages. Foreign/staff access to wizard administration remains denied. Locations are restricted to the existing accessible collection, and configure links require management rights; destination routes recheck authorization. No role grants or tenant transfers are introduced.

Ingredient scopes are derived server-side from the authenticated location/category, never from payload IDs. Platform admin manages global records; owners/managers manage their own restaurant entries. Staff, foreign tenants and another restaurant within the same organization cannot inject custom ingredients. Hidden/deleted ingredients are rejected on new assignments but existing recipe associations may be retained or removed. Product writes, pivots and validation share a transaction. Public payloads omit owner/source/review metadata; text is Vue-escaped. The PDF is build-time source material only, not runtime executable input.


## Storefront experience (0.0.12)

Anonymous checkout reverse lookup accepts only valid coordinates inside an active polygon for an accepting delivery location. Verified custom hosts cannot lookup another location. IP/minute, IP/hour and location/minute limits supplement the shared provider cooldown/cache. No customer names, phone numbers or typed addresses reach the reverse provider. The UI discloses coordinate forwarding. Client-side validation and cart image paths do not influence authoritative checkout pricing, zone or authorization.
