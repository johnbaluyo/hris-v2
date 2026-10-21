# 04 SSI portal and calendars

## Purpose and source map

Provide the employee landing page, application launcher, announcements, office/ED/personal calendars, curated links, wellness notices, celebrations, recent time-in summaries and links into HR workflows.

- [AdminController](../../../app/Http/Controllers/AdminController.php): `ssi`, `celebrations`, gate-log/filter feeds and birthday asset management.
- [SsiController](../../../app/Http/Controllers/SsiController.php): application/link/wellness CRUD, office/personal event actions, announcement actions, detail feeds and `launchDts`.
- [Events](../../../app/Events.php), [Announcements](../../../app/Announcements.php), [Ssi](../../../app/Ssi.php), [Link](../../../app/Link.php), [WellnessTip](../../../app/WellnessTip.php), [BdayFile](../../../app/BdayFile.php).
- [SSI templates](../../../resources/views/ssi), [older portal template](../../../resources/views/ssi.blade.php), [application admin](../../../resources/views/admin/application.blade.php), [user admin](../../../resources/views/admin/user.blade.php).
- [URL helpers](../../../app/Helpers/helpers.php), [routes](../../../routes/web.php), [integration contracts](../INTEGRATION_CONTRACTS.md).

**Observed** is source behavior; **target/proposed** is the rebuild; **unresolved** needs an owner decision.

## Actors and observed access

`AdminController` requires authentication; its SSI action rejects applicants. It prepares active applications, portal content and date-sensitive panels. Calendar/announcement editing checks selected account IDs, roles or a division; such hardcoded exceptions do not constitute a reusable policy.

`SsiController` has no constructor authentication middleware. `/launch/dts` explicitly has route authentication, but many other routes do not. Some methods dereference the current user or check particular IDs; others operate directly on a supplied record. Protect all portal administration/read feeds with explicit policies in the target, including records requested through popup/detail endpoints.

## Data and current workflows

| Entity/panel | Observed behavior |
| --- | --- |
| `ssis` | App URL, title, description, image and `Active`/`Inactive` status. Landing page displays active rows; admin can create/edit/toggle. Logo handler permits several formats including SVG and uses a name derived from the original filename. |
| `ssi_links` | Name, description, URL, adding user and status. CRUD endpoints include detail retrieval through `linkUpdate`; active links appear on the portal. |
| `wellness_tips` | Title/description/start/end. Current content includes today inclusively; handler validates end is on/after start. |
| `ssi_announcements` | Author, title, text, optional uploaded file. Save creates a new row, writes a filename prefixed with row ID to `public/files`, then stores reference. Delete removes the database record without a demonstrated full file lifecycle. |
| `ssi_events` | Type, schedule type, title/description, start/end, division and comma-separated involved users. Personal events use `My Calendar`. |
| Birthday panel | Matches today's month/day, employee types in the configured source list, and no termination date. Birthday images/GIFs use a separate upload record/tree. |
| Celebration panel | Loyalty/regularization calculations exist in both controller/model code; Carbon mutable date reuse and exact-date comparisons need approved fixtures. |
| Recent time-in | Earliest raw timestamp today/yesterday/two days ago in Manila time. Portal matching uses the current ATMS ID, while other log queries normalize leading zeros. |
| WFH shortcut | Uses `Events::has_work_from_home_for_date`; event type comparison is case/whitespace normalized and checks an inclusive date span. |
| Notification panel | Merges derived WFH reminders with persisted notifications; see module 20. |

Routes include `/ssi/{calendar?}`, `/admin/application`, `/admin/user`, `/application`, `/applicationUpdate`, `/ssi/toggle-status`, `/links`, `/addLink/create`, `/linkUpdate`, `/removeLink/{id}`, `/wellness/*`, `/announcements`, `/announcement/save`, `/delete_announcement`, `/events`, `/events/save`, `/get_event`, `/delete_event`, `/my-calendar`, `/my_calendar`, `/psrti_calendar`, `/ed_sched` and `/leave_calendar`. Full verbs and handlers are in the [route inventory](../inventories/ROUTES.md). The optional SSI calendar argument is not evidence of a separate complete workflow.

## Calendar behavior

