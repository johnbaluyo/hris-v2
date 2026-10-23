# 05. Attendance, gate logs, DTR and attendance reports

This specification describes repository behavior, not an approved personnel policy. **Observed** means present in source; **Proposed** means a rebuild requirement; **Unresolved** requires a recorded decision before production. Read this module together with official business, overtime/COC, CTO and leave management.

## Purpose and actors

**Implemented clarification, 23 October 2026:** System administrators have **HR Administration > Raw attendance punches** (`/hr/raw-attendance`) to search captures across all employees, former employees and unmatched IDs. Only administrators may edit punch date/time, device and record type; HR and other roles cannot use this module even with an explicit capability grant. Every correction requires a reason and records actor/time and before/after values. The first original capture is retained for device replay comparisons. Employee/collector identity remains unchanged. Affected old/new DTR dates recalculate transactionally while manual DTR overrides remain intact. Unmatched captures need existing identity reconciliation before generating DTR rows. Stale edits return 409. Apply additive migration `2026_10_23_000003_add_raw_punch_corrections.php` before use.

Maintain raw attendance punches, construct a daily time record (DTR), calculate rendered work and tardiness, allow audited corrections, and produce individual and consolidated attendance reports. Employees view their own records and clock through the web application; HR reviews staff records, corrects attendance, adds monthly remarks and generates reports. External biometric synchronization supplies raw punches.

**Observed authorization:** `DTRController` and `HRStaffController` apply authentication, but lookups such as `get_dtr`, `time_log`, `check_record`, and override operations accept record/employee/ATMS identifiers without a consistent ownership policy. Clock endpoints accept submitted `user_atms_id`. HTTP cron handlers in `CronController` do not declare authentication in their constructor. Navigation visibility and collection filtering are not endpoint authorization.

**Proposed:** authorize own attendance, division attendance, HR corrections, exports and ingestion separately; derive self-service identity from the session. Device API access must remain separate from browser sessions. Record original data, actor, reason and calculation version for overrides. Attendance reports contain sensitive personnel data and require the same scope checks as their source records.

## Source map and entry points

| Source | Behavior to inspect during implementation |
| --- | --- |
| `routes/web.php` DTR, HR staff, reports and cron routes | History, raw time logs, summary, correction, remarks, individual/multi/monthly reports, update/sync and HTTP jobs |
| `routes/api.php`; `app/Http/Controllers/APIController.php` | Token-protected raw-log max ID and batch insertion; external ingestion contract |
| `app/DTRTimeLog.php::time_log_ssi`, `time_log_ssi_wfh`, `timelog_to_dtr` | Web punch capture, geolocation and raw-log aggregation |
| `app/DTR.php::save_override`, `compute_work_hours`, `round_off_work_hours`, `check_if_tardy_function2`, `AddTardiness` | Work-hours and stored tardiness flags; multiple historical calculations |
| `app/Http/Controllers/HolidayController.php::check_dtr`, `get_tardiness` | Current summary/report display calculation, including WFH and partial suspension |
| `app/Helpers/DtrHelpers.php::staff_dtr`, `dtr`, `has_dtr`, `check_holiday`, `countLeave`, `countOB` | Consolidated report calculations and exclusions |
| `app/Http/Controllers/DTRController.php::specialOrderModify`, `workSuspensionModify`, `checkOB`, `checkCTO`, `modifyDTRLeave`, `BestAttendance` | Cross-module mutations and best-attendance computation |
| `app/Http/Controllers/HRStaffController.php::updateDTR`, `syncDTR`, `deduct_tardy_to_credits`, `has_dtr`, `get_credit_equiv` | Manual pipeline and leave-credit deductions |
| `app/DtrRemarks.php`; `app/DTRSummary.php`; `app/Exports/MonthlyDtr.php` | Monthly remarks, summary storage and Excel report view |
| `resources/views/dtr/`, `resources/views/hr_staff/attendance_monitoring.blade.php`, `public/js/dtr.js` | Forms and printed output contracts |
| `app/Console/Kernel.php`; `app/Console/Commands/AnalyzeTimeLog.php`; `app/Console/Commands/GenerateLeaveCredit.php` | Actual scheduled command pipeline |

Representative routes: `/dtr-history`, `/dtr/time_log/{filter}`, `/get_dtr`, `/dtr/summary`, `/dtr/report`, `/dtr/report/monthly/{month}-{year}`, `/dtr/multi-report`, `/dtr/report/individual`, `/dtr/remarks/{month}-{year}`, `/dtr/update`, `/dtr/sync-dtr`. `routes/web.php` also references `DTRController@dtr_analyzer`, which is absent; do not silently carry that route forward.

