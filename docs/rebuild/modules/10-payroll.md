# 10. Payroll, loans, payslips and disbursement exports

**Observed** means implementation evidence; **Proposed** means rebuild requirements; **Unresolved** identifies a decision needed. Observed allowances and tax percentages are not verified statutory rates. Obtain current approved finance rules separately before enabling production calculation.

## Purpose, actors and authorization

Prepare payroll periods, select eligible staff, calculate/store earnings and deductions, maintain loan categories, copy a previous payroll, and export regular/CSW payroll, payslips and disbursement vouchers. Existing payroll types include Regular Payroll, CSW / Probationary Payroll, Mid-year Bonus and Year-end Bonus. HR/payroll staff are the intended administrators; printed forms include certification/approval signatures.

`HRPayrollController` applies authentication only. Arbitrary payroll/staff/loan reads, edits, deletion and exports need explicit payroll capability and scope checks in the rebuild. Browser confirmation text is not server authorization. Current source provides no complete approval/locking/release lifecycle: `Payroll::store` writes status `--`. Do not invent existing draft/posted/paid semantics; a reviewed immutable finalization workflow is proposed.

## Source map and entry points

| Source | Responsibility |
| --- | --- |
| `app/Payroll.php` | Period header CRUD and compatible previous-period choices |
| `app/PayrollStaff.php::store`, `index`, `edit`, `load_previous_payroll`, `_delete` | Staff-line persistence, report projection, legacy copy and row deletion |
| `app/Loans.php`; `app/PayrollLoans.php`; `HRPayrollController::loan_store`, `loan_delete` | Provider/category catalogue and payroll-specific deductions |
| `app/SalaryGrade.php::get_salary`; `app/User.php::payroll_details`, `user_by_type` | Basic salary by position grade and step; employee eligibility |
| `public/js/hr_staff_payroll.js` | Most current calculation rules, CSW deductions and weekly allocation |
| `resources/views/hr_payroll/staff.blade.php`, `bonuses.blade.php`, `index.blade.php`; `public/js/hr_payroll.js` | Input forms and actions |
| `HRPayrollController::reportPayrollRegularPaySlip`; `app/Exports/PayrollRegularExport.php`, `PayrollCswExport.php`, `CswDisbursementVoucherExport.php` | Print/export data and recalculations |

Routes: `/hr/payroll`, `/hr/payroll/store`, `/hr/payroll/edit`, `/hr/payroll/staff/{payroll_id}/{period}`, `/hr/payroll/staff/store`, `/hr/payroll/staff/edit`, `/hr/payroll/staff/delete`, `/hr/payroll/staff/load_previous_payroll`, `/hr/payroll/staff/loan/store`, `/hr/payroll/staff/loan/delete`, `/report/payroll/regular/{id}/{date}`, `/report/payroll/regular/payslip/{id}/{date}`, `/report/payroll/csw/{id}/{date}`, `/report/payroll/csw-disbursement-voucher/{payroll_id}/{staff_id}`. `/hr/payroll/print` references `printReport`, absent in the inspected controller. `/hr/payroll` is declared twice.

## Data contract

| Table | Fields and meaning |
| --- | --- |
| `payroll` | Type, payroll number, fund cluster, date range, creator, status and timestamps |
| `payroll_staff` | Payroll/user IDs, regular/contractual/periodic salaries, PERA/RATA/bonus, tax/RLIP/PhilHealth/HDMF/MP2/optional policy/ASSERT; historical named loan columns; gross/total deduction/net; other compensation/deduction fields; CSW payment/fund and attendance/tax amounts |
| `loans` | Provider `GSIS`, `HDMF` or `LBP`, loan name; category catalogue, not an employee loan principal/schedule engine |
| `payroll_loan` | Payroll/user/loan IDs (legacy string columns), two-decimal amount; dynamic deduction lines |
| `salary_grade`, `positions`, `users`, divisions | Salary steps 1–8, position-to-grade mapping, employee step/type and current reporting hierarchy |

Schema sources: `2021_06_26_002040_salary_grade_table.php`, `2021_07_13_211058_payroll_tbl.php`, `2021_07_13_213737_payroll_staff_tbl.php`, `2021_08_02_140159_payroll_phic.php`, `2021_08_03_223233_bonuses_field.php`, CSW create/drop/consolidation migrations in April 2026, and `2026_05_12_000000_create_loans_table.php` / `2026_05_12_000001_create_payroll_loan_table.php`. Final monetary fields are often double(12,2); proposed target uses exact decimal or integer centavos. Preserve case-sensitive legacy `Absence`/`csw_Absence` mappings and obsolete field names explicitly.

