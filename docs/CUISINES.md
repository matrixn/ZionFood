# Cuisine catalogue and restaurant selection

The **Specific culinar / Cuisine** setup step follows **Name and address**, before seating. The old free-text input is removed from Basics. Existing private free-text values remain in the setup profile for historical continuity; arbitrary text is not silently mapped to catalogue choices.

Restaurant owners/managers may select multiple photo cards, search Romanian/English names, see a separate selected list and use a sticky count/link while scrolling. New choices say **Selected - not saved** until **Save step** succeeds. Persisted selections show **Active since** using the pivot creation timestamp; unchanged saves preserve that timestamp. Removing and later saving a fresh selection creates a new activation timestamp. This describes the restaurant location; it does not create products, translate menus or enable ordering/reservations. Choosing a cuisine is optional and does not become a new publication blocker.

The shared catalogue starts with 27 requested categories: pizza, burgers, salads, fast food, sandwiches, Asian, Mexican, coffee, pasta, sushi, Indian, Chinese, Italian, Thai, kebab, breakfast, Japanese, American, seafood, fish & chips, Vietnamese, Greek, Turkish, North Indian, French, Lebanese and Caribbean. Names and short descriptions have Romanian/English values.

## Platform administration

Platform administrators use **Administration > Cuisine catalogue** (`/admin/cuisines`) to create/edit names, descriptions, display order, photo/attribution and availability. Tenant owners/managers do not gain platform administration. Authenticated, verified sessions and CSRF apply to catalogue writes.

Photos are uploaded as JPEG/PNG/WebP, maximum 4 MB and 6000 x 6000 pixels, stored with generated names on the public disk. SVG and arbitrary image-path fields are rejected/ignored. The application never downloads a supplied URL; source/license URLs are attribution links only and accept HTTP/HTTPS. Existing uploads remain when no new file is supplied. Replacing a managed upload cleans up its old file after commit; bundled default photos are retained.

Disable an entry to stop new restaurant selections. Already-selected inactive entries remain visible with an unavailable warning and may be retained or removed. There is no destructive catalogue delete action. Catalogue availability is distinct from a restaurant selecting that category and from operational service/menu publication.

## Persistence and deployment

Migration `2026_10_26_000600_add_cuisine_catalog` creates `cuisines` and `location_cuisines`, importing the initial JSON catalogue once. It reads `database/data/cuisines.json`, which is deployed with the application and includes metadata for the bundled images in `public/images/cuisines`. No network access is required by migration, seeding, setup pages or deployment. Later migrations do not reset administrator edits; changing the JSON alone does not rewrite a live catalogue.

The pivot has a unique location/cuisine pair, timestamps, and a composite organization/location foreign key. Setup saves lock the location, require its current setup revision and lock requested catalogue rows while verifying available choices. Unknown/duplicate/new inactive IDs and foreign tenant writes are rejected. The selection is relational, independent of the private Basics profile, and is not automatically exposed in public storefront responses. Platform admins remain separate from tenant management authority.

Apply the additive migration, build assets and retain the existing `storage:link` for uploaded photos. Current version is 0.0.5. No subscription/payment behavior changes. Test fixtures create isolated local accounts, with no live restaurant or real email changes.

## Photo sources

All 27 bundled photos were retrieved from Wikimedia Commons; their per-file author, source page and license are preserved in the JSON catalogue, displayed in each card's attribution details and listed in [CUISINE_PHOTO_CREDITS.md](CUISINE_PHOTO_CREDITS.md). Cards display responsive crops through CSS; the downloaded thumbnail files are retained. Descriptions are original short catalogue copy, not copied encyclopedia passages. The catalogue is illustrative and makes no claim about an individual restaurant's actual dishes.

Validation results and any limits are recorded in CODEX_PROGRESS.md. Live Forge migration, custom photo uploads in production and cross-device accessibility are not established by local browser tests.
