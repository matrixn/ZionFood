# Menu content, nutrition and GloriaFood metadata — 0.0.17

The menu content editor is available under Restaurant administration → Menu content. Romanian remains the source language; operational UI is Romanian/English. Stored menu translations support English, German, French, Italian, Spanish, Hungarian, Ukrainian, Bulgarian, Turkish and Polish. Public selectors offer languages with reviewed, current translations, plus RO/EN fallback. Missing or stale product text falls back to Romanian. Fixed allergen, dietary and nutrient vocabulary is bundled locally for these menu languages; numbers, money, product IDs and ordering rules are never translated.

## Canonical metadata v1

`MenuContent` validates the shared representation used by manual editing, GloriaFood metadata staging, AI proposals and public rendering:

- `allergens`: stable named codes for the fourteen existing allergen groups; `may_contain` is a separate explicit trace list. Legacy product/ingredient booleans are preserved and are not silently converted into guessed groups.
- `labels`: vegetarian, vegan, gluten_free, halal, nut_free, dairy_free, hot and raw. Contradictions with declared allergens are rejected; absence of a code does not prove an allergen-free claim or certification.
- `nutrition`: basis (`per_100g`, `per_100ml`, `per_serving`), nullable numeric nutrient values, private reference and `estimated=false`. Units are encoded in stable keys: energy_kj, energy_kcal, fat_g, saturates_g, carbohydrate_g, sugars_g, protein_g, salt_g, fibre_g.
- `recipe`: ingredient names, grams used and reference values **per 100 g**, each with its supplier/label/database reference. `yield_grams` is cooked output weight and `servings` is the recipe serving count.
- `translations`: language-keyed dish name, description, ingredient text, portion, menu/category names and ID-keyed variant/modifier/option names. A server-calculated source hash controls freshness. No price, quantity rule or foreign item ID can be changed through translations.

The item `content_revision` and a hash of source fields, ingredient flags, options and saved content reject stale saves/approvals with HTTP 409. Editing a source description or recipe must not make an old proposal authoritative. Unchanged stale translations are not automatically marked current on an unrelated save. Changing recipe quantities, references, yield or servings clears unchanged existing nutrition until recalculated/re-entered. Existing order pricing and immutable snapshots remain unchanged.

## Recipe arithmetic, never AI estimation

For every nutrient, sum `ingredient_grams × reference_per_100g / 100`. Divide by `cooked_yield_grams / 100` for the per-100-g result, or by servings for a per-serving report. Round to three decimals. If **any** ingredient lacks a nutrient reference, that nutrient stays null; it is not treated as zero. Reject missing quantities/references/yield/servings, zero/negative values and a recipe with no completely referenced nutrient. This is deterministic arithmetic from operator-entered reference data, not laboratory measurement, verified supplier composition or certification. The operator must account for cooking losses/retention and correct reference/yield inputs. Per-100-ml values may be entered/imported as declared data; no gram-to-millilitre density is invented.

Calculation creates a reviewable local proposal; approval publishes it. The original recipe snapshot and proposal/report remain associated with the requesting/reviewing users. AI has no nutritional output field and its suggestions cannot publish nutrition, even if a provider adds unsolicited JSON fields. Imported declared values remain editable with their source/basis.

## AI setup and queues

