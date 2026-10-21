# Code Agent Implementation Specification — Modules 01–05

## 1. Objective

Implement **Modules 01 through 05** based entirely on the documentation under:

`docs/modules/`

The legacy application/codebase is no longer available. **Do not attempt to reconstruct, preserve, or depend on the previous implementation.**

The documentation under `docs/modules/` is the **source of truth** for:

- Functional requirements
- Business rules
- Workflows
- Roles and permissions
- Data requirements
- Validation
- Statuses and state transitions
- Module dependencies
- UI requirements
- Expected behavior
- Edge cases

The goal is to build a clean implementation that is:

- Simple
- Efficient
- Secure
- Maintainable
- Readable
- Consistent
- Responsive
- Faithful to the documentation

Avoid unnecessary abstraction and over-engineering.

---

# 2. Mandatory Principles

## 2.1 Documentation First

Before implementing substantial code:

1. Read all documentation relevant to Modules 01–05.
2. Understand every module's purpose and workflow.
3. Identify dependencies between modules.
4. Identify shared entities and functionality.
5. Identify business rules and state transitions.
6. Identify roles and permissions.
7. Identify validation requirements.
8. Identify UI and interaction requirements.
9. Identify edge cases.
10. Identify ambiguities or contradictions.

**Do not begin substantial implementation before completing the documentation analysis.**

---

## 2.2 Documentation Is the Source of Truth

Use this priority:

1. `docs/modules/01*` through `docs/modules/05*`
2. Other project documentation that directly supports those modules
3. Official framework/library documentation
4. General engineering best practices

Do not use assumptions from the missing legacy system.

If requirements conflict:

1. Identify the conflict.
2. Search the other module documentation for clarification.
3. Record unresolved conflicts in `docs/modules/IMPLEMENTATION_PROGRESS.md`.
4. Do not silently invent an important business rule.

---

## 2.3 Do Not Invent Features

Implement what the documentation requires.

Do not add functionality merely because it seems useful.

Examples of functionality that must not be invented without documentation:

- Additional business rules
- Additional statuses
- Additional approval levels
- Additional roles
- Additional fields
- Additional workflows
- Additional reports
- Additional permissions

Small implementation details may be chosen using reasonable engineering judgment, but business behavior must come from the documentation.

---

# 3. Initial Documentation Analysis

Before coding, analyze Modules 01–05 and determine for each module:

- Purpose
- Actors/users
- Roles
- Permissions
- Entities
- Fields
- Relationships
- CRUD operations
- Business rules
- Validation rules
- Statuses
- State transitions
- Workflows
- Dependencies
- Search/filter requirements
- Reporting requirements
- UI requirements
- Edge cases
- Error conditions
- Testing requirements

Also identify functionality shared across multiple modules.

---

# 4. Requirements Traceability

For every documented requirement, maintain the conceptual mapping:

**Requirement → Implementation → Verification**

A requirement is not complete merely because code exists.

It must be:

1. Implemented.
2. Verified.
3. Marked complete in the progress tracker.

Pay particular attention to small requirements that are easy to overlook:

- Specific statuses
- Permission restrictions
- Required fields
- Validation conditions
- Special calculations
- State transitions
- Edge cases
- Table behavior
- UI interactions

---

# 5. Progress Tracking

Create and continuously maintain:

`docs/modules/IMPLEMENTATION_PROGRESS.md`

This file is mandatory.

It must track:

- Documentation analysis
- Architecture decisions
- Database implementation
- Backend implementation
- Frontend implementation
- Validation
- Authorization
- Testing
- Integration
- Known issues
- Ambiguities
- Deviations from documentation

Use checkboxes.

Example:

