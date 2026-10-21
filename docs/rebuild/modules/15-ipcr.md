# 15 — Individual performance commitment and review (IPCR)

## Purpose and evidence

Create period-based employee performance plans, record categorized outputs and success indicators, enter actual accomplishments and ratings, calculate weighted overall ratings, submit for review, and print a review form.

**Observed** describes implemented behavior; **Proposed** describes rebuild requirements; **Unresolved** identifies gaps rather than assuming a completed appraisal process.

Sources: [IPCRController](../../../app/Http/Controllers/IPCRController.php), [Ipcr](../../../app/Ipcr.php), [Outputs](../../../app/Outputs.php), [SuccessIndicators](../../../app/SuccessIndicators.php), [ActualAccomplishments](../../../app/ActualAccomplishments.php), [Status](../../../app/Status.php), [IPCR views](../../../resources/views/ipcr), [browser actions](../../../public/js/ipcr.js), [web routes](../../../routes/web.php). Migrations: `2026_01_14_134233_create_ipcrs_table.php`, `2026_01_22_101352_create_outputs_table.php`, `2026_01_22_112326_create_success_indicators_table.php`, `2026_01_26_173920_create_actual_accomplishments_table.php`, `2026_01_28_170035_add_rating_field.php`.

## Actors and authorization

Observed: the controller constructor does not apply `auth`, and the inspected IPCR routes have no explicit auth middleware. Some methods reference `Auth`, which can fail when no session exists; this does not secure other direct-ID actions. Index scopes to the current user; division overview scopes to current-user division and active Permanent staff, excluding self. Division-chief editor/view methods do not consistently enforce chief role or record ownership.

Proposed: authenticated employee owns drafts and permitted accomplishment inputs; assigned supervisor reviews/rates only their staff; HR/head has explicit read/report permissions. Enforce row and transition policies for all methods.

## Entities and states

| Table | Observed data |
| --- | --- |
| `ipcrs` | Employee, period start/end, nullable overall rating `(3,2)`, timestamps. |
| `outputs` | IPCR ID, category ID, task text. |
| `success_indicators` | IPCR ID, output ID stored as string, indicator description. |
| `actual_accomplishments` | IPCR ID, success-indicator ID stored as string, accomplishment, nullable integer quality/efficiency/timeliness. |
| `statuses` | Shared history with `request_type = IPCR`; latest by ID is displayed as current status. |
| `ipcr_items` | Earlier Support/Intervening item schema with quantity and average fields; the inspected live controller uses the output → indicator → accomplishment tables instead. |

Observed transitions implemented by this controller are creation → `Draft`, submission → `For Review`. A staff view references `For Revision`, and the printed form has reviewed/approved blocks, but a complete approve/return/sign-off endpoint sequence is not demonstrated. Do not claim those transitions are complete.

## Workflow and outputs

1. `/ipcr/add_record` creates an employee/period header and appends `Draft` history. `/ipcr` shows current-user plans with latest status.
2. Editor pages load outputs, indicators and accomplishments. Outputs belong to strategic priority `1`, core function `2`, support function `3`; indicators belong to outputs; accomplishments belong to indicators.
3. Save/update/delete actions manage these rows. A submitted rating of `0` is converted to `null` for quality/efficiency/timeliness.
4. `/ipcr/submit` computes and stores the rounded overall rating, then appends `For Review` history. It does not enforce a single submission, ownership, valid period, completed content or permitted previous state in the inspected handler.
5. `/ipcr-overview` selects active Permanent same-division staff and filters their plans within a requested period. Defaults are hardcoded to July–December 2025; replace with configurable/current reporting periods.
6. `/ipcr/view/{id}` assembles the print form and selects a type-`2` chief in the employee's current division. `/ipcr/delete` deletes header, hierarchy and status history manually; deleting one output/indicator alone does not cascade all descendants in this controller.

## Rating contract observed in source

For each accomplishment, average only non-null quality, efficiency and timeliness values. For each category, average those accomplishment averages; a category with none has average `0`. Overall = strategic average × `0.40` + core average × `0.40` + support average × `0.20`. Submission rounds to two decimals; print recalculates from current rows.

Descriptions: exactly `5` = Outstanding; `>=4` = Very Satisfactory; `>=3` = Satisfactory; `>=2` = Unsatisfactory; otherwise Poor. This mapping and empty-category penalty are observed legacy rules, requiring business confirmation before being adopted as policy. No server-side allowed-rating range is enforced here.

## Migration issues and decisions

- Parent IDs are duplicated at multiple levels and some are strings. Reconcile output/indicator/accomplishment ancestry; reject cross-IPCR child references.
- Stored overall rating may diverge from print recalculation after edits. Store immutable submitted/reviewed snapshots or recalculate consistently with versioned ratings.
- `Ipcr::position` links position ID to employee ID, which does not express a correct employee assignment. Route the relationship through employee assignment/snapshot.
- Route `/ipcr/success_indicators/{id}` points to absent `success_indicators`; `/ipcr/accompishment_details/{data3_id}` points to absent `accompishment_details` (controller implements `accomplishment_details`). Repair the route inventory explicitly.
- No transaction surrounds multi-table delete or submission plus status history. Add foreign keys, controlled cascades and atomic transitions.
- **Unresolved:** who assigns ratings, whether category weights may vary, empty-category handling, rating range/rounding/descriptions, return/revision/approval/signature process, period uniqueness/overlap, and whether unused `ipcr_items` data must migrate.

## Build checklist

- [ ] Define performance periods, IPCR header, output categories, indicators, accomplishments, rating values and audit/status history.
- [ ] Add authentication/authorization, owner-scoped child routes and division reviewer scope.
- [ ] Validate period order, agreed uniqueness/overlap, child ancestry and rating range/null behavior.
- [ ] Implement complete output/indicator/accomplishment CRUD with transactional descendant deletion or safe archive.
- [ ] Extract the approved deterministic rating calculation and test all category/null/rounding boundaries.
- [ ] Implement draft → submitted review plus confirmed review/revision/approval states; enforce locks and idempotent transitions.
- [ ] Version submitted/reviewed content and signatory/position snapshots; prevent silent post-submission changes.
- [ ] Recreate employee list/editor, staff period overview, chief review editor and complete printable form.
- [ ] Replace hardcoded report dates and fix absent/misspelled route handlers in compatibility mapping.
- [ ] Migrate both used and potentially historical item structures after reconciling status history and rating totals.

## Acceptance checklist

- [ ] Anonymous and unauthorized direct-ID requests cannot read/change IPCR data; staff cannot rate or review another employee's plan.
- [ ] For category averages 5, 4, 3, overall is 4.2; a missing category contributes zero only if that legacy rule is approved.
- [ ] Null components are excluded from an accomplishment average; all-null accomplishments do not create a false zero component.
- [ ] Boundary values/descriptions and two-decimal rounding match the approved examples.
- [ ] Invalid period, forged parent links and out-of-range ratings are rejected without partial writes.
- [ ] Repeated submit/review requests create one effective transition and retain actor/time history.
- [ ] Removing an output/indicator cannot leave hidden/orphaned accomplishments or alter another IPCR.
- [ ] Stored ratings, submitted snapshots, overview and print outputs agree after subsequent employee/position changes.
