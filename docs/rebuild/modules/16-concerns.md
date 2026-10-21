# 16 — Employee concerns

## Purpose and evidence

Let employees lodge concerns with attachments, let HR acknowledge and resolve them with a response/evidence, and allow the employee to acknowledge the response.

**Observed** describes source behavior; **Proposed** describes rebuild work; **Unresolved** requires a product decision.

Sources: [ConcernController](../../../app/Http/Controllers/ConcernController.php), [Concern](../../../app/Concern.php), [ConcernUpload](../../../app/ConcernUpload.php), [concern screens](../../../resources/views/concerns), [web routes](../../../routes/web.php). Migrations: `2025_09_04_182013_create_concerns_table.php`, `2025_09_04_182337_create_concern_uploads_table.php`, `2026_01_28_135156_add_concerns_columns.php`.

## Actors and authorization

Observed: controller requires authentication. Type `3` users see HR queues across employees; others see records with their own `employee_id`. However get/edit/delete/upload/acknowledgment methods find a supplied concern ID without consistently enforcing owner/HR permissions. `store` accepts employee and HR IDs from the form. The employee view hides edit/delete after New, but the server does not consistently enforce that rule.

Proposed: employee may create their own concern and edit/cancel while permitted; HR may acknowledge/respond/resolve; only the concern owner acknowledges the HR response. All attachment access uses the same concern policy.

## Data and states

`concerns`: ID, employee ID, subject, description, response, status, HR ID, resolved-date string, employee-file list (`file_upload`), HR-file list (`hr_file`), timestamps. Both file fields contain JSON-encoded filename arrays in current handlers, although migrations create string columns. The acknowledgment method writes `staff_response = Acknowledged`; a matching column is not present in the inspected concern migrations and must be reconciled with actual schema.

`concern_uploads`: concern ID and file path exists as a separate model/table, but the current controller stores filenames in concern JSON fields instead of using it.

Observed UI sequence is `New` → `Acknowledged` → `Resolved`. Migration default is `Open`, whereas controller sets `New` on a fresh record. Existing status spellings require migration inventory; do not silently discard `Open` rows.

## Workflows and outputs

1. `/concerns` shows employee list or HR New/Acknowledged/Resolved queues. HR queues query exact strings.
2. `/concerns/store` creates or updates a record. New records become `New`; existing records take requested status. It stores employee/subject/description plus request-supplied response, resolved date and HR actor.
3. Employee files are added via `/concerns/upload-file`; HR response files can be included with `store`. Files use original names under `public/images/concerns`; filename arrays are merged with existing arrays.
4. The HR modal permits Acknowledged for New and Resolved for Acknowledged. This sequencing is a UI restriction, not a server state machine.
5. `/getConcern` and `/getConcernUpdate` return selected records as JSON. `/acknowledgeConcern` sets the staff acknowledgment field.
6. `/concern/remove-file` removes a filename from the employee list and deletes that physical filename. `/cancelConcern/{id}` physically deletes the concern; it does not perform an auditable cancel transition or comprehensively clean files.

## Migration issues and decisions

- Public filenames can collide across unrelated concerns; deleting one can affect another record's evidence. Proposed storage must use generated keys and record ownership.
- Convert string-encoded file arrays to normalized attachment rows with uploader/side, original name, size, MIME, object key, checksum and timestamps. Inventory any existing `concern_uploads` data before reconciling the two stores.
- Replace free-form employee/HR/status/resolution fields with server-derived actors and explicit actions. Preserve current text responses and status history if available rather than inventing dates.
- Increase subject/description/response/file metadata storage lengths appropriately; current string columns can truncate long narratives or attachment arrays.
- Resolve missing `staff_response` schema before enabling that action in a clean install.
- **Unresolved:** whether resolved cases can reopen, edit/cancel limits, HR assignment/escalation, response acknowledgment effects, anonymity, notification requirements, retention, and removal policy for evidence.

## Build checklist

- [ ] Define concern, response, audited status transition and normalized attachment entities.
- [ ] Implement employee list/new/edit/cancel and HR acknowledgment/resolve queues with enforced role/owner scope.
- [ ] Validate required subject/body and response/resolution inputs; derive employee and responding HR actor from authorization context.
- [ ] Enforce the approved New/Acknowledged/Resolved/cancel rules on the server, with idempotent responses.
- [ ] Store evidence privately with generated names and authorized preview/download/remove.
- [ ] Implement staff response acknowledgment as an explicit persisted action with actor/time.
- [ ] Migrate `Open` versus `New`, JSON attachments and any `concern_uploads` records, and report missing/colliding files.
- [ ] Add notifications only through the shared notification service after confirming trigger/recipient requirements.

## Acceptance checklist

- [ ] Employee submission creates one self-owned New concern; request-supplied employee/HR identity cannot impersonate another person.
- [ ] HR acknowledgment/resolution follows approved transitions; a staff member cannot set status or HR response through direct requests.
- [ ] Employees can see only their own narrative and attachments; HR access is explicit and auditable.
- [ ] Files with identical original names on different concerns remain independent, including after removal.
- [ ] Failed upload/save does not remove existing evidence or leave a misleading resolved state.
- [ ] Long text and multiple attachments persist without truncation.
- [ ] Staff acknowledgment is restricted to the concern owner and recorded once with time.
- [ ] Migrated New/Open/Acknowledged/Resolved records are all visible in an appropriate queue or reconciliation report.