```md
# Implementation Progress

## Overall

- [x] Documentation analyzed
- [ ] Foundation
- [ ] Module 01
- [ ] Module 02
- [ ] Module 03
- [ ] Module 04
- [ ] Module 05
- [ ] Integration
- [ ] Testing
- [ ] Final review

## Module 01

- [x] Requirements analyzed
- [ ] Database
- [ ] Models
- [ ] Backend
- [ ] Validation
- [ ] Authorization
- [ ] Frontend
- [ ] Responsive UI
- [ ] Dark/light theme
- [ ] Tests
- [ ] Final review

### Notes

### Files Created/Modified

### Known Issues
```

Update the progress file as implementation progresses.

**Never mark an item complete unless it has actually been completed and verified.**

---

# 6. Architecture

Build a new application based on the documentation.

Prefer straightforward framework conventions and simple architecture.

Use appropriate:

- Models
- Relationships
- Controllers
- Form Requests
- Policies
- Services/Actions when justified
- Reusable frontend components

Keep controllers concise.

Use services/actions when business logic is:

- complex
- reused
- transaction-heavy
- better isolated from HTTP concerns

Do not automatically introduce:

- Repository patterns
- excessive interfaces
- unnecessary service layers
- unnecessary design patterns
- excessive abstractions

The simplest clean architecture that satisfies the documentation is preferred.

---

# 7. Shared Functionality

Identify functionality shared across Modules 01–05.

Reuse common implementations for:

- Layout
- Header
- Sidebar
- Navigation
- Forms
- Tables
- Search
- Filters
- Pagination
- Status badges
- Notifications
- Validation display
- Confirmation dialogs
- Theme handling

Do not duplicate identical business logic.

Do not create abstractions merely to eliminate a few lines of similar code.

---

# 8. Database Design

Design the database directly from the module documentation.

Implement:

- Migrations
- Models
- Relationships
- Foreign keys
- Appropriate indexes
- Constraints where appropriate
- Seeders where required/useful
- Factories where useful for testing

Consider:

- Primary keys
- Foreign keys
- Nullable fields
- Required fields
- Unique fields
- Status fields
- Timestamps
- Soft deletes where required
- Relationship cardinality
- Referential integrity

Add indexes for fields frequently used for:

- Filtering
- Searching
- Sorting
- Lookups
- Foreign-key relationships

Do not create undocumented database fields simply because they appear useful.

---

# 9. Backend Implementation

Implement documented functionality using appropriate Laravel conventions.

Use:

- Routes
- Controllers
- Models
- Form Requests
- Policies
- Services/Actions when justified
- Transactions where atomicity is required

Keep business rules out of controllers when they are complex or reused.

Do not duplicate the same business rule across controllers.

---

# 10. Business Rules

Business rules documented under `docs/modules/` must be implemented exactly.

Pay particular attention to:

- Calculations
- Statuses
- State transitions
- Approval flows
- Ownership
- Dependencies
- Required conditions
- Restrictions
- Role-specific behavior

Do not replace a documented rule with a simpler interpretation.

Do not silently omit edge cases.

---

# 11. Validation

Implement server-side validation for all documented input requirements.

Where applicable, validate:

- Required fields
- Data types
- Formats
- Ranges
- Uniqueness
- Relationships
- Allowed values
- State-dependent rules
- Cross-field rules

Frontend validation may improve UX but **must never replace server-side validation**.

---

# 12. Authorization and Security

Implement authorization based on documented roles and permissions.

Security must be enforced server-side.

Ensure users cannot bypass restrictions by:

- Changing URLs
- Modifying request payloads
- Calling endpoints directly
- Modifying record IDs
- Manipulating hidden fields

Maintain:

- CSRF protection
- Mass-assignment protection
- Server-side validation
- Server-side authorization
- Record-level access control where required

Never rely solely on frontend restrictions.

Do not expose sensitive technical details or raw database errors to users.

---

# 13. Transactions

Use database transactions when an operation changes multiple related records and must either fully succeed or fully fail.

Examples:

- Approval workflows
- Multi-record updates
- Related record creation
- Balance/quantity updates
- State changes affecting multiple entities

Do not use transactions unnecessarily for simple read operations.

