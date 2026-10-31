# UTM campaigns and lead attribution — 0.0.46

The platform administrator can open **Campanii și vizite / Campaigns and visits** at `/admin/campaigns`. It reports tagged entries to the marketing landing (`/` or `/en`) and links to contact requests they generated. It does not merge restaurant menu analytics or claim unique people, physical visitors, verified ad clicks or revenue. Recording starts with this release; prior visitors cannot be reconstructed.

## Tagged entries

Append standard parameters to a marketing link, for example:

```text
/?utm_source=facebook&utm_medium=paid_social&utm_campaign=migrare_gloriafood&utm_content=ad-a&utm_term=meniu&utm_id=gf-2026
```

The server recognizes `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term` and `utm_id`. At least one nonempty recognized field is required; zero-valued string identifiers are valid. Each value must be a UTF-8 string of at most200 characters, without control/HTML delimiter characters. Malformed/array/oversized tags do not break the page but are not recorded. Source and medium are trimmed/lowercase; remaining labels keep their case. Use campaign labels rather than personal information in these public URL values. Unknown parameters, click IDs and referrers are not copied into the campaign table, and the full URL/query is not stored there.

Only successful marketing-host GETs are eligible. HEAD, known crawler/link-preview agents, speculative prefetches, platform-administrator visits and DNT/Sec-GPC opt-outs are excluded. These are basic heuristics, not proof that remaining visits are humans. Verified restaurant domains/API-only hosts and other routes remain outside this tracker. Server capture works independently of JavaScript; contact submission still needs JavaScript as described in LEAD_CAPTURE.md. Canonical URLs and shared SEO artifacts remain free of tracking parameters/contact data.

## Session and failure behavior

The existing Laravel session scopes cache locks and a thirty-minute deduplication window for the same six-field tag combination. No new persistent tracking cookie, IP/UA fingerprint or database visitor/session identifier is added. A browser refresh or language switch with identical tags in that window reuses the entry. Another tag combination or another session can record another visit; the same person can therefore account for several visits. Clean entry path and locale describe the first captured entry for a deduplicated visit.

The session holds the latest eligible visit reference until thirty minutes after its capture. Untagged navigation does not replace it. A new tagged entry that is invalid, skipped by admission control or unavailable clears the previous pointer rather than assigning the wrong campaign. Attribution checks the pointer shape, expiry and a matching recent database row. DNT/GPC opt-out clears attribution. Session/campaign cache keys are keyed hashes; a hashed IP key is used only for transient admission control (up to60 new entries per IP/minute). No raw IP/UA is stored in campaign records. Cache/analytics failures log only the exception class and do not propagate from the tracker into the landing response; valid contact requests can remain unattributed.

## Contact requests

New contact requests use the last eligible campaign visit from the same session within the window. A submitted visit ID, source or campaign name is not trusted. `CaptureServiceLead` still normalizes/deduplicates email identity and stores encrypted contact fields. Repeated anonymous submissions cannot replace the existing lead's details, status or initial campaign association. No account, email, newsletter or background job is created. The form's contact notice mentions association with an incoming campaign.

`service_leads.campaign_visit_id` is nullable. Deleting a campaign row nulls that reference; deleting a lead removes its current contribution to reported lead counts. There is no automatic deletion of visit history in this release. Campaign data and contact requests remain separate from tenant restaurant data.

## Report definitions and authorization

The page and linked lead filters require an authenticated, email-verified platform administrator. Tenant owners/managers/staff cannot list these records. Responses use private/no-store caching and encrypted Inertia history. Contact values appear only in the authorized lead view, not in visit rows.

- **Recorded visits:** deduplicated tagged entries matching the period and filters; not unique people.
- **Campaigns:** distinct combinations of source, medium, name and campaign ID. Content/term variants aggregate into the same campaign identity while remaining visible per visit.
- **Attributed requests:** currently retained lead records linked to visits in that filtered period, even if the request was submitted shortly after the entry day ended.
- **Qualified requests:** those linked leads currently marked qualified; not paying customers or receipts.
- **Ranking:** top10 by attributed requests, then visits. The visual bar compares visit volume and is not a conversion percentage. A visit may have multiple leads.

Periods are the last7/30/90 Bucharest-local days, including today, converted to UTC query boundaries. The log is paginated25 entries per page and shows all six tag values, entry path/language and local time. Source/medium/name filters and page links retain the selected context. Campaign labels are compared byte-exactly across SQLite/MariaDB; source/medium filters normalize case. Dropdown options preserve distinct label spelling despite database collation. Aggregate counts are normalized to integers because MariaDB SUM otherwise returns decimal strings. A visit with leads links to `/admin/leads?campaign_visit=...`, where its requests and campaign context can be reviewed and their statuses managed.

## Release

Run `2026_10_31_000300_create_campaign_visits_table` before serving the new code. It creates campaign_visits and adds the nullable lead FK; rollback removes the FK before the table. Existing leads remain valid and unassigned. No environment or package changes are required. Tests use private SQLite/browser fixtures and an isolated MariaDB10.11 test container, not production data. Executed checks and remaining limitations are recorded in CODEX_PROGRESS.md.
