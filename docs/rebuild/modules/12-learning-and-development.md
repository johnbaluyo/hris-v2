# 12 — Learning and development matrices and division plans

## Purpose and evidence

Compare an incumbent's training history with a position's required training matrix, manage matrix requirements, display signed individual development plans (IDPs), and assemble budgeted division learning plans.

**Observed** describes source behavior; **Proposed** is the rebuild contract; **Unresolved** is not established by the repository.

Sources: [LearnAndDevController](../../../app/Http/Controllers/LearnAndDevController.php), [learning views](../../../resources/views/learn_dev), [TrainingDataImport](../../../app/Imports/TrainingDataImport.php), [UserLDMatrix](../../../app/UserLDMatrix.php), [UserLDMatrixBudget](../../../app/UserLDMatrixBudget.php), [DivisionLndPlan](../../../app/DivisionLndPlan.php), [UserLD](../../../app/UserLD.php), [IDP](../../../app/IDP.php), [web routes](../../../routes/web.php). Relevant migrations: `2024_06_26_185219_l_n_d_matrix_tbl.php`, `2024_06_26_185845_users_matrix_id.php`, `2024_08_29_100653_matrix_budget.php`, `2024_12_09_183730_matrix_budget_isfree.php`, `2026_01_14_115139_create_division_lnd_plans_table.php`.

## Actors and authorization

Observed: controller uses `auth`; division screens derive the division from the current user. Plan deletion explicitly checks the same division. Other supplied position/plan/IDP IDs are not consistently authorized. Head-of-agency user type `4` and division-chief views affect display context, but do not provide consistent mutation enforcement.

Proposed: training administrators manage position matrices; employees manage completed training in module 03; authorized division planners manage only their division; HR/head read approved organizational reports. Enforce resource scope for all JSON and print actions.

## Entities and categories

| Entity | Observed contract |
| --- | --- |
| `users_ld_matrix` | Position, category string, training-value text, timestamps. |
| `users_ld` | Completed training record with user, title, period/hours/provider/file and optional matrix ID. |
| `users_ld_matrix_budget` | Matrix ID, year, integer budget in the create migration, later `isFree`. Distinct from division-plan budgets. |
| `users_idp` | Signed/submitted plans whose JSON `training` array is used by division plans; see module 13. |
| `division_lnd_plans` | Division, year, decimal `total_budget(15,2)`, staff count, creator, timestamps. |

Matrix categories: `1` Managerial/Leadership/Supervisory, `2` Others, `3` Technical. Matrix editor orders categories `1`, `3`, `2`, and puts unmet requirements before accomplished ones.

## Workflows and outputs

1. `/LnD` and `/LnD/datatable` present positions with filters for salary grade, position text, staff name and signed IDP year; budget years also populate the surrounding matrix screens. The datatable includes non-archived/null-status plantilla positions at salary grade 24 or below; type-`2` users are limited to their division, while other authenticated roles are not equivalently scoped in that query. Current non-archived/non-terminated incumbents drive staff details.
2. `/LnD/staff/{pos_id}` and `/LnD/position-matrix/{pos_id}` return training/matrix data. `staff_matrix_data` groups completed training under valid matrix IDs for the selected position. Missing, deleted, or different-position matrix links remain in an uncategorized group. History sorts latest end date first and displays elapsed time.
3. `/LnD/matrix/{pos_id}` shows requirements, completion flags, budget records, copy sources, and incumbent IDPs. Matrix save creates/updates category, requirement text, and position. Copy inserts source requirements only when target position/category/text has no exact match; it does not copy budgets or completion links.
4. `/print-table-matrix/{pos_id}` renders the staff training matrix in `learn_dev.print_staff`.
5. `/import` and `/import-training-data` accept XLSX, save a temporary file, and return `Excel::toCollection` data. `TrainingDataImport::collection` constructs a header-based training array but does not persist matrices. Treat this as an import preview/prototype, not a completed database import.
6. `/division-LnD` lists current-division plans. Creating a plan enforces one division/year through an existence check, stores creator, and starts with zero totals.
7. `/division-lnd/load-plan/{plan_id}` loads active, non-terminated current-division staff with chief-approved IDPs for the selected plan year; only nonempty training lists are included.
8. `/division-lnd/save-budgets` updates each selected IDP JSON training item by `training_index`, writes a `budget` property, sums the supplied items, and records unique participating staff count in the plan. Values are not saved as a separate division-plan-line table.
9. Division plan and range print endpoints gather signed IDPs for selected coverage/year ranges and render `learn_dev` report views. Deleting a plan deletes its header; budgets embedded in IDPs are not thereby isolated or reversed.

## Migration issues and decisions

- Training history is an employee record while requirements belong to positions. Keep original matrix links when staff move, and preserve uncategorized historical completions.
- Budget edits mutate signed IDP JSON and are keyed by array index. Reordered training items can receive the wrong budget; multiple division plans can share mutable data. Proposed: stable training-item IDs and explicit budgeted plan lines with immutable report snapshots.
- Existing plan totals sum the received payload, not necessarily all persisted lines. Recalculate totals server-side from the complete authorized plan.
- Matrix budget and division-plan budget are different stores; the inspected controller does not demonstrate a complete matrix-budget editing endpoint. Confirm which UI/features remain needed.
- Plan uniqueness has no matching unique constraint in the create migration. Budget/plan writes need decimal validation, consistency checks and transactions.
- **Unresolved:** yearly budget ownership, free-training semantics, reporting coverage labels, completion expiration/retraining policy, import template/error handling, and approval/finalization of division plans.

## Build checklist

- [ ] Create position requirements, employee training completions, annual matrix budget records, division-plan headers and stable plan lines.
- [ ] Implement requirement create/edit/archive/copy with deduplication and preservation of referenced training history.
- [ ] Implement position/incumbent directory filters and fulfilled/unfulfilled/uncategorized training views.
- [ ] Integrate completed-training evidence from module 03 and signed-IDP training proposals from module 13.
- [ ] Enforce division/position permissions for data, mutation and printing; constrain every submitted IDP/training item to the authorized plan.
- [ ] Implement one-plan-per-division/year constraints and validated nonnegative decimal budgets/free indicators.
- [ ] Store plan totals from persisted lines transactionally; capture reporting snapshots instead of mutating signed evidence invisibly.
- [ ] Build an XLSX preview/validate/commit flow only after agreeing a template; surface row/column errors and make repeated imports safe.
- [ ] Recreate staff matrix, division plan, and date/year-range print outputs with coverage and totals.
- [ ] Migrate double-encoded/array training JSON and detect broken matrix references/index-based budgets.

## Acceptance checklist

- [ ] Valid linked training fulfills the correct position requirement; missing/deleted/other-position links appear as uncategorized without loss.
- [ ] Copying the same matrix twice produces the agreed deduplicated requirements and preserves the target's history.
- [ ] Filters/search match approved position, employment and signed-IDP scope.
- [ ] Only signed IDPs in the correct year/division contribute to a division plan.
- [ ] Reordering a training list cannot move a budget to another item; partial save and concurrent save preserve accurate totals.
- [ ] Cross-division plan/IDP/position IDs are denied in JSON, budget and print endpoints.
- [ ] Staff count is the number of distinct included employees; free and paid training totals match printed outputs.
- [ ] Import preview has no write side effects; commit reports all rejected rows and supports an agreed repeat-import policy.
