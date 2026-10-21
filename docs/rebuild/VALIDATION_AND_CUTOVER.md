# Validation and cutover checklists

## Existing test baseline

`tests/Unit/ExampleTest.php` checks a boolean, and `tests/Feature/ExampleTest.php` expects `/` to return 200 even though `HomeController` requires authentication. These files do not validate any HR workflow. No legacy application tests or database migrations were executed while creating this documentation; the source inventory was generated without booting Laravel.

## Definition of done for every module

- [ ] All listed screens/actions and retained route behaviors are implemented or have an accepted retirement decision.
- [ ] Form fields, validation, empty/error states, list filters, pagination, attachments and printed outputs are covered.
- [ ] Actor, organization scope and ownership checks are enforced on every read, write, export and file download.
- [ ] Current-state guards and transaction boundaries prevent partial approvals, duplicate submissions and double deductions.
- [ ] Audit history records actor, action, record, time and reason without copying secrets or unnecessary personnel data.
- [ ] Migration mapping and import validation cover historical data and rejected/orphan records.
- [ ] Acceptance fixtures exercise normal, invalid, boundary, cancellation, concurrent/retry and cross-user cases as appropriate.
- [ ] The module's output is reconciled with accepted legacy samples; intentional differences are signed off.
- [ ] External calls use safe failure handling and can be tested with fakes or isolated partners.
- [ ] The module checklist has evidence links and no hidden unresolved production dependency.

## Required verification scenarios

| Area | Minimum meaningful scenarios |
| --- | --- |
| Login | Valid/invalid login, throttling, archived/terminated account policy, session regeneration/invalidation, applicant routing, reset expiry, closed staff registration |
| Authorization | Own vs other staff, same vs different division, changed division, delegated/OIC date range, HR vs payroll permissions, applicant vs employee, unauthenticated access |
| SSI | Known/unknown user, malformed/expired/replayed token under new protocol, allowed/blocked destinations, target with query/fragment/port/base path, independent partner IDs |
| Device sync | Invalid/missing/scoped token, empty/maximum batch, malformed event, duplicate/source-ID collision, retry after uncertain response, cursor recovery and timezone handling |
| Attendance | Four/five-day schedules, lunch boundaries, incomplete/reversed/duplicate logs, WFH, suspension cutoff, approved OB/leave/CTO intersections, override and historical rule versions |
| Leave and COC | Insufficient/exact balance, concurrent approvals, partial day, multi-day/weekend/holiday request, approval cancellation, reversal linkage, repeated nightly run, expiration if applicable; seven-day autoapproval uses a service actor and current eligible state, excluding later cancelled/disapproved requests |
| Payroll | Approved golden examples for each employee/payroll type, decimal rounding, loan combinations, absent days, bonuses/deductions, duplicate period, finalized period, past report after salary change |
| Recruitment | Application eligibility, duplicate application, scoped evaluators, question assignment, attempt start/resume/expiry, submitted answer immutability, file privacy |
| PDS/SALN/IPCR | Nested-record ownership, repeatable child rows, null/unknown values, totals/ratings, period isolation, submitted-record edits and accurate printing |
| L&D/IDP | Position matrix vs employee ID, import validation/rollback, duplicate plan/year, copied matrix, division totals, submit/sign/return boundaries |
| Concerns/certificates | Employee/HR scopes, response history, attachment access, authorized signing, issued snapshot stability, public verification disclosure and nonexistent/revoked identifier |
| Recognition/WFH | Voting period, duplicate/self vote policy, eligible nominees, WFH dates, return/resubmit, consolidation consistency and signatures |
| Notifications | User/role/broadcast audience, role/ID type ambiguity, two recipients with independent state, expiry/count/order, create permission, duplicate external delivery |
| Files/reports | Wrong MIME/extension, size limits, filename collision, missing file, unauthorized download, Excel formula handling, PDF fonts/page breaks/signature placement, QR verification URL |

