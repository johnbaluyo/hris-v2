# Integration contracts and compatibility requirements

Evidence date: 2026-10-21, Asia/Manila. **Observed** contracts below are reconstructed from source; no live endpoints, mail delivery or partner systems were called. **Proposed** requirements are intentionally safer target behavior and must be agreed with client owners before compatibility changes. **Unresolved** marks missing external implementations or production evidence. See [EXTERNAL_CONFIGURATION.md](EXTERNAL_CONFIGURATION.md) for provisioning and operations.

## 1. Shared HTTP conventions

`app/Providers/RouteServiceProvider.php` mounts `routes/api.php` under `/api`. `app/Http/Kernel.php` applies `throttle:60,1` and route bindings to all these API routes. This is a configured 60 requests/minute throttle, not a published per-client SLA; the framework determines request identity. JSON clients should send `Accept: application/json` and JSON POST bodies should send `Content-Type: application/json`. HTTP 429 can occur before controller processing. Hosting/WAF/body-size limits are unresolved.

The app has three distinct authentication modes:

| Mode | Observed mechanism | Applies to |
|---|---|---|
| Shared integration bearer | Exact `Authorization` header equality with `Bearer ` + `config('app.api_sync_token')` | DTR sync, `/api/users`, external notification creation |
| Laravel browser session + CSRF | API group adds `web` and `auth`; state-changing requests require CSRF | `/api/notifications/*` except external-create |
| Legacy per-user token guard | `auth:api`, `config/auth.php` token driver with `hash=false` | `/api/user` only |

The shared middleware returns HTTP 401:

```json
{"success":false,"message":"Unauthorized. Invalid or missing API token."}
```

It performs a plain string comparison and has no client identity, expiry, scope or token rotation overlap. If the configured token is empty, the middleware still constructs a bare `Bearer ` expected value; target configuration must fail closed instead. The bearer token is never required in a browser URL.

**Proposed:** authenticate each machine client separately, hash stored credentials, use explicit scopes, constant-time comparison/framework token facilities, fail closed when misconfigured, set client-specific limits and log credential IDs rather than secret values. Preserve legacy endpoint paths temporarily only through an explicit versioned adapter.

## 2. Attendance/RFID ingestion

Sources: `routes/api.php`, `app/Http/Controllers/APIController.php`, `app/Http/Middleware/VerifyApiToken.php`, `app/DTRTimeLog.php`, DTR migrations.

### GET `/api/dtr-time-log/max-id`

**Observed authentication:** shared bearer. No body/query validation. Computes `MAX(anviz_log_id)` over all `dtr_time_log` rows, or zero when empty. This is the external device/source identifier, not the HRIS primary key.

HTTP 200:

```json
{"success":true,"max_id":12345}
```

HTTP 500:

```json
{"success":false,"message":"Failed to retrieve max ID","error":"<exception message>"}
```

`max_id` JSON numeric/string representation depends on DB return typing. The code does not filter by device/source or detect missing intermediate IDs. Web-created rows use source ID zero. There is no source-reset/epoch mechanism.

### POST `/api/dtr-time-log/batch-insert`

**Observed authentication:** shared bearer. Request:

```json
{
  "records": [
    {
      "anviz_log_id": 12346,
      "user_atms_id": "999001",
      "time_log": "2026-10-20 08:01:00",
      "device": "READER-01",
      "created_at": "2026-10-20 08:02:00",
      "updated_at": "2026-10-20 08:02:00"
    }
  ]
}
```

| Field | Observed validation |
|---|---|
| `records` | Required array, at least one item, no maximum item count |
| `records.*.anviz_log_id` | Required integer; no positive/minimum/unique check |
| `records.*.user_atms_id` | Required, no type/length/user-existence rule |
| `records.*.time_log` | Required Laravel `date`, no fixed timezone/format/range |
| `records.*.device` | Required string, no length/device registry check |
| `records.*.created_at`, `updated_at` | Optional nullable dates; bulk `insert()` does not automatically create Eloquent timestamps |

