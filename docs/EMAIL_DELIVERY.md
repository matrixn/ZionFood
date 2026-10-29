# Outgoing email administration — 0.0.34

The platform administrator opens **Admin → Emails and queue** (`/admin/emails`). Restaurant owners, managers and staff have no access, including direct JSON and mutation requests. The list refreshes every 15 seconds and preserves applied filters/pagination. Details show recipients, encrypted-at-rest subject, source/initiator, state, actual transport attempts, provider ID, expiry and next-attempt time. No message body, attachment, reset token or invitation URL is exposed by the ledger.

## Sources, states and meaning

Sources: `system_error`, `account_registration`, `account_verification`, `password_reset`, `staff_invitation`, `reservation`, `email_test`, `other`, `legacy`. Registration uses the verification message generated while registering; a separate account-created email is not invented. Initiators are the authenticated user, anonymous HTTP request, system or unknown historical actor. A background notification keeps its initiating actor when its rendered transport job is created later.

States: `notification_queued` (not rendered), `queued`, `processing` (short lease), `deferred`, `accepted`, `failed`, `suppressed`, `expired`, `superseded`, `local`. **Accepted means the transport accepted the email, not that it reached the inbox.** `log`/`array` transports are explicitly local. Bounces, complaints, delivery/open/read receipts and provider-wide sent-history import are not implemented here.

Filters: source, state, initiator kind, mailer, exact case-insensitive recipient (To/Cc/Bcc), inclusive creation-date range in Europe/Bucharest, page. Twenty-five rows per page, newest first. Summary cards cover all recorded messages, independent of filters. Recipient matching uses an HMAC with APP_KEY; recipients, subject and notification context use Laravel encrypted casts. Keep APP_KEY stable and preserve it for existing encrypted queue jobs. Terminal metadata is pruned after 90 days by the scheduler; active entries are retained. Application backups and existing queue/failed-job retention remain separate.

## Three independent protections

| Protection | Scope | Resume |
| --- | --- | --- |
| Application 140 recipients / rolling 24h | All real email transports | Automatically as rolling slots expire |
| Resend local daily guard, initially 100 recipients / UTC day | All mailers using the Resend transport | Next midnight UTC; editable local guard in admin |
| Five distinct error-alert admissions / attempts per rolling hour | System error alerts only | Sixth latches suspension; manual resume on Error email alerts |

The local Resend counter counts reservations conservatively, including uncertain/failed attempts; it is not a delivered-email counter. It may overcount after a failed send. Database row locks and a shared provider send lock coordinate workers. The shared cache store must support cross-process locks (Redis in production); do not switch it to a process-local store. Other software/API keys sharing the Resend account are outside this application's counter.

Resend documents a Free-plan quota of 100 per UTC calendar day and distinct `daily_quota_exceeded`, `monthly_quota_exceeded` and `rate_limit_exceeded` responses. Typed SDK errors, including wrapped exceptions, latch a shared provider pause. Daily errors wait until midnight UTC; short rate errors wait one minute because the installed SDK does not expose response headers. A monthly error uses the last authoritative monthly reset when known; otherwise it remains paused with daily queue rechecks until usage can establish a reset/headroom. The application does not guess monthly billing reset dates or treat every HTTP 429 as a daily limit.

During a provider pause, jobs are released without calling Resend again, throwing another quota exception or spending the job's maximum-exception budget. Email payloads remain in the encrypted queue. After the pause they still need a running worker and must pass other quotas, link validity and notification checks. New password-reset/verification emails expire after the configured auth lifetime; superseded reservation versions and accepted/revoked/expired staff invitations are skipped. A long-deferral user can request a fresh auth link. Ordinary transport failures retain existing backoff/retry limits and a 30-day job lifetime. No automatic replay of failed emails is added. Crash/network uncertainty still permits at-least-once submission; no exactly-once or inbox delivery guarantee is claimed.

## Checking actual account usage

**Check Resend usage** makes a bounded, verified backend GET to the fixed `https://api.resend.com/usage` endpoint using the existing private `services.resend.key`. Only validated daily/monthly used, limit, reset times and a safe error code are saved. The daily `used` count includes received emails according to Resend. The key and raw API errors never reach the admin payload or logs. A sending-only key may lack usage permission; this shows an explanation without clearing a confirmed pause or requiring broader key privileges. Confirmed headroom can clear a provider-quota pause; editing the local threshold cannot override a provider-confirmed exhaustion. Admin checks/indexing are limited to three operations/minute per admin; settings use optimistic revisions.

