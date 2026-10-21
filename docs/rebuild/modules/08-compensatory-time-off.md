# 08. Compensatory time off (CTO)

**Observed** means source behavior; **Proposed** means the target implementation; **Unresolved** means a decision is needed. CTO files an absence against available COC; COC earning/maintenance is described in [overtime and COC](07-overtime-and-coc.md).

## Actors, authorization and source anchors

Employees file and view CTO; HR certifies credits and maintains COC; division chiefs recommend; ED or an active OIC approves; HR can override and edit filed hours. `CTOController` applies authentication. `overview_index` redirects ordinary staff, but mutation methods and arbitrary-ID reads do not consistently enforce the same role/scope restrictions. Rebuild with policies for ownership, HR certification/adjustment/override, division recommendation and effective ED/OIC authority.

Primary sources: `app/CTO.php::file_cto`, `certify_cto`, `approvedisapprove_cto`, `check_dates`, `applyPendingCocDeductions`; `app/CTODates.php`; `app/CocLog.php`; `app/Http/Controllers/CTOController.php::index`, `overview_index`, `overview_index_oic`, `staff_index`, `cancel`, `update_hours`, `override_cto`, `print_cto`, `status_history`; `resources/views/cto/`; `public/js/cto.js`; `/cto/*`, `/cto-overview`, `/cto-overview-oic`, `/employee/cto/print/{id}`, `/hr/cto/override_cto` in `routes/web.php`.

`/cto/get_info` references a controller method absent in the inspected controller. Do not infer a contract from that dead route.

## Data and state contract

`c_t_o_s`: employee `emp_id`, date filed, `total_hours`, integer-minute `current_balance` snapshot, timestamps. `c_t_o_dates`: header `cto_id`, `cto_date`, hours, `remarks` (`AM`, `PM` or blank), legacy string `overtime_date_id`, timestamps. Header/table naming is legacy and may change only with explicit import mappings. Migrations: `2021_06_16_171754_create_c_t_o_s_table.php`, `2021_06_26_083833_create__c_t_o_dates_table.php`, `2025_07_28_172832_rename_to_remarks.php`, `2025_07_30_151013_add_total_hours.php`, `2025_10_27_143254_add_current_balance.php`. COC schema and units are in module 07.

Shared status type is `CTO`. Observed states: `For HR Cert` → `For DC Approval` or `For ED Approval` → `Approved`; DC approval normally advances to ED. Terminal outcomes include `Disapproved by DC`, `Disapproved by ED`, `Cancelled`. Some legacy checks compare to generic `Disapproved` or `Cancelled by Employee`, so enumerate imported values instead of assuming all terminal variants behave equally.

## Observed workflow

1. Filing accepts a header total and displayed balance from the browser. Balance `HH:MM` becomes integer minutes. JSON `my_dates` contains date and `Whole Day`/`Half Day (AM)`/other half-day selection.
2. Date rows are calculated from the employee's **current** workweek: whole day = 10 hours for workweek 4, otherwise 8; half day = 5 or 4, with AM/PM remarks. Header total is not recomputed from these rows in this method. Status becomes `For HR Cert`; synchronous mail goes to all active type-3 HR users.
3. HR certification routes ordinary/HR staff to division chief, except division 1 goes to ED. Chief requests go to ED. A new status is recorded and mail goes to the approver, CC employee.
4. DC approval becomes `For ED Approval`. An OIC selected through `oic_index = yes` can finalize; ED approval finalizes as `Approved`. Disapproval records role-specific outcome and reason.
5. On final approval, requested `totalFiledHours` from the browser is multiplied by 60. Valid IN logs with positive remaining amount and scope date within one year are consumed. This query has no explicit FIFO order. Full requested minutes are debited even if available logs cannot cover them. OUT credit is negative, total is last log total minus requested minutes, scope date is today, remarks are `CTO-{id}`. No single transaction or idempotency guard surrounds all these changes.
6. Cancel is transactional. For an Approved request it selects the employee's **latest OUT log**, regardless of which CTO generated it; restores its absolute debit across partially consumed valid IN logs newest-first; deletes that OUT; appends Cancelled. This can reverse another request and cannot restore expired lots. Rebuild must use request-linked consumption allocations and reversal records.
7. `update_hours` validates a CTO/date array, changes matched date rows, recalculates header total, then looks for COC logs whose remarks equal the bare CTO ID. Final approval writes `CTO-{id}`, so those rows do not match. It updates credit/running total without reallocating IN remaining balances.
8. HR override writes a header/date plus Approved status directly; it does not initialize header total/current balance or consume COC. Treat that as an audit gap, not the required override behavior.