The controller inserts chunks of 500 with `DTRTimeLog::insert($chunk)`. This chunk size is an internal implementation detail, not a maximum request size. No transaction covers the request. No duplicate/upsert handling or unique index on `anviz_log_id` is established by the reviewed migrations. Do not rely on undeclared extra keys being discarded: legacy nested-array validation is not an explicit projection into a fixed record DTO.

HTTP 201:

```json
{"success":true,"message":"Records inserted successfully","inserted_count":1}
```

**Observed defect:** `insert()` returns a boolean and the controller adds that boolean to `insertedCount`; the field counts successful **chunks**, not inserted records. A 501-record request can report `2`. Partial chunks can remain committed if a later insert fails. A client that retries blindly can create duplicates, while a max-ID checkpoint can skip missing rows after partial/out-of-order writes.

HTTP 422:

```json
{"success":false,"message":"Validation failed","errors":{"records.0.time_log":["<validation message>"]}}
```

HTTP 500:

```json
{"success":false,"message":"Failed to insert records","error":"<exception message>"}
```

### Proposed target contract and acceptance checklist

- [ ] Identify collector owner, source stream, source ID monotonicity/reset behavior and replay window. The collector script is not included.
- [ ] Preserve raw source event/time/device; add a unique source/epoch/event key and idempotency. Exclude source-ID zero web entries from device checkpoint semantics.
- [ ] Require a bounded array/body size, typed ATMS string, registered device and explicit ISO timestamp/offset or documented Asia/Manila interpretation.
- [ ] Normalize employee identifiers consistently, including leading zeros; quarantine unknown IDs and report per-record failures.
- [ ] Define all-or-nothing vs per-record atomicity and return actual received/inserted/duplicate/rejected counts; provide a stable checkpoint independent of accidental DB max values.
- [ ] Return sanitized errors and request IDs, never raw SQL/driver exception text. Define retryable errors and backoff for 429/5xx/timeouts.
- [ ] Verify one record, 500/501 boundaries, duplicates, partial failures, late/out-of-order logs, unknown employees, zero source IDs, malicious extra keys and credential rotation.

## 3. Employee and calendar data APIs

| Method/path | Observed auth | Request/response | Compatibility concern |
|---|---|---|---|
| GET `/api/users` | Shared bearer | No filters/pagination; returns `User::with('division','position.sg')->get()` JSON collection | Broad employee attributes; password/remember_token hidden by model, other sensitive fields not explicitly projected. No active/terminated filter. |
| GET `/api/user` | `auth:api` legacy user token | Returns `$request->user()` | Not the shared sync token; any actual client/provisioning is unresolved. |
| GET `/api/users/timelog` | No route authentication | Paginator, 10,000 raw time-log rows per page; attached user limited to id/name/ATMS with active/not-terminated filters on relation | Filtering the relation does not exclude raw logs whose related user becomes null. No deterministic sort specified. Public personal/attendance data surface. |
| GET `/api/users/timelog/{myEmail}` | No route authentication | Email path parameter; `User::with('timelog')->where('email', $myEmail)->get()`; empty array when no match | Broad user and log serialization; no explicit email validation/authorization or pagination. |
| GET `/api/holidays` | No route authentication | Entire holiday collection ordered by `holiday_date` descending | No date-range/version query or documented client; fields are model serialization rather than a fixed response DTO. |

No custom exception envelopes are defined for these reads. They still inherit API throttling. In the target, define allowlisted resources and scopes, pagination/cursors and bounded date windows. Restrict attendance/user endpoints; retain a public holiday feed only if a documented consumer requires it. Inventory clients before changing serialized fields.

## 4. HRIS-triggered partner user synchronization

Source: `HRStaffController::syncUsersToSsi`, route `POST /hr/staff/sync-users`.

