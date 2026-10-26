# HRIS data migration status

## Result

**Completed with reconciled warnings** on 2026-10-26 10:34:57 (Asia/Manila).

- Source database: `db_hris` (`C:\websites\hris`)
- Target database: `db_hris_v2` (`C:\websites\hris-v2`)
- Target schema: rebuilt successfully from all 17 migrations
- Import execution errors: **0**
- Broken target foreign-key references: **0**
- Automated verification: **195 tests passed, 2 skipped, 2,271 assertions**
- Pre-migration rollback dump: `C:\websites\hris-v2_before_legacy_migration_20261026_102412.sql`

The pre-existing `hris-v2` content was confirmed to be synthetic demo data (`demo.hris.test` accounts and `[Demo]` portal records). It was replaced by the migrated legacy dataset after the rollback dump was created.

## Migrated data

| Area | Source | Target result |
|---|---:|---:|
| Employees | 134 users | 134 users |
| Divisions | 5 | 5 |
| Positions | 97 | 103 |
| Position assignments | 123 legacy user-position values | 94 current assignments; archived duplicates retained in legacy profile snapshots |
| PDS/profile records | 1,965 child rows plus 134 user snapshots | 2,099 PDS entries |
| Raw attendance punches | 550,354 | 550,354 |
| Daily attendance | 49,501 | 49,444 unique employee/date rows |
| Holidays | 577 | 557 unique date/name rows |
| Leave requests | 1,175 | 1,175 |
| Leave dates | 2,114 | 2,092 |
| Leave ledger entries | 7,025 | 7,025 |
| Official business | 961 | 961 |
| Overtime requests | 3 | 3 |
| CTO requests / dates | 289 / 312 | 289 / 312 |
| COC ledger | 400 | 400 |
| Workflow statuses | 6,545 | 6,493 |
| Payrolls / payroll lines / loan lines | 3 / 60 / 31 | 3 / 60 / 31 |
| Salary tables | 144 grade/year rows | 1,152 normalized grade/step rows |
| Training requirements | 2,140 | 2,140 |
| IDP plans | 2 | 2 plans and 2 preserved training items |
| SALN | 17 filings | 17 filings and 35 section entries |
| IPCR | 1 filing | 1 filing, 10 outputs, 15 indicators, 17 accomplishments |
| Employment certificates | 12 | 12 |
| WFH accomplishments / reports | 865 / 70 | 865 / 70, with 464 report lines |
| System notifications | 71 | 71 notifications, audiences, and receipts |
| SSI portal content | 31 applications/links/tips | 31 portal items |
| SSI calendar events | 1,440 | 1,440 calendar events |
| Recruitment reference data | 1 category, 1 question, 1 position/category | 1 category, 1 question, 1 vacancy/category |

Legacy IDs were retained where target constraints allowed. After migration, all 134 account passwords were intentionally reset through Laravel Tinker to the operator-provided shared password. Laravel hashing was applied; no plaintext password is stored in the database. All 134 migrated accounts were also marked email-verified so legacy users do not encounter the verification gate after login; normal verification remains enabled for newly created accounts.

Six active employees shared position records that the new schema requires to be unique per current employee. Six derived position seats were created with traceable `-migrated-user-<id>` codes so those active employees keep their position information. Two active non-applicant users remain without positions because their legacy records also had no position.

## Reconciled warnings and exclusions

- **64,443 raw punches across 34 ATMS identifiers are unmatched to an employee.** The raw evidence was retained with `user_id = NULL`; none had a safe exact or numeric-normalized match in the migrated users.
- **57 duplicate daily-attendance rows** shared the same employee/date key. The newest legacy row was retained for each key, producing 49,444 target days.
- **20 duplicate holiday rows** shared the same date/name key. The newest row was retained for each key.
- **22 leave-date rows** referenced missing legacy leave requests and were not inserted.
- **44 workflow-status rows** referenced missing legacy requests (36 leave, 3 overtime, 2 official-business, 2 CTO, and 1 SPL) and were not inserted.
- **8 upload workflow statuses** were not inserted because the rebuilt schema has no upload-request workflow entity.
- Binary/document migration was outside this database-only pass. Database rows in `bday_file` (389), `uploads` (4), and `data_sheets` (1) were not converted into `private_files`, because the target requires an existing file, size, MIME type, checksum, owner, and authorization metadata.
- Legacy calculation/reference rows in `table_1` through `table_4`, `leave_credit_minute_bases`, and 11 `users_ld_matrix_budget` rows have no equivalent target tables. They were not copied. Operational formulas remain governed by the rebuilt application's code and should be reviewed before payroll/leave cutover.
- Archived users that shared a position retain their original `position_id` inside the `legacy_profile` PDS snapshot, while their target current-position relation is null to satisfy the target's one-current-occupant constraint.

## Transformations applied

- Legacy user types were mapped to `staff`, `division_chief`, `hr`, `executive_director`, `chief_admin_officer`, `system_admin`, or `applicant` roles.
- PDS child tables were stored as structured JSON entries, with a complete legacy-profile snapshot per user.
- COC values were retained as minutes based on the live legacy values; no hours multiplier was applied to the COC ledger. CTO request/date hour values were converted to minutes.
- Salary-grade year rows were expanded into one row per grade and step, with effective dates derived from the legacy tranche year.
- Zero legacy dates were replaced only where the target disallows them, using the record timestamp or a documented fallback.
- Notifications were separated into target notification, audience, and receipt rows.
- WFH report membership was reconstructed from matching division/date accomplishments.

