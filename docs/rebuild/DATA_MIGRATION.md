# Database and file migration plan

## Evidence and limitations

The [migration ledger](inventories/SCHEMA.md) lists 192 migration files and their detected `up()` operations. The [model inventory](inventories/MODELS.md) lists 89 models and literal relationships. Neither is a verified final database schema. Capture a schema-only export from the deployed database and compare it with both before defining the target schema or importer.

SQL under `database/seeds/sql/` includes employee and operational data as well as reference tables. Do not execute it to initialize a new environment or copy its records into test fixtures. `DatabaseSeeder.php` also calls `UsersSeeder`, while the repository has `UserSeeder.php`; reconcile the declared class before using any seed workflow. New seeds should contain approved nonpersonal reference values and fictional development data only.

## Core entity map

| Domain | Legacy storage and keys | Import requirement |
| --- | --- | --- |
| Identity/staff | `users.id`, `division_id`, `position_id`, `atms_id`, `user_type`, `employee_type`, archive/termination dates | Map person, account, organization and device identifiers separately; retain leading-zero policy explicitly |
| Organization/pay | `divisions`, `positions`, `salary_grade`, `positions_history` | Use actual table names from schema/model inspection; `positions.salary_grade` references a salary row ID, not simply its displayed grade number |
| Profile | `users_personal`, family/education/eligibility/work/volunteer/L&D/other/declaration/reference/ID tables | Map every child to the correct user; preserve repeatable rows, dates, original unknown values and certificate references |
| Approval history | `statuses`: `(request_type, request_id)`, actor, status, remarks, timestamps, auto-approval marker | Retain chronological order and request type; never join only on numeric request ID |
| Attendance | `dtr_time_log`, `dtr`, `dtr_summary`, remarks/activity tables | Preserve raw evidence separately from derived rows; audit source IDs, daily uniqueness and analyzer version |
| Requests/credits | OB, overtime/date children, `c_t_o_s`, CTO dates, `coc_log`, `leaves`, leave dates/logs/types | Import history and balances before recalculation; retain cancellation/reversal provenance |
| Payroll | `payroll`, `payroll_staff`, `loans`, `payroll_loan` and historic CSW migration changes | Recover persisted inputs/results and classify any computed-only values; historical snapshots require a reviewed reconstruction |
| Recruitment | Category/question/choice, position mapping, application, evaluator, document evaluation, answer/timer tables | Preserve question assignment, answer ownership, attempts/timers and submission status; no invented exam outcomes |
| Development | Position L&D matrix, budgets, division plans, `users_idp`, employee training | Preserve position-vs-person assignment, plan/year and training references |
| Governance | SALN child tables; IPCR outputs/indicators/accomplishments | Preserve year/period, document ownership, totals, ratings and signatures/statuses |
| Employee services | Concerns/attachments; `coe_employed`, `coe_manual`; recognition/votes; WFH records/consolidations | Preserve persisted fields and any archived output. Legacy WFH headers/counts do not guarantee frozen membership, and certificates/reports use live joins; classify reconstructed history separately from actual stored snapshots |
| Portal | `ssis`, `ssi_events`, `ssi_announcements`, `ssi_links`, `wellness_tips`, `bday_file` | Convert comma-separated audiences carefully; migrate assets, publication windows and partner destinations |
| Notifications | `system_notifications`, recipient/audience data, flags and timestamps | Decide how shared read/dismiss flags map to new per-recipient state |
| Reference data | Holidays, `table_1`–`table_4`, minute conversion data, salary tranches | Import approved values and effective dates; classify data as historical vs current policy |
| Documents | `uploads` and module-specific file columns with files under public/storage directories | Reconcile each record to its physical file and target private/public classification |

Use the exact model table overrides and live schema for names. The conceptual map above groups entities; it does not authorize renaming or dropping unlisted fields.

## Known schema hazards

1. `2026_07_07_000001_create_system_notifications_table.php` creates `recipient_email` and its index, and `000002_add_recipient_email_to_system_notifications_table.php` adds both again. A clean replay encounters a duplicate column; the new app needs coherent baseline migrations instead of copying this sequence.
2. Historical CSW payroll tables are created, altered, dropped, and folded into `payroll_staff` in 2026. Inspect real data and migration history before deciding whether any separate table remains in deployment.
3. The user model refers to fields added over many years and includes zero-date assignments in applicant code. Strict database modes can reject legacy dates. Map unknown/zero dates to a reviewed representation and retain the original in the migration audit.
4. Role codes and identifiers have mixed string/integer semantics. `target_users` JSON matches integer user IDs or string role codes; casting blindly changes notification recipients.
5. ATMS IDs are matched inconsistently with leading zeros trimmed in several queries. Capture original and normalized forms and resolve collisions before creating unique constraints.
6. Approval rows can be absent; some legacy pages dereference a missing latest status. Put missing-history records in a reconciliation report rather than fabricating approvals.
7. Relationships sometimes use nonstandard directions/keys. Derive foreign keys from actual data and join logic, not model method names alone.
8. Some model/class references differ in capitalization, such as `Ipcr`/`IPCR`. Resolve on a case-sensitive filesystem before deployment.
9. COC values changed from decimal to integer in a historical migration without a demonstrated hours-to-minutes data conversion. OUT sign/remaining-amount interpretations also differ across code paths. Classify each record's units and semantics; do not blanket-convert values or assume summing signed `credit_amount` yields the available balance.
10. Immediate CTO `OUT` rows coexist with `PENDING_OUT`/`COMPLETED_OUT` processing. Some paths encode a bare request number in remarks and others `CTO-{id}`; a reliable request linkage is not universally persisted. Adjudicate each pending/out row before enabling a deduction job in the target.
11. Leave balance queries use the year of `created_at` in some paths, while grant scope dates can be month-end or December 31. Preserve creation timestamps and `scope_date` separately; replacing creation time with import time changes historical balances.
12. `UserWorkex::save_present` writes `0000-00-02` as an ongoing-job sentinel. This is distinct from an unknown zero date; map it to an explicit ongoing flag/null end date with provenance. Other PDS/IDP code expects textual `Present`, so reconcile the mismatch rather than treating every invalid date alike.

