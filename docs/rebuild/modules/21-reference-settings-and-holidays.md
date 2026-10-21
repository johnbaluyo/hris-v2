# 21 Reference settings and holidays

## Purpose and sources

Maintain salary tranches/grades/steps, leave conversion tables and dates that affect attendance and leave: regular holidays, special nonworking dates, work suspensions and employee-specific special orders. **Observed** describes current behavior; **target** is the rebuild; **unresolved** needs a business decision.

- [HRSettingsController](../../../app/Http/Controllers/HRSettingsController.php): settings index, salary-grade CRUD, table 1–4 feeds.
- [HolidayController](../../../app/Http/Controllers/HolidayController.php): holiday CRUD, `check_dtr` and `get_tardiness`.
- [SalaryGrade](../../../app/SalaryGrade.php), [Holiday](../../../app/Holiday.php), [Table1](../../../app/Table1.php), [Table2](../../../app/Table2.php), [Table3](../../../app/Table3.php), [Table4](../../../app/Table4.php), [LeaveCreditMinuteBasis](../../../app/LeaveCreditMinuteBasis.php).
- [Settings templates](../../../resources/views/hr_settings), [holiday template](../../../resources/views/system_param/holidays.blade.php), [route inventory](../inventories/ROUTES.md), [schema ledger](../inventories/SCHEMA.md).

Organization divisions and positions belong to [module 02](02-organization-and-staff.md); use their management behavior rather than assuming the empty `DivisionController` supplies CRUD.

## Observed data contracts

| Table/reference | Fields and use |
| --- | --- |
| `salary_grade` | `tranche`, displayed numeric `salary_grade`, `step_1` through `step_8`; initial schema uses integer amounts |
| Position salary reference | `positions.salary_grade` is a lookup ID into `salary_grade`; `User.step_increment` selects one of eight columns |
| `table_1` | `number_of_months`, `vl_earned`, `sl_earned` |
| `table_2` | `number_of_days`, `vl_earned`, `sl_earned` |
| `table_3` | `days_present`, `days_on_leave_wo_pay`, `leave_credits_earned` |
| `table_4` | `minutes`, `equiv_day` |
| Minute-basis reference | Separate `leave_credit_minute_bases` model convention and migration/seed; inspect actual data/formula usage before combining it with table 4 |
| `holidays` | Holiday name/date/type, optional comma-separated employee IDs and suspension cutoff time |

Initial conversion tables use double values with three decimal places for earned/equivalent days (and two for table 3 day counts). New decimal precision and rounding need approved fixtures. Do not replace these tables with a guessed formula or treat existing values as current legal policy.

## Current workflows and access

1. `/hr/settings` loads salary grades and divisions. Settings controller declares authentication but no general HR/admin policy in its constructor.
2. Salary feeds/CRUD use POST routes under `/hr/settings/sg`. `save_sg` accepts `sg_id`, tranche, grade and `step1` through `step8`; a zero ID inserts, other IDs update. Delete physically removes a row without a demonstrated reference guard.
3. `SalaryGrade::get_salary` finds the employee's position, resolves its salary-row ID, chooses the step column, and returns that amount. Missing position/grade or invalid step is not safely handled here.
4. Table 1–4 endpoints return JSON lists; the controller does not implement editing for these conversion tables. Treat administrative editing as a proposed requirement needing scope confirmation.
5. Holiday list/CRUD uses `/system-param/holidays` plus historical aliases, including misspelled `/system-parsam/holidays`. Save uses request `action` to select insert vs update and accepts name/date/type/employee IDs/suspension time.
6. `HolidayController` has no constructor authentication declaration. Direct-ID create/edit/delete endpoints require explicit protection in the target.
7. Employee lists are stored as CSV. `Holiday::getEmployeesAttribute` exposes resolved user/position/division models; the edit endpoint instead reads the raw original attribute and returns a simple ID array.
8. Empty employee/cutoff inputs do not clear old values in the save method because assignment is conditional on non-null. Target updates must define deliberate clearing semantics.
9. `Holiday::check_holiday` only treats `Regular` and `Special Non-Working` as blocking holidays. Other consumers separately process `Special Order` and `Work Suspension`. `get_tardiness` contains additional attendance logic; consolidate this into the attendance service, keeping reference management separate.

## Target requirements and migration

Represent salary/tranche values and attendance/leave policy references with effective dates and versioning. Prevent deleting references used by historical records; archive or supersede them. Snapshot approved salary values into payroll/certificates. Distinguish a grade's displayed number from its database identifier throughout imports and APIs.

Normalize employee-specific holiday/special-order memberships. Resolve date/type duplication, agency-wide versus selected-employee scope, partial-day suspension behavior and overlapping special orders. Record the authority/source behind changed reference values and the period from which reprocessing is allowed.

Resolve D05/D07/D08/D13: authoritative conversion values, effective dates, decimal scale, employee scope and who may change them. New accrual/contribution rates must come from approved inputs; this documentation is not a statutory rate source.

## Build checklist

- [ ] Implement reference read/manage policies and auditing for salary, holiday and policy changes.
- [ ] Map salary row IDs, displayed grades, tranches and steps without conflating them.
- [ ] Implement validated salary create/edit/archive, eight-step lookup and effective-date behavior.
- [ ] Preserve approved conversion data, labels, decimal precision and read feeds; decide whether editing is in release scope.
- [ ] Implement holiday/suspension/special-order CRUD, validated times and normalized employee scope.
- [ ] Define clearing, duplicate-date/type and overlap semantics, including removed employees and changed cutoff.
- [ ] Feed one effective-dated attendance/leave service; remove calculations from reference controllers.
- [ ] Guard references used by payroll, positions, requests and historical reports from destructive deletion.
- [ ] Replace operational SQL seeds with reviewed nonpersonal reference seeds and import reconciliation.
- [ ] Map legacy aliases deliberately; retire misspelled endpoints with compatibility handling if callers remain.

## Acceptance checklist

- [ ] Only authorized administrators change reference values, including direct HTTP calls.
- [ ] Each of eight salary steps returns the correct tranche/grade amount; unknown references/steps fail clearly.
- [ ] Later salary changes preserve finalized historical payroll/certificate values.
- [ ] Conversion lookup boundaries and decimal results match approved fixtures.
- [ ] Regular/nonworking/special-order/suspension dates affect only the accepted employees and dates.
- [ ] Clearing cutoff/employee scope works; overlapping or duplicate entries follow the chosen policy.
- [ ] Calendar, leave and DTR consumers use consistent effective-dated reference values.
- [ ] Referenced salary/holiday data cannot be removed in a way that invalidates historical reports.
- [ ] Imports and fresh safe seeds reproduce accepted reference counts and values without employee data.