---

# 14. Error Handling

Handle:

- Validation failures
- Unauthorized access
- Forbidden actions
- Missing records
- Duplicate records
- Invalid state transitions
- Failed database operations
- Empty datasets
- Unexpected requests

User-facing errors should be clear and understandable.

Technical details should be logged appropriately instead of exposed to users.

---

# 15. Query Performance

Avoid obvious performance problems.

Check for:

- N+1 queries
- Unnecessary queries
- Repeated queries
- Inefficient loops
- Excessively large result sets
- Missing indexes

Use:

- Eager loading
- Pagination
- Appropriate query constraints
- Efficient database operations

Do not prematurely optimize without a meaningful reason.

---

# 16. Frontend Technology

Use the **latest stable Bootstrap version that is appropriate for the project**.

Build a dashboard-style interface inspired by **AdminLTE**, but do not depend on AdminLTE unless explicitly required by the documentation.

The layout must include:

- Header/top navigation
- Sidebar
- Main content area
- Module navigation
- Active navigation state
- Responsive controls
- Breadcrumbs where useful
- Cards/panels where useful

Keep the design clean and professional.

Do not attempt to reproduce AdminLTE pixel-for-pixel.

---

# 17. Responsive Design

The application must be mobile-friendly from the beginning.

## Desktop

- Persistent sidebar
- Header
- Spacious content area
- Clear module navigation

## Tablet

- Responsive sidebar
- Adaptable content
- Responsive forms and tables

## Mobile

- Collapsible/off-canvas sidebar
- Compact header
- Touch-friendly controls
- Responsive forms
- Horizontally scrollable tables when necessary
- No unnecessary page-wide horizontal overflow

Do not build desktop-only layouts and retrofit mobile support afterward.

---

# 18. Light and Dark Theme

Implement:

- Light theme
- Dark theme

Provide a visible theme toggle.

Requirements:

- Persist the selected theme.
- Respect system preference when no preference is saved.
- Apply the theme consistently.
- Maintain readable contrast.
- Ensure Bootstrap components work correctly in both themes.
- Minimize incorrect-theme flashing during initial page load.

The theme must cover:

- Body
- Sidebar
- Header
- Cards
- Tables
- Forms
- Inputs
- Selects
- Modals
- Dropdowns
- Alerts
- Pagination
- Badges
- Validation messages
- Navigation

Prefer Bootstrap's theming capabilities and CSS variables over excessive custom CSS.

---

# 19. Table and Data Interaction Requirements

Table behavior is a **global application requirement** unless a module's documentation explicitly specifies otherwise.

The application should behave like a modern interactive dashboard rather than requiring full-page reloads for every table operation.

---

## 19.1 Table-Only Changes Must Not Reload the Whole Page

As much as reasonably possible, do **not** reload the entire page when an operation only affects table data.

This applies to:

- Search
- Filtering
- Pagination
- Sorting
- Insert/Create
- Update/Edit
- Delete
- Status changes
- Other table-only operations

Prefer:

```text
User action
    ↓
Request
    ↓
Update affected table/data region
```

Instead of:

```text
User action
    ↓
Request
    ↓
Reload entire page
```

The following should remain intact whenever possible:

- Header
- Sidebar
- Navigation
- Other static page content
- Unrelated UI components
- Current table controls/state

---

# 20. Asynchronous Table Operations

Use asynchronous requests for table-only operations where practical.

Appropriate technologies may include:

- Fetch API
- Axios
- DataTables AJAX
- Existing project JavaScript utilities
- Other lightweight AJAX mechanisms

Do not introduce a heavy frontend framework solely to avoid page reloads.

Keep the implementation simple and maintainable.

---

# 21. Table Search

Every table with a search field must support **keyup-based searching** unless its documentation explicitly requires a different interaction.

Expected behavior:

```text
User types
    ↓
keyup
    ↓
search/filter
    ↓
table updates
```

