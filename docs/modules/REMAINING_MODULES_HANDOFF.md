# Modules 11, 17–20 implementation handoff

Implementation date: 23 October 2026, Asia/Manila. Requirements: `docs/rebuild/modules/11-*` and `17-*` through `20-*`, the rebuild architecture and decision register. The referenced legacy application and production export are unavailable here. This change extends the existing replacement application; it does not scaffold over it or import personnel data.

## Delivered workflows and access

| Module | Screens and implemented behavior | Authorization |
| --- | --- | --- |
| 11 Recruitment and exams | `/careers`, `/hr/recruitment`: vacancies, applicant profile, applications, private PDF requirements/evaluations, categories, four question types, question/choice images, questionnaire/evaluator assignment, examination sessions and printable answer reports | HR has `recruitment.manage`; applicants manage themselves; assigned evaluators see only their assigned applications/evaluation files/reports. Employee accounts cannot take applicant exams. |
| 17 Employment certificates | `/cert`: current and former-employee requests, receive/acknowledge/approve/reject, guarded issuance, annual compensation totals, private completed PDF upload/download, printable record summary, stable verification references | Employees see their own records. HR has `certificates.manage`. Manual requests require a former non-Intern employee. Issuance additionally requires an approved configured signer. Public verification is disabled by default. |
| 18 Recognition | `/recognition`: event dates, normalized nominees, configured voter employee types/self-voting, one final ballot per voter, private voter identities, aggregate results, separate manual winner confirmation, archive, provisional attendance statistics | HR has `recognition.manage`. Voters must be active eligible employees during the event's voting window and choose a nominee belonging to that event. |
| 19 WFH accomplishments | `/wfh-accomplishments`: own/on-behalf-of filing, pending/returned edits, chief return/approval, draft consolidation/refresh/archive, chief signature, head approval, division/agency printing | Own record, explicit chief/OIC division authority and Director/OIC authority are enforced on the server. Interns, inactive/terminated employees, future dates and self-review are excluded. HR sees approved consolidated reports unless independently authorized as a chief. |
| 20 Notifications | `/notifications`, `/api/notifications/*`: personal/role/broadcast publishing, ten-row list, unread badge/count, per-account read/dismiss and bulk actions, producer-scoped ingestion, event deduplication, scheduled expiry | Browser publishing needs `notifications.publish` (system administrators by default). External tokens need `notifications.publish`, with an additional `notifications.broadcast` scope for non-personal audiences. Receipt writes affect only the authenticated recipient. |

All screens use the existing responsive Bootstrap/Blade layout, asynchronous tables/forms, ten-row server pagination, private storage and session-derived actors. Field IDs are unique across dialogs so labels focus the correct control. Applicant accounts now have career and notification navigation. Reports use a separate printable layout; they do not claim to be approved legal forms.

Navigation and attendance update (23 October): HR's **Careers & recruitment** link is under **HR Administration**; employees without recruitment administration see **Careers** last under **Development & Records**. Applicants retain their workspace Careers link. Division chiefs, the chief administrative officer and the Director have read-only Careers access, including when assigned as evaluators or explicitly granted recruitment management.

Office web punches require an administrator-configured window from **Attendance → Set office punch duration**. Only `attendance.office-punch.manage` (system administrators by default) can create or revoke windows. Per the user's clarification, every window applies to **all active employees**; employee selection is not offered or accepted. Windows use Manila start/end datetimes, with inclusive start and exclusive end. **Record office punch** appears beside **View my attendance** on the employee portal and remains hidden outside an active window. The Attendance button uses the same visibility and access logic. Server validation governs every punch; both pages refresh availability at boundaries, on return to the tab and at most every 30 seconds. Revocation is audited and preserves history. Historical scope metadata is retained but cannot limit access to selected employees. Device ingestion and the Wednesday/calendar WFH rule are independent of office-web-punch access.

## Important behavior

### Recruitment

- A database unique key and locked vacancy prevent duplicate applications. Closed, archived and expired vacancies reject applications even if posted directly.
- Applicant profile fields are explicitly allowlisted. Application snapshots retain the applicant name/profile at filing; status/remark/test changes have append-only actor history.
- HR can assign exam categories and named evaluator roles to each vacancy. Four question types are supported: multiple choice, true/false, essay and identification. Questions/categories are archived; issued attempt evidence is retained.
- An attempt belongs to an **application and category**, with one attempt per pair. Start/resume takes time from the server's category configuration; reload cannot reset it. The attempt snapshots category identity, ordered questions, choices, image references and the time limit.
- Answers must belong to the attempt; choice values are validated. Empty responses are deliberately persisted as null. Expired, foreign or disabled attempts reject new submissions. Repeating a finalized submission preserves the first saved answers.
- Images use an examination-specific endpoint: access requires HR, an assigned evaluator, or a current enabled applicant attempt. General file URLs cannot bypass that policy. Evaluation files are inaccessible to applicants, including the applicant being evaluated.
- There is no automatic grading, pass mark, ranking, offer/onboarding or retake/reset feature. Public registration remains disabled. Provision applicant accounts through the existing authorized account workflow.

