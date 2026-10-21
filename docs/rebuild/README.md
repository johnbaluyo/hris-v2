# HRIS Laravel rebuild documentation

This is the implementation brief for rebuilding this HRIS on modern Laravel. It covers employee self service, HR administration, approvals, attendance, payroll, recruitment, personnel records, governance forms, and the SSI portal. Each module describes the existing implementation and supplies implementation and acceptance checklists for the rebuilding agent.

Baseline reviewed: **21 October 2026, Asia/Manila**. Source revision: `c8973c067292967581b0ae5a2fe8493f905fed4c`. Static inventories contain **460 explicit route declarations, 36 controllers, 89 models, and 192 migration files**. Counts include duplicates and unfinished code; framework-generated authentication routes are additional. This brief does not claim that every legacy route works or that repository migrations match the deployed database.

## Start here

1. Read [Agent build guide](AGENT_BUILD_GUIDE.md) for implementation rules and the recommended work order.
2. Read [Architecture](ARCHITECTURE.md), [Data migration](DATA_MIGRATION.md), and [Decisions and legacy gaps](DECISIONS_AND_GAPS.md).
3. Provision dependencies using the separate [External configuration guide](EXTERNAL_CONFIGURATION.md). Implement partner interfaces from [Integration contracts](INTEGRATION_CONTRACTS.md).
4. Build modules in dependency order using their checklists below. Consult [Validation and cutover](VALIDATION_AND_CUTOVER.md) before declaring any module complete.
5. Resolve each legacy endpoint as retained, replaced with a compatibility adapter, or intentionally retired. Use the full [Route inventory](inventories/ROUTES.md), [Controller inventory](inventories/CONTROLLERS.md), [Model inventory](inventories/MODELS.md), and [Migration and field inventory](inventories/SCHEMA.md).

## Meaning of requirements

- **Observed** means directly represented in repository code. A current behavior can still be defective.
- **Target** or **proposed** means a rebuild requirement or recommended design. These are explicitly separate from legacy behavior.
- **Unresolved** means a policy, deployment fact, or incomplete feature needs a recorded decision. Continue independent work, but do not invent the dependent behavior.
- Every unchecked item describes future rebuild work. Creating this documentation does not mark implementation, migration, or user acceptance complete.

## Module checklist

| ID | Module and detailed checklist | Primary dependencies | Delivery status |
| --- | --- | --- | --- |
| 01 | [Authentication and authorization](modules/01-authentication-and-authorization.md) | Foundation, identity mapping | [ ] Build and accept |
| 02 | [Organization and staff](modules/02-organization-and-staff.md) | 01, 21 | [ ] Build and accept |
| 03 | [Profile and personal data sheet](modules/03-profile-and-pds.md) | 01, 02, private files | [ ] Build and accept |
| 04 | [SSI portal and calendars](modules/04-ssi-portal-and-calendars.md) | 01, 02, 20, 21, partner identity | [ ] Build and accept |
| 05 | [Attendance and DTR](modules/05-attendance-and-dtr.md) | 01, 02, 06–09, 21, device sync | [ ] Build and accept |
| 06 | [Official business](modules/06-official-business.md) | 01, 02, 05 | [ ] Build and accept |
| 07 | [Overtime and compensatory credits](modules/07-overtime-and-coc.md) | 01, 02, 05 | [ ] Build and accept |
| 08 | [Compensatory time off](modules/08-compensatory-time-off.md) | 01, 02, 05, 07 | [ ] Build and accept |
| 09 | [Leave management](modules/09-leave-management.md) | 01, 02, 05, 21 | [ ] Build and accept |
| 10 | [Payroll](modules/10-payroll.md) | 02, reconciled 05/09, 21 | [ ] Build and accept |
| 11 | [Recruitment and examinations](modules/11-recruitment-and-exams.md) | 01, 02, 03, 22 | [ ] Build and accept |
| 12 | [Learning and development](modules/12-learning-and-development.md) | 02, 03, 21 | [ ] Build and accept |
| 13 | [Individual development plans](modules/13-individual-development-plans.md) | 02, 12 | [ ] Build and accept |
| 14 | [SALN](modules/14-saln.md) | 01, 02, 03 | [ ] Build and accept |
| 15 | [IPCR](modules/15-ipcr.md) | 01, 02, reporting | [ ] Build and accept |
| 16 | [Employee concerns](modules/16-concerns.md) | 01, 02, private files | [ ] Build and accept |
| 17 | [Employment certificates](modules/17-employment-certificates.md) | 01, 02, 21, PDF/QR tooling | [ ] Build and accept |
| 18 | [Recognition and rewards](modules/18-recognition-and-rewards.md) | 02, 04, reconciled 05 | [ ] Build and accept |
| 19 | [WFH accomplishments](modules/19-wfh-accomplishments.md) | 01, 02, 04, report signatures | [ ] Build and accept |
| 20 | [System notifications](modules/20-system-notifications.md) | 01, SSI/service identity | [ ] Build and accept |
| 21 | [Reference settings and holidays](modules/21-reference-settings-and-holidays.md) | 01 | [ ] Build and accept |
| 22 | [Personnel document uploads](modules/22-personnel-document-uploads.md) | 01, 02, private files | [ ] Build and accept |

An arrow between attendance and requests represents a business dependency, not a reason to block all development: define the shared attendance input contract first, then build request workflows against fixtures while the analyzer is implemented.

## Legacy application baseline

The source is a Laravel 7 application (`laravel/framework: ^7.29`) with models in `app/`, large controllers, global helpers, Blade templates, AdminLTE 3, Bootstrap 4, jQuery/DataTables, Vue 2 dependencies, and Laravel Mix 5. Important packages cover PDF output, spreadsheets, QR codes, alerts, and datatables. The actual feature behavior often lives in models and inline Blade JavaScript as well as controllers. Read all three before reproducing a calculation or workflow.

The proposed rebuild baseline is **Laravel 13 with PHP 8.4**, subject to package and hosting verification. Laravel 13 requires PHP 8.3 or higher; the proposed PHP 8.4 choice is an implementation default, not a discovered hosting fact. Recheck the supported release and compatible package versions when implementation begins. [Laravel release notes](https://laravel.com/docs/13.x/releases), [deployment requirements](https://laravel.com/docs/13.x/deployment).

Existing SSI and notification Markdown guides remain useful context, but executable routes and handlers take precedence when they disagree. See [integration contracts](INTEGRATION_CONTRACTS.md) for identified discrepancies. This package contains configuration names and placeholders; operational secrets and employee data must remain outside it.

## Maintaining this brief

Regenerate inventories after source changes:

```text
node docs/rebuild/tools/generate-inventories.cjs
```

The generator reads PHP source only. It does not load Laravel, access `.env`, execute seed SQL, or connect to the database. The schema inventory is an ordered list of migration operations, not a final schema dump. Track accepted policy decisions in [Decisions and gaps](DECISIONS_AND_GAPS.md), and attach implementation and verification evidence to the relevant module checklist.
