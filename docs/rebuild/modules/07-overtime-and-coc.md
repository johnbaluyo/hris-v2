# 07. Overtime authorizations and compensatory overtime credits (COC)

**Observed** is code evidence, **Proposed** is rebuild work, and **Unresolved** needs a policy decision. Keep overtime **authorization**, measured overtime **attendance**, monetary overtime **pay**, and COC **ledger credit** as distinct concepts. The repository connects them incompletely.

## Purpose, actors and authorization

Employees request overtime dates and terms. Division chiefs recommend approval; ED finalizes. HR can create already-approved authorizations for multiple staff with a request attachment. HR maintains COC credits consumed by CTO. Controllers require authentication but do not consistently enforce role, ownership, division or transition policies. Approval/cancellation can record a request-supplied actor ID. The HR-versus-employee filing branch is selected using submitted `filed_by`.

**Proposed:** separate own filing, division recommendation, ED decision, HR bulk authorization and COC adjustments into explicit policies; authorize the server-selected action and derive actor from authentication.

## Source map and data

| Source | Responsibility |
| --- | --- |
| `app/Overtime.php::file_ot`, `check_dates`, `approvedisapprove_ot` | Employee filing, duplicate-date check and status changes |
| `app/Http/Controllers/OvertimeController.php::file_ot`, `index`, `index_hr`, `allstaff`, `cancel`, `print_form` | Personal/HR/division views, bulk approved filing, attachment and print |
| `app/OvertimeDates.php::insert_overtime_dates` | Comma-separated date insertion, duplicate check within a request |
| `resources/views/overtime/`; `public/js/ot.js`; `public/js/hr_overtime.js` | Existing form choices, employee selector and authorization-to-enter output |
| `app/DTR.php::save_override`, `compute_work_hours` | Measured OT and weekend COC field calculations |
| `app/CTO.php::ctoTotal`; `CTOController::view_coc`, `get_user_coc`, `save_coc_log`, `delete_coc_log`; `app/CocLog.php` | Legacy derived COC views and current maintained credit ledger |

Browser routes are the `/employee/ot/*` family and `/hr/overtime`; verify exact route methods against `routes/web.php`. COC staff-credit actions use `/cto/*` endpoints and are shared with [CTO](08-compensatory-time-off.md).

`overtimes`: employee, date filed, `day_type`, `start_time`, requested `ot_hours`, `ot_type`, purpose, request file, creator and timestamps. `overtime_dates`: header ID, inclusive date, nullable `used_coc_hours`. Schema sources: `2021_05_02_190440_create_overtime_table.php`, `2021_05_17_220647_create_overtime_dates_table.php`, `2021_07_25_132126_create_ot_hours_rendered.php` and later numeric/file migrations.

UI day types: `1` Saturday/Sunday; `2` Legal Holiday; `3` Declared Non-working Holiday. UI OT types: `1` With Overtime Pay; `2` Without Overtime Pay; `3` With Compensatory Time Off. These choices do not establish government policy or a complete pay-rate engine.

Shared statuses use `request_type = OT`: `For DC Approval`, `For ED Approval`, `Approved`, `Disapproved by DC`, `Disapproved by ED`, `Cancelled`. Latest scoped status should determine effective state.

`coc_log` is the maintained credit ledger: `user_id`, integer-minute `credit_amount`, `remaining_amount`, `total_credits`, `status`, `scope_date`, remarks, logged-by actor and timestamps. Statuses observed across COC/CTO code are `IN`, `OUT`, `PENDING_OUT`, `COMPLETED_OUT`. Initial decimal values were changed to integer fields by `2025_09_17_135736_change_credit_type.php`; do not assume older rows were correctly converted from hours to minutes merely because the column type changed.

## Observed workflows and formulas