**Inbound observed:** browser session authentication from controller constructor and web CSRF. Body `{"ssi_id":123}`; `ssi_id` must be an integer existing in `ssis`. No separate HR/admin permission check occurs in this method. The selected `ssis.url` supplies the partner base URL.

**Outbound observed:**

1. POST `{partner-base-url}/api/sync-users` with `Accept: application/json`, timeout 60 seconds, no user payload.
2. Add `Authorization: Bearer {API_SYNC_TOKEN}` only when token config is nonempty.
3. If POST returns 405, retry with GET to the same path.
4. Parse optional JSON `type` and `message` fields. A successful partner status becomes HRIS HTTP 200 with defaults `{"type":"success","message":"Sync completed."}`.
5. A non-success status is passed through with `type:"warning"` and partner `message` or a status-based fallback. A transport/other exception returns HTTP 502 with `{"type":"warning","message":"Unable to reach target system. Please try again later."}` and logs partner metadata/endpoint/error.

Missing/empty partner URL yields 422 warning. Basic validation errors use framework handling. Partner response schemas, side effects, identity crosswalks and the partner's pull/push implementation are **unresolved**. This call does not send a user array; do not invent that payload based on the action name. `/api/users` may support a partner pull, but no partner implementation here proves the linkage.

**Proposed checklist**

- [ ] Add a dedicated permission and explicit registered-partner allowlist; prevent arbitrary destination URLs/SSRF.
- [ ] Use per-partner credentials and documented direction/source of truth; remove unauthenticated dispatch on missing token.
- [ ] Agree stable endpoint/method and replace state-changing GET fallback with a versioned compatibility adapter.
- [ ] Specify identity matching, conflict/deletion/archive semantics, field projection and actual created/updated/skipped/error counts.
- [ ] Provide job/run IDs, asynchronous status for long runs, safe retry/idempotency and an audit trail.
- [ ] Verify rejected partner, missing URL/token, 405 compatibility, malformed JSON, 4xx/5xx, timeout and concurrent runs using mocked partner responses.

## 5. SSI browser login and DTS launch

Sources: `LoginController::ssiLogin`, `SsiController::launchDts`, `app/Helpers/helpers.php`, `resources/views/ssi/index.blade.php`, older `resources/views/ssi.blade.php`.

### GET `/ssi/login/{token}` — incoming legacy login

- **Auth:** guest middleware from `LoginController`; token itself is the authentication proof. Already-authenticated visitors are subject to guest redirection before controller execution.
- **Token format:** Laravel `encrypt($email)` output, decrypted with the receiving app's key/cipher/serialization behavior. It is encrypted email, not a JWT, OAuth authorization code or signed-expiring URL.
- **Payload:** path `{token}` and optional query `redirect`.
- **Behavior:** decrypt; find first `users.email` match; `Auth::loginUsingId($user->id, true)` enables remember login; HTTP redirect to supplied `redirect` if truthy, otherwise `/ssi`.
- **Errors:** malformed ciphertext and missing users are not explicitly caught; no stable 400/401 error contract. Archived/terminated status is not checked by this method.
- **Limits/security:** no expiry, issuer, audience, nonce, replay check or redirect destination validation is implemented. Tokens in URLs can enter history, logs and referrers. A shared compatible encryption key is an inferred prerequisite for legacy cross-app decryption; partner configuration was not inspected.

### GET `/launch/dts?doc_num=...` — outbound document shortcut

Session-authenticated route `launch.dts`; `doc_num` required string, maximum 100 characters. Reads `services.dts.url` (trailing slash removed), constructs target `/pendingIndex?doc_num={rawurlencoded-document-number}`, encrypts authenticated email, raw-url-encodes token and destination, then redirects to:

```text
{DTS_URL}/ssi/login/{encoded-token}?redirect={encoded-/pendingIndex-query}
```

Validation uses framework errors. No network call is made by HRIS; the browser contacts DTS after redirect. DTS authentication, document authorization and target response/error behavior remain **unresolved** until its owner verifies them.

### URL helper contracts

