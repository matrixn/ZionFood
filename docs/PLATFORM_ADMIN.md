# Platform administration — 0.0.46

The general administration console is separate from restaurant management. All new routes require authentication, verified email and `is_platform_admin`. Platform access does not grant tenant operating permissions. Responses contain explicit support DTOs, use private/no-store cache headers and encrypted Inertia history.

## Navigation and administration tools

- **Control centre** (`/admin`): consolidated statistics, daily activity, attention items, recent customers, expiring trials and shortcuts.
- **Customers & access**: paginated organization directory, subscription filter, organization support record and searchable account/verification directory. The support record shows restaurants, locations, memberships and subscription state; it does not impersonate an owner or change permissions.
- **Growth**: contact requests with their existing status/deletion workflow; campaign traffic and attributed requests.
- **Catalog**: shared cuisine and ingredient management, retaining existing creation, editing, visibility and deletion rules.
- **Monitoring**: operational overview and paginated import registry, integration log, email ledger/provider quota controls and system alert controls.
- **Configuration**: existing AI provider, model, secret and quota settings.

Existing `/admin/{organization-public-id}` links redirect to `/admin/organizations/{organization-public-id}`. An administrator with no restaurant assignment reaches `/admin` from `/app`; accounts with an assigned location retain their restaurant workspace destination.

## Metric definitions

The reporting selector accepts 7, 30 or 90 calendar days including today, in **UTC**. This is deliberately a platform reporting zone, independent of the location-level reports. Daily chart data are also available as a readable table.

| Metric | Definition |
| --- | --- |
| Completed order value | Orders created in the reporting period and currently completed; integer minor-unit totals grouped by currency. Not receipts, collected revenue or proof of payment. |
| Completed orders | Same creation-period/current-status definition as the value above. |
| Active orders | Current orders excluding completed, cancelled and rejected, irrespective of reporting period. |
| Organizations, restaurants, locations, accounts | All-time registered records. Accounts include unverified and platform accounts; this is not an active-user count. |
| Requests | Contact requests created in the period. Backlog separately includes all retained requests with `new` status. |
| Campaign entries | Stored UTM entries created in the period, subject to existing exclusions/session deduplication. Approximate entries, not unique people or total digital-menu traffic. |
| Reservations | Starts within the selected UTC period, excluding cancelled, rejected and expired states. |
| Active/expiring trials | `trial` subscriptions with a future trial end; expiring means the next seven days. No new billing or entitlement rules. |
| Attention items | Current stored failed imports, failed/blocked webhooks, terminal failed outbox events, failed/deferred emails and failed AI drafts. These counters can include older unresolved records. |

## Operational limits

The console reads local application records. It does not initialize/reset provider quotas, send email, call Groq/GloriaFood/Resend, retry failed work or verify that Redis, Horizon or Reverb is running. Enabled integration configurations are not connectivity checks; pending outbox events are not the total Horizon queue. Mail configuration/last quota check can be stale; provider acceptance does not establish inbox delivery. The existing email and alert pages retain their explicit control actions.

Import diagnostics include only identifiers, organization/location names, provider, timestamps and processing status. Credentials, snapshots, proposals, request payloads and customer order identity are excluded. Organization and account search treats `%`, `_` and `!` literally. Directories preserve filters during pagination.

The temporary public `/test-email` diagnostic retains the user's earlier public-access decision and existing protections. It opens separately from monitoring. Horizon also opens separately under its existing authorization.

## Interface

Responsive desktop sidebar and mobile drawer, persistent RO/EN language selection, light/dark presentation, active navigation, coherent headings and reusable statistic/table styles. New form fields use `ZFFormField` with specific bilingual help, explicit input IDs and F1 help. Help icons remain outside the form's normal Tab sequence. Metrics explain their definitions rather than implying financial settlement or physical occupancy.

## Follow-up work

- [ ] Dedicated, explicitly authorized worker/provider health probes, with timeout and freshness semantics.
- [ ] Administrative retry/recovery workflows with scoped authorization, idempotency and audit trails.
- [ ] Billing/subscription changes, suspension policy and audited privileged account changes; not introduced by this interface redesign.
- [ ] Materialized aggregate reporting if production volume warrants it. Current charts use bounded-period aggregate queries, not loading individual orders into PHP.