Platform administration → Menu AI settings configures enabled/provider/model/key/rolling-24-hour request limit. Groq is the initial free-plan option, supporting the documented strict-schema models `openai/gpt-oss-20b` and `openai/gpt-oss-120b`. OpenAI is an explicit alternative; it requires its own key/model. There is **no automatic provider switch or paid fallback**. Actual free-plan eligibility, token/request limits and billing are controlled by the provider account, not ZionFood. [Groq limits](https://console.groq.com/docs/rate-limits), [Groq structured outputs](https://console.groq.com/docs/structured-outputs), [Gemini rate-limit comparison](https://ai.google.dev/gemini-api/docs/rate-limits), [OpenAI structured output protocol](https://developers.openai.com/api/docs/guides/structured-outputs).

Keys are Laravel-encrypted at rest, omitted from models/page props, excluded from flashed input, and never placed in a URL, browser storage, queued-job payload or report. Switching providers clears the previous key. Preserve `APP_KEY` in backups. Only platform administrators can configure the provider; this does not grant tenant menu rights.

Owners or staff with menu-manage capability can request translation for a product or all products attached to the location (maximum 200 products, three target languages per request). Shared-menu write authorization remains required for every affected location. Requests create encrypted drafts and dispatch ID-only `GenerateMenuContent` jobs after the outer transaction commits. A global settings-row lock serializes the rolling platform budget across organizations. Batch creation is all-or-nothing. The default Horizon `default` queue processes jobs, paced at one provider request/minute. Middleware delays do not consume the one provider attempt; incomplete responses, 429s, refusals, malformed output or authorization failures end in a visible failed/stale state. Regeneration is explicit and consumes quota; no invisible billed retry occurs.

Each provider request bounds source text, output tokens, response bytes and time; destinations are fixed HTTPS endpoints, TLS verification is enabled and redirects/proxies are disabled. The backend requests strict JSON Schema and validates returned content again. Prompt text, imported descriptions and model replies are untrusted data. Only product/menu text, ingredient names/flags and option labels are sent, without customer/order/payment information, private supplier references or recipe nutrition. Suggestions merge existing known food codes rather than removing them. Source access is checked again by the worker; publication checks the current reviewer and source hash. Translation/rewrite/suggestion operations remain separate.

Drafts transition queued → processing → ready, or failed/stale; an authorized reviewer approves/rejects ready proposals. Review data is local, encrypted and tenant-scoped. Public menu cache entries expose only reviewed food labels, nutrition basis/numbers and current translations, excluding recipes, supplier references and AI/import reports. The editor can load a food/translation proposal for corrections before a manual publication; Romanian rewrites can be approved then corrected through the existing product editor.

## GloriaFood integration boundary

The official [Fetch Menu v2 documentation](https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/README.md) and [sample JSON](https://github.com/GlobalFood/integration_docs/blob/master/fetch_menu/payload/menu.json) are the schema references. Upload a saved Fetch Menu JSON (up to 2 MB), explicitly choose the external item and the local target, and stage its metadata. This increment is the **food-content adapter**, not the entire Migration Center or a full commercial-menu import.

Named, unambiguous allergen values map to canonical codes. Provider allergen ID 1 is not assumed to mean EU group 1. Ambiguous `Shellfish`, unknown/custom names, legacy-only IDs, unsupported tags and ID-only nutritional rows remain in the private report. Named nutrient rows are normalized only with an explicit recognized basis; no serving weight, units or ID meanings are guessed. Size-level extras are retained separately rather than flattened onto the base product. Unknown allergens block direct approval and require correction through the editor. Missing source fields do not clear known local food declarations. The report retains source item ID/name, provider/source-restaurant namespace when available, checksum, raw relevant extras, unmapped data and warnings; it can be downloaded as JSON. Nothing is published by uploading the file.

### Full Migration Center follow-up, preserved from the supplied plan

- [x] Shared canonical food/translation/nutrition representation and reviewable metadata staging.
- [x] Unambiguous named mapping, internal-ID non-assumptions, size-level evidence and local source checksums.
- [ ] Fixed-endpoint backend Fetch Menu API client and temporary encrypted key lifecycle; no scraping/password collection.
- [ ] Canonical **whole-menu** snapshots, category/product/variant/group mappings and external identity namespaces with reimport conflict handling.
- [ ] Variant-specific modifier groups, per-option quantities/defaults, per-product order-type restrictions and matching checkout enforcement.
- [ ] Verify real size-price semantics; do not presume delta versus absolute pricing from the demonstration file.
- [ ] Inactive imported menu version, field/rule fidelity report, critical publication blockers and synthetic-cart total comparisons.
- [ ] Idempotent reimport preserving local edits, publication audit and rollback limited to import-owned changes.
- [ ] PDF report, CSV/XLSX, photos, separate historical customer/order imports and real authorized restaurant acceptance.

The metadata adapter neither imports prices/options nor activates/deactivates menus, creates orders, sends customer notifications or claims that a complete GloriaFood migration is verified.