| Helper | Observed behavior |
|---|---|
| `ssi_url($systemUrl,$targetPath='/',$email=null)` | Uses explicit email or current user email. With no identity returns direct base+path. Otherwise appends `/ssi/login/{encrypt(email)}?redirect={urlencode(path)}`. Token is not raw-url-encoded in this helper. |
| `is_external_url($url)` | Compares parsed hostname only with current app hostname. Same host with different ports/base paths is treated as internal. Relative URLs return false. |
| `auto_ssi_url($url,$email=null)` | Returns internal URL unchanged; for external hostname rebuilds `scheme://host[:port]` as system root and places full path/query/fragment in redirect. External app base paths are not retained in its login base. |

**Observed app cards:** current `ssi/index.blade.php` sends HRIS app cards directly to `ssis.url`; other app cards append `/ssi/login/` plus encrypted email. Older `ssi.blade.php` uses `{ssis.url}/autologin?id={encrypt(local-user-id)}`. These protocols are not interchangeable; neither target handler is supplied for partner apps here. A DTS `DTS_URL` env value does not configure every app registry row.

**Proposed target checklist**

- [ ] Select a supported identity provider/OIDC flow or short-lived single-use code exchange with documented partner contracts. Treat this as a protocol redesign, not a direct copy of encrypted email login.
- [ ] Keep app encryption keys independent; if a temporary legacy bridge is required, scope its key/protocol, expiry, issuer, audience, replay state, allowed redirects and retirement date.
- [ ] Require active authorized users, map a stable identity subject, regenerate session after login and handle already-signed-in/mismatched identities explicitly.
- [ ] Validate redirects to registered local destinations, encode tokens safely and redact every auth token from logs/referrers.
- [ ] Maintain a partner registry with origin plus base path; explicitly distinguish ordinary external links from SSI-enabled apps.
- [ ] Validate invalid/missing/expired/replayed token, removed email, inactive employee, duplicate email, external redirect, same-host/different-port/base-path and already-signed-in browser.

## 6. External notification creation

Sources: `SystemNotificationController::externalCreate`, `SystemNotification` model and notification migrations.

### POST `/api/notifications/external-create`

**Observed auth:** shared integration bearer, stateless; browser session/CSRF is not required. Same token grants DTR ingestion and employee reads. HTTP 201 on success:

```json
{"status":"success","message":"Notification created successfully","notification_id":321}
```

Example dummy request:

```json
{
  "notification_type": "personal",
  "user_email": "employee@example.invalid",
  "system": "DTS",
  "source": "Pending Documents",
  "title": "Document requires review",
  "message": "A document is waiting for your action.",
  "url": "https://dts.example.invalid/pendingIndex?doc_num=DOC-EXAMPLE-001",
  "priority": 5,
  "expires_at": "2026-10-22 23:59:59"
}
```

| Field | Observed validation / semantics |
|---|---|
| `notification_type` | Required, `personal`, `broadcast` or `role_based` |
| `user_email` | Optional email, max 255; for personal resolves the first matching user and takes precedence over `user_id` |
| `user_id` | Optional integer; if email is not supplied, resolves user by local numeric ID |
| `target_users` | Optional array; required/nonempty for role_based; elements have no type/existence validation |
| `system` | Required string max 50 |
| `source` | Required string max 100 |
| `title` | Optional nullable string max 200 |
| `message` | Required string; no maximum length rule |
| `url` | Optional nullable string; no URL syntax/scheme/host/max-length validation |
| `badge_class`, `badge_text`, `icon` | Optional nullable strings max 50; defaults `badge-info`, `New`, `fas fa-bell` |
| `priority` | Optional nullable integer; default 0, no bounds |
| `expires_at` | Optional nullable date; no future-only or explicit timezone rule |