Date checking rejects Fri/Sat/Sun for four-day schedules and Sat/Sun for other schedules. Existing same-person/same-date requests block filing except Cancelled/Cancelled by Employee. It does not provide a complete cross-module overlap, holiday or insufficient-balance rule. Personal list excludes only generic Disapproved/Cancelled when showing filed totals, not role-specific disapprovals.

### Competing deduction mechanisms

`CTO::applyPendingCocDeductions()` is still active through `/applyPendingCocDeductions`. It finds `PENDING_OUT`, resolves CTO using bare numeric `remarks`, and deducts oldest valid IN lots only when `today > last_availment_date + 1 day` (strictly greater: it begins after that additional day has passed). It creates negative OUT, marks pending as `COMPLETED_OUT` and attempts to write `request_id`, which is absent from `CocLog::$fillable` and the inspected COC migrations. The newer final-approval path writes immediate OUT, not pending. Comments saying the pending mechanism is disabled are not executable behavior. Before cutover choose a single approved consumption date and reconcile outstanding pending logs to avoid double deductions.

### Balances and printed form

Personal/staff/edit balance = valid IN remaining minutes minus **all** OUT remaining minutes. Printing only sums valid IN remaining minutes. Header `current_balance` is a filing snapshot; `remaining_hours` on print is actually `current_balance - total_hours * 60`, hence minutes despite the name. Printed signatories include a literal HR user ID and current chief/ED, rather than a uniform effective assignment resolver. Some signature lookups do not scope request type. These require correction in a versioned form builder.

## Outputs and dependencies

Outputs: own request list, HR/division/ED/OIC approval overview, COC staff ledger/balance/expiry warning, date details, status history, printed CTO form and filing/certification/decision mails. Depends on COC units/expiry, identity and workweek history, active division/HR/ED/OIC assignments, signatures, shared status history, DTR exemption logic, mail and scheduler. `DTRController::checkCTO` clears late/undertime for qualifying half days without checking status; integrate approval-aware recalculation instead.

**Unresolved:** consume at approval or after availment; reserve credits while pending; expiry reference date and consumption priority; overdraw rules; handling partially consumed expired credits on cancellation; partial-date amendments; holiday/overlap constraints; authority and financial effect of HR override; whether filed-hours snapshots may be edited after approval.

## Implementation checklist

- [ ] Import headers, dates, snapshots, scoped status history and both legacy COC consumption formats; flag missing totals and ambiguous links.
- [ ] Select a single reviewed balance/expiry/consumption policy shared by filing, approval and printing.
- [ ] Compute total hours and available/reserved balance on the server from effective schedule and ledger data.
- [ ] Validate dates, half-day selection, holidays, overlap, eligibility and available credits within a transaction.
- [ ] Implement guarded HR/DC/ED/OIC transitions with immutable actors and queued notifications after commit.
- [ ] Lock request and credit lots when consuming; store per-lot allocations and an idempotent request-linked debit.
- [ ] Implement request-specific cancellation and amendment through compensating ledger events; never delete unrelated OUTs.
- [ ] Rebuild audited overrides and approved-hours correction with balance/attendance recalculation.
- [ ] Replace hardcoded print signatories with effective assignments and preserve filing/decision snapshots.
- [ ] Retire or migrate pending-out jobs after reconciling all pending/completed/immediate rows.

## Acceptance checklist

- [ ] Owner/HR/chief/ED/OIC can perform only authorized actions in their scope and effective period.
- [ ] Whole/half days derive 8/4 or 10/5 hours correctly, and tampered header totals/balances are ignored.
- [ ] Concurrent approval, double-click and retried jobs consume credits once; insufficient balance cannot silently overdraw.
- [ ] Two CTOs for one employee can be cancelled in either order with exact allocation restoration.
- [ ] Expiry boundary, pending reservations and cancellation after expiry follow the approved rule.
- [ ] Hour changes adjust the correct request-linked debit and every affected lot; approved override has its reviewed ledger effect.
- [ ] Personal view, HR ledger, edit modal and printed snapshot agree on units and balances.
- [ ] Cancelled/disapproved CTO cannot suppress DTR tardiness; approved AM/PM cases have reviewed attendance fixtures.