Use the production database engine for features relying on JSON, SQL date functions, collation or numeric behavior. SQLite-only results do not demonstrate equivalent MySQL behavior.

## Suggested verification commands in the replacement

These are target app checks, not instructions to run against the legacy environment or production data:

```text
composer validate --strict
composer audit
npm ci
npm run build
php artisan test
php artisan route:list
php artisan migrate --env=testing
php artisan config:cache
php artisan route:cache
php artisan schedule:list
```

Use a dedicated test database and fake mail/HTTP/storage as needed. Add the selected linter/static analyzer and browser smoke checks to CI. Verify configuration and route caching in an isolated deployment; clear caches after local inspection if necessary. A failure should have a tracked resolution, not a bypassed assertion.

## Business acceptance fixtures

Obtain sanitized examples approved by the relevant owner. For each fixture, store inputs, effective policy/rule version, expected output and approval evidence. Include a DTR day and monthly report for each schedule, approved and cancelled requests, leave/COC opening and closing balances, each payroll/report type, generated PDS/SALN/IPCR, IDP/training plans, employment certificates, and signed WFH consolidations.

Report checks compare content, totals and pagination as well as file existence. A PDF that downloads successfully may still contain incorrect dates, stale salaries or misplaced signatures.

## Staging readiness

- [ ] [External configuration](EXTERNAL_CONFIGURATION.md) prerequisites are provisioned with staging credentials and approved partner URLs.
- [ ] Fresh installation migrations and upgrades both succeed on the selected database engine.
- [ ] Import rehearsal produces accepted reconciliation and rejection manifests.
- [ ] Policies and automated scenarios above pass; module owners review workflows and print outputs.
- [ ] Staging mail cannot deliver to live staff accidentally; external jobs and calls use isolated targets or explicit disable switches.
- [ ] Database/file restoration is demonstrated, including encryption keys and verification URL continuity.
- [ ] Worker retries, failed jobs, scheduler locking, cursor recovery, logs and alerts are demonstrated.
- [ ] Large gate-log queries, imports, exports and reports meet measured response and memory targets agreed by operations.
- [ ] Private records and files remain inaccessible through direct links and guessed IDs.

## Production cutover

1. Record the release artifact, migration version, database/file backup IDs, policy versions, external API cursors, pending deliveries and rollback owner.
2. Announce and start the approved write-freeze window. Disable legacy cron/scheduler/worker writes and coordinate the device sync agent. Verify no second machine continues triggering job URLs.
3. Extract the final delta, import idempotently, copy/checksum files, and perform the full [reconciliation checklist](DATA_MIGRATION.md). Adjudicate `PENDING_OUT`/`COMPLETED_OUT` and immediate CTO `OUT` rows individually before enabling credit jobs.
4. Deploy the approved artifact/configuration, rebuild caches, verify HTTPS/document root, then run limited smoke checks with approved test accounts.
5. Switch partner destinations and DNS/routing as planned. Enable exactly one scheduler/worker set and resume device sync from the recorded cursor.
6. Confirm an employee, HR officer, division approver, payroll operator and partner client can complete their agreed smoke scenarios. Public certificate verification must use the approved disclosure policy.
7. Monitor job execution, failed deliveries, sync counts, ledger deltas, payroll/report errors and authorization failures through the agreed observation window.
8. Obtain operational and business acceptance before ending the freeze/observation plan and archiving legacy access.

## Rollback gate

- [ ] Define measurable rollback triggers and decision authority before the deployment window.
- [ ] Keep legacy code/configuration and database/files recoverable; retain source-to-target ID maps and post-cutover write journal.
- [ ] Stop new writes, workers, scheduler and partner ingestion before rollback reconciliation.
- [ ] Reconcile or reverse all accepted new-system writes through the tested recovery process; do not point old code at an incompatible target schema.
- [ ] Restore routing and partner cursors deliberately; verify no lost or duplicate logs, notifications, approvals, pay or credit movements.
- [ ] Record the incident, reconciliation and next release conditions without exposing secrets or personnel records.
