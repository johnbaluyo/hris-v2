# 06. Official business (OB)

**Observed** describes code; **Proposed** describes rebuild work; **Unresolved** identifies policy or data decisions. OB covers approved attendance away from the office, including medical OB and other types exposed by the existing form. It is a dependency of DTR exemptions and calendar displays.

## Actors, authorization and sources

Employees file/view their own OB, division chiefs review division staff, ED/OIC views review director-level/OED requests, and HR can create an approved override and see approved OB overview. `OBController::__construct` only enforces authentication. `edit_ob`, upload, cancel, print, approval and override accept IDs without a consistent ownership/role policy; some actors and status history `person_in_charge` are taken from the request. Rebuild policies must enforce owner, division, approval authority, OIC effective dates and override capability server-side, deriving actors from the session.

| Source | Contract |
| --- | --- |
| `app/OfficialBusiness.php::create_ob`, `approvedisapprove_ob` | Filing, edit/amend, status and notification logic |
| `app/Http/Controllers/OBController.php` | Personal/division/OIC/approved listings; upload, print, cancel and HR override |
| `routes/web.php` `/employee/ob/*`, `/ob-overview`, `/hr/ob/override_ob` | Browser endpoints; `/employee/ob/amend` references absent `amend_ob`, while amendment is handled inside `create_ob` |
| `resources/views/ob/`; `public/js/ob.js`; `public/js/leave_cto_ob.js` | Forms, type labels, action visibility and printed notice |
| `app/Http/Controllers/DTRController.php::checkOB`; `app/Helpers/DtrHelpers.php::has_dtr`, `check_holiday`, `countOB`; `HRStaffController::has_dtr` | Attendance effects and report counts |
| `app/Http/Controllers/SsiController.php::get_ob`; `app/OfficialBusiness.php::get_ob` | Calendar/event detail integration |

## Data and statuses

`official_businesses`: employee `emp_id`, filing date, OB date, type, departure/return times, origin (`from`), destination, departure/return options, purpose, nullable JSON `old`, amendment `reason`, attachment `ob_file` and timestamps. Schema begins in `2021_04_12_114435_create_official_businesses_table.php`; later changes include `2021_07_18_171610_add_amend_reason.php`, `2024_02_06_092040_change_type_ob.php`, `2025_01_22_184107_add_ob_type.php`, `2025_03_12_175358_add_ob_file.php`. Verify final nullable and time/string definitions from the schema inventory.

The form and request-mail template label type `1` WORK-RELATED, `2` MEDICAL, `3` BEREAVEMENT (`public/js/ob.js`, `resources/views/ob/file_ob.blade.php`). The printed form instructs filing one to three days before an official function, one OB day per form except warranted, attaching signed evidence to the monthly attendance report and using attendance certificates for training/seminars. These are observed printed instructions; the controller does not demonstrate equivalent server enforcement. Confirm the current approved instructions before turning them into validation rules.

Status events use shared `statuses` with `request_type = OB`; effective state is generally latest ID: `For DC Approval`, `For ED Approval`, `Approved`, `Disapproved by DC`, `Disapproved by ED`, `Amended`, `Cancelled`. Some OB relationships are unscoped to request type, so the rebuild must always include the discriminator. OB approval is one final decision by the selected DC or ED; it does **not** follow OT's DC-then-ED progression.

## Observed workflow

