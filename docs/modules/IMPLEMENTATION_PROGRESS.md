# Modules 01–10 implementation progress

## Documentation analysis

- [x] Read `CODE_AGENT_MODULES_01_05.md` and all five guides in `docs/rebuild/modules/`.
- [x] Read supporting architecture, decisions, schema inventory and integration contracts. The requested `docs/modules/01*` files do not exist; the rebuild guides are the requirements source. No missing legacy source is used.
- [x] Identify shared identity, organization, private files, audit, tables, dates and effective assignments.
- [x] User delegated implementation decisions (“do what's best”). Choices below are implementation defaults, not claims of approved HR policy.
- [x] Final requirement-by-requirement review recorded in the 94-row register; unresolved requirements remain unchecked.

## Decisions

- Existing Laravel 13/PHP 8.4 application; retain Fortify authentication, recovery and MFA. New module UI uses Bootstrap 5.3.8, Blade and Fetch; ten-row server pagination. No additional frontend framework.
- Named roles with explicit abilities. HR administers staff/PDS/attendance; system administrators administer accounts/organization/content without automatically receiving confidential HR rights. Temporary delegation augments rather than replaces the employee's permanent role: a chief may act as Director OIC, or a staff member may act as Division Chief OIC for their own division, during an inclusive date range. No legacy numeric administrator mapping is assumed.
- Invitation/reset onboarding; public registration closed. Archived accounts and employees terminated on/before today cannot authenticate or continue existing sessions. Applicant access is limited to account settings pending recruitment (module 11).
- All timestamps and calendar dates use Asia/Manila. Archive and termination are independent. Attendance IDs normalize leading zeros and reject all-zero values.
- Position changes are transactional with old/new assignment, actor and assumption date. Per the user's 22 October 2026 decision, every position is exclusive to one employee, including nonplantilla positions; a unique database index enforces this rule. Archiving or terminating an employee preserves their assignment. Effective delegations cannot overlap for the same employee/division/ability.
- PDS uses validated section payloads, append-only snapshot sections and repeatable rows; preserve version/audit history. Blank optional fields clear; numeric zero survives. Print output is a complete data review, **not a certified government form**: authoritative edition/labels are absent (D16).
- Attendance calculator `office-v1` uses documented office/WFH arrival/exit rules and 4/5-day schedules; worked minutes honor explicit lunch gaps, clamp at the selected office/WFH exit, and truncate seconds. This resolves the documented conflicting 18:00/20:00/21:00 calculators explicitly; no parity with every historical branch is claimed. Raw logs remain immutable; incomplete days require review. Recalculation replaces derived values while retaining revisions; manual overrides require explicit replacement. No inferred overnight or historic financial policy.
- Partner login remains disabled until a partner approves a supported protocol (D04). Ordinary validated application links do not receive credentials. Sync uses explicitly configured partners/credentials only, queued with auditable runs and optional documented 405 compatibility.
- Service credentials are hashed and scoped separately from human sessions. Collector events are unique by client/source epoch/external ID, bounded and atomic. Unknown attendance IDs remain raw evidence for reconciliation.

## Requirement → implementation → verification

### 01 Authentication and authorization

- [x] Fortify generic errors, throttling, session rotation, recovery, MFA, logout; eligibility and registration endpoint tests.
- [x] Named abilities, action/record policies and effective division-attendance delegation; cross-user/division/direct-endpoint tests. Approval delegation is reserved for modules 06?09.
- [x] Audited invitation and account changes; no fixed password or mass reset.
- [ ] Partner login/replay/expiry integration (pending D04 partner contract).
- [ ] Applicant recruitment destination (module 11 dependency).

### 02 Organization and staff

- [x] Database/models: employees/accounts, divisions, positions, assignments, delegations, private files and audits.
- [x] Validated transactional staff CRUD, normalized identity uniqueness, occupancy, archive/reactivate, nullable clearing and assignment history.
- [x] Profile/signature/personnel uploads, authorized downloads, metadata and missing-file handling.
- [x] Queued configured SSI sync, run status, fake HTTP success/405/error/timeout tests.
- [x] Responsive asynchronous staff/organization/history/delegation UI.
- [ ] Legacy data/file migration and checksums reconciliation (source dataset unavailable).

### 03 Profile and PDS

- [x] Eleven structured PDS sections, account settings and position history; synchronization, ownership, snapshots/repeatable rows, validation, clearing/zero values. Government declaration wording remains pending D16.
- [x] Private certificates/IDs: replace/retain/remove, audit and missing-file recovery.
- [x] Current-section data-review print with complete rows, education grouping, date ordering and print continuation CSS; this is not the approved government layout.
- [ ] Approved government PDS form edition and declaration labels (D16).
- [ ] Training matrix suggestions (module 12 dependency).
- [ ] Migration of source sections/sentinel representations (source dataset unavailable).

### 04 SSI portal and calendars

- [x] Employee landing, active applications/links, current wellness, announcements, own recent punches and WFH availability.
- [x] Content CRUD, validated URLs, private assets/attachments and editor policies.
- [x] Office/ED/personal calendars, normalized participants, inclusive stored dates/exclusive feed end, Manila timestamps and private detail scope.
- [x] Participant mail after commit with retry/deduplication.
- [ ] Approved-request calendar integration and persisted notifications (modules 06/08/09/20).
- [x] Birthday names use Manila month/day, documented employee types, nonarchived employees and no termination date. No age/birth year is disclosed.
- [ ] Loyalty/regularization recognition criteria and birthday media administration await business rules (D11/D13).
- [ ] Partner SSO/DTS acceptance with partner owners (D04).

### 05 Attendance

- [x] Raw log ingestion/checkpoint: bounded validation, client scopes, normalized identities, replay/conflict/atomicity and accurate counts.
- [x] Own session-derived web punches, WFH gate and identity tampering protection.
- [x] Effective schedules, deterministic aggregation/minute calculations, revisions, late arrivals, incomplete days, overrides and monthly remarks.
- [x] Ten-row raw/DTR/history views, summary and individual/monthly CSV/print using the same stored calculation.
- [x] Locked retry-safe command and no mutating GET jobs.
- [ ] Historical policy, holiday/approved-request precedence, leave-ledger reversals and financial postings (D05/D06/D07 and modules 06–09/21).
- [ ] Approved best-attendance criteria and legacy reconciliation fixtures (not supplied).

## Shared checks

- [x] Migrations up/down on isolated database; model integrity and indexes.
- [x] Tests for each implemented critical workflow and unauthorized access.
- [x] PHP syntax exercised by tests/view compilation, Pint, PHPStan level 7, frontend build and framework route/view checks.
- [x] Keyup debounce, 10-row pagination, async CRUD, state preservation/page correction, loading/empty/error/retry handling.
- [x] Bootstrap responsive layout, keyboard access and persistent/system light/dark theme.
- [x] Headless Chrome browser QA at 390px and 1440px; screenshot inspection; light/dark theme, mobile navigation, PDS, async staff/calendar workflows and table error/retry checks.

## Known issues / external dependencies

The source docs contain open business decisions and links to absent legacy files. No production data, government form sample, partner service, approved historical attendance fixture, salary table or other-module request/ledger implementation is available. These cannot be accepted based on synthetic tests. Deployment and GitHub push are outside this implementation request.

## Verification log

See the verification record and requirement register below. Full acceptance remains pending for explicitly listed external contracts, official forms and other-module dependencies.


## Implementation status and files

| Module | Status against full guide | Implemented code | Verification |
| --- | --- | --- | --- |
| 01 | Core complete; full acceptance incomplete for partner SSO and recruitment | `User`, `EmployeePolicy`, `EnsureAccountEligible`, `FortifyServiceProvider`, generic recovery responses, account invitation/bootstrap/grant commands | Existing Auth/Settings tests; `OrganizationTest`, `IntegrationReviewTest` |
| 02 | New-application core complete; approval/HRDC extensions remain with their dependent modules | `StaffController`, `SaveStaffRequest`, `StaffService`, organization/assignment/delegation models, `PrivateFileController`, sync job/run models | `OrganizationTest`, `PdsTest`, `PortalTest`, staff browser workflow |
| 03 | Functional data entry complete; full acceptance incomplete for certified form and training matrix | `config/pds.php`, `SavePdsRequest`, `PdsController`, `PdsEntry`, private files, PDS data-review print | `PdsTest`, Settings tests, mobile PDS browser workflow |
| 04 | Portal/content/calendar core complete; full acceptance incomplete for partner sign-in, recognition and request/notification feeds | `PortalController`, `CalendarController`, `CalendarEventPolicy`, `SendEventMail`, portal/event models | `PortalTest`, desktop/mobile and calendar browser workflows |
| 05 | Capture/calculation/correction/reporting core complete; full acceptance incomplete for historical exemptions, financial ledgers and award criteria | `DeviceAttendanceController`, `AttendanceController`, `AttendanceReviewController`, calculator/service/scope, raw/daily/schedule/revision models, analyzer/reconciliation commands | `AttendanceTest`, `AttendanceCalculatorTest`, `IntegrationReviewTest`, responsive attendance browser checks |

All application paths above are under `app/`; views are under `resources/views/hris/`, shared shell/components under `resources/views/layouts/` and `resources/views/components/hris/`. Shared asynchronous tables/forms and themes live in `resources/js/hris.js` and `resources/css/hris.css`. Routes are explicit in `routes/hris.php` and `routes/api.php`. Three additive migrations create the schema and pin raw evidence to employee identity. Tests use fictional people and isolated SQLite databases.

### Scope and deviations from the old rebuild guides

- The newer implementation specification explicitly says the legacy application is unavailable and irrelevant. Source migration, legacy route preservation, deployed-schema comparison and source-data reconciliation are therefore **not applicable to this new-build task**, not prerequisites to recreating missing source. Their old checklist entries remain unchecked for clarity; no source data was fabricated.
- Role values are named, not legacy numeric codes. No payroll/final business approval abilities are implied by administration.
- Personal/account data uses validated JSON section payloads in one indexed PDS model, with append-only snapshots and audited repeatable entries. This replaces duplicate section-specific plumbing while preserving the documented cardinalities and fields.
- Employee self-deletion is disabled, and provisioned employee names change through PDS rather than account settings. Existing applicant deletion remains subject to framework validation/constraints.
- New module and authentication screens use Bootstrap. Existing Livewire/Flux account-security/MFA/passkey-management screens are retained to preserve their tested security behavior; their original appearance control is separate from the new module theme toggle.
- WFH availability defaults to agencywide inclusive dated WFH events. Personal and executive event details remain scoped. Unknown device IDs are quarantined. Pinning a raw punch to its captured employee prevents later ID reuse from exposing another employee's historical punches.
- There is no guessed award formula, historical exception table, leave debit, holiday/OB/CTO precedence or partner SSO bridge. These require the missing domain inputs/other modules. `office-v1` results are versioned and reviewable and must not be treated as financial approval.
- Browser tables use Fetch and native Bootstrap components. DataTables and additional reactive frameworks were unnecessary.
- The existing redundant Vite Plus dependency was removed after its audit findings; standard Vite builds the same assets. npm audit reports zero vulnerabilities.
- Normal queued mail retries use persistent delivery deduplication. Exactly-once SMTP delivery across a process crash after send/before commit cannot be guaranteed without an idempotent provider; no live mail-provider acceptance is claimed.
- MySQL schema migrations were applied locally; SQLite up/down is tested automatically. Simultaneous multi-process MySQL contention and live partner behavior have not been exercised.

## Operating the new modules

The developer's `.env` was preserved. All three migrations have been applied to the existing **local** environment. No default HR/admin credential or fictional browser account was added to that database.

Provision an administrator using a real operator email, then grant HR access separately when authorized:

```text
php artisan hris:create-administrator operator@example.com --name="System Administrator"
php artisan hris:grant operator@example.com staff.manage
php artisan queue:work --tries=3
```

The setup email uses Laravel's password broker. Verify the email before entering protected modules. The account starts with system administration abilities; confidential HR rights require an HR role or explicit grants. Use the staff editor to invite HR employees. Configure mail and a queue worker; no password is placed in source, output or documentation.

Configure optional SSI synchronization only with a registered HTTPS partner URL and dedicated token via the placeholder keys in `.env.example`. `HRIS_SSI_GET_FALLBACK` is false by default. The adapter never sends an invented employee array and never forwards credentials to arbitrary portal URLs. Queued/running/succeeded/failed are technical job states, not added business workflow statuses. Queue middleware serializes sync; a completed/uncertain remote mutation is not blindly repeated.

Collector and maintenance commands:

```text
php artisan attendance:client COLLECTOR --device=READER-01
php artisan attendance:client COLLECTOR --revoke
php artisan attendance:rebuild --from=2026-10-01 --to=2026-10-21 --employee=EMPLOYEE_ID
php artisan attendance:match-identity EMPLOYEE_ID ATMS_ID --from=2026-10-01 --to=2026-10-21 --reason="Verified identity mapping"
php artisan hris:check-files --checksums
```

The collector command displays a newly generated credential once; store it in the collector's secret manager. Rotation replaces the old credential. Requests require the `attendance.ingest` scope and a registered device. POST batches are atomic, limited to 1,000 records, and accept an optional `source_epoch` for device/source resets. They return actual received/inserted/duplicate/unmatched counts. Checkpoints are per client/epoch and never include web entries. Unknown identities are not exposed as any employee's records until explicit reconciliation. Run the scheduler for the locked nightly seven-day recalculation, and use explicit date ranges for older late evidence. Analyzer runs never post to credits/payroll and preserve manual overrides.

Browser checks run with `npm run build` then `npm run test:browser`. The harness creates only `storage/framework/testing/browser-test.sqlite` and synthetic accounts, uses an installed Chrome on this Windows workstation, and shuts down its own server. On another host set `PHP_BINARY`/`CHROME_PATH` or install Playwright Chromium. GitHub Actions now includes the browser checks; the remote CI run has not been executed in this task.


## Source checklist traceability register

Each row is copied from a module build/acceptance checklist. `Done` means the implemented new-build portion has executable verification in the cited suite. `Partial` and `Pending` remain unchecked; the status/deviations above explain the remaining scope. `N/A` is legacy-only work superseded by the user?s new-application specification. Concurrency acceptance remains partial until multi-process MySQL tests are run.

### 01 checklist mapping

Implementation: `app/Providers/FortifyServiceProvider.php`; `app/Models/User.php`; `app/Policies/EmployeePolicy.php`; `app/Http/Middleware/EnsureAccountEligible.php`. Verification: Auth/Settings suites; OrganizationTest; IntegrationReviewTest; AttendanceTest.

| Requirement | Status | Requirement ? implementation / verification |
| --- | --- | --- |
| 01.B01 | [ ] Pending ? domain/partner/form dependency | Resolve D02/D03/D04 and specify account eligibility, applicant onboarding, role mapping, partner login and reset policy. |
| 01.B02 | [x] Done | Implement one primary login flow with throttling, generic credential errors, session regeneration and secure cookies. |
| 01.B03 | [x] Done | Implement logout with session invalidation and CSRF token regeneration; retire ambiguous GET logout behavior deliberately. |
| 01.B04 | [x] Done | Implement account invitation/reset, password confirmation where needed, and explicit email verification policy. |
| 01.B05 | [x] Done | Protect role, division, pay and signature attributes from self-service mass assignment. |
| 01.B06 | [ ] Partial ? see scope/decisions | Add policies for every module, scoped record queries and effective-dated delegation rules. |
| 01.B07 | [x] Done | Map or retire generated registration/verification routes explicitly; close unintended registration paths. |
| 01.B08 | [x] Done | Implement audited account creation, role changes, archive/termination eligibility and secure password setup. |
| 01.B09 | [ ] Pending ? domain/partner/form dependency | Implement partner login through the accepted integration adapter; reject bad/unknown/expired/replayed credentials and unsafe destinations. |
| 01.B10 | [x] Done | Replace shared broad API credentials with approved service identities/scopes and rotation procedures. |
| 01.B11 | [x] Done | Remove fixed-password/mass-reset utilities and move guest attendance compatibility routes to their reviewed service boundary. |
| 01.A01 | [ ] Partial ? see scope/decisions | Applicants and employees reach the correct destination; intended destinations cannot bypass access policies. |
| 01.A02 | [x] Done | Repeated failures are throttled and error responses do not reveal account existence or database exceptions. |
| 01.A03 | [x] Done | Sessions rotate on login, invalidate on logout, and cannot be reused after required revocation. |
| 01.A04 | [x] Done | Archived/terminated users follow the accepted account-access policy; role changes take effect without a stale authorization cache. |
| 01.A05 | [x] Done | Staff cannot change their own role, edit another account or obtain a foreign record through a guessed ID. |
| 01.A06 | [ ] Partial ? see scope/decisions | Division/OIC access works only for the accepted organization and effective dates; moving a person does not rewrite historical approval authority. |
| 01.A07 | [x] Done | Direct POST registration follows the chosen onboarding policy even when its UI link is hidden. |
| 01.A08 | [ ] Pending ? domain/partner/form dependency | Partner login tests cover query/base-path encoding, unknown email, tampering, expiry/replay and blocked redirects. |
| 01.A09 | [x] Done | Service tokens authorize only their intended endpoints; revocation and secret rotation are demonstrated. |

### 02 checklist mapping

Implementation: `app/Services/StaffService.php`; `app/Http/Controllers/StaffController.php`; `app/Http/Controllers/PrivateFileController.php`; `app/Jobs/SyncPartnerUsers.php`. Verification: OrganizationTest; PdsTest; PortalTest; staff browser workflow.

| Requirement | Status | Requirement ? implementation / verification |
| --- | --- | --- |
| 02.B01 | [x] Done | Create employees, accounts, divisions, positions, dated assignments, and temporary delegations with explicit relationships and legacy-ID mapping. |
| 02.B02 | [x] Done | Preserve employee types, original identifiers, archive and termination independently; define date normalization for zero/invalid dates. |
| 02.B03 | [x] Done | Add validated create/update requests and enforce unique email/attendance identity where approved; implement transactional position occupancy checks. |
| 02.B04 | [x] Done | Create staff search/filter, detailed editor, archive/reactivate flow, and position occupancy warnings. |
| 02.B05 | [x] Done | Store assignment history atomically with account changes, including actor and effective date; avoid appending identical snapshots accidentally. |
| 02.B06 | [x] Done | Implement secure profile/signature and personnel-file storage with generated object names and authorized downloads. |
| 02.B07 | [ ] Partial ? see scope/decisions | Add OIC date validation, overlapping-assignment rules, permission checks, and an auditable HRDC assignment flow if retained. |
| 02.B08 | [x] Done | Queue integration sync with timeout, status reporting, credential configuration, retry policy, and a fakeable HTTP adapter. |
| 02.B09 | [ ] N/A ? new build supersedes legacy migration | Migrate attachment metadata and files with checksums/missing-file reports; do not copy personal data into repository fixtures. |
| 02.A01 | [x] Done | HR can create and edit permitted employee fields; another employee cannot access staff-admin APIs by posting directly. |
| 02.A02 | [x] Done | Clearing a termination date is persisted and reflected across directory/approval eligibility. |
| 02.A03 | [x] Done | A real position change creates exactly one dated history event containing the old and new assignments. |
| 02.A04 | [ ] Partial ? see scope/decisions | Duplicate email/attendance identity and conflicting exclusive incumbency fail at save time, including concurrent requests. |
| 02.A05 | [x] Done | Archive preserves historical payroll, DTR, PDS, and certificate references. |
| 02.A06 | [ ] Partial ? see scope/decisions | OIC starts/ends on the agreed inclusive dates; invalid ranges and unauthorized assignments fail. |
| 02.A07 | [x] Done | Files retain original display names without collision or public disclosure; absent files produce a repair report. |
| 02.A08 | [x] Done | SSI success, HTTP 405 fallback, remote rejection, and timeout are exercised with fake responses. |

### 03 checklist mapping

Implementation: `config/pds.php`; `app/Http/Requests/SavePdsRequest.php`; `app/Http/Controllers/PdsController.php`; `app/Http/Controllers/PrivateFileController.php`. Verification: PdsTest; Settings suite; mobile PDS browser workflow.

| Requirement | Status | Requirement ? implementation / verification |
| --- | --- | --- |
| 03.B01 | [x] Done | Model each section with clear one-current-version versus repeatable-row semantics and legacy-ID maps. |
| 03.B02 | [ ] Partial ? see scope/decisions | Implement validated forms for every row/section in the data table, including declaration explanations and address fields. |
| 03.B03 | [x] Done | Preserve current personal/account synchronization using a transaction and an explicit field allowlist. |
| 03.B04 | [x] Done | Implement ownership policies on every read/update/delete and HR PDS export. |
| 03.B05 | [x] Done | Normalize dates, textual `Present`, and the confirmed ongoing-job `0000-00-02` sentinel without losing original values; preserve decimal salary/hours as appropriate. |
| 03.B06 | [x] Done | Store certificate/ID images privately with file metadata; support replace, retain, remove, authorized preview, and missing-file recovery. |
| 03.B07 | [ ] Pending ? domain/partner/form dependency | Build completed-training and suggested-matrix views linked to module 12. |
| 03.B08 | [ ] Partial ? see scope/decisions | Recreate the complete printable PDS with section ordering, empty-state handling, overflow/continuation pages, and approved form version. |
| 03.B09 | [x] Done | Add version history/audit events where a snapshot changes and prevent an employee from changing payroll/role fields through personal forms. |
| 03.B10 | [ ] N/A ? new build supersedes legacy migration | Migrate all section tables and attachment paths; reconcile latest snapshot and user-record name/contact conflicts. |
| 03.A01 | [x] Done | A synthetic employee can create, edit, clear, and reload every PDS section and repeatable row. |
| 03.A02 | [x] Done | Empty optional fields and zero values persist as specified; a first government-ID save succeeds with the agreed file requirement. |
| 03.A03 | [x] Done | Another employee's child IDs and PDS export return denied/not-found responses. |
| 03.A04 | [ ] Partial ? see scope/decisions | Both textual `Present` and the confirmed `0000-00-02` ongoing-job sentinel migrate to the same intended ongoing-work behavior; unknown invalid dates remain distinguishable. Incomplete education years, long text, and many training entries print without lost rows. |
| 03.A05 | [ ] Partial ? see scope/decisions | The printed PDS uses the current approved snapshot of personal/family/declarations/references/ID and all current repeatable entries. |
| 03.A06 | [x] Done | Certificate replacement preserves the old file until the new upload and database update succeed. |
| 03.A07 | [x] Done | Changes to current personal name/contact values propagate once to the employee account and retain audit history. |
| 03.A08 | [ ] Pending ? domain/partner/form dependency | Training matrix changes retain historical completions and show unmatched completions clearly. |

### 04 checklist mapping

Implementation: `app/Http/Controllers/PortalController.php`; `app/Http/Controllers/CalendarController.php`; `app/Policies/CalendarEventPolicy.php`; `app/Jobs/SendEventMail.php`. Verification: PortalTest; calendar and responsive browser workflows.

| Requirement | Status | Requirement ? implementation / verification |
| --- | --- | --- |
| 04.B01 | [x] Done | Implement authenticated landing page and policy-driven module/app/content visibility. |
| 04.B02 | [x] Done | Build app/link/wellness/announcement administration with URL validation, safe asset processing and authorized file retrieval. |
| 04.B03 | [ ] Partial ? see scope/decisions | Implement event create/edit/delete, participant selection, private personal calendar, office/ED feeds and scoped request details. |
| 04.B04 | [x] Done | Preserve inclusive stored all-day dates and exclusive feed end dates with explicit timezone handling. |
| 04.B05 | [x] Done | Queue participant mail after commit, with retries and no duplicate sends on retry/import. |
| 04.B06 | [ ] Partial ? see scope/decisions | Reproduce birthday, wellness, recognition and recent-time panels from approved eligibility/date rules. |
| 04.B07 | [ ] Partial ? see scope/decisions | Integrate approved WFH availability and notification audiences. |
| 04.B08 | [x] Done | Replace hardcoded editor account lists with roles/delegations and protect server actions. |
| 04.B09 | [ ] Pending ? domain/partner/form dependency | Implement configured partner launch adapters and stable document deep links; retire unsupported template protocols deliberately. |
| 04.B10 | [ ] N/A ? new build supersedes legacy migration | Migrate participant lists, assets and references; remove diagnostic routes from production. |
| 04.A01 | [x] Done | Inactive applications/links and expired/future wellness content are hidden according to approved rules. |
| 04.A02 | [x] Done | Noneditors cannot call content administration directly; users cannot read/delete another personal event. |
| 04.A03 | [x] Done | All-day one-day and multi-day events display the correct last day; timed events retain Manila time. |
| 04.A04 | [ ] Partial ? see scope/decisions | Office, ED and personal calendars contain the correct event/request subsets, including cancelled/amended requests. |
| 04.A05 | [x] Done | Participant notifications include the accepted dates/recipients and retry without duplicate content. |
| 04.A06 | [ ] Partial ? see scope/decisions | WFH availability respects accepted event/date scope and does not leak private employee request details. |
| 04.A07 | [x] Done | Leading-zero ATMS IDs do not hide recent time-in data. |
| 04.A08 | [ ] Partial ? see scope/decisions | External links handle ports, application subpaths, query strings and fragments safely under the agreed partner protocol. |
| 04.A09 | [x] Done | Files, logos and filenames cannot overwrite another asset or expose private documents. |

### 05 checklist mapping

Implementation: `app/Services/Attendance/`; `app/Http/Controllers/AttendanceController.php`; `app/Http/Controllers/DeviceAttendanceController.php`; `app/Http/Controllers/AttendanceReviewController.php`. Verification: AttendanceTest; AttendanceCalculatorTest; IntegrationReviewTest; MigrationTest; responsive browser workflow.

| Requirement | Status | Requirement ? implementation / verification |
| --- | --- | --- |
| 05.B01 | [ ] N/A ? new build supersedes legacy migration | Inventory all attendance routes and classify active, broken, administrative and scheduled use; implement policies for each. |
| 05.B02 | [x] Done | Preserve raw logs and external IDs; make ingest replay-safe and normalize ATMS mapping without collisions. |
| 05.B03 | [ ] N/A ? new build supersedes legacy migration | Import DTRs and corrections with a mapping to original records; reconcile duplicate employee/date rows before adding a uniqueness constraint. |
| 05.B04 | [ ] Partial ? see scope/decisions | Create an attendance calculation service with typed minute units, effective-dated work schedules and policy versions; obtain decisions for the divergences above. |
| 05.B05 | [x] Done | Preserve a historical result snapshot and add a reviewed recalculation workflow that records before/after results. |
| 05.B06 | [x] Done | Implement raw-log aggregation, mixed WFH handling, incomplete-punch review, override reason/audit and monthly remarks. |
| 05.B07 | [ ] Pending ? domain/partner/form dependency | Define deterministic exemption precedence using latest scoped status and affected employee membership. |
| 05.B08 | [x] Done | Consolidate schedule commands into monitored, locked, retry-safe jobs; replace state-changing HTTP GET jobs with controlled execution. |
| 05.B09 | [ ] Pending ? domain/partner/form dependency | Link attendance ledger debits to employee/date/type/calculation version; reverse prior debit before applying a corrected result. |
| 05.B10 | [ ] Partial ? see scope/decisions | Rebuild all attendance outputs using a shared calculation result and explicitly approved report scope. |
| 05.B11 | [ ] N/A ? new build supersedes legacy migration | Compare imported monthly totals, tardiness, debit ledger totals and sample printed forms against sanitized legacy fixtures. |
| 05.A01 | [x] Done | Employees cannot read/clock/correct another employee through submitted IDs; HR/division report scope is enforced server-side. |
| 05.A02 | [ ] Partial ? see scope/decisions | Replayed device batches and concurrent analyzers do not duplicate raw logs or daily rows. |
| 05.A03 | [x] Done | One punch, morning-only, afternoon-only, missing lunch, multiple lunch punches, mixed devices and late-arriving punches yield reviewed deterministic results. |
| 05.A04 | [x] Done | Office and WFH schedules cover 07:00/09:00/noon/13:00 boundary values, 8/10-hour days, early/late exits and suspension before/after lunch. |
| 05.A05 | [ ] Pending ? domain/partner/form dependency | Historical exception dates, Friday holidays, scoped special orders, cancelled requests and overlapping approvals are covered by approved fixtures. |
| 05.A06 | [ ] Partial ? see scope/decisions | Month/year boundaries, leap day, hire/termination dates and reporting with no DTRs are correct. |
| 05.A07 | [ ] Partial ? see scope/decisions | Running each scheduled job twice, concurrently, or after partial failure produces one net financial/leave effect. |
| 05.A08 | [ ] Pending ? domain/partner/form dependency | Reversal/recalculation adjusts only linked deductions and reports the before/after difference. |
| 05.A09 | [ ] Partial ? see scope/decisions | Screen, individual form, monthly report, summary and ledger agree for the same policy version and period. |


## Executed verification ? 21 October 2026, Asia/Manila

- [x] PHP 8.4.26 runtime verified; Laravel 13 application routes loaded.
- [x] Backend suite: **100 tests, 98 passed, 2 skipped, 453 assertions**. The skipped starter tests require public registration, which is deliberately disabled; new tests independently prove both GET and POST registration are closed.
- [x] PHPUnit covers login eligibility/throttling/generic errors/session rotation/logout/recovery, staff permissions and transactional assignments, normalized uniqueness and occupancy, archive/reactivate, duplicate personal snapshot prevention, section/child ownership, PDS CRUD and optional/zero values, private replacement/download/image-preview/missing-file behavior, calendar privacy/dates, birthdays, queued adapters with fake responses, collector scopes/replay/conflicts/501-row counts, immutable employee association, explicit identity reconciliation, effective schedules/mixed devices, calculator boundaries/lunch/suspension/OT, corrections/revisions/idempotency, monthly scope/exports, and migration up/down.
- [x] Browser suite: **4 passed**, using installed headless Chrome. Verifies ten-row pages, debounced keyup search, async create/update/archive/delete, inline validation, retained page shell, retry/empty states, mobile navigation/no page overflow, persistent light/dark theme, PDS children, inclusive calendar date/time round trips and retained participant selections. Employee pickers also use ten-row server pagination and expose only IDs/names.
- [x] PHPStan level 7: **0 errors**, without ignores or a baseline.
- [x] Repository Pint style check: passed.
- [x] Standard Vite production build: passed. The existing optional font-fallback optimization notice does not prevent building.
- [x] npm audit: **0 vulnerabilities** after removing redundant Vite Plus.
- [x] Blade view compilation and route checks passed.
- [x] All three additive migrations applied to the existing local MySQL connection. SQLite rollback/reapply passed on the isolated test database. No destructive migration reset was run against the developer database.
- [x] Read-only private-file checksum report: **0 repair issues** in the local database. Synthetic missing-file behavior is covered separately in tests.
- [x] Tracked diff whitespace check passed. The developer's `.env` has no change.
- [x] Inspected generated desktop/mobile screenshots; subsequent browser checks also cover the loaded tables and alternate theme. Artifacts stay under ignored `storage/framework/testing/`.
- [ ] Live partner login/synchronization, mail provider delivery, approved official form/award acceptance, other-module financial/exemption integration and multi-process MySQL contention have not been performed. These are not represented by green synthetic tests.

Technical references checked: [Bootstrap installation](https://getbootstrap.com/docs/5.3/getting-started/introduction/), [Laravel authorization](https://laravel.com/framework/docs/13.x/authorization). Installed versions and behavior were also checked against the local lockfiles and framework code.

## Local demo data — 21 October 2026, Asia/Manila

Used Laravel Tinker to inspect `db_hris` in read-only transactions and populate `db_hris_v2` with fictional records. The reference database has 134 accounts, 5 divisions, 97 positions, 48,991 DTR rows and 545,202 raw logs. Its schema and aggregate distributions informed the examples; names, contact details, government identifiers and credentials were not copied.

Legacy organization tables map to divisions, positions, position assignments and delegations. The legacy `users_*` PDS tables map to sectioned `pds_entries`; SSI content/events map to portal items/calendar events; `dtr_time_log`, `dtr` and `dtr_remarks` map to raw punches, calculated daily attendance and monthly remarks. This is sample data, not a legacy migration or financial reconciliation.

| Target records | Inserted |
| --- | ---: |
| Users, covering all six roles | 50 |
| Divisions / positions | 5 / 53 |
| Position assignments / work schedules | 48 / 48 |
| PDS entries, covering all eleven sections | 650 |
| Portal items / calendar events | 9 / 5 |
| Raw punches / daily attendance | 2,587 / 756 |
| Attendance revisions / monthly remarks | 758 / 45 |
| Delegations / audit events | 1 / 50 |
| Revoked demo collector / offline integration history | 1 / 2 |

Attendance spans 23 September–20 October 2026, with four/five-day schedules, office/WFH logs, late arrivals, early exits, afternoon-only days, 123 records needing review, and two reviewed missing-punch corrections. The personnel examples include archived, terminated and applicant accounts. All sample accounts use `@demo.hris.test`, synthetic ATMS IDs and clearly marked demo names/identifiers. No mail or partner requests were sent; the sample collector token is revoked.

The opt-in `database/seeders/HrisDemoSeeder.php` accepts only local MySQL/MariaDB `db_hris_v2` or an isolated testing SQLite `:memory:` connection. It rejects production, the legacy database and mismatched connection aliases. Normal `DatabaseSeeder` does not create demo accounts. A Tinker rerun preserves existing passwords, user/PDS edits, deleted PDS entries and attendance corrections without adding duplicate rows. Relative fixture dates remain anchored to the initial demo administrator creation date.

To populate an empty local `db_hris_v2`, open `php artisan tinker` and run:

```php
$demoPassword = Illuminate\Support\Str::password(24);
(new Database\Seeders\HrisDemoSeeder)->seed($demoPassword);
$demoPassword;
```

The generated password applies only to newly created demo accounts. Example accounts are `admin@demo.hris.test`, `hr@demo.hris.test`, `chief-1@demo.hris.test`, `director@demo.hris.test`, `staff-09@demo.hris.test` and `applicant-1@demo.hris.test`. Password values are not stored in repository documentation.

Verification: five dedicated seeder tests passed with 695 assertions, covering valid PDS payloads, repeat runs across dates, edit/password preservation, deleted entries, environment/database/alias guards, collision rollback and absence of external side effects. The backend regression suite passed before the final alias guard was added (102 passed, two registration tests skipped); the five final seeder tests, PHPStan level 7 and Pint then passed. Live MySQL verification through Tinker confirmed stable counts/passwords on the second run, unchanged reference counts, no unmatched punches, duplicate attendance days, employee/position division mismatches, active demo tokens or event delivery rows. Ignored schema and verification reports are under `storage/framework/testing/`.

## Positions and service records — 22 October 2026, Asia/Manila

- Each employee requires a division, a dedicated position and a hire date. Applicant accounts may remain unassigned. All positions are exclusive, regardless of plantilla status; application checks and the `users_position_id_unique` database index prevent shared assignments, including assignments held by archived or terminated employees. A position change releases the previous position and updates vacancy status. The staff picker offers unassigned positions in the chosen division plus the employee's own current position, and displays position codes/IDs to distinguish matching titles.
- Position Classification is a required dropdown accepting only **Staff** or **Supervisory**, with matching server validation. Existing local Technical/blank values were normalized to Staff and audited.
- Staff **History** is now **Service record**. Its modal identifies the employee and displays effective dates, position, previous position, division and employment type. Hiring, position changes, termination, rehiring and date corrections remain recorded as dated snapshots in the append-only audit store. Renaming a position later does not rewrite earlier service snapshots; generic account/PDS edits do not create duplicate employment events.
- The Service record **Print** button opens the employee's complete record in a print view and launches the browser print dialog. Both modal and printed table omit Employment event and Notes. Printing includes all pages and enforces the same self/HR access rules. Print-update verification: eight service-record tests passed (76 assertions), the staff browser workflow passed including print invocation, and PHPStan/Pint/whitespace checks passed.
- To rehire an employee, edit the same employee account, set a new hire date after the previous termination, select their dedicated position and clear/update the termination date. Rehiring preserves prior service events, reactivates an archived account and starts a new effective work schedule. Clearing a past termination without a later hire date is rejected. Archiving an account alone is not a termination.
- The read-only `/hr/staff/{employee}/service-record` endpoint returns ten events per page, ordered by effective date, and permits the employee or authorized HR. The former `/history` path remains an alias. Existing known hire/termination dates and dated position assignments were populated once; missing earlier employment episodes are not invented. Demo seeding also creates these opening records safely on repeat runs.
- Applied the additive migration to local `db_hris_v2` through Tinker: **zero duplicate position assignments**, 46 Staff and 8 Supervisory positions, and **50 service events** (48 hires, one recorded position change and one termination). Repeating the population created no duplicates. No employee was deleted or reassigned by this update.
- Verification: **112 backend tests passed, two public-registration tests skipped, 1,215 assertions; five browser workflows passed**. Coverage includes the full hire/transfer/terminate/rehire sequence, immutable snapshots, repeat saves, self/HR access boundaries, required assignments, database-level uniqueness, nonplantilla/inactive assignment protection, date validation, classification allowlisting, repeat-safe population, migration rollback/reapply, the Service record modal and position create-after-edit. PHPStan level 7 passed; Pint and whitespace checks passed.

## Shared interface and month calendar — 22 October 2026, Asia/Manila

- Font Awesome Free 7.3.1 is bundled locally for navigation, dashboard cards, table actions, theme controls and the account/settings icon overrides. Icons retain accessible text or labels.
- Shared icon-only theme switches expose their checked state and support the keyboard. HRIS, authentication and account/settings layouts share light/dark/system preference storage. Appearance settings use the same preference. Typography prefers the installed macOS/SF system fonts, with Helvetica/Segoe UI/sans-serif fallbacks on other platforms; the Instrument Sans web-font dependency was removed from the layout/build setup.
- SweetAlert2 11.26.25 handles submission success/error messages for Fetch forms, account photo uploads, Livewire settings, authentication flash messages and auxiliary attachment uploads. Validation remains visible beside its form. Archive/delete confirmations use SweetAlert2, and read-only table failures retain their existing retry controls.
- Account pictures appear in shared account controls, account settings, the staff directory and PDS. Owners can upload a JPG/PNG up to 2 MB in account settings; HR can still manage staff photos. The authenticated avatar endpoint serves the latest private profile image or an initials SVG fallback, without publishing storage paths or using external avatar services. Unrelated accounts cannot retrieve another employee's photo; existing HR/PDS record access is honored. Invalid replacements preserve the prior file.
- Calendars now includes a physical month grid using FullCalendar 6.1.22 with Luxon 3.7.2 and the Asia/Manila timezone plugin. My/Office/Executive selection sits above the grid and applies to both grid and list. Previous/next/Today controls, click-to-create on dates, click-to-view/edit events, multi-day events and overflow links are supported. Saves/deletes refresh both views. The existing event list remains paginated at ten rows, while the bounded, authorized month feed includes every matching event in the visible range; inclusive all-day storage is converted to exclusive feed ends. Calendar loading failures offer retry.
- Verification: **118 backend tests passed, two registration tests skipped, 1,287 assertions; seven browser workflows passed**. New coverage includes private photo ownership/unverified-account uploads, safe fallbacks and replacement rejection, full feeds beyond ten rows, office/executive privacy, month range validation, SweetAlert2 Fetch and Livewire success/error messages, account image refresh, persistent icon theme controls, mobile overflow and a Los Angeles browser showing Manila month-end events correctly. Calendar switching/navigation and settled mobile-to-desktop resizing were also checked. Inspected desktop/mobile screenshots under ignored `storage/framework/testing/`; production build, PHPStan level 7, Pint and whitespace checks passed. npm installation reported zero vulnerabilities. Browser cache/session fixtures remain isolated from the developer database and application cache.

## Modules 06-10 - 22 October 2026, Asia/Manila

### Documentation analysis and shared decisions

- [x] Read modules 06-10 in `docs/rebuild/modules/` and the architecture/decision register. `docs/modules/06*` through `10*` do not exist; the rebuild guides remain the requirements source.
- [x] Implement one scoped, append-only request-status history keyed by request type and request ID. All actors come from the authenticated session; latest scoped state controls queues, calendar visibility and attendance effects.
- [x] Use server pagination at ten rows, keyup search, Fetch forms, responsive Bootstrap tables/modals and the existing light/dark layout.
- [x] Add private request evidence metadata and authorized download behavior. No attachment is placed under a public path.
- [x] Keep authorization, measured overtime, COC earning, CTO consumption, leave credits and payroll money as distinct records/services.
- [ ] Legacy data/file import and cutoff reconciliation are not applicable without an approved source dataset and file manifest. Migration mappings remain required before production cutover.

### 06 Official business

- [x] Database, model, personal/division/HR/ED scoped ten-row listing, validated filing, session-derived actor and guarded DC/ED approval/rejection/cancellation.
- [x] Work-related/medical/bereavement catalogue values and departure/return options are retained. Filing cannot include evidence; the owner or authorized HR can upload private PDF/JPG/PNG evidence only after the latest status is Approved.
- [x] Only latest Approved state affects attendance: direct departure clears late and no-return clears undertime; later cancellation removes the exemption on recalculation.
- [x] Approved OB appears in authorized personal/office calendar feeds.
- [ ] Amendment UI/version-chain and printed signature form are pending an approved amendment reapproval route and official form sample.
- [ ] Medical monthly limits/exceptions and compulsory evidence remain disabled pending the documented HR decision; no personnel-ID exception was copied.
- [ ] Mail delivery is not enabled because recipients and the staff/type-2 routing mismatch remain unresolved.

### 07 Overtime and COC

- [x] Stable employee-ID filing, atomic HR multi-employee authorization support, unique request dates, private evidence, scoped lists and guarded DC-to-ED workflow.
- [x] Approved authorization does not manufacture rendered overtime or COC. Request minutes and actual attendance remain separate.
- [x] Append-only minute COC entries use idempotency keys, expiry fields and source provenance; HR adjustments require the named capability and a reason.
- [x] Approved OT dates appear in authorized calendar feeds.
- [ ] Automatic COC earning and monetary OT are disabled pending D06/D08 decisions on multipliers, caps, expiry and rates.
- [ ] Authorization print form and migration/unit reconciliation remain pending approved source artifacts.

### 08 Compensatory time off

- [x] Server derives whole/AM/PM minutes as 8/4 or 10/5 hours from the employee schedule and rejects non-workdays, overlaps and insufficient available COC.
- [x] HR certification, division recommendation, ED/OIC decision and owner cancellation use guarded transitions.
- [x] Final approval locks expiring credit lots, consumes them FIFO by expiry/scope, records request-specific allocations once, and cancellation restores exactly those lots through a compensating event.
- [x] Latest Approved whole/AM/PM dates affect attendance and appear in calendar feeds; cancelled requests do not.
- [ ] Credit reservation before approval, cancellation after lot expiry and approved-hours correction are pending the D06 policy decision.
- [ ] Printed CTO form/signatory snapshots and legacy pending-out reconciliation require approved form/source data.

### 09 Leave management

- [x] Stable leave codes preserve observed IDs for VL, SL, FL, SPL, Solo Parent, Emergency and Wellness leave; Study Leave is keyed by the stable `STUDY` code.
- [x] Filing contains leave type, requested/not-requested commutation, type-specific detail choices and inclusive whole dates. VL, SL, SPL and STUDY detail allowlists are enforced server-side; FL exposes only the exigency-of-service disapproval flag; other types have no special option.
- [x] Dates can be added singly or as an inclusive range. The browser prompts for agency holidays and Fri/Sat/Sun on four-day schedules or Sat/Sun on five-day schedules; the server recomputes the warnings and requires explicit acknowledgement.
- [x] Multi-date filing is atomic, detects active leave/CTO overlaps, and shows/accepts number of days only for detail-code-7 monetization, which has no absence dates.
- [x] HR certification, division-chief recommendation and Director final approval are independently guarded. A division chief receives Director authority only through an active `director.oic` acting-role delegation; staff receive chief attendance/request scope only through an active `division-chief.oic` delegation for their own division. CAO witness actions exist only for the Director's own application, and cancellation is creator-only.
- [x] Review actions are generated server-side from the latest state, reviewer role, ownership and active delegation. The review modal displays the append-only action history with actor, role, resulting status, reason and Manila timestamp; new status rows retain the exact action code.
- [x] Paid/unpaid total validation, exact three-decimal debit, idempotent cancellation reversal and the audited HR adjustment endpoint are implemented.
- [x] Latest Approved inclusive leave dates affect attendance and authorized calendar feeds.
- [ ] Automated accrual, four-day conversion, FL dual-debit, proration, timed approval and negative-balance policy are disabled pending D07.
- [ ] Returned-request resubmission, official print/leave-card layouts and scheduled tardiness deductions need approved rules/forms.

### 10 Payroll

- [x] Payroll access is restricted to `payroll.manage`; headers validate type/period/fund/number and start as drafts.
- [x] Employee salary is derived server-side from effective salary grade/step. Submitted gross/net/basic salary are ignored; compensation and deductions are recomputed with exact two-decimal rounding.
- [x] One weekly allocation algorithm reconciles exactly to net, including amounts already divisible by four.
- [x] Employee identity, organization, grade/step, money inputs and weekly allocation are snapshotted. Finalization is transactional and immutable; CSV export reads only finalized snapshots.
- [x] Loan categories use the documented provider allowlist and preserve categories instead of deleting history.
- [ ] Statutory/allowance defaults, CSW percentage/divisor automation, bonus eligibility and negative-net exceptions remain disabled pending D08 Finance approval. Reviewed amounts may be entered, but no UI default is represented as law or policy.
- [ ] Copy-previous, payroll-loan line UI, void/correction lifecycle, Excel/DV/payslip form layouts and legacy reconciliation remain pending Finance decisions and approved output samples.

### Modules 06-10 known gaps and deviations

- Unresolved rules are deliberately not automated. This is a documented incomplete production-policy state, not a claim of legacy parity.
- Request mail is not sent until recipient authority and templates are approved. Database transactions therefore cannot be left half-complete by mail failure.
- Leave filing currently uses inclusive whole dates as specified in the reviewed interface; no undocumented AM/PM option is exposed.
- Payroll supports safe reviewed draft calculation/finalization and CSV evidence. Statutory calculations and official spreadsheet/PDF layouts are not represented as complete.

### Modules 06-10 verification

- [x] Backend suite: **132 tests, 130 passed, two intentionally skipped registration tests, 1,398 assertions**.
- [x] New workflow coverage verifies owner/division scope, temporary acting roles and expiry, role-specific leave actions, creator-only cancellation, Director/OIC/CAO boundaries, action-history output, guarded OB/OT/CTO/leave transitions, double-decision rejection, request-specific COC allocation/restoration, exact leave debit/reversal, latest-state attendance effects, request calendar feeds, server-derived payroll salary/totals and immutable snapshots.
- [x] PHPStan level 7: zero errors. Pint style check: passed. Production Vite build: passed.
- [x] Playwright browser suite: **eight passed** after prepending the installed PHP 8.4 executable directory for the Node web-server process. It covers asynchronous tables, mobile layout, themes, calendars, settings, error/empty states, leave-type option switching, conditional monetization fields, holiday confirmation, creator-only review actions and rendered action history.
- [x] Additive migration is applied to local `db_hris_v2`; routes and all five new module pages load under their authorized roles.
- [x] The fictional local demo dataset was restored after an Artisan `--env=testing` migration verification inherited the local database connection. Restored counts: 50 users, five divisions, 53 positions, 650 PDS rows, 2,581 raw punches, 756 daily attendance rows, nine portal items and five calendar events. The reference/legacy database was not touched. Any uncommitted edits made only inside the former local demo database cannot be reconstructed; the replacement demo credential is reported in the implementation handoff and is not stored in the repository.

## Modules 12-16, 21 and 22 - 22 October 2026, Asia/Manila

### Shared implementation decisions

- [x] All lists use authenticated, server-enforced owner/custodian/division/acting-role scopes and ten-row pagination. Ordinary staff cannot gain coworker access merely by sharing a division.
- [x] Status-bearing records use append-only action rows with session-derived actors. Confidential evidence uses generated private object paths, checksums, metadata, and the same resource authorization on download.
- [x] Signed/reported structures use stable child IDs and immutable snapshots. Updates do not address array indexes, and division budgets are recalculated transactionally from persisted lines.
- [x] Historical/reference records are archived, superseded, or versioned instead of destructively removed.

### 12 Learning and development

- [x] Position requirements support category validation, create/edit/archive, exact-deduplicated copy, and preserved historical references.
- [x] Completed PDS learning records can be linked to the current position requirement. Matrix output separates fulfilled, unfulfilled, and uncategorized history, including broken/other-position links without deleting the training record.
- [x] Authorized division chiefs/OICs, Director/OIC, and HR planners can create one division plan per year, select only approved same-year/same-division IDP items, mark free training, and save nonnegative decimal budgets. Server totals and distinct staff count come from the persisted stable lines.
- [x] Division learning plans have snapshot-based print output.
- [ ] Annual position-matrix budget editing and XLSX import remain disabled because the repository does not establish budget ownership, an accepted template, expiration rules, or repeat-import behavior.

### 13 Individual development plans

- [x] Employees create one annual plan with one-to-three coverage years, documented purpose, position-scoped requirements, and stable proposal/training items. Draft edits preserve item IDs.
- [x] Submission captures the employee/position/division snapshot. Staff plans route to the division chief/OIC; a chief's plan routes to the Director/OIC. Only the derived supervisor can approve, and approved plans feed division planning.
- [ ] A separate HRDC approval is not enabled because no evidenced HRDC actor, quorum, or final-state rule exists.

### 14 SALN

- [x] Private owner working copies cover declarant/spouse, children, real and personal property, liabilities, business interests, and government relatives. Section keys are allowlisted and monetary values are bounded nonnegative decimals.
- [x] Assets, liabilities, and net worth are derived in integer cents; owner/custodian reads are enforced and a grouped print view is available.
- [ ] Electronic submission, revisions after submission, signatures, and filing-period uniqueness remain disabled pending the unresolved filing/signatory policy. The interface labels records as working copies rather than representing them as filed.

### 15 IPCR

- [x] Employees create non-overlapping review periods and stable category-output/indicator/accomplishment hierarchies with complete draft CRUD. Submission captures an employee snapshot and enters `For Review`.
- [x] Only the derived division chief/OIC or Director/OIC supervisor can rate submitted accomplishments. Quality, efficiency, and timeliness are bounded to 1-5; the compatibility calculator uses observed 40/40/20 category weights, exposes category and overall results, and prints the hierarchy.
- [ ] Final approval, return/revision, and a replacement rating formula are disabled until the agency confirms D16. `For Review` is deliberately the terminal evidenced workflow state.

### 16 Employee concerns

- [x] Self-owned New concerns support private employee evidence and edit/cancel while New. HR alone can acknowledge then resolve with a required response; only the owner can acknowledge the resolution once.
- [x] New, Acknowledged, Resolved, Staff Acknowledged, and Cancelled actions are append-only. Employee and HR attachments are normalized, generated privately, authorized with the concern, and committed atomically with the concern/action metadata.
- [ ] Reopening, assignment/escalation, anonymity, notifications, retention, and evidence removal remain disabled pending policy decisions.

### 21 Reference settings and holidays

- [x] Authorized HR/system administrators manage effective-dated grade/step salary values with non-overlapping periods and historical end-dating. Payroll continues to snapshot the resolved value.
- [x] Regular holidays, special nonworking days, work suspensions, and special orders support agency-wide or normalized employee-specific coverage, validated suspension cutoffs, authority/source, deliberate clearing, auditing, and archival.
- [x] Leave warnings and attendance use the same scoped holiday query. Full-day covered references suppress penalties; work suspensions pass the earliest covered cutoff to the deterministic attendance calculator. Uncovered employees are unaffected.
- [ ] Leave-conversion tables remain read-only/unpopulated until approved legal values, boundaries, decimal scale, and effective dates are supplied; no statutory formula was invented.

### 22 Personnel document uploads

- [x] The seven documented staff document types use explicit annual/semester/one-time periods, private validated files, owner/HR lists, and Submitted/Acknowledged/Returned/Cancelled action history.
- [x] Duplicate type/year/period submissions are rejected. A Returned correction is the implemented safe default: it creates a new file version under the same record and preserves all earlier evidence and history. Only a Submitted owner record can be cancelled; only HR can acknowledge or return, with a required return reason.
- [ ] The returned-correction default requires owner confirmation before production cutover. Applicant requirement files remain a separate workflow and are not mixed into this queue.

### Modules 12-16, 21 and 22 verification

- [x] Backend suite: **138 tests, 136 passed, two intentionally skipped public-registration tests, 1,463 assertions**.
- [x] Playwright browser suite: **ten passed**, including responsive live-table rendering for every new module page and nested IDP, SALN, and IPCR editor submissions without a page reload.
- [x] Dedicated feature tests cover page access, IDP item stability/supervisor scope/division budget totals, PDS training matrix classification, exact-cent SALN totals/privacy, nested IPCR review/rating scope, concern transitions, private attachments, returned document versioning, employee-specific calendar effects, and salary-period overlap rejection.
- [x] The additive migration rolls back/reapplies on isolated SQLite and applies on local MySQL. MySQL verification caught and corrected a generated index-name length issue; the six empty tables from that failed attempt were verified empty, removed, and recreated by the successful migration. No existing table or user record was removed.
- [x] PHPStan level 7, Pint, Blade compilation, production Vite build, and a focused mobile Playwright run for all seven module pages pass.
