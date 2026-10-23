# 20 System notifications

> Replacement implementation updated 23 October 2026. Checked technical build items have source and regression evidence in [the remaining-modules handoff](../../modules/REMAINING_MODULES_HANDOFF.md). Production-policy acceptance, official certificate generation and legacy reconciliation remain separate gates; unchecked items are not claimed complete.

## Purpose and sources

Deliver actionable reminders inside SSI, including local HRIS events and notifications submitted by partner systems. **Observed** describes legacy behavior; **target/proposed** describes required improvements; **unresolved** requires an owner decision.

- [SystemNotification model](../../../app/SystemNotification.php): audience/active scopes, URL processing, create/read/dismiss helpers and presentation resource.
- [SystemNotificationController](../../../app/Http/Controllers/SystemNotificationController.php): list/count/read/dismiss/bulk/create/external-create actions.
- [API routes](../../../routes/api.php), [VerifyApiToken](../../../app/Http/Middleware/VerifyApiToken.php), [URL helpers](../../../app/Helpers/helpers.php).
- [SSI landing template](../../../resources/views/ssi/index.blade.php): merged reminders and persisted notices, localStorage state, click/dismiss AJAX.
- Migrations [create table](../../../database/migrations/2026_07_07_000001_create_system_notifications_table.php), [add recipient email](../../../database/migrations/2026_07_07_000002_add_recipient_email_to_system_notifications_table.php), and [legacy notification guides](../../../NOTIFICATIONS_SYSTEM_GUIDE.md).
- [Integration contracts](../INTEGRATION_CONTRACTS.md) contains exact request/response shapes and error handling.

## Data and observed visibility

`system_notifications` contains optional `user_id` and resolved `recipient_email`, source `system`/`source`, title/message, destination URL, badge/icon presentation fields, integer priority, `notification_type`, JSON `target_users`, shared read/dismiss flags/timestamps, expiry and active flag.

| Type/state | Actual source rule |
| --- | --- |
| `personal` | Visible when notification user ID equals current user ID |
| `broadcast` | Visible to every authenticated user through the current-user scope |
| `role_based` | Visible if `target_users` contains integer current user ID OR string current `user_type` |
| Active | `is_active = true`, not dismissed, and expiry is null or strictly later than now |
| Ordering | Higher priority first, then most recently created |
| Read | One `is_read` flag and `read_at` on the notification row |
| Dismissed | One `is_dismissed` flag and `dismissed_at` on the notification row |

The mixed integer-ID/string-role audience is type sensitive. A role code is not interchangeable with an integer of the same numeric value. The current `recipient_email` supports creation-time identity evidence; the visibility scope uses `user_id`, not email.

## Workflows and authorization

1. Browser endpoints under `/api/notifications` explicitly attach `web` and `auth` inside the API group. They use session authentication and CSRF for writes, plus the API group's rate limit. Controller methods also return 401 when unauthenticated if invoked without middleware redirection.
2. `index` and `count` call `cleanupExpired`, which marks expired rows inactive, then query the current user's active notices. Count returns active total and unread total. This is a database write during a read request in the legacy app.
3. Read/dismiss actions validate numeric database `id`, scope the record to the current audience and return 404 if absent. The formatted resource has display ID `notif-{id}` plus numeric `notification_id`; send the numeric field back when mutating.
4. Read-all/dismiss-all affect active rows in the current audience. Single-item actions scope the audience but do not also require active status.
5. Browser create requires authentication and validates personal/broadcast/role audience inputs. The intended create permission check is commented out, so authentication alone reaches creation. Target create/publish requires an explicit ability.
6. External create uses the shared `API_SYNC_TOKEN` middleware, accepts local ID or email for personal recipients, and prefers email if both are supplied. Resolve partner identifiers before saving; numeric IDs are not assumed equal across systems.
7. The model appends `processed_url` using `auto_ssi_url`, so serialization can wrap an external URL with a current-user SSI credential. URL trust and partner allowlisting must be resolved before this behavior is retained.
8. SSI merges derived WFH approval reminders with persisted notifications. The template also persists local UI state using `ssi-system-notifications-state`, without a per-account namespace. Database rows post AJAX read/dismiss requests; derived reminders rely on local state until source workflow changes.

## Defects and target design

**Shared state:** one user reading/dismissing a broadcast or role notice changes its row for every recipient. The target should separate content/audience from per-recipient receipts, with a unique notification/user pair. Resolve how existing shared flags import; they cannot reconstruct individual history.

**Audience and trust:** implement explicit role and user recipient fields or relations, not an overloaded JSON array. Separate external publisher scopes from attendance ingestion, allow only approved source systems and destinations, and assign an external event ID/idempotency key. Never embed SSI login credentials for an arbitrary externally submitted URL.

**Delivery:** domain events publish after commit. Queue email/external delivery where used, with retry deduplication. Decide whether role/broadcast membership is snapshotted at publication or evaluated dynamically, including new hires, role changes and applicants.

**UI:** scope local state to user/environment or make receipt state server authoritative. Keep source workflow queues authoritative for actionable reminders. Dismissing a reminder must not approve, cancel or suppress the underlying work forever.

**Schema:** both July 2026 migrations add `recipient_email`; replace with coherent baseline schema. Do not blindly replay the legacy sequence.

## Build checklist

- [ ] Resolve D04/D09/D13 for partner destinations, recipient semantics, receipts and retention.
- [x] Implement content, explicit audience and per-recipient receipt tables with appropriate indexes/uniqueness.
- [ ] Preserve list/count/resource compatibility for retained consumers and document the ID conventions.
- [x] Implement authorized read/dismiss and bulk operations scoped to the current recipient.
- [x] Require explicit publishing permission for browser create and producer-specific scopes for external create.
- [ ] Resolve personal recipients through accepted stable identity mapping; preserve email evidence without using it as permanent identity.
- [ ] Validate/sanitize text and presentation values, restrict destinations and use accepted SSI launch adapters.
- [ ] Add external event deduplication, retries, expiry handling and a retention/archive procedure.
- [ ] Merge actionable local reminders with persisted notices consistently; isolate state across accounts/devices.
- [ ] Migrate existing audiences/flags according to the accepted receipt policy and retain legacy source IDs.

## Acceptance checklist

- [ ] A user sees only their personal/role notices and eligible broadcasts; count/list/order agree at expiry boundaries.
- [ ] Reading or dismissing a broadcast for user A leaves user B's state unchanged.
- [ ] Bulk actions cannot affect foreign receipts; repeated read/dismiss operations are idempotent.
- [ ] Changing role/account on a shared browser follows the accepted audience policy without inheriting another user's local state.
- [ ] Unauthorized users cannot publish; attendance-only credentials cannot publish notifications in the target.
- [ ] Email/ID resolution, unknown recipient, malformed audience and missing required fields return the documented errors.
- [ ] Replaying an external event creates one logical notification; retry after a timeout is safe.
- [ ] Untrusted destinations never receive SSI credentials; query/base-path deep links work for approved partners.
- [ ] Expiry/cleanup and newly generated workflow reminders behave correctly with empty, dismissed and read lists.

Dependencies: [authentication](01-authentication-and-authorization.md), [SSI portal](04-ssi-portal-and-calendars.md), business event producers, and [external configuration](../EXTERNAL_CONFIGURATION.md).