Do not require a separate Search button by default.

For server-side searches, use a small debounce when appropriate to avoid excessive requests while the user is typing.

Example behavior:

```text
Typing
  ↓
keyup
  ↓
short debounce
  ↓
server request
  ↓
table update
```

---

# 22. Table Pagination

Unless a module explicitly requires another value:

> **Every table must display 10 rows per page.**

This is a global default.

Pagination must:

- Display 10 rows per page.
- Allow navigation between pages.
- Preserve search/filter state where practical.
- Update only the table region.
- Avoid full-page reloads.

Do not use different default page sizes between modules unless explicitly required.

---

# 23. DataTables

Use **DataTables when it provides a clear benefit**.

DataTables may be used for:

- Pagination
- Searching
- Sorting
- Filtering
- AJAX data loading
- Server-side processing
- Table state management
- Responsive tables

However:

> Do not introduce DataTables automatically for every table if a simpler implementation is more appropriate.

Choose the simplest reliable implementation.

If DataTables is used:

- Default page length must be `10`.
- Search should work with keyup-style behavior.
- Pagination must be enabled.
- Responsive behavior should be enabled where appropriate.
- AJAX should be used where table-only updates are required.
- Avoid full-page reloads.
- Preserve relevant table state where practical.

---

# 24. Client-Side vs Server-Side Tables

Choose based on expected data volume.

## Small Dataset

Client-side handling is acceptable when loading the complete dataset is efficient.

## Large Dataset

Prefer server-side processing when loading all records would be inefficient.

For large datasets:

- Search should query the backend.
- Filtering should query the backend.
- Pagination should query the backend.
- Only the required records should be returned.

Do not load thousands of records into the browser simply to provide client-side pagination.

---

# 25. Create / Insert Table Records

When creating a record related to a table:

1. Submit asynchronously where practical.
2. Validate on the server.
3. Display validation errors without a full-page reload.
4. On success:
   - Close/reset the form or modal as appropriate.
   - Display success feedback.
   - Refresh only the affected table.

Example:

```text
Create
  ↓
Async request
  ↓
Server validation
  ↓
Success
  ↓
Refresh table only
```

---

# 26. Update Table Records

For table record updates:

1. Open the edit form/modal.
2. Submit asynchronously where practical.
3. Validate on the server.
4. Display validation errors without reloading the page.
5. On success:
   - Close the modal/form.
   - Display success feedback.
   - Refresh only the affected table.

Do not reload the entire page simply to display an updated table row.

---

# 27. Delete Table Records

For delete operations:

1. Ask for confirmation where appropriate.
2. Send the request asynchronously.
3. Handle the server response.
4. Remove the affected row or refresh the table.
5. Display success/error feedback.
6. Do not reload the entire page.

If removing a row directly could make pagination or table state unreliable, refresh only the table data.

---

# 28. Search and Filtering

Search and filtering must affect only the relevant table.

Example:

```text
Search: John

Header       → unchanged
Sidebar      → unchanged
Other UI     → unchanged
Table        → updated
```

Filters should update the table asynchronously.

Where appropriate, multiple filters should work together:

```text
Search + Status + Date + Other Filters
                  ↓
             Table update
```

Do not require a full-page reload simply because a filter changed.

---

# 29. Loading States

When an asynchronous table request is running, provide appropriate feedback.

Examples:

- Table spinner
- Loading indicator
- Disabled action button
- Loading/skeleton state

Prefer loading feedback on the affected table or action instead of freezing the entire page.

---

# 30. Empty States

Tables must handle empty results gracefully.

Example:

```text
No records found.
```

The empty state should appear within the table/data region without disrupting the surrounding layout.

---

# 31. Async Error Handling

If an asynchronous table operation fails:

- Keep the current page intact.
- Do not perform a full-page reload.
- Display a clear error message.
- Preserve search/filter state where possible.
- Allow the user to retry.

Do not expose raw backend or database errors.

---

