# 22 Personnel document uploads

## Purpose and sources

Allow staff to submit annual/periodic personnel documents for HR acknowledgement or return. This is distinct from editing structured PDS/SALN/IPCR data and from applicant requirement uploads, although the legacy app reuses `uploads` for several purposes.

- [PersonnelDataManagementController](../../../app/Http/Controllers/PersonnelDataManagementController.php): `index`, `hr_index`, `upload`, `cancel`, `display_year`, `hr_action`.
- [Upload](../../../app/Upload.php), [Status](../../../app/Status.php), [upload template](../../../resources/views/employee/uploads.blade.php), [upload migration](../../../database/migrations/2021_11_06_202157_create_uploads_table.php).
- Routes: GET `/employee/uploads`, POST `/employee/uploads/add`, GET `/hr/uploads`, POST `/employee/uploads/cancel`, POST `/hr/uploads/hr_action`, POST `/hr/uploads/display_year`.

**Observed** means code behavior. **Target/proposed** means rebuild requirements. **Unresolved** requires an explicit decision.

## Actors and current data

Staff own submissions; HR processes incoming records. The controller declares no constructor authentication middleware. Current lists often use the current account, but cancel and HR action write history for a supplied upload ID without a demonstrated record policy. The view controls action visibility by numeric user type; enforce scope on the server in the target.

`uploads` stores employee ID, type, year, filename and remarks. `statuses` stores request type `UPLOADS`, upload ID, status, actor and optional reason. Latest status is the greatest status ID within that type/record pair.

Visible document types are `IPCR (1st Sem)`, `IPCR (2nd Sem)`, `SALN`, `Medical OB Form`, `Training Needs Assessment Form`, `Training Effectiveness Evaluation` and `OTHERS`. Applicant uploads use short codes such as TOR/PDS/WES/CSE and may omit fields expected in the staff workflow; migration must classify these records correctly.

## Observed workflows and defects

1. Staff index loads the account's uploads and resolves the latest `UPLOADS` status/remarks. A missing status is dereferenced rather than handled explicitly.
2. Upload optionally accepts a file, derives a timestamped name from its original filename, moves it to `public/images/uploads/staff`, creates the record and appends `For HR Acknowledgement`. The controller does not demonstrate a common file type/size validator or atomic file/record/history handling.
3. The template links to `images/uploads/{filename}`, omitting the `staff` directory used by this upload action. Resolve actual stored files rather than copying the broken URL convention.
4. HR index joins staff names, resolves latest status, and includes only `For HR Acknowledgement` or `Approved`. HR action writes `Returned` when a return reason is supplied, otherwise `Acknowledged`. Therefore a successfully acknowledged record can disappear from that list; the view's success badge checks `Approved` instead.
5. Cancel appends `Cancelled` without demonstrating an ownership or state-transition check.
6. `display_year` returns one arbitrary first upload's year for the chosen type plus one, without scoping to current employee or ordering; it defaults to current year only if no row exists. Treat this as a defect, not the rule for annual submission periods.
7. No complete route for replacing/resubmitting a returned record is demonstrated. Define whether a corrected document is a new version or a new submission before implementing.
8. The controller's `has_dtr` contains unrelated incomplete attendance code and is not one of these routed document operations. Do not carry it into this module.

## Target workflow

Choose canonical states such as submitted, acknowledged, returned and cancelled, then map legacy strings explicitly. The exact state names and permitted resubmission behavior need an owner decision; do not silently map `Acknowledged` to a substantive approval. Preserve all imported history and reasons.

Use private storage, validated allowed document types, generated keys, checksum and ownership metadata. Uploading/creating history should be recoverable if file or database work fails. HR can filter all statuses and periods; employee lists show only owned submissions. Define duplicate employee/type/year/semester policy, and derive a suggested period from that policy rather than another person's upload.

## Build checklist

- [ ] Define submission types/periods, canonical states, acknowledgement meaning, correction/resubmission and duplicate policy.
- [ ] Implement employee-own and HR-processing policies for lists, details, files, cancellation and action history.
- [ ] Implement required/optional-file rules per document type with MIME/size validation and private storage.
- [ ] Store file/version metadata and transactional/recoverable record/history updates.
- [ ] Implement consistent HR acknowledgement/return and employee cancellation/resubmission transitions.
- [ ] Offer year/type/status filters, sensible period suggestions and readable history/return reasons.
- [ ] Migrate staff vs applicant uploads separately, including missing statuses and inconsistent physical paths.
- [ ] Replace public/broken file links with authorized download actions; account for orphan/missing files.
- [ ] Audit legacy aliases and retire unrelated incomplete attendance helper code from the target module.

## Acceptance checklist

- [ ] Staff cannot view, download or cancel another employee's upload, including through direct IDs.
- [ ] Only authorized HR staff acknowledge/return records; invalid and repeated transitions have no duplicate effect.
- [ ] Acknowledged records remain searchable and have a consistent state label in employee and HR lists.
- [ ] Returned-file correction preserves earlier files/history according to the accepted versioning rule.
- [ ] Duplicate period handling and suggested year work across multiple employees and semesters.
- [ ] Unsupported/oversized files fail validation; filenames cannot overwrite another document.
- [ ] Failed file/database work leaves no untracked metadata or permanently orphaned file.
- [ ] Migrated files resolve through the correct path, missing status/file records are reported, and applicant files do not enter the staff acknowledgement queue accidentally.

Dependencies: [authentication](01-authentication-and-authorization.md), [staff](02-organization-and-staff.md), [profile/PDS](03-profile-and-pds.md), [recruitment](11-recruitment-and-exams.md), and [private storage configuration](../EXTERNAL_CONFIGURATION.md).