For personal notifications: email lookup failure returns 422 `No user found for provided user_email`; invalid ID returns 422 `No user found for provided user_id`; missing both returns 422 `user_email or user_id is required for personal notifications`. If both are supplied with different users, email wins silently. It also stores the resolved `recipient_email` as metadata; reads still scope by numeric `user_id`, not by email. Archived/terminated users are not filtered here.

Broadcast creates a shared row; role_based creates a shared row with `target_users`. Role matching is unusual: JSON array contains either current numeric **user ID**, or current **user_type cast to a string**. Thus `[2]` and `["2"]` can mean different recipient groups. The producer should not assume the entire array contains roles.

Validation errors return 422:

```json
{"status":"error","message":"Validation failed","errors":{"source":["<validation message>"]}}
```

Conditional role-target error: 422 `target_users array is required for role_based notifications`. Shared auth failures use `success:false` from middleware, while controller errors use `status:error`; clients must handle both envelopes. Other failures fall through normal exception handling; there is no per-request transaction/idempotency or stable 500 envelope. No external update/delete or deduplication endpoint is implemented; retried create calls can duplicate notifications.

### Proposed target checklist

- [ ] Require producer-specific credentials and namespace/scopes; restrict which producer can claim each `system` and which recipients it can address.
- [ ] Prefer stable cross-app user identity/email mapping over coincidental numeric IDs; define email normalization, uniqueness and mismatch handling.
- [ ] Split explicit `user_ids` and `role_ids/types`; require typed existing values and bounds.
- [ ] Add a producer event ID/idempotency key and lifecycle update/close mechanism for pending external tasks.
- [ ] Bound message size/priority/expiry and validate destinations against registered HTTP(S) partner origins/base paths.
- [ ] Make read/dismiss state per recipient with delivery/read receipts separate from shared notification content.
- [ ] Define consistent error responses, correlation IDs, quota, retry rules and no raw secret/identity payload logging.
- [ ] Test unknown/mismatched identity, personal/broadcast/role delivery, invalid URL, duplicate delivery, producer boundaries, expiry and concurrent retries.

## 7. Browser notification API

All following routes add `web` + `auth` to the API group. Authenticated session cookie and a valid CSRF token (`X-CSRF-TOKEN` or supported framework equivalent) are required for POST. `Accept: application/json` gives predictable authentication behavior; without JSON negotiation the auth middleware may redirect to login. Missing/mismatched CSRF ordinarily yields 419 before controller handling. A bearer integration token alone does not authenticate these routes.

| Method/path | Request | Observed success response |
|---|---|---|
| GET `/api/notifications` | None | 200 `{status:"success", notifications:[...], count:N}`; deactivates expired rows first, returns active applicable rows ordered priority desc then created_at desc, no pagination |
| GET `/api/notifications/count` | None | 200 `{status:"success", total:N, unread:N}`; deactivates expired rows first |
| POST `/api/notifications/read` | `{id: <integer>}` | 200 status/message plus formatted `notification` |
| POST `/api/notifications/dismiss` | `{id: <integer>}` | 200 `{status:"success",message:"Notification dismissed"}` |
| POST `/api/notifications/read-all` | No fields | 200 status/message plus `count` of active unread rows updated |
| POST `/api/notifications/dismiss-all` | No fields | 200 status/message plus `count` of active rows updated |
| POST `/api/notifications/create` | Internal creation fields below | 201 status/message plus formatted `notification` |

`read`/`dismiss` use the applicable-recipient scope, not the active scope, so a permitted expired/dismissed notification can still be looked up. Invalid ID type produces 422 validation error; unknown/non-applicable ID produces 404 `{status:"error",message:"Notification not found"}`. Marking an already-read/dismissed record succeeds without resetting its timestamp. Controller auth fallback returns 401 `{status:"error",message:"Unauthorized"}` where reached.

Formatted notification shape (`SystemNotification::toNotificationArray`):

