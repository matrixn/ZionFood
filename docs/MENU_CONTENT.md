# Automatic menu generation — 0.0.20

Restaurant administration → Automatic generation is a dish-first workspace. Search products by name/category, choose a dish, check prefilled ingredients and the finished weight of **one portion**, then select nutrition, food suggestions, Romanian description rewriting and/or up to three translation languages. The main action stays visible on mobile. Preparation details are optional. Public ingredient associations are edited in the existing product editor; the analysis text is a separate private input.

## Generation and publication

`analyze` combines the chosen tasks in one encrypted draft and one provider call so sibling proposals do not invalidate one another. The API retains translate/suggest/rewrite operations and all-location translation batches. The primary interface selects individual dishes; a secondary dialog starts whole-menu translation. Published food codes and translations remain manually editable in a separate modal. Romanian remains the source; translations are stored locally. Empty variant/option maps are omitted from strict wire schemas and restored locally. Public translation hashes remain compatible with existing records.

Nothing generated is published automatically. Completed proposals open from a top-right toast, the persistent bell inbox or the compact history dialog. Review shows food codes, description, translations and both nutritional bases. Authorized restaurant staff can correct descriptions, translated name/description/ingredients and dietary codes, select sections, approve or reject. Nutritional estimates cannot be relabelled as verified measurements through this review. Publication is transactional and rechecks current source hashes and shared-menu write rights. Concurrent edits return 409. Missing/contradictory/invalid output fails without publication. Prices, identifiers and order rules are never generated or changed.

## Nutrition is approximate

The user explicitly replaced the earlier reference-only requirement with automatic estimates. `NutritionEstimate` derives weight only from unambiguous g/gr/kg portion text (for example 350 g or 0,35 kg); combined weights such as 100/300 g need clarification. Ingredients prefill from the product catalogue or description. Users do not enter amounts or nutrient references for every ingredient, and there is no portion-count field.

AI proposes nutrients for one finished portion and states its assumptions about unknown proportions. The server checks nonnegative/bounded values, saturates ≤ fat, sugars ≤ carbohydrates and total principal nutrient mass ≤ portion weight. It derives kJ from kcal and normalizes to 100 g; unknown optional fibre stays null. Canonical nutrition retains `estimated=true`, `method=ai_estimate`, portion weight, per-serving/per-100g values and private assumptions. Public rendering clearly labels estimates in all supported menu languages. These checks detect structural implausibility, not dietary accuracy. No verified food-composition database, laboratory analysis or regulatory compliance certification is claimed.

Changing analysis inputs invalidates existing nutrition. Changing the product portion/ingredient associations clears both stale nutrition and its saved analysis inputs. Existing historical recipes/references and published metadata remain readable; they are not recalculated or erased by the upgrade. Only reviewed public fields are exposed; inputs, recipes, references, assumptions and provider reports remain private.

## Notifications

`CompleteMenuContent` stores a ready/failed/stale transition and one `workspace_notifications` record per requester/draft in the same transaction. After commit, `WorkspaceNotificationChanged` sends only a notification ID on `user.{id}.notifications`, authorized strictly to that authenticated recipient. Broadcast failure is logged without losing the durable record. Duplicate completion does not produce duplicate records.

The authenticated workspace shell checks the inbox every five seconds while visible, on focus/online, and immediately after a Reverb invalidation. No manual refresh or Inertia page reload is needed. The latest 100 recipient records are returned after current location/feature/shared-menu read authorization. Full proposal data is fetched only when opening a result and reauthorized independently. Read markers persist across navigation. Up to three newly discovered result toasts are shown together; the inbox retains all returned records. General workspace success flashes also use this top-right stack. Field validation stays next to the relevant form.

## Provider setup

Platform-only administration configures enabled/provider/model/encrypted key/global rolling-24-hour limit. Groq supports configured `openai/gpt-oss-20b` and `openai/gpt-oss-120b`; OpenAI remains an explicit alternative with its own key/model, never an automatic paid fallback. Account quotas and free-plan availability belong to the provider. [Groq structured outputs](https://console.groq.com/docs/structured-outputs), [provider rate limits](https://console.groq.com/docs/rate-limits).

Only supplied product text, ingredient names/flags, option labels, portion weight and preparation details are sent. Customer/order/payment data and private supplier references are excluded. Queue payloads contain draft IDs, not keys or content. Input/proposal/report and provider keys are encrypted at rest; retain APP_KEY in backups. Requests use fixed TLS endpoints, no redirects/proxies, bounded response/time/token budgets. The settings-row lock serializes the global quota; the default Horizon queue is paced at one provider request/minute. Incomplete/rate-limited/interrupted attempts fail visibly; regeneration is explicit and consumes quota. No live provider call is made by automated tests.

## Upgrade and removed workflow

Run migrations, build assets and gracefully restart supervised workers (`php artisan horizon:terminate`) through the normal release process. Migration `2026_10_27_001000` creates the notification inbox and deletes **only unpublished/historical drafts of retired kind gloriafood**; published product metadata is preserved. Rolling back this migration removes the inbox table but cannot restore deleted import drafts. Back up before any production migration.

The manual single-product GloriaFood JSON adapter, upload route, import reports and manual recipe calculator have been removed as requested. The previously discussed whole-menu Migration Center remains future work: official backend API client, canonical whole-menu mapping, variant-specific modifiers/quantities, price-semantic verification, inactive publication, fidelity reports, reimport conflicts and controlled rollback. No complete GloriaFood migration or import capability is offered in this release.

In 0.0.20 successful approval/rejection closes the review modal and emits a top-right confirmation. Save failures preserve the open proposal/error. A real configured Groq job was generated and its nutrition-only selection approved on Forge, without copying the stored key; this does not validate estimate/allergen accuracy. All form controls now carry contextual FieldHelp.
