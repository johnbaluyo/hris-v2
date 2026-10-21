# 14 — Statement of assets, liabilities and net worth (SALN)

## Purpose and evidence

Allow employees to maintain SALN filing records and section data, then assemble a printable declaration with asset/liability totals and net worth. This is code-derived functional documentation, not a statement that the existing form meets current filing regulations.

**Observed** means implemented in source; **Proposed** means rebuild work; **Unresolved** means the repository does not settle the requirement.

Sources: [SALNController](../../../app/Http/Controllers/SALNController.php); [SALN](../../../app/SALN.php); `app/SalnDeclarantInfo.php`, `SalnChildrenInfo.php`, `SalnRealAssets.php`, `SalnPersonalAssets.php`, `SalnLiabilities.php`, `SalnBusinessInterests.php`, `SalnGovernmentRelatives.php`; [SALN views](../../../resources/views/saln); [web routes](../../../routes/web.php). Schema: August–September 2025 SALN create migrations, `2026_05_07_171330_add_saln_fields.php`, `2026_06_04_141449_modify_fields.php`.

## Actors and access

Observed: the controller requires authentication. The list is limited to `emp_id = Auth::id()`, but supplied SALN and child IDs on section/view/save/delete actions are not consistently checked against that owner. The module has no implemented review/HR approval status machine in these handlers.

Proposed: employees maintain their own filings; authorized filing custodians receive narrowly scoped read/export capabilities if required. Apply ownership to every section, child mutation and print action. Decide any review/submission workflow explicitly.

## Entities and fields

| Table | Observed fields beyond ID/timestamps |
| --- | --- |
| `salns` | `emp_id`, `filing_type`, `compliance_type`, `compliance_date`. The later migration drops original `year`. |
| `saln_declarant_info` | SALN ID; declarant last/middle/first names, position, office, office address; spouse equivalents. Original `dec_address` was later removed. Save updates the latest row or creates one. |
| `saln_children_info` | SALN ID, name, date of birth, age; controller's add method writes name and age only. |
| `saln_real_properties` | SALN ID, description, kind, exact location, assessed value, current fair value (`current_fair`), acquisition year/mode/cost. Values use decimal `(15,2)` in create migration. |
| `saln_personal_properties` | SALN ID, description, acquired year, acquisition cost `(15,2)`. |
| `saln_liabilities` | SALN ID, nature, creditor, balance. |
| `saln_business_interests` | SALN ID, business name/address/nature, acquisition date. |
| `saln_govt_relatives` | SALN ID, name, relationship, position, office. |

Filing type values in the UI: `1` joint, `2` separate, `3` not applicable. Compliance type: `1` assumption of office, `2` annual filing, `3` exit. The compliance date supplies the year/date shown in the print form; do not treat the removed header year as the current authoritative input.

## Workflows and outputs

1. `/saln` lists the employee's filings; `/saln/add_record` creates a header with the current employee and requested filing/compliance data.
2. `/saln/edit/{id}` opens a section menu. Dedicated pages load declarant information, children, real assets, personal assets, liabilities, business interests and government relatives.
3. Declarant save allowlists the named identity/office fields and upserts the newest section row. Other save actions append a row. Delete methods call their models with a supplied record ID. The inspected code does not implement full edit-in-place handlers for all repeatable sections.
4. `/saln/view/{id}` loads the header/latest declarant, children by descending age, real property by descending acquisition year, personal property by descending acquired year, business interests by descending acquisition date, and all liabilities/relatives.
5. `saln.form` prints filing/compliance choices and the section tables, plus declaration/signature areas. Observed arithmetic: real-assets subtotal = sum of real acquisition costs; personal subtotal = sum of personal acquisition costs; total assets = both subtotals; liabilities = sum of balances; net worth = total assets minus liabilities. Assessed/fair value are displayed separately and are not summed into this formula.

Main route patterns: GET `/saln/{section}/{id}`, POST `/saln/save_*`, POST `/saln/delete_*`, plus header/list/edit/view routes. There is no inspected header delete, submit or approval route.

## Migration issues and decisions

- SALN header `year` was dropped without a reverse migration. Recover original reporting meaning from retained compliance dates and backups; never invent a missing date.
- Child date-of-birth exists in schema but is not written by current `save_child_info`; the child page also references `salns.spouses`, which is not part of the inspected header model's fillable fields. Treat multi-spouse/child extensions as unconfirmed.
- An unused `SalnUnmarriedChildren` model exists; confirm whether any live data references it before deciding to omit it.
- Current sections accept many unvalidated values and raw cross-record IDs. Decimal/year/date validation and parent ownership are required.
- The form's explanatory declarations refer to spouse and children, but code does not establish automated eligibility rules or a compliance review process. Confirm form edition and business interpretation with the filing owner.
- **Unresolved:** filing uniqueness/revisions, immutable submitted copies, whether HR receives filings, amendment/correction workflow, who signs, document retention, and print overflow handling.

## Build checklist

- [ ] Define a filing header and all seven section entities with parent foreign keys and explicit ownership policies.
- [ ] Implement filing/compliance selectors with validated dates and legacy mapping for removed year values.
- [ ] Build create/read/update/delete section forms, distinguishing confirmed legacy behavior from new edit features.
- [ ] Validate money, acquisition years, child ages/dates, required declarations and optional spouse fields.
- [ ] Extract deterministic subtotal/net-worth calculations using decimal arithmetic and preserve original declared amounts.
- [ ] Recreate approved print layout, declaration/signatory fields, continuation pages and empty-section behavior.
- [ ] Preserve declaration snapshots and actor/timestamp audit; implement submission/revisions only after requirements are resolved.
- [ ] Migrate all section rows and report orphaned SALN IDs, duplicate declarant snapshots and malformed numeric/date data.

## Acceptance checklist

- [ ] A synthetic filing can populate every section and retain correctly linked rows through editing and reload.
- [ ] Filing type/compliance type/date appear correctly for assumption, annual and exit filings.
- [ ] For real cost 100, personal cost 50, and liability 30, total assets = 150 and net worth = 120, independently of assessed/fair values.
- [ ] Decimal totals remain exact and negative/invalid inputs obey the approved validation rules.
- [ ] Another employee's header/child IDs cannot be viewed, edited, deleted or printed.
- [ ] Missing spouse/child/declarant data renders an agreed blank-state form without exceptions.
- [ ] Long addresses and many properties/relatives print on continuation pages without clipped or missing content.
- [ ] Imported filings reconcile counts and monetary totals per employee/filing, with unresolved rows reported separately.