### Certificates

- States: `For Acknowledgment → Received → Acknowledged → Approved → COE Generated → Completed`. HR can reject a pending request with a mandatory reason into distinct terminal `Rejected` status. A repeated or out-of-order transition cannot silently reissue a certificate.
- There is one unfinished request per employee/type. Requests preserve employment identity/assignment dates; issuance adds the authenticated configured issuer snapshot and a stable verification URL. Subsequent employee/position changes do not rewrite this evidence.
- Monetary inputs are **annual amounts**, parsed into integer cents. Annual total = basic pay + PERA. Benefit total = mid-year + year-end + CNA + SRI + clothing + medical + PEI + cash gift. Grand total is the sum. Posted totals are ignored. Example: 100 + 20 + 30 produces 120, 30, 150.
- Completion stores an authorized HR-uploaded PDF privately. The printable record is clearly a summary; it does not fabricate official wording, letterhead or a signature.
- Issuance is deliberately unavailable until `CERTIFICATE_SIGNER_IDS` contains approved HR signer IDs. Public verification additionally requires `CERTIFICATE_PUBLIC_VERIFICATION=true`; only completed, issued references resolve, exposing reference, certificate type and issue date. No name, salary or personnel identifiers are public.
- Official generated certificate PDF/QR templates, approved branding/signature assets, legal wording, disclosure approval, revocation/reissue and historical-reference aliases remain **unimplemented pending D10/source artifacts**. Do not treat the summary's browser “save PDF” as the official generated certificate feature.

### Recognition

- HR explicitly chooses coverage, recognition date, voting opening/closing, voter employee types, nominees and whether self-voting is allowed. The implemented provisional ballot rule is one immutable ballot per employee/event; a retry for the same nominee is idempotent.
- Nominees are snapshotted at event creation. Opened/voted/published/archived events cannot be edited. Archival retains ballots and nominees.
- HR sees aggregate counts; employees see counts after closing. Individual voter identities are never returned. Winner confirmation is a separate HR operation after voting closes; counts do not automatically establish a winner or resolve ties.
- Attendance statistics use explicit date bounds (maximum one year), selected leave types, an explicit recent-hire choice, stored DTR minutes and latest LEAVE state. Sort is numeric leave days, numeric late+undertime minutes, then stable employee ID. Unreviewed/missing DTR coverage is shown rather than represented as zero-absence proof. The report is provisional and does not generate awards.
- HR must approve ballot/confidentiality/tie and ranking eligibility policies before production acceptance. No automatic adjudication or invented award eligibility formula is enabled.

### WFH

- The user's rule from 23 October is shared by punches and accomplishments: **every Wednesday OR a date inclusively covered by a Work From Home calendar event**, in Manila time. There is no fixed 2026 window.
- Existing explicit `division-chief.oic` and `director.oic` effective-date delegations are reused. There is no salary-grade-ID-based acting-chief fallback. The actual actor is recorded at signature/approval; nobody can review their own accomplishment or approve their own report signature.
- Approved individual records cannot be edited. A returned record clears return remarks when resubmitted. Deletion is archival with action history. An archived historical record cannot move divisions when recreated.
- Division-row locking serializes filing/review and report consolidation/signing. Signature recaptures normalized employee/accomplishment/fallback lines, then freezes the report date against later mutations. The report retains employee, division, content, source IDs and actual signer/head snapshots.
- Missing accomplishment fallback is latest **full-day Approved leave**, otherwise a pinned employee's non-null/non-`WEB WFH` attendance device, otherwise `no record`. Raw device identity is already pinned/normalized by module 05; unknown unlinked evidence is not reassigned implicitly.
- Chief queues and Director report queues generate a dashboard reminder from current workflow state. Dismissing a persisted notice cannot dismiss the underlying work queue.
- Partial-day precedence, exceptional reopening, historical eligibility after transfers/termination and mandatory completeness thresholds remain owner decisions. At least one captured eligible approved accomplishment is required; reports include explicit fallback rows for other eligible staff.

### Notifications

- Audiences use explicit role/user rows. Recipients are **snapshotted at publication** from active eligible accounts; broadcasts exclude applicants. A later hire/role change does not acquire old content or rewrite old receipts. This technical default needs D09 acceptance before production.
- Receipt keys are unique per notification/user. Read/dismiss timestamps are idempotent. Listing/counting never writes expiry state. The hourly `notifications:expire` command deactivates expired content and retains all receipts/audiences.
- WFH review results and certificate processing publish personal notifications after the source transaction commits. Delivery failure is reported without undoing the source transition. There is no email or external outbound delivery; production durable delivery/retry requirements remain a handover item.
- External publishers supply a stable `event_id`; identity is resolved from a matching active local ID/email. Producer identity comes from its service credential. Replays return the existing logical notice; conflicting content returns 409. A service credential for attendance alone is rejected.
- Destinations accept local absolute paths or explicitly configured HTTPS origins/base paths in `config/records.php`. Arbitrary URLs, protocol-relative links, credentials and legacy mixed `target_users` arrays are rejected. No destination receives an SSI credential.
- There is no destructive retention/purge operation until records owners approve D13. Existing shared legacy read flags cannot reconstruct individual history.