```json
{
  "id": "notif-321",
  "notification_id": 321,
  "url": "<processed destination or null>",
  "source": "Pending Documents",
  "title": "Document requires review",
  "meta": "A document is waiting for your action.",
  "badgeClass": "badge-info",
  "badgeText": "New",
  "icon": "fas fa-bell",
  "priority": 5,
  "is_read": false,
  "is_dismissed": false,
  "system": "DTS",
  "created_at": "<ISO-8601 timestamp>"
}
```

Use numeric `notification_id` in mutation bodies, not display ID `notif-321`. Source `message` is renamed `meta` for the frontend. `url` is computed through `auto_ssi_url()`, so external destinations can contain a freshly generated encrypted login identity; raw stored URL and returned URL are not necessarily equal.

### Internal creation differences

`POST /api/notifications/create` uses the same notification types/content fields and defaults as external creation with these differences:

- `user_id` must exist in `users`; `user_email` is not accepted/resolved by this method; personal requires `user_id`.
- `system` is optional and defaults to `HRIS`.
- `url` is validated as `nullable|url`; external-create accepts any nullable string.
- It returns the formatted notification object rather than only `notification_id`.
- A proposed permission check is commented out. **Any authenticated session can currently call this method**; “Admin/System use” in the comment is not enforced authorization.

### Observed delivery/state behavior

Personal rows target `user_id`. Broadcast rows target everyone. Role-based rows match numeric user ID or string user type as described above. Active means `is_active=true`, `is_dismissed=false`, and unexpired. Read state does not remove an active notification.

`is_read`, `is_dismissed`, `read_at` and `dismissed_at` live on the notification row. **Broadcast/role-based state is shared:** one user's read/dismiss changes the result for other recipients. This is an observed implementation defect, not target acceptance behavior.

The SSI page renders WFH approval summary bubbles plus database notifications directly in Blade (`ssi/index.blade.php`). Clicks mark database items as read with CSRF and open the destination in a new window. Dismissal hides immediately, records browser localStorage state keyed by user and makes the API request; request failure does not restore the hidden item. No notification polling or websocket transport was found in that page. Pusher/Echo setup is commented out in `resources/js/bootstrap.js`.

**Proposed:** move notification query/authorization out of the template, use per-recipient state, paginate history, choose an explicit refresh policy, reconcile local optimistic state on errors, authorize creation and preserve WFH summaries as derived task notifications where appropriate.

## 8. WFH geolocation provider calls

Source: `DTRTimeLog::time_log_ssi_wfh`. These are outbound synchronous enrichment calls while saving a WFH web log; they are not external attendance collector APIs.

| Call | Observed contract |
|---|---|
| GET `http://ip-api.com/json/{ip}` | No auth; cURL return-body mode, timeout 5 seconds. IP selected from first available forwarded/header value, taking first comma-separated value. Parses JSON only if body returned; expects `status:"success"`. Maps city/district/zip/regionName/country/countryCode/lat/lon/timezone/isp/org/as into stored JSON. HTTP transport is plaintext. |
| GET `https://us1.locationiq.com/v1/reverse.php` | Only when `LOCATIONIQ_API_KEY` and nonempty lat/lon exist; query `key`, `lat`, `lon`, `format=json`, `addressdetails=1`; cURL timeout 5 seconds. Expects JSON without `error`; reads address road, neighbourhood or suburb, municipality or city_district, postcode and display_name. |

Both calls lack explicit response status checks, retries, caching and documented quotas. Some errors become `wfh_location` JSON with IP/error/geocode_error; empty/failing responses can leave location unset. The log is still saved when enrichment does not succeed if no outer database error occurs. The submitted device is `WEB WFH`, timestamp `Carbon::now()`, source ID zero. Provider credentials/terms/availability are unresolved; do not invent a current free-plan SLA.

`AdminController` builds browser links `https://www.google.com/maps?q={lat},{lon}` from location data; this is an outbound user link, not a server-side Maps API integration or requirement for a Google API key.

