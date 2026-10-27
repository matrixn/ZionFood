# Restaurant overview and contextual help — 0.0.24

The overview uses actual database records for the selected location. Its period is 7, 30 or 90 local calendar days, including today. `RestaurantOverview` checks organization/location access first, then independently gates orders, reservations, menu/visits and tables by their existing feature permissions. Hidden features return null, not their private totals. There is no platform-admin bypass of tenant membership.

## Definitions

- Orders: placed within the selected period, including rejected/cancelled orders. Active orders are current non-terminal records, irrespective of period.
- Completed order value: sum of immutable order totals for completed orders placed within the period, including any delivery fee. It is **not confirmed receipts** or a payment reconciliation; payments remain placeholders. Average value divides that total by completed-order count. Cancelled/rejected values are excluded.
- Daily series: grouped by per-day UTC boundaries calculated from the location time zone, including daylight-saving changes. The portable derived-calendar query fills missing days with zero on both SQLite and MariaDB. The chart switches between order counts/value, exposes point titles and has an expandable accessible numeric table.
- Top products: up to five products ranked by completed-order item quantities; variants count toward their base product identity. Values come from immutable order-line totals. Names use a deterministic stored snapshot when the same product has multiple historical names. Current menu prices never rewrite this history.
- Menu inventory: attached menus, categories, non-deleted products and product availability flags. This is inventory, not a guarantee of current orderability under hours/modifier rules.
- Reservations: starting in the period, with separate status totals. Reserved guest totals exclude cancelled/rejected/expired/no-show records and expired pending holds. Upcoming counts are current confirmed bookings or unexpired pending holds, regardless of selected reporting period. These are not confirmed physical arrivals.
- Tables: active configured tables and seat capacity. Active QR-session count is distinct tables with unclosed, unexpired sessions and an active table. It does not establish physical occupancy. Table-order rankings use completed order snapshots; reservation rankings use confirmed/seated/completed bookings and current associated table names. No utilization percentage or occupancy duration is invented.

## Approximate menu visits

A rendered public menu sends POST `/api/v1/public/locations/{location}/visit`. Mere API menu fetches and server-rendered share previews are not counted. Existing host/location resolution is mandatory; the endpoint is throttled to 30 requests/minute/IP. Known crawler/preview user agents are ignored.

`RecordMenuVisit` creates a transient HMAC from local date, location, connection IP and browser user agent, using APP_KEY. A cache entry expires after 30 minutes and suppresses refresh duplicates. Raw IPs, user-agent strings, visitor IDs, customer data and order details are **not stored in the analytics table**, and the endpoint does not issue a tracking cookie or browser identifier. This is not a claim about unrelated web-server access logs.

Only `{organization_id, location_id, day, visits}` plus row timestamps/ID are stored in `menu_visit_stats`. Database increments are transactional; failed increments release their deduplication entry. These figures are approximate visits, not unique people: shared networks/browser profiles may merge, profile changes can split, and automated traffic cannot be perfectly excluded. Midnight starts a new daily bucket. Recording begins with this release; old visits are not reconstructed. Date series and tracking start are available in the overview payload; locations remain separate so unassigned branches never leak through an organization-wide total.

## Contextual field help

`FieldHelp` renders an info-circle trigger with a bounded, teleported tooltip. Hover, keyboard focus and touch expose it; Escape/blur/leave close it. The icon does not submit forms, change checkbox values or disclose entered field values. `ZFFormField` supplies shared help; standalone labels/controls pair with the same component. Input accessible names remain explicit when help buttons sit beside text labels. Tooltip content follows RO/EN interface language, not generated menu language.

`fieldHelp.ts` resolves shared semantic explanations for credentials, addresses, dates, money, ingredients, nutrition, permissions and other field types, with a labelled general fallback. Exceptional fields/statuses can supply explicit text. `fieldHelpCoverage.test.ts` inspects every declared control so new form fields cannot silently omit help. It does not certify bespoke help wording for every dynamic field. New fields must use FieldHelp or ZFFormField and meaningful matching RO/EN copy.

## Table controls and generation review

The table toolbar chooses all rooms or one room and applies open/close to that scope. Closing still requires confirmation. Permanent QR identities, service activation and ordering hours/cutoff are unchanged. Effective state uses a short label with green circled check or red circled X, a 2 px state border and the detailed reason in a tooltip. A manually enabled table can still be effectively unavailable outside hours.

AI approval waits for a successful server response before closing the result modal and issuing a success toast. A failed save stays open with its error and editable proposal. Inbox refresh failure after successful publication cannot turn that success into an apparent failed save. Reject also closes on success. Existing source hashes, selective approval and audit remain authoritative.

## Upgrade and validation boundaries

Migration `2026_10_27_001100` creates the aggregate visit table; no existing orders, reservations, menus or table data are rewritten. Build new assets through the normal release process. No additional chart/tooltip/analytics dependency or provider credential is required. Existing global AI limits and queue pacing remain unchanged.

A real Groq `openai/gpt-oss-20b` request was made on the existing Forge restaurant for Feta saganaki (350 g); its notification/detail arrived and its nutritional section was approved and saved. Food-label sections were deliberately left unapproved in that test. This proves the live generation/review path, not nutritional accuracy or completeness of allergen suggestions. UI auto-close and dashboard/help/table changes are tested locally and require the new assets/code to be deployed. Real occupancy measurement, verified nutritional references, long-period performance/load characterization and analytics retention/archiving remain separate future work.
