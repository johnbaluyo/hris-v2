# 11 — Recruitment and applicant examinations

> Replacement implementation updated 23 October 2026. Checked technical build items have source and regression evidence in [the remaining-modules handoff](../../modules/REMAINING_MODULES_HANDOFF.md). Production-policy acceptance, official certificate generation and legacy reconciliation remain separate gates; unchecked items are not claimed complete.

## Purpose and evidence

Manage vacancies, applicant accounts and documents, applications, examination question banks, position questionnaires, evaluator assignments, timed examination sessions, and printable answer reports.

**Observed** identifies implemented source behavior. **Proposed** identifies rebuild requirements. **Unresolved** identifies decisions or incomplete functionality; do not treat such items as existing features.

Sources:

- [RecruitmentController](../../../app/Http/Controllers/RecruitmentController.php): position/category/question/choice/profile/application CRUD, uploads, questionnaires, evaluators, test toggle, answer report.
- [RecruitmentExamController](../../../app/Http/Controllers/RecruitmentExamController.php): `applicant_index`, `my_applications`, `exam`, `submit_exam`, `start_timer`, applicant profile operations.
- [Recruitment views](../../../resources/views/recruitment), [Position](../../../app/Position.php), [User](../../../app/User.php), [Upload](../../../app/Upload.php).
- Models `RCategory`, `RQuestion`, `RChoices`, `RPositionApplicant`, `RPositionCategory`, `RExamTimer`, `RExamAnswer`, `PositionEvaluators`, `REvalDocs` in `app/`.
- Recruitment migrations from `2021_10_27_154436_position_recruitment_status.php` through December 2021 exam/evaluator/document alterations; [web routes](../../../routes/web.php).

## Actors and authorization

Observed: both controllers require authentication. HR helpers check `user_type == 3`, but callers ignore the helper's returned redirect, and many mutation methods never call it. Applicants are `users.user_type = 0`, with employee type `Applicant`. `User::save_profile_2` can update an account using a supplied profile ID. Examination submission accepts supplied user/category identifiers. These are access-control gaps to fix.

Proposed: HR administers vacancy/questionnaire/application/evaluator data. Applicants read vacancies, manage their own profile/documents/applications, and take only examinations assigned to their eligible application. Evaluator access must be constrained to assigned positions/applications. Do not assume evaluator assignment currently enforces a full review workflow.

## Entities and states

| Entity | Observed contract |
| --- | --- |
| `positions` | Shared staff position record, salary-grade reference, division, title/code, plantilla, recruitment status. Closing/archive is not physical deletion. |
| `users` | Applicant identity and credentials share the employee account table. HR profile lists select type `0`. |
| `r_position_applicant` | Applicant/user, position, textual status, remark, `allow_test`. |
| `r_categories` | Category name, time limit, exam-type label. |
| `r_questions` | Category, question type, text, optional image. Types: `1` multiple choice, `2` true/false, `3` essay, `4` identification. |
| `r_choices` | Question, choice text (`answer`), optional image. No grading/answer-key workflow is demonstrated by these CRUD methods. |
| `r_position_category` | Position-to-category/questionnaire association; model prevents a duplicate pair with an existence check. |
| `r_exam_timer` | User/category, time end, submission flag `N`/`Y`. Keyed by user and category rather than application/attempt. |
| `r_exam_answers` | User, question, answer. Current submission creates a response only if that user/question has no saved response. |
| `position_evaluators` | Position, user, role; duplicate position/user rejected by application check. |
| `uploads`, `r_eval_docs` | Shared applicant requirements versus evaluation files associated with an application. |

Application options in `resources/views/recruitment/pages/applications.blade.php`: `pending application`, `for assessment`, `hired`, `short listed`, `failed`. Position listings use `Open for Application`, `Filled Up`, and `archived`; enumerate any additional live values before converting to enums. Requirement types: `TOR`, `WES`, `PDS`, `CSE`, `IPCR`, and others.

## Workflows and outputs

