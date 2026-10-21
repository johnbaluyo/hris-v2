# 13 — Individual development plans (IDP)

## Purpose and evidence

Prepare an employee's annual development plan from current position requirements, submit it to a supervisor, record the supervisor's signature, and provide training proposals to division learning plans.

**Observed** describes code behavior; **Proposed** specifies the rebuild; **Unresolved** requires clarification rather than invented behavior.

Sources: [IDPController](../../../app/Http/Controllers/IDPController.php), [IDP model](../../../app/IDP.php), [User relationships and chief routing](../../../app/User.php), [IDP views](../../../resources/views/profile/idp), [LearnAndDevController](../../../app/Http/Controllers/LearnAndDevController.php), [web routes](../../../routes/web.php). Migrations: `2024_11_19_175334_user_idp_table.php`, `2024_11_26_194413_idp_submit_date.php`, `2024_11_27_145655_user_hrdc_setting.php`.

## Actors and access

Observed: controller requires authentication. `index` scopes to the current employee. Store can accept `user_id` from the request; get, delete, submit and sign use supplied IDs without consistent ownership/supervisor authorization. `sign_dc` simply records the current user as approver. Supervisor display routes chiefs to the head of agency and other users to a chief in their division. The first user with `hrdc = Y` is displayed as HRDC signatory.

Proposed: employee owns drafts, assigned supervisor/chief approves eligible submitted plans, HRDC can perform a separate action only if that workflow is confirmed. The existence of HRDC fields in the database does not prove an implemented HRDC approval endpoint.

## Data and derived states

`users_idp` stores ID, `idp_year`, `user_id`, position and salary-grade snapshots (`curr_position`, `sg`), years-in-position/agency strings, `year1`/`year2`/`year3` flags, purpose and other-purpose detail, submission date, chief/HRDC approver IDs and approval dates, JSON `training`, timestamps. The model casts training to array; controller code may also manually JSON-encode it.

Observed training items created by the employee UI contain `training_value` and `title` text. Division planning later adds `budget`. They do not have stable item IDs in this UI.

Purpose options:

| Value | UI meaning |
| --- | --- |
| 1 | Meet competencies of current position |
| 2 | Increase competency level of current position |
| 3 | Meet competencies of next higher position |
| 4 | Other, with description |

There is no dedicated status column. Views/relationships infer draft/unsubmitted from missing `submit_date`, waiting signature from submitted and missing chief/HRDC approvals, and chief-signed from non-null submission/chief fields.

## Workflow and outputs

1. `/idp` loads the current employee, position/grade/division, position matrix/budgets, and their existing plans. It derives service duration from work-experience rows.
2. Service calculation groups recognized agency aliases (PSRTI/SRTC and full agency names) together; otherwise it uses the latest work-experience department. It calculates overlapping periods without intending to double-count, and formats years/months. Current-position service compares the position text of rows with the latest row's position.
3. `/idp/store` creates/updates the plan. It checks uniqueness of employee/year in application code, excluding the current ID on update. It accepts array or string training payloads and returns text success/error responses.
4. `/idp/get-idp/{id}`, `/idp/get-idp-with-matrix/{id}`, and `/idp/get-trainings` support editor/details. The matrix endpoint resolves the employee's current position matrix.
5. `/idp/submit-to-dc` records the current timestamp. `/idp/sign-dc` records current actor ID and approval timestamp. Existing methods do not enforce draft locks, readiness, supervisor identity or once-only transitions.
6. `/idp/idp_form/{id}` renders `profile.idp.idp_form`, using the employee's supervisor and current HRDC signatory. `/idp/delete` physically deletes the selected plan.
7. Chief-signed plans feed `/LnD` views and division L&D planning in module 12. Budgets may be added later to this same training JSON.

## Migration issues and decisions

- JSON array casts plus manual encoding can produce string/array/double-encoded variants. Normalize with reversible parsing, validate every item's shape, and report malformed payloads.
- No database uniqueness on employee/year is demonstrated. Add it after resolving duplicates and whether multiple revisions are allowed.
- Chief/HRDC approval columns are `date` in the create migration while handlers set timestamps. Define desired precision and preserve what was actually stored.
- Snapshot role/grade and service values can disagree with current user assignments. Retain submitted snapshots and show current values separately.
- Work-history overlap handling and text-based department/position grouping need agreed examples, including nested overlaps and future/invalid dates. Do not assume current calculations are authoritative tenure rules.
- `UserWorkex::save_present` stores ongoing jobs with `date_to = '0000-00-02'`, but IDP tenure recognizes only textual `Present` and otherwise passes the value to Carbon. This mismatch can corrupt tenure calculations. Normalize the confirmed sentinel and textual variants to an explicit ongoing interval before calculating tenure, retain original source values, and keep other invalid dates in a reconciliation report rather than treating them as ongoing automatically.
- `hrdc_approved`/`hrdc_approve_date` exist, but no HRDC signing route appears in inspected routes. **Unresolved:** HRDC approval, return/revision, withdrawal, amendment after signature, multi-year coverage interpretation, and who may prepare for another employee.

## Build checklist

- [ ] Define plan header, stable training items, coverage flags, purpose, signatories and audited transitions.
- [ ] Implement validated draft CRUD with one-plan-per-employee/year or explicit revision keys.
- [ ] Load approved position requirements and preserve free-text proposal/title data independently of future matrix edits.
- [ ] Extract service-duration calculation with explicit agency aliases and approved overlap/date rules, including the confirmed `0000-00-02` ongoing-work sentinel and textual `Present` variants.
- [ ] Enforce employee ownership and assigned-supervisor scope on all reads, changes, printing and signatures.
- [ ] Add submit/readiness checks, supervisor approval, and immutable signed snapshots or a revision workflow.
- [ ] Implement HRDC signing/return only after resolving its actual requirements; do not mark it implemented because columns exist.
- [ ] Recreate IDP list, training preview, editor and printable form/signature blocks.
- [ ] Connect signed plan items to module 12 through stable references and auditable budget updates.
- [ ] Migrate all JSON variants, employee/year duplicates, signatories and historical service snapshots with a reconciliation report.

## Acceptance checklist

- [ ] Purpose/coverage/trainings persist through create/edit/reload, including empty optional fields and decimal budgets added by division planning.
- [ ] Duplicate employee/year submissions are rejected or create an explicit permitted revision under concurrency.
- [ ] An employee cannot sign their own plan by calling the endpoint or alter another employee's plan via `user_id`.
- [ ] Correct supervisor/head routing is applied; archived/terminated signatories follow an agreed historical policy.
- [ ] Only complete permitted plans can be submitted/signed; retries do not replace signature identity or dates.
- [ ] Tenure examples cover textual `Present`, the confirmed `0000-00-02` ongoing-work sentinel, other invalid dates, agency aliases, overlapping jobs, different position names, and no work history.
- [ ] Printed plans retain submitted position/training/signatory values after staff or matrix changes.
- [ ] Only approved plans for the relevant division/year feed the division learning plan.
