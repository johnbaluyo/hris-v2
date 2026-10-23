# 19 — Work-from-home accomplishments and consolidated reports

> Replacement implementation updated 23 October 2026. Checked technical build items have source and regression evidence in [the remaining-modules handoff](../../modules/REMAINING_MODULES_HANDOFF.md). Production-policy acceptance, official certificate generation and legacy reconciliation remain separate gates; unchecked items are not claimed complete.

## Purpose and evidence

Collect staff WFH accomplishments per date, let division chiefs return or approve them, consolidate and sign division reports, let the head of agency approve, and print division/agency summaries with leave/onsite/no-record fallbacks.

**Observed** describes implemented code. **Proposed** describes rebuild work/corrections. **Unresolved** identifies policy not settled by the implementation.

Sources: [WorkFromHomeAccomplishmentController](../../../app/Http/Controllers/WorkFromHomeAccomplishmentController.php), [individual model](../../../app/WorkFromHomeAccomplishment.php), [consolidated model](../../../app/WorkFromHomeConsolidatedReport.php), [WFH screens/reports](../../../resources/views/wfh_accomplishments), [web routes](../../../routes/web.php); migrations `2026_05_14_090000_create_work_from_home_accomplishments_table.php` and `2026_05_14_090100_create_work_from_home_consolidated_reports_table.php`.

## Actors and scope

Observed controller requires `auth`. Types `1` and `3` are supervised staff, excluding case-insensitive Interns. Type `2` is division chief; type `4` is head of agency; HR also prints agency-approved reports. Chief operations normally enforce own division.

When no active type-`2` chief exists in a division, `isActingChief` picks the highest `positions.salary_grade` value among active non-Intern type-`1` staff. This is a salary-grade **reference ID**, so it may differ from actual grade ordering. It is independent of the `oics` date-range table used elsewhere. Acting staff can have both staff and chief capabilities.

Proposed: retain legitimate staff/chief/head workflows while resolving delegated supervision through an explicit, auditable authority rule. Apply the same policy to print endpoints and chief-created records.

## Entities and states

| Entity | Observed contract |
| --- | --- |
| `work_from_home_accomplishments` | User, division snapshot, WFH date, text; status; approving actor/time; returning actor/time/remarks; timestamps. Unique `(user_id,wfh_date)`, index `(division_id,wfh_date)` and status. |
| `work_from_home_consolidated_reports` | Division, chief actor, date, submitted count, status, signed time, approving actor/time, timestamps. Unique `(division_id,wfh_date)`. It stores a header/count, not a snapshot of included rows. |

Individual states: `for approval` → `approved` or `returned`; editing a returned/pending item restores `for approval` and clears return metadata. Approved individual items cannot be edited/deleted through staff/chief editor handlers. Consolidated states: `for review` → chief signature → `for approval` → head approval → `approved`. Only a `for review` header can be deleted or reconsolidated.

## Workflows and outputs

1. `/wfh-accomplishments` provides staff records/date options, chief queues/consolidated headers, and head approval/approved print dates based on role.
2. `store` accepts a nonfuture date and required accomplishment text, rejects duplicate user/date, excludes Intern staff, and blocks a date if the division header is already for approval or approved.
3. UI available dates are Wednesdays from **2026-06-03 through 2026-12-31**, excluding existing user dates and signed division dates. The server create method does not enforce Wednesday or that fixed range; it only requires date ≤ today. This mismatch is a policy decision to fix.
4. Staff `update`/`destroy` require ownership, non-approved status and an unsigned date. Chief update requires same division and eligible staff; it also clears prior approval/return metadata. Return requires nonempty remarks up to 2000 characters and pending status; approve requires pending status and same division.
5. Chief available-staff and chief-store endpoints support entering on behalf of missing staff. The picker excludes inactive/terminated/Intern staff and, for an acting chief, the actor. The POST validates division/type but does not repeat every picker exclusion; enforce these consistently in the rebuild.
6. Consolidate requires at least one eligible approved staff accomplishment and no existing division/date header. It stores count and chief ID as `for review`. Reconsolidate refreshes approved count only while for review. Sign recounts and marks for approval; head approval marks approved.
7. Signing blocks later staff creation/edit/deletion for that date. Existing approved rows remain live rather than captured as immutable consolidation lines; report content can change with current employment/reference data.
8. `/wfh-accomplishments/print/division/{id}` lists active non-terminated non-Intern supervised staff hired by report date, sorted by position salary-grade reference; uses approved accomplishments. It restricts another division only when the requester is type `2`, leaving other authenticated types insufficiently constrained in this method.
9. `/wfh-accomplishments/print/agency/{date}` permits HR/head and includes approved division headers for that date. It selects the current active head signatory.
10. Missing-accomplishment fallback checks approved leave using the latest LEAVE status per request; otherwise an onsite attendance log whose device is non-null and not `WEB WFH` produces `worked onsite`; otherwise `no record`. The helper also contains a terminated fallback, but normal report staff queries already exclude terminated accounts. Attendance IDs are matched in raw and leading-zero-normalized forms.

