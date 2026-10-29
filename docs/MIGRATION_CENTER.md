# GloriaFood Migration Center — 0.0.33

Access: restaurant dashboard → Connections → Migration Center. Requires a verified owner/manager with menu management permission. Platform administration alone is insufficient. Each import belongs to one organization and location.

## Flow

1. Generate the location Fetch Menu key in GloriaFood (Others → 3rd party integrations → Enabled integrations → Fetch Menu). Paste it into the masked field. The backend contacts only `https://pos.globalfoodsoft.com/pos/menu`, using Authorization, JSON and API version 2. Alternatively upload an already exported JSON file (maximum 5 MiB); there is no manual JSON editor.
2. Select size-price semantics (product + adjustment, or absolute size price), fallback nutrition basis and tax basis points. No currency conversion is attempted. Explicit source nutrition basis takes precedence over the fallback. Confirm authorization.
3. The queued fetch leaves the public menu unchanged. The page polls its selected import automatically and shows a completion toast. The report shows source/category/product counts, issues, rules/defaults, exact integer prices, nutrition, source differences and source-derived example baskets. A fresh report can be downloaded as JSON or opened in a print view and saved as PDF by the browser.
4. Resolve unnamed nutrient IDs using selectors; revalidation uses the encrypted source without a second provider request. No nutrient IDs are guessed. Confirmed mappings from the most recent published import for the same source restaurant/location are reused on reimport; they remain in report evidence. Unknown allergens/tags/units or malformed prices remain blockers; correct the source and import again. Named nutrients accept supported English aliases and canonical keys. All fourteen canonical allergen groups and eight documented dietary labels are represented.
5. Review food declarations, every variant price, selection bounds and warnings against the real source. Confirm the report and resolve local-edit conflicts. A confirmation dialog publishes transactionally. Approval does not bypass blockers. A stale review fingerprint returns 409 and requires a fresh preview.
6. Test pickup/delivery/table-service availability and kitchen receipt with the restaurant’s actual configuration. Source order types still intersect with enabled location services; imports never enable services or payments.

## Compatibility matrix

| GloriaFood field | Implemented behavior |
| --- | --- |
| Categories/products/descriptions | Canonical staged tree, explicit source IDs, location-specific published version |
| Product/size prices | Decimal-to-integer conversion (at most two decimals), selected additive/absolute normalization, currency/range blockers; checkout uses stored prices |
| Sizes | Absolute local price, default flag, availability, size nutrition and own modifier groups; editing preserves variant IDs and existing nutrition |
| Category groups | Explicit relation; inherited by every product/size; editable in category dialog |
| Product groups | Existing relation, combined with category and selected-size groups, deduplicated by local group ID |
| Size-specific groups | Explicit variant relation; only the selected variant’s options can be ordered; editable per variant |
| required/force_min/force_max | Required group needs at least one and its minimum; optional group permits zero, otherwise its minimum applies; bounds count distinct selected options |
| allow_quantity | Each selected option has 1–50 units (per-option maximum), multiplied into each product unit price and snapshotted; non-quantity groups permit exactly one |
| default | Default size and options initialize the configurator; invalid defaults are blocked; customers may change them |
| Allergens/tags | Canonical local food data, named mapping, conflicting labels blocked and explicit restaurant confirmation; existing food editor/AI review remains available |
| Nutrition | Structured product and variant data, basis/units/ranges validated; unnamed IDs require explicit nutrient mapping; original raw data retained encrypted |
| Order types | Nullable unrestricted list or explicit pickup/delivery/dine_in set (empty list disables all); UI editor and authoritative checkout enforce it |
| Internal kitchen names | Product/option internal names imported; immutable order snapshots retain public and internal names; KDS uses internal names and quantity |
| Photos | API does not document photo export; existing local photos retained on reimport, upload supported in product editor; no remote URL downloads |
| Hours/delivery | Existing setup retained, reviewed separately; no fabricated source settings |

No structural entry from the original partial/incompatible matrix is left unsupported. This is compatibility for the documented contract, with explicit resolution for ambiguous data, not a claim that every undocumented provider extension can be imported automatically.

The official fixture has four products, four sizes, five group appearances and ten option appearances. Its size nutrition uses IDs 9–14 with no nutrient names. Documentation does not establish those ID meanings; tests deliberately supply a test-only mapping rather than presenting it as authoritative. The fixture’s base 7/size 2 is handled by an explicit price-mode choice. Distinct-option bounds with quantity pricing are shown for confirmation; real restaurant behavior must be checked before production cutover. Undocumented active periods, promotions and availability fields are flagged as not interpreted.