## Data contract

| Table/model | Important fields and semantics |
| --- | --- |
| `dtr_time_log` / `DTRTimeLog` | External `anviz_log_id`, normalized `user_atms_id`, `time_log`, `device`, optional `record_type` and JSON `wfh_location`; raw evidence, independent of a DTR |
| `dtr` / `DTR` | `user_id`, `created_at_date`, nullable `time_in`, `lunch_out`, `lunch_in`, `time_out`, `ot_in`, `ot_out`; decimal rendered/OT hours; `work_sched` (4/5 or null), `late`, `undertime`, `isAnalyzed`, `notes`; model also writes `coc` |
| `dtr_remarks` / `DtrRemarks` | Employee/month coverage, `fc_absence`, text remarks; used in consolidated attendance |
| `dtr_summary` / `DTRSummary` | Existing summary persistence; confirm active writers before selecting it as authoritative |
| `users`, `holidays`, holiday employee membership | ATMS mapping, archive/employment/hire/termination dates, current workweek; holiday type/time and affected employee scope |
| Leave, OB, CTO and `statuses` | Approved absences and exemptions; latest status must be scoped by request type and request ID |

Schema history begins at `database/migrations/2021_04_14_051907_dtr_table.php`; subsequent migrations add date/lunch/OT/RFID/index/workweek/tardiness fields and change numeric types. Use the complete migration inventory and a sanitized deployed schema comparison; the initial migration alone is insufficient. Preserve null versus zero: null `work_sched` is actively used to suppress deductions.

## Observed workflows and calculations

### Capture and aggregate punches

1. Device logs enter the raw table. Employee ATMS IDs are matched after `ltrim(id, '0')`; confirm that an all-zero or missing ID cannot map multiple people.
2. Web punches use server `Carbon::now()`, external log ID `0` and device `WEB APP` or `WEB WFH`. WFH capture optionally calls `http://ip-api.com/json/{ip}` and LocationIQ reverse geocoding with `LOCATIONIQ_API_KEY`, each with a five-second timeout. Forwarded IP headers are read directly. Failure generally permits the punch and may store an error. This is IP-derived location, not verified device GPS.
3. `timelog_to_dtr()` scans nonarchived employees from January 1 of the current year through today. It selects dates missing a DTR; existing DTR dates are not rebuilt when later punches arrive.
4. For all-WFH punches, first punch becomes the start and notes become `WEB WFH`; otherwise the first non-WFH punch supplies the start. Last punch can come from the mixed set. A first punch at or after 12:01 becomes `lunch_in`; earlier starts become `time_in`.
5. If the last punch is before noon it becomes `lunch_out` and afternoon fields are null. Lunch punches are non-WFH punches between 12:00:00 and 13:00:59. One lunch punch can supply both lunch-out and lunch-in; multiple use earliest/latest; no lunch punch synthesizes noon values where there is a morning start.
6. For multi-punch days, an upcoming Friday holiday other than `Special Order` forces `work_sched = 5`; otherwise the employee's current workweek is copied. One-punch rows omit a work schedule in the insert payload. OT fields are currently initialized null; the commented WFH-to-OT logic is inactive.

The ingestion API exposes GET `/api/dtr-time-log/max-id` → `{success, max_id}` and POST `/api/dtr-time-log/batch-insert` with a nonempty `records` array. Each record requires integer `anviz_log_id`, `user_atms_id`, date `time_log`, string device and optional created/updated timestamps. It inserts chunks of 500 with ordinary `insert`, without a deduplication/upsert transaction. Its `inserted_count` sums insert return values rather than actual row counts. Preserve client compatibility while adding explicit batch limits, replay protection and correct counts; a max-ID cursor alone cannot repair missing/out-of-order source rows.

### Rendered hours and rounding

`DTR::compute_work_hours()` selects only unanalysed rows in the current year through current month end, excluding today, and handles several combinations of available fields. It truncates punch seconds to the minute, clamps morning work to 07:00–12:00 and afternoon work to 13:00–21:00, and floors negative segment lengths to zero. Full-day hours are the sum of morning and afternoon hours, excluding the fixed lunch gap. Incomplete combinations may yield a half-day calculation or null hours. The override path uses an 18:00 afternoon boundary in setup but its afternoon-duration expression uses actual `time_out`, so it is not equivalent to the batch path.