**Proposed:** configure provider URLs/keys under services, verify HTTPS/support with provider documentation, trust only known proxy headers, minimize personal data, cache, rate-limit and enrich asynchronously or fail open for attendance submission. Test unavailable providers, bad JSON, quota errors, empty coordinates, private/spoofed IPs and timeout bounds.

## 9. Mail transport boundary

Observed workflows send SMTP mail synchronously; classes `SendMail`, `SSINotif` and `bday` do not implement `ShouldQueue`. Leave approval templates, OB/CTO notices, event notifications and birthdays must be covered by rebuild fixtures. Current sender configuration and some call sites are literal values; they must be centralized and credentials rotated without copying them into documentation.

The repository defines no delivery webhooks, provider API payload contract, bounce processor, provider quota, guaranteed retry or deduplication. These are unresolved operational/provider contracts. Proposed mail jobs need workflow-event IDs, after-commit dispatch, selected provider authentication/TLS, safe sender DNS, bounded retry and an operator-visible failure trail. Do not enable commented-out overtime mail without an explicit feature decision.

## 10. Existing documentation discrepancies

The root SSI/notification guides are useful historical notes, not an authoritative contract when source disagrees.

| Existing claim/example | Source-confirmed discrepancy | Rebuild instruction |
|---|---|---|
| Same-host `localhost/hris` versus `localhost/dts` is detected as external | `is_external_url` compares hostname only | Explicit registry must include app base paths and ports; add regression fixture. |
| Generic auto-helper produces `/dts/ssi/login` for a subdirectory app | `auto_ssi_url` derives only scheme/host/port for login base | Store partner base URL separately from destination path. |
| SSI “validates” login tokens | Decrypts only, with no expiry, audience, replay or active-user checks; unchecked redirects | Adopt a real identity protocol; do not describe encryption as complete validation. |
| DTS DB variables/config in `SSI_NOTIFICATION_EXAMPLE.md` / quick reference | `DTS_DB_HOST`, `DTS_DB_PORT`, `DTS_DB_DATABASE`, `DTS_DB_USERNAME`, `DTS_DB_PASSWORD` are examples, not implemented DB connection keys | No required direct DTS DB access unless separately designed/approved. |
| All notification endpoints use cookie auth | External-create uses shared machine bearer; browser endpoints use web/session/CSRF | Document both authentication modes and test isolation. |
| IDs identify the recipient across systems | External personal notification can resolve by email first; numeric IDs are local and ignored when email supplied | Prefer explicit identity mapping and reject conflicting identifiers. |
| “Admin/System use” for internal creation | Permission check commented out | Implement a real creation policy. |
| Broadcast/role state represents each viewer | State fields on shared row | Introduce per-recipient receipts. |
| Queued mail implied by Queueable/ShouldQueue imports or queue env | Imports/traits do not queue synchronous send calls | Queue explicit after-commit jobs and verify actual worker execution. |
| Generic SSI route describes all launcher behavior | Active cards use encrypted email; old template uses encrypted numeric ID; login controller is guest-only | Inventory partner contracts and browser-state behavior before rollout. |
| Root examples can be pasted into modern Laravel | Old string-controller routes/bootstrap/package APIs and mismatched config names | Reimplement in the selected modern skeleton with adapter tests; do not paste old framework setup. |

## 11. Contract validation gate

- [ ] Capture sanitized example requests/responses from each actual consumer/partner owner and compare with the observed source contract.
- [ ] Record consumers and retirement dates for public attendance/user APIs and HTTP cron URLs before closing them.
- [ ] Give each external client a versioned contract, auth scope, timeout/retry policy, idempotency rules, owner and staging URL.
- [ ] Preserve compatibility intentionally through adapters; document every changed error/status/field instead of retaining known defects silently.
- [ ] Run contract tests against fakes and staging partners for auth/CSRF/throttle/error paths; tests must not contact production, ingest real logs, create real notifications or send real mail.
- [ ] Verify logs and generated documentation contain no real bearer tokens, app keys, SMTP credentials, personnel payloads or secret-bearing SSI URLs.
