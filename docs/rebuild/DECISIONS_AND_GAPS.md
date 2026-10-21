# Decisions and legacy gaps

These items prevent the rebuilding agent from treating accidental code behavior as approved policy. All decisions start **open**. Assign a business/technical owner, record the chosen behavior and effective date, and link the implementing change and acceptance evidence. Independent modules may proceed while a decision remains open.

## Decisions requiring an owner

| ID | Decision | Existing evidence and impact | Suggested owner |
| --- | --- | --- | --- |
| D01 | Framework, runtime, database and frontend baseline | Proposed Laravel 13/PHP 8.4; dependency compatibility must be demonstrated, including reports | Technical owner |
| D02 | Canonical role and permission model | Staff editor calls role 5 Admin; sidebar calls role 6 Admin; hardcoded user IDs appear in several workflows | HR and system administrator |
| D03 | Applicant registration and account provisioning | GET `/register` is overridden, while `Auth::routes()` also declares POST registration; default controller expects `name` but employee schema uses separate name fields | HR/recruitment and technical owner |
| D04 | SSI and DTS trust model | Encrypted-email shared-key login has no expiry/replay protection; older template uses encrypted numeric ID | Partner system owners |
| D05 | Authoritative attendance rules and historical exceptions | Several calculators/analyzers differ; schedules, WFH, holidays, OB, CTO and leave overlap | HR attendance owner |
| D06 | COC availability, deduction timing, expiry and reversal rules | Immediate CTO deduction and pending deductions coexist; cancellation may restore the latest employee OUT entry | HR attendance owner |
| D07 | Leave eligibility/accrual/cancellation and automated approval policy | Legacy rate tables/date conditions encode policy; seven-day autoapproval examines historical approval rows and calls Auth-dependent approval code. Define service actor, latest-state eligibility, allowed ages and exclusions | HR leave owner |
| D08 | Payroll formulas, approved rates, finalization and correction process | Browser-computed amounts and current salary lookups can change historical output | Payroll/finance owner |
| D09 | Notification audience semantics and shared-state migration | Integer user ID and string role code share one audience array; broadcast/role row flags affect every recipient | Product/partner owner |
| D10 | Certificate verification disclosure and signing | Public verification exists; define approved visible fields, invalidation, identifier stability and signatory authority | HR records owner |
| D11 | WFH policy and delegation | WFH button availability uses calendar events; accomplishment workflows also contain fixed 2026 date windows and acting-chief logic | HR/division chiefs |
| D12 | Completion or retirement of unfinished actions | Routed methods are missing; some resource controllers are empty; IDP HRDC approval fields have no complete routed flow | Module business owners |
| D13 | Retention, privacy, file access and audit periods | Personnel files and signatures are stored in public directories; define scope and authorized access | Records/system owner |
| D14 | Organization approval routing | Current division, job titles, user IDs and OIC delegation affect approvers; transfers can change historical interpretation | HR and ED office |
| D15 | Migration source of truth and cutover strategy | Need deployed schema, validated opening credit/payroll figures, file manifest and external scheduler/device inventory | DBA/operations and HR |
| D16 | SALN/IPCR/PDS forms and legal content | Existing layouts and calculations need approved form samples and review; field presence does not prove a mandated workflow | HR records owner |
| D17 | L&D matrix budgets and certification process | Position matrix, division plan and IDP linkages need consistent year/period rules and accepted budget totals | L&D/HRDC owner |

## Observed technical gaps to resolve in the replacement

| Gap | Source evidence | Required treatment |
| --- | --- | --- |
| Missing explicit handlers and duplicate routes | [Route inventory](inventories/ROUTES.md) | Give each endpoint an explicit disposition and test retained handlers; inspect inheritance before declaring a method absent |
| Authentication is not uniformly declared | `SsiController`, `HolidayController`, `PersonnelDataManagementController`, `IPCRController`, `COEController` and [constructor inventory](inventories/CONTROLLERS.md) | Audit every action and apply middleware/policies; an Auth dereference is not authorization |
| UI permissions differ from server permissions | Sidebar/user editor and hardcoded checks in controllers | Implement server policies first and derive UI actions from the same abilities |
| Public GET mutation/job routes | `/AnalyzeTimeLog`, `/GenerateLeaveCredit`, `/DeductLeaveCredit`, `/set_all_unsetted_password`, `/applyPendingCocDeductions` and related routes | Replace with restricted commands/jobs and a controlled operational handover |
| Registration and logout ambiguities | `Auth::routes()`, GET `/register`, GET `/logout`, custom `/ssi/login` | Define one audited login/logout/provisioning flow and test every generated route |
| SMTP credentials embedded in configuration | `config/mail.php` | Move to managed secrets and rotate exposed credentials; documentation never reproduces values |
| Runtime env access and mismatched env names | `MAIL_DRIVER` vs `MAIL_MAILER`, ignored `SESSION_SAMESITE`, runtime `env()` in code | Centralize configuration and validate after config caching; see external guide |
| Duplicate notification migration column/index | Notifications migrations dated 2026-07-07 | Create coherent target baseline; reconcile deployed migration state before import |
| Shared notification read/dismiss state | `SystemNotification` flags on a broadcast/role row | Implement per-recipient state and a deliberate legacy flag mapping |
| Public uploads and inconsistent file validation | Upload/controller/model handlers and public file paths | Private storage, authorization and common validation for confidential records |
| Calculations spread across views/models/controllers | Attendance helpers and payroll Blade scripts | One tested domain implementation, server-side totals and historical snapshots |
| Database-seeding mismatch | `DatabaseSeeder` calls `UsersSeeder` while file is `UserSeeder.php` | Build safe new seeds and validate class resolution; never blindly execute SQL employee data |
| Minimal existing verification | Only example feature/unit tests | Build domain fixtures and workflow/authorization coverage; do not infer parity from green boilerplate tests |

## Endpoint disposition register

The route inventory contains 460 explicit declarations including repeated paths. Maintain one entry per declaration when comparing legacy behavior and one canonical target entry per effective method/path. Example format:

| Legacy method/path and source | Known callers | Disposition | New handler or adapter | Acceptance evidence |
| --- | --- | --- | --- | --- |
| `GET /api/dtr-time-log/max-id` | External sync agent, confirm deployment | Retain contract until partner migration | Scoped service endpoint | Token, cursor and replay checks |
| `GET /DeductLeaveCredit` (two declarations) | External cron, confirm deployment | Retire after command handover | Idempotent restricted command | Scheduled execution and no duplicate deductions |

Do not treat the examples as authorization to run the endpoints. Job endpoints mutate real HR records.

## Decision record template

```text
Decision ID:
Owner and approver:
Question and affected modules:
Chosen behavior and effective date:
Legacy behavior intentionally changed:
Data migration impact:
Partner/operations impact:
Acceptance examples and expected results:
Implementation reference:
Status and approval date:
```