When both explicit OT punches exist, OT hours are `(ot_out - ot_in) / 3600`, clamped to 0–2. A weekend override instead uses rendered day hours as OT and writes `coc = hours * 1.5`. No verified rule for overnight shifts is present: aggregation is by calendar date and override times share the submitted date.

`round_off_work_hours()` converts exact stored values 7.97/7.98/7.99 to 8, 9.97/9.98/9.99 to 10, and OT 1.98/1.99 to 2. This is an explicit legacy adjustment, not general mathematical rounding.

### Displayed tardiness versus stored flags

`HolidayController::check_dtr` selects a start/end pair from available punches; unsupported patterns return `array failed`. `get_tardiness` then:

- Excludes 12:00–13:00 from counted work, clamps start to 07:00, and caps end at the applicable latest exit.
- Uses required work of 600 minutes for schedule 4 and 480 otherwise. Office latest arrival is 09:00; latest exit is 20:00 for schedule 4 or 18:00 otherwise.
- For notes equal to `WEB WFH` ignoring case/outer spaces, latest arrival is 07:00; latest exit is 18:00 or 16:00. WFH undertime is missing required minutes, without office flex arrival.
- Computes lateness after the latest arrival excluding the fixed lunch interval. An arrival during lunch incurs lateness only through noon.
- For office arrival by 09:00, undertime is `max(0, required - worked minutes)`; expected exit is arrival + required work + lunch hour. After 09:00, undertime is time short of the latest exit.
- For a timed work suspension, latest exit is reduced if earlier; undertime becomes time short of that exit. Lateness becomes zero when suspension is at/before latest allowed arrival.
- Returns adjusted start/end, expected exit, schedule and `HH:MM` lateness/undertime.

`DTRController::getSummary` only loads rows already flagged `late = 1` or `undertime = 1` and then uses this calculator. A correct display calculator cannot recover omitted unflagged rows. Stored flags come from separate model calculations, not this exact function.

### Historical helper and leave debit behavior

`DtrHelpers` and `HRStaffController::has_dtr` contain further branches: the four-day schedule begins after 2023-04-10; 2023-08-22 through 2023-09-15 is treated specially as eight-hour work; Friday holiday rules change requirements; OB options and suspensions alter tardiness. Variables named `tardy_minutes`/`ut_minutes` often contain **seconds**. Monthly report day equivalents divide seconds by 60 and then by 600 or 480 and format to three decimals; zero equivalents render blank.

`deduct_tardy_to_credits()` scans the **previous calendar month**, excludes ED, selects nonarchived Permanent/Contractual Regular employees, and processes rows with a nonnull schedule. Deductions go to VL. `get_credit_equiv()` floors seconds to whole minutes, adds `0.125` day per whole hour, then looks up remaining minutes in `Table4`; missing lookup values become zero. Deduplication relies on exact activity text plus user, so corrected minutes can create a second debit. No-record dates insert an OUT ledger entry of **zero**, tagged `no-record`. Some report counting expects `lt` while insertion tags late as `late`.

The debit-path `check_holiday()` excludes any CTO date even without an approval-status check, whereas the helper's missing-record check looks for Approved history. It also treats any matching holiday as an exclusion without consistently applying employee scope. These differences can change balance deductions without changing the displayed report.

### Cross-module mutations and scheduling

| Invocation | Observed actions |
| --- | --- |
| Daily `command:AnalyzeTimeLog` | Convert raw logs; delete duplicate DTRs; compute hours; apply exact-value rounding. Does not call `AddTardiness` here. |
| HTTP `/AnalyzeTimeLog` | Convert logs; compute hours; add stored tardiness flags. No duplicate cleanup or exact-value rounding here. |
| HR `updateDTR` | Same broad pipeline as HTTP analyzer; rounding is commented out. |
| HR `syncDTR` | Apply special orders, suspensions and OB clearing only. |
| `specialOrderModify` | Clears late/undertime and schedule for affected event employees; absent employee selection applies to all DTRs on the date. |
| `workSuspensionModify` | Clears undertime for qualifying exit punches; a full-day suspension clears both flags and schedule. |
| `checkOB` | Clears late for `go_directly`, undertime for `not_expected_to_return`; query has no approval-status filter. |
| `modifyDTRLeave` | Uses latest LEAVE status Approved, excludes leave type 3, and nulls flags/schedule. |
| `checkCTO` | For dates after 2025-12-31 and CTO hours 4/5, clears both flags if start/end duration minus one lunch hour is at least 300 minutes; no status filter in this query. |
| Daily `command:GenerateLeaveCredit` | Grants monthly/yearly credits, then debits previous-month tardiness. |

