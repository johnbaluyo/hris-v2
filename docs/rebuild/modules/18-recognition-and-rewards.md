# 18 — Recognition, rewards and attendance ranking

## Purpose and evidence

Maintain recognition events with nominee/winner lists, collect votes, display vote counts, and generate attendance statistics used to select attendance-based awardees.

**Observed** means source behavior; **Proposed** means rebuild requirements; **Unresolved** requires a business decision.

Sources: [HRStaffController](../../../app/Http/Controllers/HRStaffController.php), methods `nominationAwardees`, `nominationAwardeesSave`, `nominationAwardeesDelete`, `nominationAwardeesVote`, `attendanceReport`; [RecognitionReward](../../../app/RecognitionReward.php); [RecognitionAwardVote](../../../app/RecognitionAwardVote.php); [recognition screen](../../../resources/views/hr_staff/nominationAwardees.blade.php); [monthly attendance report](../../../resources/views/monthly_attendance.blade.php); [web routes](../../../routes/web.php). Migrations: `2021_12_22_010558_create_recognition_rewards_table.php`, `2021_12_27_022329_create_recognition_award_votes_table.php`.

## Actors and scope

Observed: `HRStaffController` requires authentication; recognition methods do not consistently enforce HR role or voting eligibility. Voting records the current user's ID but trusts submitted recognition/nominee IDs. Employee selection offers Permanent, Contractual Regular and CSW. The screen reads counts and manually stored winner lists; a complete automatic award adjudication process is not demonstrated.

Proposed: HR administers events, eligible employees vote during defined periods, and permitted reviewers read counts/rankings. Confirm whether voting is secret and who may see counts before closing.

## Data and workflow

| Entity | Observed fields |
| --- | --- |
| `recognition_rewards` | Name, coverage start/end, recognition date, type (`voting` or `attendance`), comma-separated nominee IDs, comma-separated optional winner IDs, timestamps. |
| `recognition_award_votes` | Event ID, nominee ID (`nominees_id`), voter ID, timestamps; no demonstrated uniqueness constraint. |

1. `/hr/recognition-awardees` lists all events; it resolves comma-separated nominees/winners to users and aggregates votes per nominee.
2. Saving creates/updates an event from a date-range text field, recognition date, type, nominee list and winner list. Parsing splits the coverage string by `-`; use validated explicit dates in the rebuild.
3. Voting simply appends a row. It does not check duplicate voter, eligible nominee, event type, coverage window or self-voting rules. Award deletion removes the event but does not explicitly remove vote rows.
4. `/hr/recognition-awardees/attreport` creates a monthly/year-selected attendance report. Source selection includes Permanent/Contractual Regular staff hired before the reporting year, excludes head-of-agency type `4`, and applies termination conditions. It does not automatically assign the saved award winners.
5. Attendance report computes tardiness/undertime through `HolidayController::check_dtr`, counts approved leave dates for selected type IDs, and ranks by total counted leave then late-plus-undertime duration. Current duration is formatted `HH:MM`, and string comparison is used in sorting.
6. Year/all-month handling has hardcoded November-end coverage in branches and simplified leap-year/month boundary logic. Leave joins use Approved history rows without consistently resolving each leave's latest status. These details must be corrected or expressly approved, not replicated as policy.

Routes: `/hr/recognition-awardees` GET/POST, `/awardees`, `/delete`, `/vote`, `/attreport` suffixes. `/hr/recognition-awardees/show` points to `nominationAwardeesShow`, absent from the inspected controller.

## Dependencies and decisions

Attendance ranking depends on agreed DTR computation, holiday/workweek logic, leave types/current leave status, hire/end-date eligibility, and modules 02/05/06. No external voting provider is involved in the inspected implementation.

**Unresolved:** vote limits, eligible voters/nominees, self-voting, period open/close semantics, tie-breaks, winner selection authority, vote confidentiality, attendance period definitions, excluded/credited leave types, recent hires, and retention/revocation of an award. Current comma lists and repeated votes must be reconciled against the approved policy before migration.

## Build checklist

- [ ] Define event, normalized nominees, ballots/votes, approved winner records and audit history with legacy mappings.
- [ ] Implement HR-only event create/update/archive and explicit coverage/recognition dates.
- [ ] Implement approved voter eligibility/limits, event open/closed checks, nominee membership and self-vote/tie policies.
- [ ] Enforce ballot uniqueness transactionally and decide visibility/privacy of individual voters versus aggregate results.
- [ ] Implement manual or approved automatic winner confirmation separately from vote/attendance result calculation.
- [ ] Reuse authoritative DTR and latest leave-state services to calculate attendance ranking in numeric minutes.
- [ ] Replace calendar hardcoding with explicit coverage, valid month bounds and complete leap-year handling.
- [ ] Recreate event lists, nominee/winner selectors, vote counts and printable attendance ranking with transparent criteria.
- [ ] Migrate comma lists, duplicates, orphan votes and archived/terminated nominees without dropping historical recognition evidence.

## Acceptance checklist

- [ ] Only permitted administrators alter events/winners; a voter cannot post an arbitrary nominee/event ID.
- [ ] Repeat/concurrent votes obey the confirmed limit and cannot inflate counts.
- [ ] Count totals match eligible stored ballots and resolve ties by the approved policy.
- [ ] Attendance ranking handles month/year boundaries, leap years, recent hires and former employees as agreed.
- [ ] Cancelled/returned leave with an older Approved history row is handled by its current state.
- [ ] Durations above 99 hours sort numerically and ties remain deterministic.
- [ ] Event deletion/archive retains or removes ballots according to the explicit retention policy without orphan rows.
- [ ] Published winners are distinguishable from provisional vote/ranking results.