## Validation performed

- All 17 target migrations report `Ran`.
- Import report contains zero SQL errors.
- A complete foreign-key orphan scan reports zero broken references.
- Source/target counts were reconciled for all populated mapped modules.
- `MigrationTest`, `PortalTest`, `AttendanceTest`, `WorkforceRequestsTest`, and `PdsTest` passed: 40 tests and 307 assertions.

## Post-migration configuration

- `john.baluyo@psrti.gov.ph` was assigned the `system_admin` role and remains active and eligible.
- The legacy PSRTI logo, SSI login background, eight application logos, and birthday celebration GIF were copied into the rebuilt public assets and applied to the corresponding screens.
- The legacy split-panel login presentation was adapted to the rebuilt authentication flow.
- Shared asynchronous tables now keep detected date/time columns on one line and size them to their values, with horizontal scrolling retained on narrow screens.
- Attendance printing now uses the legacy `print_my_dtr` CS Form No. 48 layout, including the ten-column daily grid, six-week statistics, certification, and signature lines, backed by the rebuilt attendance, leave, CTO, official-business, and holiday records.
- UI verification after these changes: production frontend build succeeded, Blade templates compiled, and 46 targeted feature tests passed with 300 assertions.
- DTR print verification: Blade compilation succeeded and all 18 attendance feature tests passed with 162 assertions.
- Final application verification: 195 tests passed with 2,271 assertions; 2 environment-dependent tests were skipped.

## Legacy report migration

**Result: SUCCESS (0 generation errors).** The printable, downloadable, and spreadsheet outputs exposed by the legacy application were traced to their controllers, routes, and Blade/Excel templates and reproduced against the normalized `hris-v2` records.

| Legacy output | `hris-v2` result |
|---|---|
| CS Form No. 48 individual DTR | Print and Excel-compatible download use the legacy daily grid, six-week statistics, certification, and signatures |
| Monthly DTR monitoring/export | Legacy 23-column monthly attendance workbook, leave balances/usages, tardiness, undertime, absence, CTO, medical OB, flag-ceremony absence, and remarks |
| Official Business | FAD Form No. 01 duplicate printable form and certificate-of-appearance section |
| Leave application | Civil Service Form No. 6 (Revised 2020), including balances, recommendation, and approval sections |
| Overtime authority | FAD Form No. 03 authority-to-enter printable form |
| CTO application | Duplicate compensatory-time-off printable form with COC/balance fields |
| Regular payroll | Legacy payroll Excel-compatible export and two-payslips-per-landscape-page print layout |
| CSW payroll | Payroll Excel-compatible export and per-employee Form No. 03 disbursement-voucher export |
| PDS | Four-page CS Form No. 212 (Revised 2017) populated from migrated legacy section aliases |
| SALN | Two-page legal-landscape SALN, with migrated singular/plural section aliases included in totals |
| WFH accomplishment | Division and agency consolidated accomplishment print layouts with captured signatories |
| Learning and development | Position matrix, divisional L&D plan, and FAD-LDP-001-01 individual development plan |
| IPCR | Individual Performance Commitment, Review, and Monitoring Form with outputs, indicators, accomplishments, Q/E/T/A ratings, and signatures |
| Certificate of Employment | Legacy letterhead, compensation/benefits schedule, purpose, signer, QMS/footer, and verification reference |
| Recruitment examination | Legacy applicant/position/category/question/answer report, including question and answer images |
| Recognition attendance | Legacy Attendance Monitoring columns for VL, SL, other leaves, absences, late, undertime, and total tardiness |

Report verification completed on 2026-10-26: all Blade templates compiled, the production frontend build succeeded, the report-related feature suite passed, and the complete application suite passed with 195 tests, 2 skipped tests, and 2,271 assertions. The two skipped tests are environment-dependent and are not report-generation failures.

### DTR monthly and bulk-print follow-up

- The DTR Blade markup was refreshed directly from the legacy `dtr/multi_dtr_report` design: original heading and spacing, employee details, ten-column bordered daily table, six-week statistics table, certification statement, and three signature blocks.
- Individual DTR printing now prompts for a month and year and always normalizes the report period to the first through the last calendar day of that month.
- The DTR print stylesheet uses A4 portrait at an 80% content scale, keeps one employee/month per page, and places each employee on a separate page during bulk printing.
- HR users can select multiple employees and open their DTRs in one bulk printable document, following the legacy multi-DTR workflow.
- Monthly attendance review is hidden from and forbidden to `staff` users.
- Division chiefs are forced to their assigned division, cannot change the division filter, and cannot correct, rebuild, or edit any employee DTR—even if an attendance-correction grant is added directly.

## Recommended cutover follow-up

1. Resolve the 34 unmatched ATMS identifiers through the explicit attendance identity reconciliation workflow.
2. Replace the temporary shared password with individual credentials before production use.
3. Review the 44 orphan workflow histories and 22 orphan leave dates against business records before declaring historical workflow reconciliation complete.
4. Run a separate controlled document migration if the legacy uploaded files are required in `hris-v2`.
5. Have HR/payroll owners sign off on COC/leave balances and payroll totals before enabling production writes.