1. Employee filing writes a header, `For DC Approval`, then unique dates parsed from a comma-separated string. Even a chief's filing begins at DC status although recipient selection can choose ED. Mail send calls in `Overtime` are commented out; preparing an email array is not evidence of delivery.
2. Non-ED approval appends `For ED Approval`; ED approval appends `Approved`. Rejections append the corresponding DC/ED disapproval and reason. Cancellation appends `Cancelled`. The duplicate-date check ignores effective status, so cancelled dates can still block refile.
3. HR filing accepts a shared attachment under `public/images/overtime`, parses staff **names**, resolves each with `CONCAT(firstname, ' ', lastname)`, and creates one already-approved header/date set per name. Duplicate names, missing names or partial failure are not robustly handled. A similarly named model method `file_ot_hr` loops selected staff but assigns the current actor as employee; the controller has a different actual implementation. Build from the routed controller behavior, then correct identity selection.
4. Rendered OT comes from DTR, not the requested hours. Explicit `ot_out - ot_in` is clamped to 0–2 hours; weekend overrides may use all rendered work as OT and write `dtr.coc = hours * 1.5`.
5. Older COC views join approved overtime dates to DTR dates within the previous year, multiply Saturday/Sunday hours by **1.5**, and other days by **1.25**. Some joins match by date without matching DTR employee, so a shared work date can overcount or cross employees. `CTO::ctoTotal` does not return its computed total in the inspected implementation. These are not a reliable ledger posting pipeline.
6. Current COC adjustments in `CTOController::get_user_coc` parse submitted `HH:MM` into `hours * 60 + minutes`, create an IN or OUT row, and set both credit and remaining amount to positive entered minutes. Running total is previous total plus/minus entered minutes. Automated CTO OUT rows instead use a **negative** credit amount and commonly omit remaining amount. Normalize the semantic difference during migration.
7. Active balance displays use sum of remaining IN minutes whose `scope_date >= now()->subYear()`, minus sum of remaining OUT minutes across all dates. Expiring-credit warning uses an exclusive date window between today minus a year and today plus one month minus a year. Print and legacy views use other balance calculations.
8. COC editing accepts credit, remaining and total values independently. Deletion recalculates historical running totals from ordered IN/OUT credit amounts but does not reconstruct consumption allocations. Negative minute `HH:MM` formatting uses floor and modulo without a dedicated sign formatter.

There is no verified automated bridge that posts each approved and attended OT session once into `coc_log`, and no complete overtime-pay calculation connected to payroll was found in these sources. Treat automatic earning and overtime monetary payment as **unresolved scope**, not completed existing features.

## Outputs, dependencies and migration

Outputs: personal/HR/division OT tables, approval details, authorization-to-enter printed form, attached source authorization, COC staff balances/log detail and expiry warnings. Depend on employee identity, divisions/positions/chief/ED, DTR work measurements, holiday calendar, shared statuses, private file storage and the CTO consumption module.

Migrate authorizations and raw OT attendance independently; link by employee/date only after resolving ambiguous rows. Retain used-COC legacy fields and original units for reconciliation. Import COC with provenance and an explicit unit-conversion strategy; do not blindly multiply integer rows by 60. Reconcile earned, consumed, expired and remaining minutes per employee as of a frozen cutoff. Preserve old outputs as evidence without copying personnel data into fixtures.

**Unresolved:** earning authority, maximum OT and daily/monthly/yearly COC caps, holiday multipliers, Friday treatment for four-day schedules, expiry based on earning versus posting date, minimum increments/rounding, monetary overtime policy, attachment requirements and initial balances. The observed 1.25/1.5 multipliers require policy confirmation before use in the new earning service.

## Implementation checklist

- [ ] Import headers, dates, scoped status history, request files, actual OT measurements and COC logs with unit provenance.
- [ ] Replace name-based bulk selection with stable employee IDs and atomic or explicitly resumable bulk requests.
- [ ] Implement validated filing, duplicate-date policy, guarded DC/ED transitions, cancellation and audited HR authorization.
- [ ] Rebuild personal/division/HR listings and printable authorizations with server-side scope enforcement.
- [ ] Implement private attachment validation/download and optional queued notifications according to an approved delivery decision.
- [ ] Implement a COC ledger with earning source, minute units, expiry, adjustment reason, actor and reversible allocations.
- [ ] Add reviewed earning rules only after confirming multipliers, caps and source authorization/attendance requirements.
- [ ] Reconcile existing ledger balances; expose unresolved conversions for HR review instead of silently adjusting them.
- [ ] Connect CTO consumption to the same ledger service; retain complete correction/reversal history.

## Acceptance checklist

- [ ] Cross-employee IDs, forged HR filing flags and unauthorized approvals/cancellations are rejected.
- [ ] Bulk filing handles duplicate names by ID, missing staff, repeated submissions and attachment failure predictably.
- [ ] Approved authorization does not itself create rendered OT hours; measured work from another employee never contributes.
- [ ] Replayed earning or adjustment events produce one net ledger effect.
- [ ] Minute conversion handles 00:00, multi-day hours, malformed `HH:MM`, negative values and rounding under approved rules.
- [ ] Boundary expiry dates, partially consumed credits and credit reversal reconcile to the same balance on every screen/report.
- [ ] OT with-pay/without-pay/COC selections have approved behavior and no invented rate calculation.