## Schema and migration mapping

`2026_10_23_000001_create_remaining_hris_modules.php` is additive and reversible, using restrictive foreign keys and explicit short unique-index names for MySQL compatibility.

| Legacy source | Replacement |
| --- | --- |
| Shared positions, category/question/choice tables | `recruitment_vacancies`, `exam_categories`, `exam_questions` (ordered choice/image JSON) |
| Position categories/evaluators | `vacancy_categories`, `recruitment_evaluators` |
| Applicant profile/applications/uploads/evaluation docs | `applicant_profiles`, `recruitment_applications`, `recruitment_documents`, `private_files` |
| User/category timers and user/question answers | `exam_attempts`: application/category identity, immutable question/category snapshots, explicit finalized response map |
| `coe_employed`, `coe_manual`, older `coe` | `employment_certificates`, `certificate_files`, `private_files`; type distinguishes current/manual; compensation uses integer cents |
| Recognition comma-separated nominees/winners and vote rows | `recognition_events`, `recognition_nominees` with snapshots/winner flags, `recognition_votes` with unique voter key |
| WFH individuals and header-only consolidations | `wfh_accomplishments`, `wfh_reports`, normalized `wfh_report_lines` snapshots |
| Shared system notification content/flags | `system_notifications`, `notification_audiences`, `notification_receipts` |
| Workflow mutations | Existing `request_statuses` keyed by request type/ID plus audit events |

This is a schema mapping, **not a completed legacy import**. No approved export/file manifest exists. Import requires original IDs/crosswalks, duplicate/orphan reconciliation, policy disposition of duplicate ballots, actual document path/case mapping, reference aliases and opening-state evidence. Preserve original issued files. Legacy header-only WFH reports and certificate live joins cannot establish original signed contents; reconstructions must be labeled separately. Do not manufacture old exam attempts or individual notification receipts from shared flags.

## Endpoint compatibility

Existing modules and device attendance URLs remain unchanged. New browser routes use guarded writes and session CSRF. Legacy recruitment/COE/award/WFH mutation names are not silently mapped to unauthenticated or ID-trusting handlers; replace callers with the canonical screens/actions in `routes/hris.php`. The missing legacy `upload_files` and recognition `show` handlers are replaced by the working career upload and details flows.

Notification compatibility keeps `notif-{id}` as a display ID and numeric `notification_id` for receipt writes. List/count/resource names and personal email resolution are preserved. Intentional partner changes: mandatory external `event_id`, separate credentials/scopes, typed `target_user_ids`/`target_roles` instead of mixed `target_users`, destination allowlisting, conflict rejection for mismatched ID/email and paginated ten-row lists. Coordinate consumers before enabling partner production credentials.

## Operation and verification

1. Apply `php artisan migrate`. It creates only new module tables; it does not seed accounts or modify personnel records.
2. Grant recruitment/certificate/recognition abilities through the existing authorized account process. HR receives these abilities by default. System administrators publish notifications; staff retain only their own workflow access.
3. Keep public registration disabled until recruitment approves provisioning. Configure approved certificate signer IDs and public verification only after HR authorization; no production values or signature images are included here.
4. Configure notification destinations by producer name in `config/records.php`. Provision a personal-only token with `php artisan notifications:client "Producer name"`; use `--broadcast` only when broad audiences are approved, and `--revoke` to revoke it. The token is displayed once; store it securely. A collector's existing service name is rejected to prevent replacing attendance credentials. Test against staging partners before production enablement.
5. Maintain the existing scheduler so `notifications:expire` runs hourly. Run it manually to verify idempotent expiry without deleting history.
6. Use the repository PHPUnit configuration (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) and the isolated browser fixture server. Do not run migration refresh/rollback against the local personnel database or assume `--env=testing` alone changes its connection.

Verification evidence and current execution totals are recorded in [IMPLEMENTATION_PROGRESS.md](IMPLEMENTATION_PROGRESS.md). Module status: implementation delivered and validated locally; business-policy acceptance, official generated certificate artifacts, production integrations and legacy reconciliation remain open as described above.

## Administrator raw attendance corrections

Use **HR Administration > Raw attendance punches** (`/hr/raw-attendance`) as a system administrator. Search by employee, ATMS ID or device, filter dates/employee matches, choose **Edit**, and enter the corrected Manila punch time, device/record type and a required reason. **History** shows each actor, reason and before/after values. The list includes former employees and unmatched captures. HR, chiefs, directors, staff and applicants cannot access the page or its APIs even with an explicit raw-management grant.

Migration `2026_10_23_000003_add_raw_punch_corrections.php` adds original evidence/version columns and `raw_punch_revisions`. Corrections preserve employee and collector identities. Device retries compare against original evidence and cannot overwrite a correction; conflicting source payloads still fail atomically. Old/new DTR dates rebuild in the correction transaction, preserving manual DTR overrides. Optimistic versions reject stale edits; unmatched captures require the existing identity reconciliation process before a DTR can be generated. The module provides edits and history only.
