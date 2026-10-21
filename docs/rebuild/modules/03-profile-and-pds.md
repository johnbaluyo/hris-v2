# 03 — Employee profile and personal data sheet

## Purpose and evidence

Provide employee self-service for account details and all personal data sheet (PDS) sections, with a printable assembled PDS and links to training certificates and government ID images.

**Observed** describes the inspected source. **Proposed** describes the rebuild. **Unresolved** marks requirements the repository does not settle.

Primary sources: [ProfileController](../../../app/Http/Controllers/ProfileController.php); [profile views](../../../resources/views/profile); [User](../../../app/User.php); `app/UserPersonal.php`, `UserFam.php`, `UserEduc.php`, `UserEligibility.php`, `UserWorkex.php`, `UserVolWork.php`, `UserLD.php`, `UserOtherInfo.php`, `UserGovDec.php`, `UserCharRef.php`, `UserGovID.php`; [web routes](../../../routes/web.php). The PDS layout is `resources/views/profile/pds_form.blade.php`; review its field placements and continuation behavior during implementation.

Schema evidence: `2022_04_08_173814_user_personal.php`, the May 2022 family/education/eligibility/work experience/voluntary-work/L&D migrations, December 2022 other information/declaration/reference/ID migrations, and later nullable/date/type changes through 2024. Use the complete migration sequence, not the first create migration alone.

## Actors and authorization

Observed: the controller requires `auth`. Most section indexes select the current user. `/profile/pds_file/{id}` builds another employee's PDS directly from a supplied ID; many detail/update/delete methods find child records by ID without proving ownership. A hidden form field or a menu restriction does not establish an access boundary.

Proposed: employees maintain their own PDS. HR reads or edits another employee only with an explicit permission. Bind every child query through the authorized employee. Separate account email/password changes from HR-controlled identity/employment fields.

## Sections and data contract

| Section/table | Observed contents and cardinality |
| --- | --- |
| Account / `users` | Email and optional matching password/retyped password. Personal save also updates selected name/contact/account fields. |
| Personal / `users_personal` | Name/extension, birth date/place, citizenship and dual-citizenship details, sex/civil status, height/weight/blood type, government identifiers, telephone/mobile, structured residential/permanent addresses, emergency/contact details. Appends snapshots; latest ID is displayed. |
| Family / `users_fam` | Spouse identity/occupation/employer/contact, father and mother names/extensions, children payload. Appends snapshots; latest row is displayed. |
| Education / `users_educ` | Level, school, course, period, highest level, graduation year, honors. Multiple rows grouped by level. |
| Eligibility / `users_eligibility` | Eligibility, rating, exam date/place, license number and validity. Multiple rows. |
| Work experience / `users_workex` | Period, position, department, salary, step increment, appointment status, government-service flag. Multiple rows; ordinary forms accept textual `Present`, while `UserWorkex::save_present` writes the special ongoing-job sentinel `0000-00-02`. |
| Voluntary work / `users_vol_work` | Organization/address, period, hours, position. Multiple rows; later migrations permit non-date representations. |
| Learning / `users_ld` | Training title, period, hours, provider, certificate file and position-matrix reference. Multiple rows. |
| Other information / `users_other_info` | Special skills/hobbies, non-academic distinctions/recognition, association membership. Multiple rows. |
| Government declarations / `users_gov_dec` | PDS questions 34–40 and conditional explanation fields. Request names and stored column names differ; see `UserGovDec::save_gov_dec`. Latest row used. |
| Character references / `users_char_ref` | PDS references; current implementation stores the form as one latest snapshot. Inspect migration/model for each numbered field. |
| Government ID / `users_gov_id` | ID type/number, issue date/place, uploaded file; new snapshot on save, latest displayed. |
| Position history | Read-only employee view of `positions_history`; management belongs to module 02. |

Observed education level labels are `ELEMENTARY`, `SECONDARY`, `VOCATIONAL / TRADE COURSE`, `COLLEGE`, `GRADUATE STUDIES`. L&D matrix categories map to managerial/leadership/supervisory `1`, other `2`, technical `3`.

## Workflows and outputs