# 32. Pagination State

When refreshing table data after create/update/delete:

- Preserve the current search where practical.
- Preserve active filters where practical.
- Preserve the current page where possible.
- Correct the page automatically if it no longer exists.

Example:

```text
User is on page 3
      ↓
Deletes last record on page 3
      ↓
Page 3 becomes empty
      ↓
Move to page 2
      ↓
Refresh table only
```

---

# 33. Standard Table Behavior

Unless documentation explicitly specifies otherwise:

| Feature | Requirement |
|---|---|
| Search | Keyup-based |
| Pagination | 10 rows/page |
| Search update | Table only |
| Filter update | Table only |
| Insert | Async where practical |
| Update | Async where practical |
| Delete | Async |
| Loading | Table/action-specific |
| Errors | No full-page reload |
| Empty state | Required |
| Responsive | Required |
| Dark mode | Required |

---

# 34. UI/UX Consistency

Use consistent patterns across all modules.

Standardize where appropriate:

- Page headers
- Buttons
- Forms
- Tables
- Search
- Filters
- Pagination
- Status badges
- Confirmation dialogs
- Notifications
- Validation errors
- Empty states
- Loading states

Do not create unrelated visual patterns for different modules.

---

# 35. Accessibility

Where practical, ensure:

- Semantic HTML
- Proper labels
- Keyboard-accessible controls
- Visible focus states
- Appropriate ARIA attributes
- Sufficient contrast
- Accessible modal/dialog behavior

Do not rely solely on color to communicate status.

---

# 36. Testing

Create automated tests where supported by the project.

Prioritize:

- Business rules
- Validation
- Authorization
- CRUD behavior
- Workflows
- State transitions
- Module dependencies
- Important edge cases

At minimum, test critical paths for every module.

Test table behavior where practical, including:

- Search
- Filtering
- Pagination
- Create
- Update
- Delete
- Authorization
- Validation

After implementation, run available:

- Automated tests
- Static analysis
- Linting
- Frontend builds
- Migrations
- Relevant framework checks

Fix failures before marking functionality complete.

---

# 37. Module Implementation Process

For each module, follow this sequence:

## Step 1 — Analyze

Read the complete module documentation.

## Step 2 — Map Requirements

Map requirements to:

- Database
- Backend
- Frontend
- Authorization
- Validation
- Tests

## Step 3 — Design

Determine the smallest clean implementation that satisfies the requirements.

## Step 4 — Implement

Implement the module completely.

## Step 5 — Verify

Verify:

- Documented behavior
- Business rules
- Validation
- Authorization
- Database integrity
- Responsive UI
- Light/dark theme
- Table behavior

## Step 6 — Test

Run relevant automated tests and checks.

## Step 7 — Update Progress

Update:

`docs/modules/IMPLEMENTATION_PROGRESS.md`

Only then proceed to the next module.

---

# 38. Module Dependencies

Do not assume Modules 01–05 are independent.

Determine dependencies from the documentation.

If Module 02 depends on Module 01:

- Implement the required Module 01 foundation first.
- Use actual Module 01 entities/workflows.
- Do not duplicate Module 01 functionality.

Review all cross-module behavior after implementing all five modules.

---

# 39. Integration Review

After Modules 01–05 are implemented, perform a complete integration review.

## Data

Check:

- Relationships
- Foreign keys
- Referential integrity
- Shared entities

## Business Logic

Check:

- Cross-module workflows
- State transitions
- Calculations
- Permissions

## UI

Check:

- Navigation
- Shared components
- Consistent terminology
- Responsive behavior
- Theme behavior
- Table behavior

## Security

Check:

- Authentication
- Authorization
- Record-level access

## Performance

Check:

- N+1 queries
- Repeated queries
- Missing indexes
- Excessive frontend requests

---

# 40. Code Cleanup

Before finalizing:

