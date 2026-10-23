# 17 — Certificates of employment (COE)

> Replacement implementation updated 23 October 2026. Checked technical build items have source and regression evidence in [the remaining-modules handoff](../../modules/REMAINING_MODULES_HANDOFF.md). Production-policy acceptance, official certificate generation and legacy reconciliation remain separate gates; unchecked items are not claimed complete.

## Purpose and evidence

Process current employees' certificate requests, let HR generate certificates with pay/benefit details, produce manual certificates for former employees, and expose QR-linked authenticity pages.

**Observed** is implemented source behavior. **Proposed** is rebuild work. **Unresolved** is a decision or inconsistency that must be resolved before claiming parity.

Sources: [COEController](../../../app/Http/Controllers/COEController.php); [CertEmployed](../../../app/CertEmployed.php); [CertManual](../../../app/CertManual.php); [certificate views](../../../resources/views/cert); [web routes](../../../routes/web.php). Schema: `2026_03_02_131309_cert_employed.php`, `2026_03_02_134419_add_request_id_column.php`, `2026_03_02_170406_cert_manual.php`, `2026_03_03_110528_add_hr_position_column.php`, `2026_03_03_133121_add_employee_id_manual_column.php`, `2026_03_03_134449_drop_full_name_column.php`. An earlier `coe` table also exists; inspect whether it contains legacy records before retiring it.

## Actors and access

Observed: `/cert` branches type `1` to their own staff requests and types `2`/`3` to the HR list; others redirect to login. Controller has no authentication constructor, and inspected routes have no explicit auth protection. Many lookup/mutation/PDF actions use supplied IDs without authentication/ownership checks. Public verification is a distinct intended capability; it does not justify unauthenticated certificate administration.

Proposed: employee reads/requests their own certificate; HR receives, acknowledges, approves/rejects, generates and uploads a completed certificate. Former-employee/manual certificates require HR permission. Public verification exposes only approved minimal authenticity metadata and an unguessable token.

## Entities and states

| Entity | Observed data |
| --- | --- |
| `coe_employed` | Employee/reference ID, prefix, position/division/salary-grade references, hire date, status, forwarding code, acknowledger name and HR position reference, rejection reason, HR response, signature field, requested-file JSON list, basic pay/PERA and eight benefit values, annual/benefit/grand totals, timestamps. Most values are strings in the create migration. |
| `coe_manual` | Former employee/reference, prefix, position/division, hire/end dates, status/forwarding/acknowledger/HR response/signature/file list. Later migrations replace the original free-text fullname with employee ID. |
| Shared staff/reference data | User, position, division, salary-grade row, current relationships; current templates can resolve names/titles live rather than from an immutable issued snapshot. |

UI/handler states include `For Acknowledgment` → `Received` → `Acknowledged` → `COE Generated` → `Completed`. HR response is a separate `Approved`/`Rejected` field. `create` sets `COE Generated` regardless of response, so rejection is not a clean separate state. `forwarded_to = 3` selects the HR queue; acknowledger is stored as a name, not consistently as a user ID.

## Workflows and outputs

1. `/cert` or `/cert/hr` lists permitted current-user requests. Request form snapshots employee/assignment/hire information, defaults For Acknowledgment/forward-to-HR, and posts `/cert/request`.
2. Request handler checks for a blank-response pending request only in certain role branches, using submitted employee ID plus current user. New or edited requests generate a new `COE` reference using `uniqid`; editing can change an existing verification reference.
3. `/cert/received` marks Received; `/cert/acknowledged` marks Acknowledged and records acknowledger/HR position. Their matching `/getCert*Update` endpoints populate modals.
4. `/cert/create` saves HR response/reason, signature field, pay/benefit values and totals and marks COE Generated. Browser totals in `cert.lists` are annual total = basic pay + PERA; benefit total = mid-year + year-end + CNA + SRI + clothing + medical + PEI + cash gift; grand total = both. Inputs are accepted by the server without recomputing; these values are already presented as annual amounts, not multiplied by twelve in this function.
5. `/cert/upload-file` appends requested files under `public/images/coe` and marks Completed. Generated PDF and uploaded completion files are different artifacts.
6. `/cert/pdf/{id}` uses DomPDF to download `cert.cert_pdf`; it includes letterhead/footer, employment and compensation details, signatory and QR code.
7. `/cert/manual` lists manual records and former non-Intern employees with a non-null termination date. `/cert/manualrequest` creates/updates the historical employment snapshot. Manual upload and PDF endpoints provide corresponding completion/output behavior without the current-employee compensation breakdown.
8. `/verification/{request_id}` and `/verification/manual/{request_id}` display certificate/employee metadata or a custom 404 view. Current verification only checks reference existence, not approval/completion/revocation.

