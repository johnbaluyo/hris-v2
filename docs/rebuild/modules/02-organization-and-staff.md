# 02 — Organization and staff

## Purpose and evidence

Maintain the employee directory, assignment to divisions and positions, employment details, attendance identity, signatures, personnel attachments, and temporary officer assignments. This is a foundation for payroll, attendance, approval routing, PDS, learning plans, and certificates.

**Observed** means implemented in the inspected source; **Proposed** means a rebuild requirement or correction; **Unresolved** requires a product decision or a sanitized database inspection. Source review does not prove that production matches the migrations.

Source of truth:

- [HRStaffController](../../../app/Http/Controllers/HRStaffController.php): `index`, `store`, `edit`, `archive`, `check_email`, `check_atms_id`, `check_position`, `dataSheetUPload`, `showDatasheet`, `setOIC`, `checkOIC`, `setHRDC`, `syncUsersToSsi`.
- [User](../../../app/User.php), [Division](../../../app/Division.php), [Position](../../../app/Position.php), [PositionHistory](../../../app/PositionHistory.php), [OIC](../../../app/OIC.php), [DataSheet](../../../app/DataSheet.php).
- [Staff screens](../../../resources/views/hr_staff), [position screens](../../../resources/views/positions), [web routes](../../../routes/web.php).
- Migrations: `2014_10_12_000000_create_users_table.php`; `2021_04_09_082743_division_table.php`; `2021_04_09_082851_positions_table.php`; subsequent user/position alterations; `2024_09_04_132035_add_positions_history_tbl.php`; `2024_09_05_165059_add_assume_date.php`; `2026_03_02_101559_create_oics_table.php`.

## Actors and authorization

Observed: `HRStaffController` requires a logged-in session. Staff management endpoints do not consistently enforce an HR permission after that check. User types elsewhere map to applicant `0`, employee `1`, division chief `2`, HR `3`, head of agency `4`; these are identity/routing values, not proof of adequate authorization. `setHRDC` exists but has no matching route in the inspected web routes. `DivisionController` contains scaffold methods and is not a working division administration module.

Proposed: apply explicit staff-admin, organization-admin, personal-record-read, and integration-sync permissions. Scope employee self-service separately. Enforce these on reads, mutations, attachment access, exports, and autocomplete endpoints.

## Data contract

| Entity | Observed fields and meaning |
| --- | --- |
| `users` | Names, nickname, email/password, `user_type`, `employee_type`, `division_id`, `position_id`, `date_hired`, `termination_date`, `isArchived`, `step_increment`, `workweek`, `atms_id`; contact/emergency details, birthday/sex, government/payroll identifiers, profile image and signature. `User::$fillable` is the controller's effective write boundary. |
| `divisions` | Division name and acronym; used as the approval/report boundary. |
| `positions` | Title, division, salary-grade reference, position code, plantilla flag, status; later alterations also introduce position classification fields. `salary_grade` refers to a `salary_grade` table row, not necessarily the grade number. |
| `positions_history` | Employee, position, HR actor, timestamps; a later migration adds nullable `assume_date`. No inspected migration adds a history `termination_date`, although `User::edit` reads that attribute. Confirm any deployment-only column before mapping it. |
| `users_personal` | A fresh personal snapshot is appended on a staff save; PDS selects the newest row. |
| `oics` | `dc_id`, `period_start`, `period_end`, timestamps; no separate delegated division or approving role in this table. |
| `data_sheets` | User, original document name, stored attachment path. Separate from the `uploads` acknowledgment workflow. |

Observed position statuses include `Open for Application`, `Filled Up`, and `archived`. Employee archive uses `Y`/`N`; employment end uses `termination_date`. These are separate concepts and query filters vary across modules.

## Workflows and rules