Daily reset is 00:00 UTC, displayed in Europe/Bucharest (02:00 in winter, 03:00 in summer). Provider month reset comes from its API. History records acceptance separately from provider usage; this UI does not fetch or render old provider email bodies.

## Existing backlog and rollout

Run additive migration `2026_10_27_130000_create_email_ledger`, normal configuration/asset build and a graceful Horizon restart as part of the existing deployment process. The initial local daily guard comes from `RESEND_DAILY_LIMIT=100`; after the row exists the admin setting takes precedence. Existing global 140/24h and alert circuit settings remain unchanged.

The minute scheduler runs `php artisan zionfood:mail-index --limit=10000`. The admin can also request indexing. This reads the configured delivery/default database queues or Redis ready/delayed/reserved lists without popping, sending, retrying or rewriting jobs. Only authenticated encrypted DeliverEmail/SendQueuedNotifications payloads are deserialized. Indexing is idempotent and bounded to 10,000 scanned jobs per invocation; the response marks a partial scan. Historic unclassified deliveries retain source `legacy` and initiator `unknown`. Existing custom queue names outside the configured defaults need separate operational indexing; only configured defaults are scanned. Failed-job archives and emails already sent before the ledger existed cannot be reconstructed completely. No fabricated sent history is generated. A scan assumes trusted application queue storage/APP_KEY, as does Laravel's normal worker.

Use **Check Resend usage** after rollout so the account's current external consumption can be recognized; otherwise the first typed daily quota rejection establishes the pause. A second scan is harmless. Inspect deferred reason/next-attempt and Horizon worker health without clearing queues. The daily terminal-metadata prune does not remove queued payloads or pending metadata. Existing log channels retain safe queued/processing/provider-accepted/failure diagnostics and the original application exception remains in PHP/Laravel logs.

## Reverb incident distinction

The separately supplied broadcast log identified cURL error 6 resolving `ws.food.zion3d.ro`: a DNS failure before reaching Reverb. Resend quota errors cannot cause that broadcaster DNS error. In the subsequent authorized operational follow-up, the owner corrected their environment, Horizon was gracefully refreshed and one explicitly retried exhausted event was confirmed delivered in one attempt. This confirmed internal backend publication only; public browser DNS/WSS and the separation of REVERB_PUBLISH_* from public/VITE values still need independent verification. This email-ledger increment performed no new Forge mutation or production email send.

Sources: [Resend usage limits](https://resend.com/docs/api-reference/rate-limit), [Retrieve Usage](https://resend.com/docs/api-reference/usage/retrieve-usage), [error types](https://resend.com/docs/api-reference/errors).

## 0.0.33 account-workflow protection

Registration, password-reset requests and verification resends share admission controls before account/notification/job creation: default 10 requests/hour/IP and 30 requests/rolling24h across IPs. Invalid or non-sending admitted requests count conservatively. Excess returns429 and Retry-After. Queue delivery of account registration/verification/reset and unknown historical legacy sources has a separate30-recipient rolling24h allowance. Staff invitations, reservations, system alerts and explicitly classified other sources do not consume it. Both daily allowances are capped to half the smallest known daily application/provider capacity, using the admin local guard and synchronized provider daily limit when available; zero available account capacity blocks admission. These are daily controls, not a monthly fairness scheduler. Other apps/provider usage can still consume shared provider capacity.

Configure MAIL_ACCOUNT_DAILY_LIMIT (default30) and MAIL_ACCOUNT_REQUESTS_PER_HOUR (default10), rebuild config through the normal release procedure and restart workers through that procedure. The existing MAIL_RATE_LIMIT_STORE must be shared Redis in production; locks serialize updates and cache failure does not admit requests. A budget-exceeded queued message retains normal expiry and is deferred with account_mail_quota, localized in platform-admin email history. Historical unknown jobs are deliberately constrained until classified; it is safer than guessing message meaning from private bodies. Existing140/24h application and Resend UTC-day quotas remain final guards.

The temporary public test-email page exposes only whether ADMIN_EMAIL is valid, never its value. Its fixed server-side recipient and shared3/hour limiter are unchanged. The owner accepted anonymous consumption of that diagnostic quota. No live-provider send or quota exhaustion was performed in this audit.

## 0.0.34 registration admission order

Ordinary registration first validates account fields and consumes a valid access-receipt proof, then reserves the existing account-mail admission slot before creating a user or notification. Failed fields or missing/invalid CAPTCHA no longer spend that global allowance. Proven staff invitations skip the CAPTCHA and retain normal admission. A separate registration IP attempt throttle still bounds invalid requests; reset/resend admission and all worker delivery sub-budgets remain unchanged.