## Observed workflow and formulas

1. Create/update payroll header with type, fund, number and dates; actor becomes current user and status resets to `--`.
2. `staff_index` selects users using `User::user_by_type`, loads loan categories and compatible previous payrolls, then shows regular/CSW or bonus forms.
3. Basic salary is current position salary-grade row at employee step increment. No effective-date salary history is selected here. The browser loads this value, eligibility/type and division/chief.
4. Regular Permanent/Contractual Regular staff get browser defaults PERA **2,000**, RATA **10,000** for user type 2 or **18,000** for type 4. Other roles do not receive that RATA default. These are configurable legacy defaults to confirm with Finance.
5. Regular gross = sum of fields marked compensation, typically salary + PERA + RATA. Deductions = sum of entered contribution/loan fields. Net = gross - deductions. `PayrollStaff::store` strips currency/comma characters and trusts submitted calculated gross, deductions and net; no authoritative server recomputation or statutory contribution/tax table engine is demonstrated.
6. Regular weekly preview uses `base = round(salary - deductions, 2)`, `weekly = floor(base / 4)`, `remainder = round(base - 4 * weekly, 2)`. Week 1 = weekly + RATA; weeks 2/3 = weekly; week 4 = weekly + PERA + remainder. Thus fractional and integer remainder goes to week 4.
7. Payslip controller uses a different algorithm: remove PERA/RATA from stored net, floor it, repeatedly subtract until divisible by four (always subtracts at least one), split that result and put remainder/PERA into week 4. When already divisible by four, this moves an extra four units into week 4. It also recomputes displayed salary from **current** grade, so historical payslips can change. Choose one reviewed allocation rule and freeze historical amounts.

### CSW / probationary computation

Browser defaults `periodic_salary = basic_salary / 2`, to two decimals. Inputs are late minutes, undertime minutes and absence days; current monetary amounts are:

```text
per_minute = monthly_basic / 22 / 8 / 60
per_day = monthly_basic / 22
late_amount = round(per_minute * late_minutes, 2)
ut_amount = round(per_minute * undertime_minutes, 2)
absence_amount = round(per_day * absence_days, 2)
taxable_subtotal = periodic_salary - late_amount - ut_amount - absence_amount
tax_3 = round(taxable_subtotal * 0.03, 2)
tax_5 = round(taxable_subtotal * 0.05, 2)
total_tax = round(tax_3 + tax_5, 2)
total_deduction = round(late_amount + ut_amount + absence_amount + tax_3 + tax_5, 2)
net = round(periodic_salary - total_deduction, 2)
```

Both 3% and 5% amounts are added by current JS; there is no shown eligibility selector choosing one. The divisor is always 22 days/8 hours, even when an employee has a four-day/10-hour schedule. Negative taxable subtotal is not clamped in that function. Inputs are manually entered rather than automatically fetched from DTR. Editing reconstructs minutes/days from stored monetary deductions, which loses original input precision. Rebuild must store source minutes/days, denominator and rate version, and verify the intended tax treatment rather than copying both percentages as policy.

`PayrollStaff::store` branches by **employee type** inside regular or CSW payroll: permanent/contractual regular writes regular fields; other employees write CSW fields. Payment mode/fund strings are passed through the currency normalization helper. Validate payroll-type/employee eligibility explicitly to prevent mismatched fields.

### Bonuses, copying, loans and deletion

Bonus input's keyup handler sets net to entered bonus amount. Server stores salary, bonuses and submitted net without a complete bonus eligibility/proration policy. Previous-bonus choices include either mid/year-end type.

Copy-previous skips existing staff but copies legacy fields incompletely: it reads nonexistent/old `salary` into both salary columns, copies `hdmf_mp2` into `cpl`, and does not copy dynamic payroll-loan rows or all CSW fields. Rebuild copying should create a reviewed new-period draft and recalculate date-dependent values using current approved inputs; preserve original copy provenance.

