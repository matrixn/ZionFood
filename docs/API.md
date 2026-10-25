# API v1

Machine-readable contract: [openapi.json](openapi.json). API host in production: `https://api.zionfood.ro`; browser clients normally use the same-origin `/api/v1` routes on the application/storefront host. No permissive CORS policy is configured. WordPress integrations should call from their backend and retain tokens server-side.

| Method | Endpoint under /api/v1 | Access |
| --- | --- | --- |
| GET | /public/locations/{location}/menu | Public location ULID; verified storefront host cannot access another location |
| POST | /public/locations/{location}/checkout | Public; Idempotency-Key required, 16–100 letters/digits/hyphens/underscores |
| GET | /public/orders/{order} | Bearer tracking token; public order ULID; returns no customer contact details |
| GET | /locations/{location}/orders | Session or Sanctum orders:read; organization and location authorization |
| PATCH | /orders/{order}/status | Session or Sanctum orders:manage; status, expected version, optional reason |
| GET | /locations/{location}/menu | Session or Sanctum menu:read; tenant/location authorization |
| POST | /locations/{location}/items | Owner/manager; session or Sanctum menu:write |
| PUT | /items/{item} | Owner/manager; session or Sanctum menu:write |
| GET, POST | /locations/{location}/integration-tokens | Verified browser session, owner/manager; GET excludes token values |
| DELETE | /locations/{location}/integration-tokens/{token} | Verified issuing user's browser session, owner/manager |
| GET, POST | /locations/{location}/webhooks | Verified browser session, owner/manager; signing key only in create input |
| PUT, DELETE | /locations/{location}/webhooks/{endpoint} | Same location/organization owner/manager browser session |
| POST | /locations/{location}/webhook-deliveries/{delivery}/retry | Owner/manager browser session, enabled endpoint, retryable delivery |

Locations/items/orders in URLs use public ULIDs. Category, variant, modifier-option and delivery-zone IDs in payloads are internal numeric references checked against the resolved tenant and menu. Authenticated order results use API Resources with pagination; `?active=1` excludes terminal statuses. Follow `links.next` until null to recover the complete active queue.

Checkout input contains `customer_name`, `customer_phone`, `fulfillment` (`pickup` or `delivery`), optional notes, and items containing `product_id`, `quantity`, optional `variant_id`, optional `modifier_ids`. Delivery also requires `delivery_address`, `postal_code` and `delivery_zone_id`. Prices/totals from clients have no effect. The server returns 201 and `order_id`, `tracking_token`, `replayed:false`; an identical replay returns 200 and `replayed:true`. A changed payload with an existing key returns 409.

Status transitions require the current integer `version`. A successful operation increments it. Invalid transitions return 422; stale versions return 409; forbidden tenant/ability access returns 403. Invalid or mismatched tracking tokens return 404. Public tracking responses use `Cache-Control: no-store`.

Product images: multipart POST, `_method=PUT` for updates, JSON string in `payload` for the product fields and an `image` file. Only JPEG/PNG/WebP, maximum 4 MB and 6000×6000 pixels. Ordinary JSON requests also support item create/update. Product fields include integer price, tax basis points, variants and modifier group IDs. Updating variants replaces their IDs; existing carts may require reconfiguration. Orders retain independent snapshots.

Browser CRUD retains CSRF protection. Personal access tokens have the selected abilities from `menu:read`, `menu:write`, `orders:read`, `orders:manage`, are scoped to one organization, and expire in 90 days. Only the creation response reveals the plaintext token once; it must be stored by the integration backend. Existing secrets are never sent in token lists or broadcast events.

Signed webhook configuration and delivery are implemented. Events are order.created, order.updated, menu.updated and item.updated. The configuration schema, signing algorithm, receiver verification and retry semantics are documented in [administration and webhooks](STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md). Existing signing secrets and response bodies are never returned. Removed endpoints retain their organization-scoped delivery history. No WordPress plugin has been implemented.

Browser routes under /app/locations/{location} include /staff, /branding and /webhooks. Staff invitation/member management and multipart branding use CSRF-protected session routes and reusable domain Actions. /invitations/{public_id} accepts a token from the email fragment only in its verified-email acceptance POST. /locale accepts only ro/en and changes the encrypted preference cookie plus the signed-in user preference. Integration clients may explicitly request Accept-Language: ro or en; field names/status values are stable across languages. Tracking responses additionally contain public restaurant branding.