## Reimport and rollback

Publication creates a new menu version and switches only the selected location’s assignment. Shared original menus and other locations stay intact. Old menu/product rows remain available; order snapshots and QR/menu entry URLs are preserved. Publish/rollback commit the existing menu.updated outbox event only for the changed location. Retired-version imported groups are hidden from the active editor catalogue. Entity mappings contain provider/source restaurant, location, entity type, hierarchical external ID and publication batch; each live version has one mapped product per source ID. Inactive retained versions intentionally retain their own rows.

Reimport compares the original canonical source with its predecessor and fingerprints current local products, categories, rules, ingredients, content, images and sizes. Locally changed products require keep-local/use-source decisions. Keep-local copies the full product graph (including inherited rules, nutrition and photo); source-removed and locally added products are retained by default. A locally deleted product stays deleted when preserved. The interface reports source additions/changes/removals. Conflicts are resolved at product granularity, not by merging individual text characters or individual price fields. Source replacements keep existing photos and ingredient associations. Removed source products are not silently deleted.

Rollback restores previous location menu assignments only if the published menu graph and assignment still match the publication fingerprint. It refuses later local edits or a newer publication. It never deletes historical orders or rows. Menu versions are retained; there is no destructive automatic publication-history cleanup.

## Storage/security/operations

Tables: import_batches, import_snapshots, import_issues, import_reports, import_entity_mappings and import_publications. Source/canonical snapshots and queued keys use Laravel encryption. Reports contain no key. Queue payloads contain only a batch ID. Credentials are cleared on success/failure; stale queued keys expire after one hour via `zionfood:imports-prune`. Unpublished ready/failed snapshots expire after `ZF_IMPORT_RETENTION_DAYS` (default 30). Published evidence is retained for audit/reimport; define an organization retention policy before broad launch.

Provider transport has a fixed HTTPS URL, verified TLS, disabled redirects/proxy, five-second connect/30-second total timeout, and five-MiB header/body/stream bounds. No arbitrary endpoint is accepted. Fetch jobs have one attempt; a new import explicitly retries a failed fetch. Logs include only batch ID and exception class. Secret inputs are excluded from validation flash. File parsing uses bounded size/depth; canonical counts cap categories, products, sizes and options. Failures never mutate the public menu. Report routes repeat tenant/location authorization. Publication takes organization location locks in a consistent order, then the batch lock, and uses a transaction. Product/category/modifier management and product removal share the location locking convention to serialize menu writes with publication. Menu caches clear after commit.

Deploy the additive migration and built assets through the normal release process; ensure Horizon processes default jobs and restart workers through that process. Run Laravel scheduler for pruning. Set PHP/web upload limits at least 5 MiB if offering the maximum file size. No live key is required in .env; keys are supplied per restaurant/import. This change performs no manual deployment or external credential setup.

## Acceptance and limits

Automated tests cover official payload ingestion, unknown-ID resolution, precise server pricing, quantity snapshots, defaults, category/size isolation, local-edit reimport, guarded rollback, stale previews, tenant boundaries, credential lifecycle, file input, a 300-product menu and a second location. Provider HTTP is simulated; browser file import uses the actual backend. Example baskets are evidence, not exhaustive combinatorial certification. The operator can print the report to PDF; no server PDF engine/dependency is installed.

A real Fetch Menu request using a restaurant-authorized key and full cutover acceptance are still required. CSV/XLSX/XML/PDF/OCR adapters, bulk photo matching, customer/order-history migration, automated external source order comparison, field-level conflict merges and marketing migration landing pages are separate follow-ups. Existing AI translation does not imply translation export from Fetch Menu; the documented API provides no multilingual export schema.

Sources checked 2026-10-27: [official Fetch Menu v2](https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/README.md), [official JSON fixture](https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/payload/menu.json). The attached Migration Center plan informed scope; only the implemented behaviors above are claimed.

## 0.0.33 generated-evidence budget

ImportVerification checks cumulative generated work incrementally during normalization and again before allocating baskets: at most10,000 baskets,50,000 selected choices including category/product/size inheritance, and8MiB conservative escaped JSON evidence bytes. ProcessImport generates accepted evidence before the snapshot transaction. Upload, provider fetch and revalidation share this boundary. Excess fails explicitly as import_complexity_limit with RO/EN guidance, without silent truncation, publication or replacing previous staged snapshots. This is an additional workload bound; existing source/cardinality limits and authorization remain. Large simple menus remain supported, while particularly complex menus must be simplified or split. The same valid1.33MB input that exhausted a256MiB isolated baseline PHP process now rejects in a controlled manner under the same ceiling.