1. `/hr/staff` joins users/divisions/positions and loads integration targets. It displays the latest attendance identifiers from Permanent and CSW numeric ranges. The code assumes both ranges have a matching row.
2. `store` handles create and update. It title-cases names, hashes a supplied password, translates an explicitly cleared termination date to `null`, maps `work_week` to `workweek`, and removes empty strings from the update payload.
3. Signature and profile-image uploads go to `public/images/esign` and `public/images/profile_pic`. A `users_personal` snapshot is then appended. Account and personal snapshot writes are not wrapped in one transaction.
4. `check_email`, `check_atms_id`, and `check_position` provide UI checks. The position check treats a position as occupied when a user has no termination date. These checks are not a substitute for database constraints or save-time validation.
5. Archive sets `users.isArchived = Y`; it does not remove the account or end the employment record. Position deletion separately archives the position.
6. Multi-file personnel attachments are stored under `public/files`, with metadata in `data_sheets`; `/staff/datasheet/{id}` lists them.
7. OIC creation records a person and inclusive date range. `checkOIC` returns the current user's ID if any assignment includes today. WFH uses a separate acting-chief rule; do not conflate them.
8. `syncUsersToSsi` calls the configured target system's `/api/sync-users`, POST first and GET if the response is HTTP 405. It uses `config('app.api_sync_token')` when present and a 60-second timeout. It triggers remote synchronization; the request does not send employee records in its body.

Routes: `/hr/staff`, `/hr/staff/store`, `/hr/staff/edit`, `/hr/staff/archive`, `/hr/staff/check_email`, `/hr/staff/check_atms_id`, `/hr/staff/check_position`, `/hr/staff/oic`, `/hr/staff/sync-users`, `/staff/personnel-file`, `/staff/datasheet/{id}`. Position maintenance is currently routed through `RecruitmentController`; see module 11. Salary schedules/reference settings are covered separately.

## Migration issues and decisions

- **Observed defect:** `store` reads the user's current position after saving the requested position, then compares it to the request. That can suppress history creation. `PositionHistory` also has no fillable declaration for the `create` call. Capture the previous assignment before updating and define the history write contract explicitly.
- Empty-string filtering prevents many fields from being cleared. Define field-specific null/empty behavior and preserve valid zero values.
- Do not infer employment history from the current account alone. Reconcile positions history, work experience, date hired, termination, archive, and current assignment; report inconsistencies instead of rewriting evidence.
- `User::edit` overlays assumption/termination dates from the newest position history onto the returned user object. The history termination field has no matching inspected migration; an absent value can replace the returned user's actual termination date with null. Treat this as a schema/code mismatch, retain `users.termination_date` as the observed persisted employment end, and reconcile any deployment-only history field separately.
- The current `Position::user` relationship selects one latest, non-terminated user; confirm whether each plantilla position is exclusive and whether non-plantilla positions can have multiple incumbents.
- **Unresolved:** supported employee types, workweek values, required identifiers, attendance-ID allocation, division CRUD requirements, historical OIC scope, reactivation rules, and permitted HRDC changes.

## Build checklist

- [ ] Create employees, accounts, divisions, positions, dated assignments, and temporary delegations with explicit relationships and legacy-ID mapping.
- [ ] Preserve employee types, original identifiers, archive and termination independently; define date normalization for zero/invalid dates.
- [ ] Add validated create/update requests and enforce unique email/attendance identity where approved; implement transactional position occupancy checks.
- [ ] Create staff search/filter, detailed editor, archive/reactivate flow, and position occupancy warnings.
- [ ] Store assignment history atomically with account changes, including actor and effective date; avoid appending identical snapshots accidentally.
- [ ] Implement secure profile/signature and personnel-file storage with generated object names and authorized downloads.
- [ ] Add OIC date validation, overlapping-assignment rules, permission checks, and an auditable HRDC assignment flow if retained.
- [ ] Queue integration sync with timeout, status reporting, credential configuration, retry policy, and a fakeable HTTP adapter.
- [ ] Migrate attachment metadata and files with checksums/missing-file reports; do not copy personal data into repository fixtures.

## Acceptance checklist

- [ ] HR can create and edit permitted employee fields; another employee cannot access staff-admin APIs by posting directly.
- [ ] Clearing a termination date is persisted and reflected across directory/approval eligibility.
- [ ] A real position change creates exactly one dated history event containing the old and new assignments.
- [ ] Duplicate email/attendance identity and conflicting exclusive incumbency fail at save time, including concurrent requests.
- [ ] Archive preserves historical payroll, DTR, PDS, and certificate references.
- [ ] OIC starts/ends on the agreed inclusive dates; invalid ranges and unauthorized assignments fail.
- [ ] Files retain original display names without collision or public disclosure; absent files produce a repair report.
- [ ] SSI success, HTTP 405 fallback, remote rejection, and timeout are exercised with fake responses.