- Remove unused code.
- Remove unused imports.
- Remove unnecessary dependencies.
- Remove dead routes.
- Remove duplicated logic.
- Remove unnecessary CSS.
- Remove unnecessary JavaScript.
- Simplify overly complicated implementations.
- Ensure naming is consistent.
- Ensure files are logically organized.

Do not refactor working code solely for stylistic preference if the refactor provides no meaningful benefit.

---

# 41. Documentation Compliance Review

Perform a final requirement-by-requirement review.

For each requirement determine:

**Requirement → Implementation → Verification**

Do not mark a requirement complete without verification.

Pay particular attention to:

- Specific statuses
- Permission restrictions
- Validation conditions
- Edge cases
- Required fields
- Special workflows
- State transitions
- UI behavior
- Table behavior

---

# 42. Handling Ambiguity

When documentation is ambiguous:

### First

Search the other module documentation for clarification.

### Second

Check whether workflow/dependency context resolves it.

### Third

Choose the simplest implementation only if the ambiguity has no significant business impact.

### Otherwise

Record the ambiguity in:

`docs/modules/IMPLEMENTATION_PROGRESS.md`

Do not silently invent important business rules.

---

# 43. What Not To Do

Do not:

- Reconstruct the missing legacy application.
- Assume undocumented functionality.
- Add unnecessary features.
- Over-engineer simple requirements.
- Create excessive abstraction layers.
- Duplicate business logic.
- Duplicate UI components.
- Skip authorization.
- Rely solely on frontend validation.
- Ignore mobile behavior.
- Ignore dark mode.
- Ignore table interaction requirements.
- Reload the entire page unnecessarily for table-only changes.
- Use inconsistent pagination sizes.
- Mark incomplete work as complete.
- Claim tests passed without actually running them.
- Claim requirements are implemented without verification.

---

# 44. Completion Criteria

Modules 01–05 are complete only when:

- [ ] Documentation for all five modules has been analyzed.
- [ ] All documented requirements are mapped to implementations.
- [ ] Database structure is implemented.
- [ ] Models and relationships are implemented.
- [ ] Backend workflows are implemented.
- [ ] Business rules are implemented.
- [ ] Validation is implemented.
- [ ] Authorization is implemented.
- [ ] Frontend functionality is implemented.
- [ ] Responsive behavior is implemented.
- [ ] Light theme works.
- [ ] Dark theme works.
- [ ] Table search uses keyup behavior where applicable.
- [ ] Tables use 10 rows per page by default.
- [ ] Table-only operations avoid full-page reloads where practical.
- [ ] Create/update/delete table operations work correctly.
- [ ] Cross-module workflows work.
- [ ] Critical automated tests pass.
- [ ] Relevant checks pass.
- [ ] Known issues are documented.
- [ ] `IMPLEMENTATION_PROGRESS.md` is up to date.
- [ ] Final requirement review is complete.

---

# 45. Final Report

At the end of implementation, provide a concise final report containing:

## Modules

- Module 01 — Complete / Incomplete
- Module 02 — Complete / Incomplete
- Module 03 — Complete / Incomplete
- Module 04 — Complete / Incomplete
- Module 05 — Complete / Incomplete

## Architecture

Summarize the implemented architecture and major decisions.

## Database

Summarize:

- Major entities
- Relationships
- Important indexes
- Significant constraints

## Frontend

Summarize:

- Bootstrap version
- AdminLTE-inspired layout
- Responsive behavior
- Light/dark theme
- Table implementation

## Testing

List the checks/tests actually executed and their results.

## Known Issues

List incomplete or unresolved items.

## Documentation Deviations

List anything implemented differently from the documentation and explain why.

**Never claim something was tested, verified, or completed unless it was actually performed.**

---

# 46. Final Principle

> **Build what the documentation requires — no more, no less.**

The missing legacy application is irrelevant to the implementation.

The quality target is:

**Documentation fidelity + simple architecture + efficient implementation + secure backend + consistent responsive UI + efficient table interactions + verified behavior.**