1. `Events::save_event` creates/updates the supplied event. It serializes selected participants with a trailing comma. Nonpersonal events with participants synchronously send mail to participant emails and CC the actor.
2. `Work From Home` events are forced to `All Day` and assigned to the executive office division if its name lookup succeeds. Their presence controls the portal WFH shortcut; office calendar feeds deliberately exclude this event type.
3. `psrti_calendar("office_calendar")` excludes `My Calendar`, `Work From Home` and ED activity events. The ED feed selects `Executive Director Activities`. Both add holiday rows except `Special Order`.
4. All-day event output adds one day to the stored end before returning calendar data, implementing an exclusive feed end for inclusive storage. Timed events return their stored date/time values.
5. `my_calendar` combines regular/special-nonworking holidays, approved leave, selected approved OB/CTO data, and any event whose participant CSV includes the current user. It is not restricted to personal event types. OB is included only for `go_directly` plus `not_expected_to_return`; CTO date rows are filtered to hours `[4, 5, 8, 10]`. Request feeds use approval history to determine visibility. Preserve the observed subsets for comparison, then confirm the desired rules.
6. Event/get-request detail endpoints expose data by supplied IDs. The target must scope those lookups to viewers allowed to see the parent event or request; a calendar title does not authorize disclosure of private reasons/details.
7. `my_calendar_index` selects personal events using exact `involved_pax = user ID`, whereas saves build trailing-comma CSV. This can omit newly saved personal events from the list. Normalize membership and test both the list and feed.

## SSI launching

The active application cards generate encrypted-email `/ssi/login/{token}` links; the old `ssi.blade.php` template uses an encrypted numeric-ID `/autologin` pattern. `launchDts` builds a DTS pending-document path from `doc_num` using configured `services.dts.url` and encoded query values. Helpers wrap external hosts but do not recognize two apps under different paths on the same host. See exact protocols and defects in [Integration contracts](../INTEGRATION_CONTRACTS.md).

Resolve partner protocol per app, allowed schemes/hosts/base paths, identity mapping, tokens, redirect rules and key rotation. A stored arbitrary external URL must not automatically receive reusable login credentials.

## Migration and unresolved decisions

Normalize participant membership into a relation while retaining raw legacy lists for reconciliation. Preserve app/link statuses, author IDs, event end semantics, local timestamps and assets. Classify announcement attachments by approved audience; public storage in the legacy app does not establish that all attachments should remain public.

Resolve D02/D04/D11/D13/D14: content editors, executive/personal event privacy, birthday/loyalty eligibility, WFH policy, partner trust and retention. `/test-upload` and `/test-upload/submit` are diagnostic upload routes; give them an explicit retirement/restricted-development disposition. The `/admin/user` page is not proof of a complete protected account administration workflow.

## Build checklist

- [ ] Implement authenticated landing page and policy-driven module/app/content visibility.
- [ ] Build app/link/wellness/announcement administration with URL validation, safe asset processing and authorized file retrieval.
- [ ] Implement event create/edit/delete, participant selection, private personal calendar, office/ED feeds and scoped request details.
- [ ] Preserve inclusive stored all-day dates and exclusive feed end dates with explicit timezone handling.
- [ ] Queue participant mail after commit, with retries and no duplicate sends on retry/import.
- [ ] Reproduce birthday, wellness, recognition and recent-time panels from approved eligibility/date rules.
- [ ] Integrate approved WFH availability and notification audiences.
- [ ] Replace hardcoded editor account lists with roles/delegations and protect server actions.
- [ ] Implement configured partner launch adapters and stable document deep links; retire unsupported template protocols deliberately.
- [ ] Migrate participant lists, assets and references; remove diagnostic routes from production.

## Acceptance checklist

- [ ] Inactive applications/links and expired/future wellness content are hidden according to approved rules.
- [ ] Noneditors cannot call content administration directly; users cannot read/delete another personal event.
- [ ] All-day one-day and multi-day events display the correct last day; timed events retain Manila time.
- [ ] Office, ED and personal calendars contain the correct event/request subsets, including cancelled/amended requests.
- [ ] Participant notifications include the accepted dates/recipients and retry without duplicate content.
- [ ] WFH availability respects accepted event/date scope and does not leak private employee request details.
- [ ] Leading-zero ATMS IDs do not hide recent time-in data.
- [ ] External links handle ports, application subpaths, query strings and fragments safely under the agreed partner protocol.
- [ ] Files, logos and filenames cannot overwrite another asset or expose private documents.