1. New filing derives employee from `Auth`, accepts submitted dates/times/options/purpose and writes the record. User type 2 or division 1 routes to `For ED Approval`; others route to `For DC Approval`.
2. Notification uses synchronous `SendMail`. Staff/HR in division 1 choose ED; other staff choose division chief. The type-2 recipient branch selects that division's chief even though status routes to ED: preserve this as a gap to resolve.
3. `ob_action = update` changes the existing row and sets employee to the current user, without a new status event. `ob_action = amend` stores the previous row JSON in `old`, writes new values/reason, sends mail and appends `Amended`. It stores one previous snapshot, not a complete version chain. Amendment recipient selection handles fewer roles than filing.
4. Approval action `app` appends `Approved`; rejection uses role-specific disapproval and reason. Both send outcome mail to the employee. No explicit transition guard or status/ownership validation is shown in the model.
5. Cancel appends `Cancelled`. Upload accepts an existing file-text value or moves a file under `public/images/uploads/staff/ob`. The existing-file branch uses `file`, while the upload branch uses `ob_file`, and deletion reads `file`: reconcile this mismatch before migrating attachments.
6. HR override creates OB plus `Approved` status directly; departure/return times and origin are empty strings, bypassing ordinary form input and notification. It does not immediately recalculate DTR.

Medical OB counting in `OBController::index` uses type `2`, the **current month/year**, and any matching Approved status. The UI limit is one for most type-2 users, two for other listed staff roles, with literal employee-ID exceptions in code. This is computed in the personal index, not a reliable server-side filing limit. Move the approved policy into effective-dated configuration and named assignments; do not copy personnel IDs into new rules. Confirm the authoritative type catalogue from form JS and deployed data.

Attendance options are stored as `go_directly`/`report_first` and `not_expected_to_return`/`return_to_office`. DTR batch clearing removes late for direct departure and undertime when return is not expected, but that batch query does not require Approved status. Other calculators check the latest OB status and use 16:00 or 18:00 return thresholds depending on schedule. Missing DTR detection may treat any historical Approved OB as an exemption. Monthly `countOB` counts approved type-2 full-day direct/no-return rows. Rebuild the DTR integration only after the authoritative policy is settled.

## Outputs, dependencies and migration

Outputs: personal OB table, division/ED/OIC approval tables and counters, approved all-staff overview, status shown in edit details, printable OB form with signatures, request/amendment/outcome mail, calendar descriptions and uploaded evidence. Depend on staff/division/position/signature records, active chief/ED/OIC assignments, shared status history, mail, storage and DTR rules.

Import all status history and amendment JSON rather than flattening to Approved. Match attachment names to existing files using a manifest; report missing files without deleting records. Audit records lacking statuses and multiple same-day OBs before deciding uniqueness. Keep legacy type values and map them to a reviewed catalogue. Proposed private storage/download authorization replaces the public upload path; preserve original filenames as metadata and validate MIME/size.

**Unresolved:** approved medical limits and exceptions, counting by current versus requested month, amendment reapproval routing, holiday/half-day behavior, whether rejected/cancelled OB permits refile, compulsory evidence, type catalogue, partial-time attendance exemption and notification recipient mismatch.

## Implementation checklist

- [ ] Import OB, scoped status history, old snapshots, attachment metadata and employee/type mappings.
- [ ] Implement authorized personal/division/ED/OIC/HR overview queries using latest status.
- [ ] Implement validated filing, editable states, immutable amendment revisions and reviewed routing.
- [ ] Add server-side medical-limit validation using approved configuration and the requested OB month.
- [ ] Implement guarded approval/rejection/cancel and audited HR override with a required reason.
- [ ] Queue notifications after database commit and record delivery status/retries.
- [ ] Rebuild authorized file upload/download, printed form, signatures and calendar integration.
- [ ] Publish OB-change events to recalculate attendance with proper approval/cancellation checks.

## Acceptance checklist

- [ ] Staff cannot edit, cancel, upload for or print another person's request without an explicit capability.
- [ ] Ordinary staff, division chiefs, OED staff and an active OIC route to the correct authority and recipient.
- [ ] Invalid transitions, double approval and forged actor IDs have no effect.
- [ ] Medical limits handle requested month/year, rejection/cancellation and configured exceptions consistently.
- [ ] All four departure/return combinations have approved DTR fixtures, including cancellation after approval.
- [ ] Amendment history, prior signatures and original request details remain reviewable.
- [ ] Missing attachment/status/approver produces a recoverable review case, and mail failure does not leave half a filing.
