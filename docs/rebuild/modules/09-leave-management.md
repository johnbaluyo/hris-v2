# 09. Leave applications, balances, ledgers and leave calendar

**Observed** is repository behavior, **Proposed** is rebuild work, and **Unresolved** is a decision gate. The source is evidence of implementation, not authority for leave entitlements or legal policy.

## Purpose and actors

Employees apply for leave and inspect balances/history; HR certifies, adjusts credits and manages staff leave; division chiefs recommend; ED/OIC makes a final decision; CAO witnesses an ED application. The module also grants recurring credits, deducts attendance-related VL, supports cancellation/reversal, calendar feeds and printed application/leave-card outputs.

`LeaveController` requires authentication but arbitrary request IDs, ledger IDs and user IDs are not consistently authorized by owner/division/capability. `check_solo` reads a literal employee ID. Overview filters and hidden controls do not enforce authorization for mutations. Rebuild explicit owner, HR certification/ledger, division recommendation, ED/OIC decision and CAO witness policies; establish effective approver assignment instead of personnel-ID exceptions.

## Source anchors and routes

| Source | Responsibility |
| --- | --- |
| `app/Leave.php::submit`, `approve`, `cancel_leave`, `return_request`, `_delete`, `overview`, `leave_calendar`, `get_leave` | Filing, routing, decision details, cancellation, return, list/calendar and form data |
| `app/Http/Controllers/LeaveController.php::submit`, `approve`, `ApproveAFL`, `undo_cancellation`, `override_leave` | Status posting, credit debit, timed autoapproval, cancellation undo and HR override |
| `app/LeaveLog.php::insert_logs_and_scope`, `insert_logs_and_scope_for_tardy`, `leave_logs`, `auto_leave_credits` | Running-balance ledger, workweek scaling, display/reset semantics and scheduled grants |
| `app/LeaveDates.php`; `app/LeaveType.php` | Selected dates and leave catalogue |
| `app/Http/Controllers/HRStaffController.php::deduct_tardy_to_credits`, `has_dtr`, `get_credit_equiv` | Previous-month attendance deductions; module 05 documents formulas |
| `app/Status.php` | Shared status writers, including differences between `create_status2` and autoapproval `create_status3` |
| `resources/views/leaves/`; `public/js/leave.js`; `public/js/leave_credits.js` | Filing choices/conditional inputs, approval forms, history, credits and printable leave application/card |
| `app/Console/Commands/GenerateLeaveCredit.php`; `app/Console/Kernel.php` | Daily recurring grant and tardiness-debit command |

Routes: `/my-leave`, `/leave-overview`, `/leave-overview-oic`, `/leaves/submit`, `/leaves/check_dates`, `/leaves/check_holiday`, `/leaves/approve`, `/leaves/return_request`, `/leaves/cancel_leave`, `/leaves/undo_cancellation`, `/leaves/status_history`, `/leaves/staff-credits`, `/leaves/staff_credits/update`, `/leaves/log/delete`, `/leaves/calendar`, `/leaves/calendar/feed`, `/leaves/report/{leave_id}`, `/hr/leave/override_leave`, plus HTTP `/ApproveAFL` and credit jobs. Confirm routed and actually working exports separately.

## Data contract

| Table | Important fields |
| --- | --- |
| `leaves` | Employee, filing date, leave type ID, details code/specification, commutation, recommendation/reason, with/without-pay days, other remarks, `num_of_days`, `fl_for_disapproval`, return message, `leave_log_id`, timestamps; historical direct status field is not the primary history |
| `leave_dates` | Leave ID and inclusive date; filing deletes/replaces all selected dates, de-duplicating per header |
| `leave_types` | Name/acronym, `accumulation` (`monthly`, `yearly reset`, other granted types), optional `get_credit_from_id`; deployed catalogue is required reference data |
| `leave_log` | Employee, type, activity, credit amount, running total, IN/OUT, scope date, tardy type; legacy numeric columns are double with three decimals |
| `statuses` | `request_type = LEAVE`, request ID, state, actor, remarks and timestamps |

Schema begins in `2021_05_04_083838_leave_tbl.php`, `2021_05_04_114603_leave_dates_table.php`, `2021_05_11_152445_leave_logs_tbl.php`; catalogue/remodel in September 2022; leave-log linkage/scope/tardy type in January–February 2023; fractional number-of-days changes in 2025. Reconstruct the final schema rather than copying only create migrations, including typo-prone/obsolete fields.

