# Agent instructions for the HRIS rebuild

Use this brief to build a replacement HRIS while retaining the legacy repository as a reference. The deliverable is feature parity with explicitly recorded corrections, a repeatable data migration, verified external integrations, and operational handover. A refreshed interface alone is insufficient.

## Implementation contract

1. Read the module guide and source references before implementing it. Consult the inventories for additional handlers and fields.
2. Create the new Laravel application in an isolated repository or separately authorized destination. Do not overwrite the legacy source, its `.env`, uploaded documents, or production database to scaffold the replacement.
3. Record the selected framework, PHP, database, frontend, and package versions in an architecture decision and lock dependencies. Use [Architecture](ARCHITECTURE.md) as the proposed baseline.
4. Implement each workflow using server validation, explicit authorization, scoped queries, transactional updates, and auditable transitions. Menu visibility is presentation only.
5. Preserve externally used URLs and payloads through adapters until partners agree to migrate. Improve insecure behavior through a coordinated protocol change; do not silently break SSI or device ingestion.
6. Keep verified legacy calculations as characterization fixtures. Record intentional fixes separately so expected differences can be reviewed by HR and payroll owners.
7. Never invent leave eligibility, contribution/tax rates, approval powers, legal form content, retention periods, or salary values. Use approved reference data and resolve the relevant open decision before dependent implementation is accepted.
8. Use fictional fixture data. Do not paste `.env` values, credentials, personnel records, signatures, exam answers, or uploaded documents into documentation, logs, public artifacts, or generated examples.
9. Implement module acceptance checks and update its checklist with evidence. A checkbox needs a source change, a meaningful verification result, and resolution of its blocking decisions.
10. Keep the handoff current: document the implemented behavior, migration mappings, compatibility changes, outstanding decisions, and how to operate the feature.

## Recommended implementation order

| Phase | Work | Exit evidence |
| --- | --- | --- |
| 0 | Snapshot legacy code; acquire approved schema-only database export and sanitized test cases; confirm policy owners, external partners, release scope, and report samples | Baseline manifest, feature disposition register, decision owners |
| 1 | New app, CI, database, private storage, logging, test isolation; modules 01, 21, then 02 | Login/access matrix, reproducible migrations/reference seeds, staff identity mapping |
| 2 | Modules 03 and 22; shared request history, approval routing, COC/leave ledgers, audit events | Owner isolation, history integrity, idempotent balance operations |
| 3 | Raw attendance ingestion, attendance rule service and fixtures; modules 06, 07, 08, 09 integrated with 05 | Approved request transitions, one consistent DTR calculation, reconciliation |
| 4 | Modules 10 and 17; salary snapshots, payroll verification, PDF/spreadsheet/QR output | Payroll and certificate totals accepted; historical output stays stable |
| 5 | Modules 11, 12, 13, 14, 15, 16, 18, 19 | Domain workflows, privacy boundaries, imports, reports and signatures verified |
| 6 | Modules 04 and 20; SSI compatibility may be developed earlier using partner fixtures | Portal feeds, notifications, service credentials and partner tests pass |
| 7 | Migration rehearsals, operational checks, user acceptance and controlled cutover | [Cutover gates](VALIDATION_AND_CUTOVER.md), reconciliation and rollback evidence |

Shared contracts and static UI work can proceed in parallel. Attendance and payroll must use approved calculations before balances and pay are released. Partner login should be prototyped early because changing the legacy shared-key protocol may require work outside this repository.

## Workflow for a module

- [ ] Read its source files, route entries, data entities, frontend validation/calculations, and known gaps.
- [ ] Map each endpoint to retain, adapter, replacement, or retirement, with reason and caller impact.
- [ ] Write schema mappings and explicit state transitions, including cancellations and reversals.
- [ ] Build policies and tests for own record, other record in same division, other division, HR, ED, administrator, applicant, unauthenticated browser and external client as applicable.
- [ ] Build application actions, requests, models and UI; put business calculations in one reusable service.
- [ ] Include import rules, historical records, attachments, signed output, and failure recovery.
- [ ] Run meaningful domain, authorization, integration and report checks; record expected legacy differences.
- [ ] Reconcile migrated row counts, relationships, money and credit balances.
- [ ] Update the module checklist and handoff with test commands/results and unresolved decisions.

## Module completion record

Use this format in the new application's documentation or issue tracker. Do not mark completion based solely on code compiling.

```text
Module ID and name:
Implemented routes/screens/actions:
Source and schema mappings:
Policy decisions applied:
Intentional legacy behavior changes:
Tests and acceptance evidence:
Migration reconciliation evidence:
External dependencies verified:
Remaining limitations and owner:
Status: not started / implementing / awaiting a decision / validating / accepted
```

## Suggested initial prompt for the implementing agent

```text
Read docs/rebuild/README.md and AGENT_BUILD_GUIDE.md, then ARCHITECTURE.md,
DATA_MIGRATION.md, DECISIONS_AND_GAPS.md and EXTERNAL_CONFIGURATION.md.
Use the legacy code and inventories as evidence. Build the replacement in the
designated new app destination. Start with foundation, authentication, reference
settings and staff identity. Follow module checklists, preserve partner contracts
through adapters, and record deliberate fixes. Do not use production credentials,
run legacy mutation endpoints, or fabricate HR/payroll policy. Resolve only the
decisions that block the current work and continue independent work. For each
module, provide implementation, authorization tests, migration reconciliation,
report checks and an updated completion record.
```