1. `/profile` opens account/profile information. `/profile/save_account` changes email and, when provided and matching confirmation, a hashed password.
2. Dedicated GET pages under `/profile` load personal, family, education, eligibility, experience, voluntary work, learning and development, other information, declarations, references, government ID, and position history.
3. Personal and family saves append records. Personal save then updates the user record; this is not currently an atomic operation. Declarations/references/IDs use their models' snapshot pattern.
4. Education, eligibility, work experience, voluntary work, other information, and L&D have save, details, update, and delete actions. Request field names differ between create and update; migrate behavior rather than preserving confusing names in the new API.
5. `/profile/learning_and_development` lists completed training and suggests current-position matrix items with no linked completion. Training linked to an obsolete/other-position matrix needs explicit treatment; module 12 retains it as uncategorized.
6. Training creation/update/certificate endpoints accept base64 data or an existing filename and write to `public/images/uploads/staff/training_cert`. Government ID files go to `public/images/govid`. These are source behaviors, not the proposed security design.
7. `/profile/pds_form` assembles the current user's latest snapshot sections and all repeatable rows. `/profile/pds_file/{id}` assembles the same print view for a selected employee. Education is grouped by the exact labels above; work experience sorts newest start first, learning newest end first.

Main mutation route families are `/profile/save_*`, `/profile/delete_*`, `/profile/*_details`, `/profile/update*_details`, `/profile/upload_cert`, and `/profile/save_present`; retain a route-to-handler inventory from `routes/web.php` during API replacement. Personnel-document submissions and HR acknowledgment are a separate module (22).

## Migration issues and decisions

- Snapshot sections can contain many versions. Preserve source IDs/timestamps; select an approved current version without dropping historical rows prematurely.
- `array_filter` in personal/user saves can remove zero/false/empty values and prevents expected field clearing. Define null semantics per field.
- Work/education/voluntary periods include textual and nullable legacy values; strict date conversion requires a documented policy and a rejected-row report.
- Ongoing work needs a distinct migration rule: `UserWorkex::save_present` writes `date_to = '0000-00-02'`, while the PDS view and IDP tenure calculation recognize only case-insensitive textual `Present`. Preserve both original representations and map the confirmed ongoing-job sentinel/text to an explicit ongoing flag with no end date; do not classify every invalid/zero date as ongoing or discard the sentinel as an unknown date. Reconcile ambiguous rows separately.
- `updateeduc_details` does not update school even though school is saved at creation. Confirm the intended editor behavior.
- Government declaration question mappings must be reconciled against actual field labels; do not rename solely from numeric column names.
- Government ID upload can dereference the previous ID row when none exists, and replacing without a file can leave a blank filename. First-save/no-file cases need explicit behavior.
- Certificate storage trusts request MIME/base64 and filenames too broadly. New uploads need type/size validation, generated storage keys, safe previews, and private access.
- **Unresolved:** authoritative PDS form edition, required sections, allowed non-date values, character-reference structure/limits, whether signed PDS snapshots must be immutable, and HR edit rights. This document is code-derived and does not certify current government form compliance.

## Build checklist

- [ ] Model each section with clear one-current-version versus repeatable-row semantics and legacy-ID maps.
- [ ] Implement validated forms for every row/section in the data table, including declaration explanations and address fields.
- [ ] Preserve current personal/account synchronization using a transaction and an explicit field allowlist.
- [ ] Implement ownership policies on every read/update/delete and HR PDS export.
- [ ] Normalize dates, textual `Present`, and the confirmed ongoing-job `0000-00-02` sentinel without losing original values; preserve decimal salary/hours as appropriate.
- [ ] Store certificate/ID images privately with file metadata; support replace, retain, remove, authorized preview, and missing-file recovery.
- [ ] Build completed-training and suggested-matrix views linked to module 12.
- [ ] Recreate the complete printable PDS with section ordering, empty-state handling, overflow/continuation pages, and approved form version.
- [ ] Add version history/audit events where a snapshot changes and prevent an employee from changing payroll/role fields through personal forms.
- [ ] Migrate all section tables and attachment paths; reconcile latest snapshot and user-record name/contact conflicts.

## Acceptance checklist

- [ ] A synthetic employee can create, edit, clear, and reload every PDS section and repeatable row.
- [ ] Empty optional fields and zero values persist as specified; a first government-ID save succeeds with the agreed file requirement.
- [ ] Another employee's child IDs and PDS export return denied/not-found responses.
- [ ] Both textual `Present` and the confirmed `0000-00-02` ongoing-job sentinel migrate to the same intended ongoing-work behavior; unknown invalid dates remain distinguishable. Incomplete education years, long text, and many training entries print without lost rows.
- [ ] The printed PDS uses the current approved snapshot of personal/family/declarations/references/ID and all current repeatable entries.
- [ ] Certificate replacement preserves the old file until the new upload and database update succeed.
- [ ] Changes to current personal name/contact values propagate once to the employee account and retain audit history.
- [ ] Training matrix changes retain historical completions and show unmatched completions clearly.