Numeric leave IDs observed in code: 1 VL, 2 SL, 3 FL, 6 SPL, 7 solo parent, 12 emergency, 14 wellness. Do not reseed new IDs in an order that changes these mappings. Preserve the imported catalogue and replace magic IDs with stable codes. Details code `7` is Monetization of Leave Credits and uses a special no-selected-dates/credit-count path. Other form detail labels are `1` Within the Philippines, `2` Abroad, `3` In Hospital, `4` Out Patient, `5` Completion of Master's Degree, `6` BAR/Board Examination Review and `8` Terminal Leave. Detail codes and leave type IDs are separate domains.

## Observed application and decision flow

1. Submit sends mail to active HR, then creates/updates the application under the current user, with filing date today in Asia/Manila. Submitted days, type, detail code/specify, commutation and FL-for-disapproval flag are accepted. Type-2 FL-for-disapproval can set recommendation Disapproved. There is no transaction across mail, header, dates and status.
2. Controller replaces inclusive dates unless details code 7; appends `For HR Cert`. Refiling a returned request can update the same header and date rows.
3. HR certification routes division-1 staff/chiefs to ED, ED's own application to `For CAO Witnesseth` (position-ID lookup), other staff to `For DC Approval`.
4. DC ordinarily records recommendation/reason and advances to `For ED Approval`, including a disapproval recommendation. Active OIC can produce the requested final recommendation directly. CAO witness branch also has special final handling. ED records final requested recommendation and reason.
5. Controller appends the returned status. On Approved, it debits `w_pay` from the submitted request unless detail 7 uses header days. The model accepts `w_pay`/`wo_pay` arguments but does not visibly persist them in its approval method, so with/without-pay metadata needs reconciliation.
6. Ordinary leave debits its type. FL commonly debits both VL and FL when FL remains and `fl_for_disapproval != Y`; with no FL balance, it debits VL only. In the FL-for-disapproval branch, early `return 4`/`return 5` precede debit statements, leaving them unreachable. The autoapproval implementation differs and does create these FL debits. This needs a policy decision and regression fixture.
7. Primary debit ID is saved to `leave_log_id`. Related FL debit is not directly linked by a foreign key.
8. Return-request records `Returned by HR` and a return message, then emails employee; the status actor is currently the applicant ID. Ordinary delete removes application, inclusive dates and scoped status events, but does not explicitly reconcile associated debits. Preserve the actual history and ledger linkage in the rebuild rather than silently deleting audit evidence.

Observed states include `For HR Cert`, `For DC Approval`, `For ED Approval`, `For CAO Witnesseth`, `Approved`, `Disapproved`, `For Cancellation`, `Cancelled`, `Returned by HR`. Duplicate-date checks allow cancelled variants, but historical joins in several overviews/calendars can select requests based on an old approval event. New effective-state queries must use latest scoped status consistently.

### Ledger units, accrual and reset

Leave amounts are **days**, usually three decimal places. OUT subtracts and IN adds to the last ledger total. For OUTs in `insert_logs_and_scope`, a four-day-workweek employee's VL/SL amount is multiplied by **1.25**, except activity strings containing `credit for:`. The separate tardiness insertion method does not apply that multiplier. Formatting with `number_format` is used inside arithmetic; preserve mathematical intent using decimal values rather than formatted strings.

For types 3/6/7/12/14, the general insertion method looks up the latest total by **created-at year**; VL/SL carry across years. `leave_logs` selects annual or all-time totals based on `leave_types.accumulation`. Scope date and created-at date are therefore not interchangeable, especially for backdated entries.

`auto_leave_credits()` runs through nonarchived staff eligible as Permanent or Contractual Regular and skips employees considered terminated. In per-user transactions it posts once per matching activity text:

| Grant | Observed amount | Scope |
| --- | --- | --- |
| VL and SL | 1.25 days each | Current month end, activity includes month/year |
| FL | 5 days | Current December 31 |
| SPL | 3 days | Current December 31 |
| Wellness (14) | 5 days | Current December 31 |
| Solo parent (only `solo_parent = Y`) | 7 days | Current December 31 |

Daily execution means a month's full grant can appear before month end; it is not a proration calculation. Uniqueness currently depends on exact activity text and checks an alternate legacy month/year spelling for VL/SL. No complete hire-date proration or entitlement-policy engine is demonstrated. Annual reset behavior is lookup-by-year plus a new grant, not an explicit prior-year reversal.

Attendance deductions and no-record behavior are specified in module 05. They can produce negative balances; do not invent automatic SL substitution or cash deduction policy.