1. HR configures positions at `/positions` and `/hr/recruitment/positions/*`; category/question/choice operations build a reusable question bank. Optional question and choice images are written under `public/images/questions`.
2. HR associates categories with each position via `/hr/recruitment/*questionnaire`; `RPositionCategory` orders them by exam type. Evaluator assignment stores a position/user/role pair.
3. `/careers` lists open positions excluding positions for which the logged-in applicant already has an application. This filtering is UI/query behavior; the save method does not enforce uniqueness or vacancy openness.
4. `/hr/submit_application` and HR CRUD delegate to `RPositionApplicant::save_application`, which accepts applicant, position, status, remark, and selected application ID. `/careers/my_applications` loads current-user applications and the UI exposes eligible test links.
5. HR toggles `allow_test` through `/toggle`. `/careers/exam/{category_id}` groups questions by type. Timer helpers report open when no timer exists; otherwise open only before expiry and while not submitted.
6. `/careers/start_timer` creates the first user/category timer using a request-supplied time limit. `/careers/submit_exam` iterates submitted form fields after its first two entries, creates non-empty unsaved answers, and marks the timer submitted. It does not enforce the server time limit or an eligible application in these methods.
7. Applicant profile and document screens live at `/careers/profile`; HR requirement upload allows PDF up to `10000` KB, while evaluation-document upload only checks required/max size. Applicant profile upload uses shared `Upload::profile_uploads`.
8. Requirement files are under `public/images/applicants/{type}` with mixed case/path conventions; evaluation uploads use `public/images/applicants/tor` regardless of type. Preserve actual stored paths during migration.
9. `/recruitment/exam_answers/{application_id}` renders a printable answer report. It contains examiner/evaluation spaces; a complete automatic scoring, ranking, offer, or onboarding workflow is not demonstrated.

## Migration issues and decisions

- Current timers and answers can be shared across separate applications using the same category/question. **Unresolved:** intended retake/reapplication behavior. Proposed attempts should belong to an application and retain the exact question version.
- Time limits, ownership, question membership, application eligibility, and once-only submission must move into the server transaction. Browser timers are presentation only.
- Existing applicant account creation/reset uses a fixed default password in `User::save_profile`. Replace this with secure invitation/password setup; do not reproduce the default credential.
- `save_profile_2` writes zero division/date sentinel values. Normalize invalid dates and unknown references with a report, keeping original values for reconciliation.
- Category/question deletes cascade some child rows manually but do not comprehensively protect historical exam evidence. Version/archive used question banks.
- The route `/careers/upload_files` targets `RecruitmentExamController@upload_files`, which is absent in the inspected class; use the working profile upload flow or explicitly replace the broken route.
- **Unresolved:** grader/answer-key design, evaluator roles, scoring criteria/pass marks, application status transitions, retention/consent, hiring handoff, and whether registration is intended to remain disabled.

## Build checklist

- [x] Define vacancies, applicant profiles, applications, question-bank versions, questionnaire assignments, evaluator assignments, attempts, answers, and file metadata.
- [ ] Migrate applicant IDs separately from employee IDs while preserving the shared legacy account mapping.
- [x] Implement HR-only administration and applicant self-service policies on every endpoint, including attachments and reports.
- [ ] Implement vacancy open/close/archive, application uniqueness rules, status/remark history, and test eligibility.
- [x] Support all four question types, optional images, category time limits, questionnaires, and evaluator role assignment.
- [x] Start an attempt from server configuration and snapshot its questions/order/time limit; make submit transactional/idempotent.
- [x] Reject answers for foreign/unknown questions, expired/ineligible attempts, and supplied alternative user IDs.
- [ ] Implement private validated requirements/evaluation uploads and map all legacy case/path variants.
- [ ] Recreate printable answer reports and explicitly scope any new grading/ranking workflow after resolving requirements.
- [x] Inventory missing/broken endpoints and retire them with a documented compatibility mapping.

## Acceptance checklist

- [ ] An applicant sees only open eligible vacancies and can create the agreed number of applications even under concurrent requests.
- [ ] Applicants cannot alter another profile/application, read answer reports, toggle tests, or administer questions.
- [ ] Time starts from server configuration, survives reload, cannot be extended by changing a submitted value, and prevents late submission.
- [ ] Repeated submissions do not duplicate/overwrite finalized answers; blank responses are represented deliberately.
- [ ] Two applications/attempts obey the approved retake policy without silently sharing answers.
- [ ] Each question type and image renders in both exam and report; long essay answers remain readable.
- [ ] Assigned evaluator access is constrained; unassigned users cannot retrieve evaluation files.
- [ ] Existing application statuses, timer/submission states, and original responses reconcile to the migration report.
