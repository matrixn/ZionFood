# Ingredients, portion labels and prices

## Source and deduplication

The user-provided `meniu-restaurant-queen.pdf` has 12 pages. Ingredient lists on food pages 2-8 and explicit single-ingredient entries were extracted with pdfplumber and visually checked. Beverage/dish names without an ingredient list were not guessed into recipes. The bundled dataset contains **183 unique ingredients**, not the source restaurant's complete menu, product prices or contact details. The PDF itself is not committed or required at deployment.

`database/data/ingredients.json` is the deployable dataset. `INGREDIENT_IMPORT_AUDIT.json` records the PDF SHA256, page numbers, original token occurrences, name aliases and four occurrence counts: unmarked, allergen-only, frozen-only and both. Obvious spelling/singular/plural aliases are unified, such as casstraveti/castravee -> castraveti, piept pui -> piept de pui, sos de rosii -> sos rosii, feta -> branza feta and ulei masline -> ulei de masline. Distinct preparations remain distinct. The malformed salau recipe row is separated at its visible line boundary; the stray star before bagheta is not assigned to dressing. An outside-parenthesis product star is not spread across every ingredient.

Flags are counted **independently**. A single star means allergens; two stars mean possibly frozen, not automatically allergens; `**/*` means both. For each flag, enable it when positive occurrences outnumber negative ones. The user explicitly chose **enabled on positive ties**, with a review marker. Any mixed flag history is marked for review. Zero positive occurrences never enables a flag. Results: **19 allergen flags, 16 frozen flags and 32 review records**. The ambiguous printed token `mai` is retained for traceability but initially hidden, pending correction by the administrator. These are source-count decisions, not scientific allergen inference; an unchecked flag is not a certification of allergen absence.

## Ownership and administration

Platform administrators manage the common catalogue at `/admin/ingredients`. Restaurant owners/managers manage only their restaurant's custom ingredients at `/app/locations/{public_id}/ingredients`; shared rows are read-only there. Menu's **Manage ingredients** dialog uses the same manager and endpoints, so it also works within `setup/menu` without leaving the guide. Names have optional English translations, with Romanian/source-name fallback.

Records support add/edit, availability, independent allergen/frozen checkboxes, review status and removal. Names are normalized for case, whitespace and diacritics; the database enforces unique normalized names within each scope. Creating a restaurant custom ingredient that duplicates a common name is rejected. Hidden or soft-deleted records cannot be selected for a new recipe. Existing recipes retain their associated names and flags, and may keep or explicitly remove them when edited. Catalogue removal never silently erases consumer information. Removed names remain reserved; this increment does not add a restore screen.

The server determines organization/restaurant scope from the authenticated route and selected product category, never from client-supplied scope IDs. It rejects staff, foreign tenants, ingredients belonging to another restaurant, duplicate IDs and new use of hidden/deleted IDs. This remains true for restaurants inside the same organization. Existing menu-token permissions and pricing authorization are retained.

## Deployment and data

Additive migration `2026_10_26_000700_add_ingredients_and_portions` creates ingredients and the ordered recipe pivot and adds nullable portion labels plus explicit product flags. It calls IngredientSeeder automatically, so normal `php artisan migrate --force` imports the bundled list on deployment. DatabaseSeeder also calls it. An explicit `php artisan db:seed --class=IngredientSeeder --force` is safe to repeat: stable global-prefixed source keys and insert-only semantics preserve administrator edits, hidden rows and removal tombstones. Pre-publication legacy source keys are renamed to the global namespace without changing IDs, metadata or recipe links. No network/PDF runtime dependency is introduced.

The pivot has an organization/item composite FK plus ingredient FK. Shared rows have no tenant owner; custom rows have a restaurant/organization composite FK. Assignment validates recipe scope and availability inside the product transaction. Ingredient edits/removal invalidate affected public-menu caches after commit and emit existing menu.updated outbox events. Public ingredient payloads omit tenant IDs, review details and internal normalization/source fields.

## Product and public menu

Product forms provide a searchable checkbox list with separate selected badges, preserving selection order, a free-form portion label up to 50 characters (for example `350 g`, `200/250 g`, `250 ml`) and explicit allergen/frozen flags for additional recipe components. Base and variant price controls display RON with two decimals; the form converts to integer bani. API prices and checkout calculations remain integer minor units. Existing clients may omit the new fields; product edits preserve existing ingredients when ingredient_ids is omitted.

JSON and multipart product payloads accept ingredient_ids, portion_size, contains_allergens and from_frozen. Public cards/details show the ordered ingredient list and portion label. Effective product flags combine direct flags with associated ingredient flags; the public API returns those effective values. The private editor retains explicit product values so inherited flags are not accidentally copied into permanent overrides. Combined markers display `* / **`.

Public product titles do not carry asterisk suffixes. Ingredient names retain their individual `*` / `**` markers, in smaller parenthesized text below the description. Portion labels explicitly identify the serving; the details modal displays the photograph once and offers separate allergen/frozen badges.

When a visible product is marked, a compact RO/EN information panel explains the markers and cites current [Regulation (EU) 1169/2011, Annex II](https://eur-lex.europa.eu/legal-content/RO/TXT/?uri=CELEX%3A02011R1169-20250401). The fourteen groups are an expandable summary; exceptions and full wording remain in the linked official text. The obsolete Directive 2000/13/EC quotation has been removed at the user's request. The feature does not certify legal compliance, identify individual allergen groups per recipe, or compute nutrition/ingredient quantities. Menu metadata does not alter order-price snapshots or payment behavior.

See CODEX_PROGRESS.md for completed automated, browser and database checks and their limits.