Mutation suffixes under `/wfh-accomplishments`: `/store`, `/update`, `/chief-update`, `/chief-available-staff` (GET), `/chief-store-staff`, `/delete`, `/approve`, `/return`, `/consolidate`, `/sign`, `/head-approve`, `/consolidated/delete`, `/consolidated/reconsolidate`.

## Migration issues and decisions

- The fixed 2026 Wednesday schedule must become configured work arrangement/effective-date rules, and the server/UI must agree. Clarify whether occasional non-Wednesday WFH is valid.
- Historical reports currently use today's employee assignment/archive/termination state and head/signatory. Proposed: snapshot included employees, assignment, accomplishment content, fallback evidence and signer at signature/approval.
- Existing consolidated rows persist only header metadata/count, not the original included employee/accomplishment IDs or rendered report. Import those persisted facts faithfully. Any reconstructed membership/content must record its derivation and uncertainty; current joins cannot establish exactly what an earlier signed report contained. Preserve independently retained original report files if available and distinguish them from reconstructions.
- `division_chief_id` records the consolidating user; `signConsolidated` sets the signature time/status without replacing that actor. If the chief/delegate changed between consolidation and signature, the header does not independently establish the actual signing identity. Retain the original actor field and do not infer a different signer without evidence.
- Consolidation eligibility counts active approved staff and does not require all staff to submit. Confirm completeness thresholds and treatment of pending/returned/no-record rows.
- Staff division snapshot may differ from current employee division after transfer; define which division owns historical records.
- Use actual numeric salary grade and deterministic tie-breaks if acting-chief fallback remains. Resolve self-approval and dated OIC rules explicitly.
- Approval and signing currently use separate queries/writes; protect concurrent edits and transitions with transactions/locking, in addition to the existing uniqueness constraints.
- **Unresolved:** head return/rejection/reopening workflow, exceptional edits to approved records, WFH eligibility proof from attendance, partial-day leave precedence, historical terminated staff inclusion, and notification triggers.

## Build checklist

- [x] Preserve individual/consolidated entities and uniqueness constraints; add normalized immutable consolidation lines and transition history.
- [x] Implement configured WFH-date eligibility shared by UI and server, including effective dates and exceptions.
- [x] Implement staff submit/edit/delete/resubmit, chief on-behalf-of entry/edit/approve/return, and division-scoped access.
- [x] Implement explicit chief/delegate authority, eligibility, self-review restrictions and deterministic routing.
- [x] Implement consolidate/reconsolidate/delete-before-sign, chief sign and head approval with atomic state guards.
- [x] Lock signed report content and preserve employee/position/signature snapshots and included record versions.
- [ ] Integrate latest-state leave and normalized attendance identity/device logic for approved fallback labels.
- [x] Recreate staff/chief/head dashboards and division/agency print outputs with counts, dates and signatories.
- [x] Apply permission checks to print/data endpoints and repeat staff eligibility checks in mutation handlers.
- [ ] Migrate statuses/counts/approval metadata and reconcile headers against individual records, active/historical staff, and missing signatures; label reconstructed membership/content separately from persisted facts and original retained report files.

## Acceptance checklist

- [ ] Only eligible dates appear and are accepted; posted non-eligible/future dates fail by the same rule.
- [ ] User/date and division/date uniqueness survive concurrent submissions/consolidation.
- [ ] Returned records display remarks and resubmit correctly; approved individual records cannot be modified through direct endpoints.
- [ ] Chief/delegate cannot manage another division; Intern/inactive/terminated exclusions are consistent in picker and POST.
- [ ] Self-approval follows the approved delegation policy; equal-grade acting candidates resolve deterministically.
- [ ] Consolidation count equals its captured approved lines; signing/approval is idempotent and prevents late edits.
- [ ] Leave takes precedence over onsite/no-record according to approved rules; leading-zero attendance IDs and `WEB WFH` devices are handled consistently.
- [ ] Cross-division staff cannot print a report by guessing its ID; agency printing includes only approved headers.
- [ ] Old reports remain identical after employee transfers, terminations, role changes, later log imports and head changes.