Sources: [migration ledger](inventories/SCHEMA.md), [User](../../app/User.php), [Status](../../app/Status.php), [SalaryGrade](../../app/SalaryGrade.php), [DatabaseSeeder](../../database/seeds/DatabaseSeeder.php), and the module guides.

## Target mapping specification

For every legacy table and file category, create a mapping with these fields before import:

| Field | Required content |
| --- | --- |
| Source | Connection alias, table, primary/natural keys, extraction boundary and schema checksum |
| Target | Table, column and type; retained legacy ID or durable old-to-new mapping |
| Transformation | Trim/case/date/timezone rules, decimal scale, null/zero-date rules, enum/status conversion |
| Relationship | Parent mapping, expected cardinality, required foreign key and orphan handling |
| Retention | Full record/history, approved archive, or approved retirement with reason |
| Validation | Source/target count, uniqueness, nullability, aggregate and checksum checks |
| Rejection | Reason, original source key, remediation owner and retry method |

Do not log full personnel records when reporting failures. A reject manifest can refer to secure source keys and field names; keep any necessary original values in the restricted migration environment.

## Import sequence

1. Take versioned, verified database and file backups and obtain an extraction boundary. Rehearse using an approved isolated dataset with mail, external calls and scheduled writes disabled.
2. Build a fresh target schema and safe reference seeds. Import divisions, salary references, positions, accounts/staff, and identity maps in dependency order; resolve cycles such as references to approvers in a second pass.
3. Import profile/document metadata and records that establish effective employment history.
4. Import requests, child dates, all approval histories, and credit ledger entries. Reconcile initial leave/COC balances, units, original timestamps, and every pending versus immediate CTO deduction before running any generator/deduction job.
5. Import raw time events, daily DTR results, overrides/remarks and summaries. Preserve imported derived results; run the new calculator in comparison mode first.
6. Import payroll, loan and issued certificate snapshots; keep reconstructed and originally persisted values distinguishable.
7. Import recruitment, L&D/IDP, SALN/IPCR, concerns, recognition, WFH accomplishments and consolidations.
8. Import portal/notification configuration and content. Map old shared audience flags only according to the accepted notification decision.
9. Copy files with checksums, rewrite metadata to target keys, and enforce download authorization. Preserve stable public certificate verification identifiers or provide adapters.
10. Run reconciliation and address rejects. No mail, notifications, approvals, accruals or deductions should fire as a side effect of importing historical records.

## Reconciliation checks

- [ ] Counts match by table, year/period, employee, request type and status, with explained approved exclusions.
- [ ] Every child, actor, approver, delegate, evaluator, signer and attachment reference resolves or has an accepted rejection.
- [ ] Email and normalized ATMS collisions are resolved; external identity maps are verified independently for each partner.
- [ ] Latest approval status matches the last ordered history event for the same type and ID.
- [ ] Leave balances match by leave type, employee and both creation/scope years; COC available/remaining/used/expired totals match their accepted units and ledger model.
- [ ] Every pending/completed/immediate CTO deduction has one accepted request linkage and financial effect; no imported pending item will repeat an already applied OUT entry.
- [ ] Raw time-log count, maximum source IDs and per-device/per-day totals match without duplicate ingestion.
- [ ] DTR day results, tardiness/undertime, absences and rendered hours match accepted fixtures or a signed correction report.
- [ ] Payroll gross/deduction/net totals and loan totals match by payroll period and employee using defined rounding.
- [ ] SALN totals, IPCR hierarchy/ratings, training budgets and WFH consolidation memberships match.
- [ ] Every referenced file is found and checksummed; missing/orphan files are reported; private files cannot be downloaded without permission.
- [ ] Applicant attempts/answers and submitted timestamps remain intact; no staff role is accidentally assigned to an applicant.
- [ ] The import is restartable and idempotent; rerunning a chunk creates no additional rows, votes, approvals or credit entries.

## Cutover and rollback requirements

Prefer a controlled write freeze and final delta import unless business owners explicitly require and fund a dual-write design. Snapshot the device-sync cursor, scheduler state and pending external deliveries at the boundary. Exactly one system owns job execution and incoming writes at a time.

Keep a ledger of writes accepted after switching. Rolling back the deployed application alone does not restore data written by the new version. Reconcile those writes through a tested reverse mapping or an agreed recovery procedure before reopening the legacy system. See [Validation and cutover](VALIDATION_AND_CUTOVER.md).