The source exposes additional HTTP cron endpoints, but their presence does not prove an external scheduler invokes them or establish execution order. Document production scheduler evidence separately.

## Reports, dependencies and known gaps

Outputs include DTR history/raw logs, individual printed DTR, multi-employee reports, annual/monthly attendance views, best-attendance results, monthly remarks and the `MonthlyDtr` Excel view. `staff_dtr()` currently restricts its employee query to a single literal user ID; its scope date uses `$month + 1` while `dtr()` uses `$month`. CTO counts are not constrained to the requested month and whole-day counting only checks 8 hours despite 10-hour schedules. Several historical joins accept any Approved status ever recorded. `countLeave()` counts date rows rather than fractional day units. Best-attendance and monthly reports therefore need explicit parity and correction decisions.

`BestAttendance()` is a hardcoded February 2024/division-specific calculation, not a generic current-period ranking service. It derives scheduled weekdays from 4/5 workweeks, subtracts Regular/Special Non-Working holidays and full/early suspensions, then calculates absent days as working days minus qualifying DTR rows, full-day OB, special orders, whole-day CTO and approved leave other than VL/SL. It also sums stored late/undertime flags and writes `dtr_summary` (`days_present`, legacy `abscences`, `lates`, `undertimes`) if missing, with a hardcoded schedule value. The `/BestAttendance` GET therefore has persistent effects. `checkHolidays()` also contains fixed diagnostic dates/employee selection and an early return before its mutation code. Define desired attendance-award criteria before rebuilding these as production features.

Dependencies: identity/ATMS mapping, employee effective work schedules, holidays and employee scopes, status history, leave ledgers and Table4 conversion data, OB/CTO, device ingestion, timezone, geolocation providers, Excel runtime and report logos/signatures. External configuration belongs in the separate configuration documentation.

**Unresolved:** authoritative calculator and historical policy versions; how corrected punches rebuild existing dates; precedence for overlapping approved leave/OB/CTO/holidays; half-day semantics; Friday holidays; negative or incomplete intervals; overnight work; required approval before exemptions; no-record debit policy; report month indexing; whether special orders without employee membership mean everyone.

## Implementation checklist

- [ ] Inventory all attendance routes and classify active, broken, administrative and scheduled use; implement policies for each.
- [ ] Preserve raw logs and external IDs; make ingest replay-safe and normalize ATMS mapping without collisions.
- [ ] Import DTRs and corrections with a mapping to original records; reconcile duplicate employee/date rows before adding a uniqueness constraint.
- [ ] Create an attendance calculation service with typed minute units, effective-dated work schedules and policy versions; obtain decisions for the divergences above.
- [ ] Preserve a historical result snapshot and add a reviewed recalculation workflow that records before/after results.
- [ ] Implement raw-log aggregation, mixed WFH handling, incomplete-punch review, override reason/audit and monthly remarks.
- [ ] Define deterministic exemption precedence using latest scoped status and affected employee membership.
- [ ] Consolidate schedule commands into monitored, locked, retry-safe jobs; replace state-changing HTTP GET jobs with controlled execution.
- [ ] Link attendance ledger debits to employee/date/type/calculation version; reverse prior debit before applying a corrected result.
- [ ] Rebuild all attendance outputs using a shared calculation result and explicitly approved report scope.
- [ ] Compare imported monthly totals, tardiness, debit ledger totals and sample printed forms against sanitized legacy fixtures.

## Acceptance checklist

- [ ] Employees cannot read/clock/correct another employee through submitted IDs; HR/division report scope is enforced server-side.
- [ ] Replayed device batches and concurrent analyzers do not duplicate raw logs or daily rows.
- [ ] One punch, morning-only, afternoon-only, missing lunch, multiple lunch punches, mixed devices and late-arriving punches yield reviewed deterministic results.
- [ ] Office and WFH schedules cover 07:00/09:00/noon/13:00 boundary values, 8/10-hour days, early/late exits and suspension before/after lunch.
- [ ] Historical exception dates, Friday holidays, scoped special orders, cancelled requests and overlapping approvals are covered by approved fixtures.
- [ ] Month/year boundaries, leap day, hire/termination dates and reporting with no DTRs are correct.
- [ ] Running each scheduled job twice, concurrently, or after partial failure produces one net financial/leave effect.
- [ ] Reversal/recalculation adjusts only linked deductions and reports the before/after difference.
- [ ] Screen, individual form, monthly report, summary and ledger agree for the same policy version and period.
