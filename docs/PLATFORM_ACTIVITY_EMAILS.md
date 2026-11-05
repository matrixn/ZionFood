# Administrator activity email — 0.0.52

Verified platform-admin accounts (`users.is_platform_admin = true`, non-null email_verified_at and a valid email address) receive one private email for each new event. Delivery uses each account's current email address and preferred RO/EN locale. Ordinary owners/managers/staff and unverified platform accounts are excluded. There is no fallback to ADMIN_EMAIL: that environment address remains dedicated to the existing technical error/test flows. If there is no eligible administrator at event time, no notice is created; older events are not backfilled when an administrator is promoted later.

| Event | Ledger source | Details and destination |
| --- | --- | --- |
| New contact request | platform_contact_request | Name, normalized phone, email, form language, record reference and available server-attributed UTM data; /admin/leads |
| New registration | platform_account_registered | Name, email, phone, language and new-owner/invited-staff context; /admin/users |
| Email verification | platform_email_verified | Account contacts after successful verification, including email-bound invitation proof; /admin/users |
| Restaurant onboarding | platform_restaurant_created | Owner contacts, restaurant, organization, first location and address; /admin/organizations/{organization} |

Messages contain the event timestamp in Europe/Bucharest, an internal notification reference, localized explanatory text and a protected admin link. They contain no passwords, password hashes, CAPTCHA proof, API credentials, invitation/verification/reset tokens, raw IP or user-agent data. User-controlled values are escaped in HTML; plain text is rendered separately. This is an administrator notification, not a welcome email to the contact or a newsletter subscription.

## Capture and deduplication

`RecordPlatformActivity` records a durable per-recipient intent and the pending EmailDelivery row in the business transaction. Lead capture locks the unique-email lookup and permits three transaction attempts for concurrent insertion deadlocks, recording only a new first-or-create result; duplicate identities and honeypot submissions remain generically acknowledged without new mail. Fortify's CreateNewUser records registration inside its user/invitation transaction. User::markEmailAsVerified wraps persistence plus notification intent in a transaction; the standard Verified listener and invitation flow can repeat the same intent safely. Invitation-proven new staff receive both registration and verification activity, while acceptance by an existing unverified account records only the verification stage. Restaurant creation uses the current OnboardOrganization action after hierarchy/trial setup succeeds. Factories, seeders, direct model creation and ordinary logins do not simulate registrations or onboarded restaurants.

A unique SHA-256 event key plus recipient ID prevents duplicate messages for the same event. Verification keys also include a digest of the verified address so verification of a changed address is distinct. Keys are opaque and do not contain plaintext email. A rolled-back business transaction retains neither the intent nor email metadata. Historical events are not sent retroactively.

## Queue, failures and quotas

The additive `2026_11_03_000200_create_platform_activity_emails` migration must run through the normal release process before the updated application handles these events. No new dependency or environment variable is required. Existing mail transport/from settings and `MAIL_QUEUE_CONNECTION` remain in effect. In production keep a persistent Redis/database queue, the configured Horizon/queue worker and Laravel scheduler running as for other mail.

After commit, QueuePlatformActivityEmail is dispatched on the configured email connection. Immediate dispatch failure is logged with bounded IDs/class only and leaves recoverable intent rather than failing an already committed contact/account operation. Preparation renders PlatformActivityNotification synchronously in its worker, then QueueOutgoingEmail hands transport to the existing encrypted DeliverEmail queue. Both stages use the same stable notification/delivery ID. A duplicate worker or repeated recovery cannot resubmit a terminal accepted/local message; transport leases handle concurrent jobs. As with the pre-existing transport pipeline, provider acceptance followed by an infrastructure crash before recording acceptance is an external exactly-once limitation, not an inbox guarantee.

`php artisan zionfood:platform-activity-mail` runs every minute, processing up to200 pending/recoverable intents. It also retries a queued handoff older than five minutes, covering loss between commit and queue publication. Deferred/processing transport jobs remain governed by the existing transport worker rather than being rendered again by this recovery scan. Normal preparation backoff is60/300/900/3600 seconds. Notices expire after30 days. Queue failures, suppressed/expired status and transport acceptance remain visible in Admin → Emails and queue, with the four new source filters and RO/EN labels.

All activity mail shares the existing application140-recipient rolling24h limit and configured Resend daily/monthly/rate guards. Exhaustion defers transport; it does not bypass those limits or reject a saved contact. Activity sources are separate from customer account-mail admission/sub-budget and the five-error-alert circuit. More administrator recipients consume more recipient quota. A scheduler/worker outage still requires operational restoration; a durable intent does not operate without workers.

## Privacy and retention

Recipient access and verified-address eligibility are rechecked before rendering. The transport claim also rechecks platform role, verification and recipient/address correspondence; a revoked/deleted/unverified administrator cannot receive a newly claimed private notice. A deleted source record is suppressed before preparation. Already submitted emails cannot be recalled.

Intent details are encrypted and hidden from model serialization. Queue jobs are encrypted; initial preparation jobs carry only an intent ID. Existing ledger recipient/subject/context encryption remains. Successful local preparation clears the intent snapshot immediately; production snapshots remain available for handoff recovery and are cleared by the daily `zionfood:mail-prune` command once delivery is terminal. Minimal event/source/recipient/subject references remain as deduplication evidence, without the original contact payload. Ledger metadata retains its existing retention policy. No message bodies are exposed through the admin email-history endpoint. An actual mailbox/provider has its own retention outside the application.

## Verification boundaries

See CODEX_PROGRESS.md for exact local PHP/MariaDB/frontend/browser results. Local acceptance uses synthetic contacts, isolated storage, array mail or a canceled/faked transport. No real Resend send, recipient inbox, production migration, Forge operation or deployment is implied by local checks.