## Dependencies and migration issues

- DomPDF and QR PNG/logo-merge generation must work in deployment. Templates reference `images/bagongpilipinas_letterhead.png`, `psrti_logo.png`, `footer.png`, `QMS.png`, and a fixed `images/coe/e_sig.png`; verification also needs its logo/check assets. Correct application URL is essential for generated QR links.
- Current PDF uses a fixed signature asset rather than establishing signer identity from an approved issuance action. Resolve signatory authority and asset handling without copying a signature into documentation or fixtures.
- The PDF download handlers regenerate content from the certificate row, current related employee/position data and current template/assets; they do not persist an immutable generated-PDF snapshot. Files referenced in `requested_file` may contain the actual completed certificate and should be preserved as original artifacts when available. Otherwise, label regenerated historical PDFs as reconstructions; the original issued bytes and values cannot be assumed recoverable from current joins alone.
- Server must validate/recalculate decimal amounts and define annual-vs-monthly units. Salary-grade relations are inconsistent: some map a certificate field to a position, others to a salary-grade row. Reconcile actual IDs.
- Original string/non-null schema conflicts with partial workflow writes that omit later-stage fields. Clean-install migrations must support valid draft/pending records explicitly.
- Replace name-based acknowledger and mutable live employee/position joins with actor references plus immutable issued snapshots. Preserve old verification references as aliases if public links already exist.
- Original filenames and string JSON lists can collide/truncate; migrate to attachment rows and private downloads.
- **Unresolved:** rejection status, resubmission/reissue/revocation, authorized signer, manual certificate eligibility, public verification disclosure, pending-request uniqueness, and whether old `coe` records require conversion.

## Build checklist

- [x] Define certificate request, current/manual type, issuance snapshot, compensation lines, status history, attachments and stable public verification tokens.
- [x] Implement authenticated employee/HR/manual administration policies and explicit field allowlists.
- [ ] Implement receive/acknowledge/approve/reject/generate/complete transitions with audited actor/time and duplicate-pending rules.
- [x] Validate annual compensation units and compute all totals on the server with exact decimals.
- [ ] Preserve employee/assignment/hire/end/signatory information as issued; retain historical references and PDFs.
- [ ] Generate current/manual PDFs and QR codes through testable services with configurable approved branding/signature assets.
- [x] Implement private file uploads and completion downloads without filename collisions.
- [ ] Build limited public verification and invalid/revoked/not-issued results; add rate limiting and stable reference migration.
- [ ] Reconcile earlier `coe`, employed/manual tables, string amounts, reference collisions and missing assets/files; distinguish preserved completed-file originals from reconstructed historical PDFs.

## Acceptance checklist

- [ ] Staff cannot request for another employee, alter HR status/amounts, or retrieve another certificate/PDF by ID.
- [ ] HR can process valid requests and rejection follows the approved distinct workflow.
- [ ] For annual basic pay 100, PERA 20 and benefits 30, totals are 120, 30 and 150; posted forged totals are ignored/rejected.
- [ ] The PDF and verification page retain issued identity/position/signatory after the employee record changes.
- [ ] Every issued QR resolves using the deployment URL; unknown/unissued/revoked tokens reveal only agreed information.
- [ ] Manual certificates show correct former employment dates and omit inappropriate compensation content.
- [ ] Generated PDF branding/QR assets render in the deployment runtime; missing assets fail clearly.
- [ ] Repeated workflow calls do not duplicate issuance or invalidate a previously distributed verification link.