Loan category create/update validates provider and prevents duplicate provider/name in application code. Per-staff submitted positive category amounts upsert by payroll/user/loan, nonpositive amounts delete; absent amounts may remain. Deleting a loan category deletes all historical `payroll_loan` amounts for it. Staff-row deletion transactionally deletes that employee's dynamic loans. Whole-payroll deletion deletes staff rows and header but not explicitly all dynamic loan rows in the model. Proposed: preserve historical categories/amounts by retiring categories and use controlled payroll voiding, with explicit cleanup rules for abandoned drafts.

## Outputs and dependencies

Outputs: payroll headers/staff tables, bonus tables, regular Excel payroll (`payroll-{dates}.xlsx`), HTML printable payslips, CSW Excel payroll, employee CSW disbursement voucher and loan category/amount views. Regular export groups and sorts staff by current position group/grade and aggregates dynamic loans. Some report projections replace stored salary with current salary and current employee names/positions/division/chief/signatures; final output must use payroll snapshots in the rebuild. CSW exports use `NumberFormatter` for amount words and embed the organization logo through PhpSpreadsheet drawings; deployment needs PHP intl, Excel/ZIP/XML/image support and writable temporary storage.

No complete payroll bank-file transmission, remittance submission, accounting posting, government API or employee self-service payslip authorization contract was verified. Do not mark those features implemented without further evidence.

## Migration and decision gates

Import headers, staff monetary lines, dynamic loans and catalogue separately; reconcile old fixed loan columns against dynamic rows to avoid double counting. Determine whether historical reports used fixed columns, dynamic rows or both. Compare every payroll's stored gross/deduction/net totals and exported totals before correcting anything. Freeze position, grade/step, employee type, division, signatories, rate inputs and weekly allocation for each finalized run. Review orphan loan/staff records from past deletion. Preserve original monetary representation before exact-decimal conversion and document any centavo differences.

**Unresolved:** payroll eligibility by type, pay-period rules/proration, salary effective dates, allowance entitlement, approved statutory rates and tax bases, simultaneous CSW taxes, 22/8 divisor, rounding mode, negative net rules, bonus policy, release/approval/void lifecycle, report signature assignments and record retention. These are Finance decisions; do not infer them from UI defaults.

## Implementation checklist

- [ ] Import and reconcile all payroll/loan/salary mappings with sanitized per-run control totals.
- [ ] Define payroll administration/export policies and a reviewed finalization/correction lifecycle.
- [ ] Create exact-money server calculation service with versioned effective rates and stored source inputs.
- [ ] Implement validated period/type/fund/employee eligibility, duplicate staff protection and consistent field mappings.
- [ ] Implement regular compensation/deductions and a single reviewed weekly allocation algorithm.
- [ ] Implement CSW attendance deductions with original minute/day inputs, approved divisor/tax treatment and centavo rounding.
- [ ] Implement reviewed bonus rules and copy-previous as a new-period draft with complete loan/CSW mappings.
- [ ] Implement loan category retirement and protected historical deduction lines; guard totals and concurrent edits.
- [ ] Freeze finalized payroll snapshots; generate all reports from stored finalized amounts and identities.
- [ ] Rebuild regular/CSW Excel, payslip and DV outputs with stable totals, grouping, signatures and amount words.
- [ ] Produce reconciliation reports for fixed/dynamic loan duplication, orphans and legacy report/stored salary differences.

## Acceptance checklist

- [ ] Unauthorized users cannot inspect, mutate, delete or export payroll/loan data by guessing IDs.
- [ ] Forged browser totals, allowance amounts, employee types or percentage inputs cannot bypass server rules.
- [ ] Regular and CSW calculations cover zero amounts, centavo fractions, large values, missing salary, absences exceeding period and approved negative-net behavior.
- [ ] All weekly amounts sum exactly to net and match screen/payslip/Excel, including a base already divisible by four.
- [ ] CSW input minutes/days survive editing unchanged; separate rounding steps match approved Finance examples.
- [ ] Changing salary grade, position or loan category after finalization cannot change historical payroll or exports.
- [ ] Copying previous payroll maps salary/CSW/loans correctly, avoids duplicates and recalculates approved date-dependent values.
- [ ] Category retirement, staff removal, payroll voiding and retry/concurrent edits retain auditable consistent totals.
- [ ] Exported regular/CSW/DV totals reconcile to finalized data; PHP intl/logo/temp-file failures are handled clearly.