### Cancellation, undo, adjustments and overrides

`cancel_leave(type = 1)` appends For Cancellation. Other types append Cancelled. If an Approved event ever exists and the application has a linked debit, it posts an IN reversal for that debit; for VL/FL it attempts to locate an FL debit by same creation date/activity and nearest ID. Repeating cancellation can repeat reversal because an historical Approved event still exists. Undo cancellation deletes a status event and does not reverse any credit restoration. Ledger deletion simply deletes a selected employee/log match without recalculating all later running totals. Manual adjustments accept submitted type/action/credit/scope. HR override creates Approved leave/dates with current workweek scaling but does not create the normal linked debit.

### Seven-day autoapproval

`LeaveController::ApproveAFL()` finds any `For ED Approval` event older than seven days, excluding IDs with any Approved event; it does not consistently exclude every newer terminal/returned state. It invokes approval logic dependent on `Auth`, then uses `create_status3` inside a per-request transaction and posts debits. The HTTP cron entry point does not establish an authenticated approver. The method's existence does not prove it is externally scheduled. Do not enable timed approval in the new app until authority, last-effective-state eligibility, actor identity and timing are explicitly approved.

## Outputs, dependencies and migration concerns

Outputs: own leave/balance/history, HR/division/ED/OIC queues/counters, staff-credit maintenance, leave status history, leave calendar including other attendance events, printable application with signatures, leave-card/template views and synchronous notifications. Depend on employee types/workweek/solo-parent status/hire/termination, catalogue, Table4, holidays, OB/CTO, effective approvers/OIC, signature files, mail and scheduler.

Migration must preserve applications, dates, full status history and both primary/secondary debit provenance. Reconcile orphan `leave_log_id`, repeated reversal/debit events, negative totals, deleted-log gaps and annual-year versus scope-date differences. Snapshot balances by employee/type at cutoff and compare recomputed ledger movement with displayed last totals. Avoid recalculating historical days with today's workweek. Retain existing policy codes while converting double arithmetic to exact three-decimal day values.

**Unresolved:** approved entitlements/proration, four-day conversion policy, monetization/detail-7 behavior, actual allowed half-day filing, FL-for-disapproval debits, autoapproval authority, without-pay handling, refile after cancellation/disapproval, correction after final approval and precise document retention/signature requirements.

## Implementation checklist

- [ ] Import catalogue, applications, inclusive dates, scoped status events and ledger with original units/IDs and provenance.
- [ ] Obtain a signed decision log for accrual/reset/conversion/proration/FL/autoapproval/without-pay policies.
- [ ] Implement validated type-specific forms, date conflicts/holidays, returned-request resubmission and effective-day calculation.
- [ ] Implement HR/DC/ED/OIC/CAO state transitions and record authority, actor and decision snapshot.
- [ ] Calculate paid/unpaid days and all debit lines server-side; post decision and ledger movement atomically.
- [ ] Link every debit/reversal to request, original movement and affected entitlement type; ensure idempotency.
- [ ] Implement reviewed cancellation/undo/amendment without deleting financial/audit evidence.
- [ ] Implement exact-decimal grant/debit service with effective schedule and structured idempotency keys instead of activity text.
- [ ] Implement audited manual adjustments, correction workflow and restricted ledger views.
- [ ] Rebuild calendar, queues, balances and printed forms from consistent latest state and snapshot data.
- [ ] Run reviewed scheduled grants/tardy deductions with locks, monitoring and reconciliation reports.

## Acceptance checklist

- [ ] Cross-user edits/prints, cross-division approvals, forged ledger changes and unauthorized timed approval are rejected.
- [ ] HR, ordinary chief, OED, chief applicant, ED applicant, CAO and effective OIC follow approved routes.
- [ ] Resubmission and multi-date filing are atomic, reject duplicate/conflicting dates and handle detail 7 correctly.
- [ ] Approval/retry produces precisely the approved VL/SL/FL/other ledger lines once, including FL-for-disapproval fixtures.
- [ ] Four-day conversion, three-decimal rounding, annual reset, backdating, leap year and hire/termination boundaries match approved rules.
- [ ] Repeated grant jobs and concurrent approval cannot double-credit/debit.
- [ ] Cancellation restores only original debits once; undo/amendment restores the proper net effect, including secondary FL movement.
- [ ] Manual corrections preserve audit history and recompute affected balances consistently.
- [ ] Calendar, request details, printed application, staff balances and ledger reconcile at a frozen cutoff.
