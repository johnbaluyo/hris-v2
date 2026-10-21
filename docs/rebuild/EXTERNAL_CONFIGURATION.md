# External configuration and operations

Evidence date: 2026-10-21, Asia/Manila. This guide describes infrastructure and systems outside the application implementation. Read [INTEGRATION_CONTRACTS.md](INTEGRATION_CONTRACTS.md) for request contracts and compatibility defects.

**Observed** means confirmed in this repository, not confirmed in the running production environment. **Proposed** describes the rebuild requirement. **Unresolved** requires an operator, partner-system owner, or business owner to supply evidence. Examples use reserved dummy hosts and replacement secrets; no existing `.env` values are documented.

## 1. Deployment baseline and service inventory

The legacy app requires Laravel `^7.29`, PHP `^7.2.5|^8.0`, Laravel Mix 5/Webpack, AdminLTE 3, Vue 2, jQuery, Dompdf, Maatwebsite Excel, Simple QrCode, and Yajra DataTables (`composer.json`, `package.json`). A dependency declaration is not proof that every supported PHP version can execute this code. For example, application arrow functions require at least PHP 7.4.

The proposed rebuild baseline is **Laravel 13 with PHP 8.4**, subject to a package compatibility check and pinned lockfiles. Laravel 13 requires PHP 8.3 or newer; verify the supported matrix at implementation time. See [official Laravel releases](https://laravel.com/framework/docs/13.x/releases) and [deployment documentation](https://laravel.com/docs/13.x/deployment). Do not copy the old `vendor/`, dependency lockfile, bootstrap caches, or frontend bundles into a fresh modern skeleton.

| Outside component | Observed source/behavior | Rebuild provisioning requirement |
|---|---|---|
| Web host and PHP CLI | `public/.htaccess`, `public/web.config`, `artisan` | Choose Nginx/Apache with PHP-FPM, or supported IIS/PHP FastCGI. Web and CLI must use the same release, PHP version, extensions, configuration, and application identity. Point the document root at `public/`. |
| Relational database | `config/database.php` defaults to MySQL, `utf8mb4`, strict mode false; application uses MySQL-oriented date/JSON queries | Provision a supported MySQL-compatible target after query/schema testing. Capture actual source database version, SQL modes, collation, timezone, size, and grants; generic PostgreSQL/SQL Server config blocks do not establish portability. |
| DNS/TLS/reverse proxy | HTTPS forcing is optional runtime code; proxy middleware trusts all proxies | Assign separate environment hostnames and certificates; terminate TLS correctly; restrict trusted proxies and forwarded headers; forward `Authorization`. |
| Persistent files | Many uploads use `public/images`, `public/files`, `public/birthday_gif` directly; filesystem config also exposes `storage/app/public` | Preserve all legacy upload trees and database references during migration. Proposed target: private durable object/filesystem storage with authorized downloads, plus separately public assets. |
| Mail transport | SMTP, synchronous mail in leave/OB/CTO/calendar/birthday workflows | Obtain a dedicated provider account, verified sender and credentials, delivery/bounce policy, staging sink, DNS records, and egress access. |
| Attendance collector | Incoming bearer-authenticated DTR sync endpoints | Obtain the RFID/Anviz collector script, device/source database owner, source-ID rules, machine clock settings, outbound network path, scheduling and replay procedure. Collector implementation is absent. |
| SSI partner applications | `ssis` database rows, DTS config and encrypted-email launch links | Inventory each app, environment URL, identity mapping, launch protocol, decryption compatibility, permissions, owner, callback URLs and rollout order. Partner repositories are absent. |
| External notifications | Incoming `/api/notifications/external-create` shares attendance token | Provision producer-specific credentials/scopes in the target; arrange identity mapping and sender ownership with every producer. |
| WFH IP geolocation | HTTP ip-api call and optional LocationIQ reverse geocoding, `app/DTRTimeLog.php` | Decide whether to retain the feature. If retained, verify provider plan/terms, HTTPS availability, quota, credentials, privacy and retention, failure policy, and egress. No browser GPS is used by this flow. |
| Cache/session/queues | File cache/session and sync queue are configuration defaults; Redis and other driver stubs exist | Choose database or Redis-backed queues and an appropriate shared cache/session store. Provision drivers, tables or Redis namespaces, worker supervisor, failed-job retention and alerting. |
| Scheduler | Three daily commands in `app/Console/Kernel.php`; other HTTP cron routes exist | Obtain actual host cron/Task Scheduler entries before cutover. Proposed: one scheduler service with locking, run logs and alerts. |
| Build/package registries | Composer packages, npm packages, vendored browser assets and CDN resources | Build in controlled CI with registry access and pinned dependencies; publish immutable assets. Runtime production hosts need no Node process for a conventional compiled frontend. |
| PDF/Excel/QR assets | Dompdf in COE, Excel exports/imports, QR codes, local letterheads | Provision PHP extensions, fonts, writable temp directories and templates; verify rendering. No wkhtmltopdf, headless Chrome service or remote PDF service was found in app code. |
| Monitoring/backups | File logging configured; no deployment backup service defined | Provision protected centralized logs, metrics, alerts, encrypted off-host database/file backups and restore drills. |
| Optional adapters | Pusher, AWS S3/SES/SQS/DynamoDB, Mailgun, Postmark, Memcached, Papertrail/Slack/Flare config stubs | Enable only services selected for the rebuild. Config stubs and `.env` key presence alone do not prove an active dependency. |

## 2. Environment variable register

### 2.1 Keys actually declared in the local `.env`

Only key names were inventoried. Presence does not confirm that a value is valid, active, or used in the current code.

| Group | Exact observed names | Required interpretation for the rebuild |
|---|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Generate a separate key per target environment; production debug off; canonical HTTPS URL. Coordinate any temporary SSI key compatibility explicitly. |
| App integrations | `API_SYNC_TOKEN`, `DTS_URL`, `LOCATIONIQ_API_KEY` | Split token scope/ownership in the target. Centralize all integration configuration under `config/services.php`; restrict partner URLs. |
| Logging | `LOG_CHANNEL` | Choose a protected channel, retention and redaction policy. |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dedicated least-privilege runtime account; separate schema-migration account; TLS where applicable. |
| Runtime stores | `BROADCAST_DRIVER`, `CACHE_DRIVER`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `SESSION_LIFETIME` | Modern skeleton names can differ; implement explicit mappings below. |
| Cookies | `SESSION_SECURE_COOKIE`, `SESSION_SAMESITE` | Secure-cookie key is read. `SESSION_SAMESITE` is **not read**: legacy `same_site` is hardcoded `lax`. Target commonly uses `SESSION_SAME_SITE`; verify skeleton. |
| Redis | `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | Relevant only when chosen drivers use Redis; never share unnamespaced cache/queue/session databases with partner apps. |
| Mail | `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | `MAIL_DRIVER` is **not** the selector used by this app; configuration reads `MAIL_MAILER`. Legacy username/password env bindings are commented out in favor of literals. |
| Cloud | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Optional; prefer workload identity over static credentials if the chosen service supports it. |
| Realtime/build | `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`, `MIX_PUSHER_APP_KEY`, `MIX_PUSHER_APP_CLUSTER` | Broadcasting code in `resources/js/bootstrap.js` is commented out; do not provision Pusher merely because these keys exist. Browser build variables must contain public values only. |

**Observed secret handling defect:** `config/mail.php` contains literal SMTP credentials and sender details. Do not reproduce those values, copy that config into the target, or assume setting `MAIL_PASSWORD` overrides it. Rotate the provider credential, remove literals from the rebuild and review repository history/access with the responsible owner. Record rotation completion without recording secrets.

### 2.2 Additional keys referenced by source/config

These exist in configuration/code but are not all declared in the local `.env`. A selected driver needs its relevant keys; the entire list is not a requirement to purchase every service.

| Area | Exact observed key names | Source / notes |
|---|---|---|
| App/URL | `ASSET_URL`, `FORCE_HTTPS` | `config/app.php`; `FORCE_HTTPS` is read directly in `AppServiceProvider::boot`, outside config. |
| DB variants/TLS | `DATABASE_URL`, `DB_SOCKET`, `DB_FOREIGN_KEYS`, `DB_SSL_CA`, `DB_SSL_CERT`, `DB_SSL_KEY`, `DB_SSL_VERIFY_SERVER_CERT` | `config/database.php`. Legacy MySQL verification defaults false; proposed production validation must verify certificates. |
| Redis | `REDIS_CLIENT`, `REDIS_CLUSTER`, `REDIS_PREFIX`, `REDIS_URL`, `REDIS_DB`, `REDIS_CACHE_DB` | `config/database.php`. Install the selected PHP client/extension. |
| Cache | `CACHE_PREFIX`, `MEMCACHED_PERSISTENT_ID`, `MEMCACHED_USERNAME`, `MEMCACHED_PASSWORD`, `MEMCACHED_HOST`, `MEMCACHED_PORT`, `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT` | `config/cache.php`. Optional alternate stores. |
| Queue | `QUEUE_FAILED_DRIVER`, `REDIS_QUEUE`, `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX` | `config/queue.php`. Verify modern failed-job schema and worker retry/timeout configuration. |
| Filesystems/cloud | `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT` | `config/filesystems.php`; old variable names do not automatically map to a modern skeleton. |
| Sessions | `SESSION_CONNECTION`, `SESSION_STORE`, `SESSION_COOKIE`, `SESSION_DOMAIN` | `config/session.php`; also hardcoded path `/`, httpOnly true, sameSite `lax`. |
| Mail transport/log | `MAIL_MAILER`, `MAIL_LOG_CHANNEL` | `config/mail.php`. Sender names/addresses are literals in legacy config and some send paths. |
| Provider adapters | `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT`, `POSTMARK_TOKEN` | `config/services.php`; optional adapters, not demonstrated production dependencies. |
| Logging/error tools | `LOG_SLACK_WEBHOOK_URL`, `PAPERTRAIL_URL`, `PAPERTRAIL_PORT`, `LOG_STDERR_FORMATTER`, `FLARE_KEY`, `DATATABLES_ERROR` | Config stubs. Slack log alerts, if enabled, are an operational choice; this documentation task sent no messages. |
| Hashing/templates | `BCRYPT_ROUNDS`, `VIEW_COMPILED_PATH` | Hashing cost and writable compiled Blade location. |
| Development tooling | `IGNITION_EDITOR`, `IGNITION_THEME`, `IGNITION_SHARING_ENABLED`, `REGISTER_IGNITION_COMMANDS`, `IGNITION_ENABLE_RUNNABLE_SOLUTIONS`, `IGNITION_REMOTE_SITES_PATH`, `IGNITION_LOCAL_SITES_PATH` | Legacy Ignition settings; modern development package configuration must be reviewed anew. Keep production debug tooling disabled. |

`config/sweetalert.php` also reads UI-only keys: `SWEET_ALERT_CDN`, `SWEET_ALERT_ALWAYS_LOAD_JS`, `SWEET_ALERT_NEVER_LOAD_JS`, `SWEET_ALERT_TIMER`, `SWEET_ALERT_WIDTH`, `SWEET_ALERT_HEIGHT_AUTO`, `SWEET_ALERT_PADDING`, `SWEET_ALERT_ANIMATION_ENABLE`, `SWEET_ALERT_ANIMATECSS`, `SWEET_ALERT_CONFIRM_BUTTON`, `SWEET_ALERT_CLOSE_BUTTON`, `SWEET_ALERT_TOAST_POSITION`, `SWEET_ALERT_MIDDLEWARE_AUTO_CLOSE`, `SWEET_ALERT_MIDDLEWARE_TOAST_POSITION`, `SWEET_ALERT_MIDDLEWARE_TOAST_CLOSE_BUTTON`, `SWEET_ALERT_MIDDLEWARE_ALERT_CLOSE_TIME`, `SWEET_ALERT_AUTO_DISPLAY_ERROR_MESSAGES`, `SWEET_ALERT_CONTAINER_CLASS`, `SWEET_ALERT_POPUP_CLASS`, `SWEET_ALERT_HEADER_CLASS`, `SWEET_ALERT_TITLE_CLASS`, `SWEET_ALERT_CLOSE_BUTTON_CLASS`, `SWEET_ALERT_ICON_CLASS`, `SWEET_ALERT_IMAGE_CLASS`, `SWEET_ALERT_CONTENT_CLASS`, `SWEET_ALERT_INPUT_CLASS`, `SWEET_ALERT_ACTIONS_CLASS`, `SWEET_ALERT_CONFIRM_BUTTON_CLASS`, `SWEET_ALERT_CANCEL_BUTTON_CLASS`, `SWEET_ALERT_FOOTER_CLASS`. They create no separate service requirement; a CDN override adds a browser network dependency.

### 2.3 Legacy-to-target mapping

| Legacy behavior/key | Proposed target | Migration action |
|---|---|---|
| `MAIL_DRIVER` in `.env`; `MAIL_MAILER` in actual config | `MAIL_MAILER`; `MAIL_SCHEME`/transport-specific keys as required by selected modern mailer | Treat the generated target config as authoritative; configure sender through `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`; remove every hardcoded sender override unless explicitly approved. |
| `MAIL_ENCRYPTION` | Target transport TLS scheme/settings | Map explicitly and verify provider handshake; do not assume a copied old env file enables TLS. |
| `CACHE_DRIVER` | Usually `CACHE_STORE` in a modern skeleton | Create matching store tables/Redis config; verify selected target version. |
| `FILESYSTEM_DRIVER`, `FILESYSTEM_CLOUD` | `FILESYSTEM_DISK` plus explicit named disks | Rewrite direct `public_path()` upload code; changing a disk variable alone does not migrate any files. |
| `BROADCAST_DRIVER`, `MIX_PUSHER_*` | `BROADCAST_CONNECTION`, `VITE_*` only if broadcasting is implemented | Remove unused integration or configure the selected broadcaster; never expose private tokens via `VITE_*`. |
| `SESSION_SAMESITE` | `SESSION_SAME_SITE` mapped through config | Keep a host-only, unique session cookie unless a separately designed cross-origin flow requires otherwise. |
| Runtime `env('FORCE_HTTPS')`, `env('LOCATIONIQ_API_KEY')` | `config(...)` populated in config files | Fix before `config:cache`; runtime `.env` reads can disappear when cached. |
| Both `app.dts_url` and `services.dts.url`, different defaults | One canonical `services.dts.url` | Validate explicit HTTPS base URL and app base path for every environment. |
| Shared `API_SYNC_TOKEN` | Per-client hashed credentials with distinct attendance/user-read/notification scopes | Coordinate producers and collector, expiry, dual-credential transition, revocation, audit logs and rate limits. |
| App encryption key reused across SSI apps | Independent app keys and a documented identity protocol | Prefer a supported identity provider or one-time authorization-code exchange. If a compatibility bridge is necessary, isolate its key, audience, expiry, replay store and shutdown date. |

The target names above are proposed implementation requirements, not claims that the legacy app currently reads them. Create a target `.env.example` during implementation with dummy values and documented conditional requirements; this documentation does not modify the existing `.env`.

### 2.4 Dummy target configuration sketch

Adapt to the generated Laravel 13 config and selected drivers. Empty/placeholder secrets intentionally cannot authenticate.

```dotenv
APP_NAME="HRIS Rebuild"
APP_ENV=staging
APP_KEY=REPLACE_WITH_GENERATED_ENVIRONMENT_KEY
APP_DEBUG=false
APP_URL=https://hris.staging.example.invalid
DB_CONNECTION=mysql
DB_HOST=db.staging.example.invalid
DB_PORT=3306
DB_DATABASE=hris_rebuild
DB_USERNAME=hris_runtime
DB_PASSWORD=REPLACE_FROM_SECRET_MANAGER
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_COOKIE=hris_rebuild_session
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hris@example.invalid
MAIL_FROM_NAME="HRIS"
DTS_URL=https://dts.staging.example.invalid
```

Do not enable external collectors, notification producers or real SMTP merely by copying this sketch. Provision their selected credentials and implementations first. Proposed per-client credentials, identity provider settings, health endpoints and feature flags need corresponding target code; no legacy support is implied.

## 3. Hosting, TLS and network setup

**Observed:** Apache and IIS rewrite examples route missing files to `index.php`. Apache forwards the Authorization header. There is no infrastructure-as-code, TLS certificate setup, container definition, production process supervisor or deployment pipeline in the reviewed application files. `config/app.php` uses `Asia/Manila`, locale `en`, and `AES-256-CBC`. `TrustProxies` trusts `*`; `config/trustedproxy.php` contains a different unused-looking proxy default, so middleware behavior must drive the audit.

**Proposed provisioning checklist**

- [ ] Assign a hosting owner, environment hostnames, private database route, deployment identity and incident contacts.
- [ ] Use a supported PHP 8.4 runtime and Composer 2; install extensions required by Laravel and selected packages, including PDO MySQL, cURL, mbstring, OpenSSL, XML/DOM, fileinfo, zip, GD/image support and any required intl/bcmath support. Run `composer check-platform-reqs` against the final lockfile rather than treating this list as exhaustive.
- [ ] Install a Node version supported by the chosen frontend toolchain in CI, pin it, build from lockfiles and publish hashed assets. Do not reuse Laravel Mix 5 or Vue 2 by default.
- [ ] Set web root to the release's `public/`; deny access to dotfiles, `.env`, source, `.git`, storage internals and backup archives; disable PHP/script execution in upload directories.
- [ ] Make `storage/` and `bootstrap/cache/` writable only by the application/deploy identities; ensure immutable application files outside deliberate shared storage.
- [ ] Configure TLS, certificate renewals and alerts; redirect HTTP to HTTPS; verify generated links and cookies behind the real proxy.
- [ ] Allow only known proxies to set client IP/host/proto headers. Prevent direct backend access that bypasses the proxy.
- [ ] Forward `Authorization`, cookies, CSRF headers and needed request IDs through the proxy/WAF; test multipart and JSON requests.
- [ ] Set explicit body/upload limits and PHP time/memory limits after testing largest legitimate import, PDF and batch. Limits are operational settings, not permission to accept unlimited inputs.
- [ ] Permit outbound DNS/HTTPS to approved SMTP/provider APIs, SSI partners and optional geolocation. Restrict database/Redis/administrative ports to private networks.
- [ ] Configure exact CORS origins if browser cross-origin access is required. Legacy wildcard API CORS with credentials false does not make cookie-based notifications cross-origin compatible.
- [ ] Keep NTP/time synchronization on web, CLI, database and collector hosts; document Asia/Manila interpretation of legacy local timestamps and any target UTC conversion.

## 4. Database and data-transfer configuration

**Observed:** only one normal application DB connection is implemented; `DTS_DB_*` appears in example documents, not in `config/database.php` or executing app queries. No second DTS connection is an established prerequisite. Direct DTS database access must not be added merely to reproduce those examples.

- [ ] Capture source schema, migration ledger, counts, nullability, indexes, defaults, data encodings, SQL modes and actual date/time types from a sanitized read-only export.
- [ ] Build a clean target schema; resolve migration defects first. The notification create migration already declares `recipient_email`, while its follow-up migration adds the same column/index again. Replaying legacy migrations blindly fails.
- [ ] Preserve employee identities, emails, ATMS IDs, partner mappings, document references and foreign keys; do not remap numeric IDs without explicit crosswalks.
- [ ] Test strict SQL mode with actual legacy data rather than carrying `strict=false` as a permanent fix. Quarantine malformed/zero dates, empty values and orphans with reconciliation evidence.
- [ ] Provision separate runtime, migration and backup accounts; verify TLS CA/certificate validation for remote DB connections.
- [ ] Record import ordering and checksums, chunk size and restart checkpoints. Rehearse on a restored copy; database migrations must not run against production during discovery.
- [ ] Prove payroll/leave/attendance totals and attachment references before switching any traffic or external sync.

## 5. Storage, reports and browser assets

The backup and migration scope includes both database and mutable files. `php artisan storage:link` only handles `storage/app/public`; it does not capture direct legacy uploads.

| Observed tree | Examples/source | Proposed handling |
|---|---|---|
| `public/images/profile_pic`, `public/images/esign`, `public/images/govid` | Staff profile, signatures, government IDs | Private access-controlled files; preserve ownership and original database filenames in a migration manifest. |
| `public/images/uploads/staff`, child `training_cert`, child `ob` | Personnel records, certificates, OB attachments | Private durable disk; authorization on download; retention and malware scanning/quarantine as selected by operations. |
| `public/images/applicants/{tor,wes,pds,cse,ipcr,others}` | Recruitment and `Upload` model | Private applicant documents with applicant/HR permission checks; do not publish via a general storage symlink. |
| `public/images/coe`, `public/images/overtime`, `public/images/concerns` | COE, overtime and concern uploads | Private files or explicitly public subsets chosen by module; reconcile individual references. |
| `public/files`, `public/birthday_gif`, `public/images/questions` | Announcements, HR file uploads, birthday media, examination images | Classify by sensitivity and viewer role; preserve existing referenced files before cleanup. |
| `public/images/icons/BORDER/5.5`, letterheads/logos | SSI app icons, wellness uploads; payroll export letterhead | Static approved public assets; preserve case-sensitive paths when moving from Windows. |
| `storage/app/temp`, `storage/framework/laravel-excel`, compiled views/cache/logs | Excel imports and temp output, framework config | Writable with bounded cleanup/retention. Temp output is not an archival copy of a personnel document. |

**Observed:** Dompdf generates COE PDFs in-process; Excel exports use images such as `public/images/bagongpilipinas_letterhead.png`. Spreadsheet formulas, fonts, images, QR verification links, paper sizes, line wrapping, names with non-ASCII characters and print layouts need fixture-based comparison. QR targets depend on the correct canonical `APP_URL` and public certificate verification routes.

COE templates request PNG QR output and merge `public/images/psrti_logo.png` into the image; they also embed `public/images/coe/e_sig.png`. The legacy lockfile's Bacon QR dependency suggests `ext-imagick` for QR image generation. Validate/install the image backend required by the selected target QR package (including Imagick if retaining that renderer); Composer's required-extension check alone may not exercise a suggested PNG backend.

**Browser network dependencies observed in views:** `fonts.googleapis.com` (and font delivery), `code.jquery.com`, `cdn.jsdelivr.net`, `cdnjs.cloudflare.com`, `cdn.datatables.net`, `code.ionicframework.com`, `maxcdn.bootstrapcdn.com`, and a Font Awesome kit in an older layout. Assets include Bootstrap 4 and 5 variants, AdminLTE, jQuery/UI, DataTables/buttons, daterangepicker, Font Awesome/Ionicons, toastr, SweetAlert2, Moment, Inputmask, JSZip, pdfmake, Summernote, Select2 and iCheck. No paid account is necessarily needed for these public CDNs; actual availability was not tested.

- [ ] Inventory files with counts, sizes, hashes and owning records; identify orphans without deleting them during discovery.
- [ ] Provision durable shared storage before scaling beyond one web/worker host; never keep uploaded personnel records solely on an ephemeral release disk.
- [ ] Preserve old URLs only through authorized compatibility redirects or routing where needed; do not expose private records for URL compatibility.
- [ ] Validate MIME/extension/size, randomize storage names, serve safe content disposition and block executable uploads. Do not infer global upload safety from the isolated `/test-upload` implementation.
- [ ] Provision fonts, image libraries and temp-space limits; verify PDF/Excel generation without internet access where possible.
- [ ] Bundle and pin frontend dependencies locally in the target. If any CDN remains, document CSP, integrity/version pinning, availability and user-network requirements.
- [ ] Test reports, print, uploads/downloads and QR verification on the target OS with production-sized fixtures.

## 6. Mail and background workers

**Observed:** `Mail::send()` / `Mail::to()->send()` run inline in leave, official business, CTO, events and birthdays. Mailable classes use `Queueable` and import `ShouldQueue`, but do not implement it. The shown call sites do not queue mail. Changing `QUEUE_CONNECTION` alone does not make these sends asynchronous. Overtime notification mail calls are commented out.

**Proposed:** dispatch mail after successful DB commit, provide idempotency per workflow transition, use bounded retries and a failure ledger, and separate notification delivery failure from the saved business action. Configure sender centrally; prohibit production recipients in ordinary staging tests.

- [ ] Obtain provider SMTP/API endpoint, port, TLS scheme, service account, sender and reply-to policy; store credentials in the host secret manager.
- [ ] Verify sender domain and configure provider-prescribed SPF/DKIM/DMARC with the DNS owner. Existing provider access and DNS control are unresolved.
- [ ] Use `log` or a mail sink in development/staging; a dedicated allowlisted delivery test requires an operator-approved test recipient.
- [ ] Provision queue driver storage and failed-job tables or Redis as selected; define named queues if separating imports, reports and mail.
- [ ] Configure systemd/Supervisor or a Windows service equivalent to start workers on boot, restart on failure and stop gracefully. Worker timeout must be shorter than queue retry visibility and long enough for legitimate jobs.
- [ ] Set concurrency based on database/provider capacity; implement per-provider rate limits and backoff.
- [ ] Restart workers on each release/config change; monitor oldest pending job, failures, retries and last successful mail delivery.
- [ ] Reconcile resent workflow events and birthday runs to prevent duplicate mail during retries or cutover.

## 7. Scheduler and legacy HTTP cron routes

### 7.1 Code-confirmed schedule

| Registered schedule | Legacy frequency | Side effects |
|---|---|---|
| `optimize:clear` | `daily()` = 00:00 app timezone | Clears cached framework state; this is not a business requirement. |
| `command:AnalyzeTimeLog` | `daily()` = 00:00 Asia/Manila | Raw logs to DTR, duplicate cleanup, work-hour computation and rounding. |
| `command:GenerateLeaveCredit` | `daily()` = 00:00 Asia/Manila | Automatic credits and tardiness deductions. |
| `command:SendBirthdayMail` | Registered, **not scheduled** | Sends birthday emails when called. |

No host cron/Task Scheduler configuration is included, so even the three declared schedules are not proof they run in production. Both business commands are due at the same minute, and no `withoutOverlapping`, `onOneServer`, explicit dependency chain or execution audit is declared.

### 7.2 Other HTTP entry points that must be inventoried

`routes/web.php` declares GET routes for `/AnalyzeTimeLog`, `/GenerateLeaveCredit`, `/DeductLeaveCredit`, `/sendBirthdayGreet`, `/ApproveAFL`, `/AddWorkWeek`, `/AddTardiness`, `/BestAttendance`, `/checkHolidays`, `/workSuspensionModify`, `/specialOrderModify`, `/checkOB`, `/checkCTO`, `/modifyDTRLeave`, `/applyPendingCocDeductions` and `/update_filled_position`. Some target controllers require session auth, but `CronController` has no auth middleware; these are not a uniformly protected integration surface. `/DeductLeaveCredit` is declared twice with different controllers. Route comments such as “add to cron job” do not establish a host schedule.

**Never use these URLs as discovery/health checks:** they can mutate attendance, leave, positions and mail. The HTTP and Artisan paths are also not identical: HTTP `AnalyzeTimeLog` adds tardiness, while the Artisan command cleans duplicates and rounds work hours. Preserve intended business results after resolving this difference.

### 7.3 Proposed scheduling design

The following is a design starting point, not an assertion of approved business timing:

| Job | Proposed cadence/order | Required decision/evidence |
|---|---|---|
| Collector delivery | Every 1–5 minutes, incremental checkpoint | Device/network owner confirms freshness requirement, backfill and collector reliability. |
| Attendance reconciliation | After collector import and a daily catch-up at 00:15 Asia/Manila | Include a prior-day/late-arrival window; consolidate holiday, suspension, leave, OB, CTO and special-order application in a deterministic sequence. |
| Leave accrual/deductions | Daily at 00:45 after attendance reconciliation | Business rules decide effective dates; immutable/idempotent ledger prevents double grants/deductions. |
| Pending COC deductions | After relevant approved attendance/CTO updates | Confirm interaction with leave processing and locking. |
| Birthday notifications | Optional daily at 08:00 | Owner explicitly enables the feature and confirms recipient rules; per-person/day deduplication. |
| Expired notification deactivation | Hourly | Read endpoints currently deactivate expired rows; move housekeeping off reads. |
| Position occupancy reconciliation | Daily or on staffing transactions | Recruitment/HR owner confirms source of truth and stale-position behavior. |
| Cache building | Release deployment only | Remove daily `optimize:clear` from the target schedule. |

- [ ] Export actual legacy host schedules, including scripts that call HTTP routes, and identify overlapping/manual jobs.
- [ ] Replace public mutation GETs with commands/jobs and protected administrative actions; retire old cron URLs only after host jobs are switched.
- [ ] Run one scheduler service every minute (`php artisan schedule:run`) under the application identity, or use the deployment platform's scheduler. On Windows use Task Scheduler with an explicit working directory/PHP executable and prevent simultaneous instances.
- [ ] Set explicit timezone, job dependencies, locks, single-server ownership, retry policy, deadline and run records. Locks across servers require a shared supported cache store.
- [ ] Verify `schedule:list` and one controlled staging run; test duplicate invocation, interruption, catch-up and month/year boundaries with fixture data.
- [ ] Alert on missing scheduler heartbeat and missed business runs; capture duration, affected count, run ID and sanitized error summary.

## 8. SSI, DTS, collector and geolocation provisioning

### SSI / partner systems

- [ ] Export partner registry from `ssis` with URL, title, status and owner in a protected operator inventory; the production list is not embedded in this guide.
- [ ] Identify the protocol used by every partner: `/ssi/login/{encrypted-email}`, older `/autologin?id={encrypted-id}`, direct link, or another protocol. Confirm the live SSI template and partner handler.
- [ ] Reconcile identity by normalized unique email or stable identity subject; do not presume local numeric IDs agree across apps.
- [ ] Confirm DTS document route `/pendingIndex?doc_num=...`, base path and query-string encoding. `DTS_URL` must be explicit because the two legacy config defaults differ.
- [ ] Provision independent target app keys; design an approved identity migration/bridge and callback/redirect allowlist. Do not solve cross-app SSI by casually copying all application encryption keys.
- [ ] Test external-host, same-host/different-base-path, query, fragment, encoded email, logged-in browser, inactive user, missing user, bad token, expiry and denied redirect.
- [ ] Move partner links/notification destinations behind an explicit registry; ordinary external sites must not receive SSI identity tokens.

### RFID/Anviz collector

- [ ] Obtain source collector code/config and device list; document who controls source database credentials and machine scheduling.
- [ ] Map source IDs to `anviz_log_id`, employee ATMS identifiers, device strings and local `time_log` dates; confirm leading-zero normalization and web entries using source ID zero.
- [ ] Verify `Authorization: Bearer ...` reaches PHP; use a dedicated target credential scoped only to attendance ingestion.
- [ ] Provide bounded batches, a durable checkpoint, replay protection and idempotent server-side ingestion. Legacy `max_id` alone cannot safely recover gaps or partial insertion.
- [ ] Rehearse offline collection, delayed delivery, duplicates, unknown employees, clock skew, out-of-order logs and rotation of credentials.
- [ ] Stop or redirect the legacy writer at cutover; only one writer owns each source stream. Keep a replay window and reconcile source/destination counts before advancing checkpoints.

### WFH geolocation

- [ ] Confirm business purpose and retention for IP/location data and whether the feature remains required.
- [ ] Validate actual provider contract and service limits with its owner; no quotas or account plans are recorded in source.
- [ ] Use verified HTTPS endpoints and safe HTTP clients in the target; reject spoofed forwarded IPs by trusting only configured proxies.
- [ ] Place `LOCATIONIQ_API_KEY` in config and secret storage; redact query keys, IPs and full location payloads from ordinary logs.
- [ ] Cache appropriate lookups, bound timeouts and quota usage, and keep WFH attendance submission available during provider outages. IP-derived coordinates are approximate and do not prove physical presence.

## 9. Secrets, configuration caches and release procedure

**Observed:** app key, DB password, API token, mail/provider credentials and browser-public values are mixed in the local env/config surface. Legacy config cache artifacts may contain resolved secrets. Never commit `.env`, application config caches, database dumps or exported real credentials into the rebuild documentation or target repository.

- [ ] Create environment-specific secret namespaces and grant read access only to runtime/deployment identities that need them.
- [ ] Rotate hardcoded SMTP credentials first; inventory any other credential already exposed in source/history and rotate with its owner.
- [ ] Generate the new target `APP_KEY` once per environment and preserve it durably. Re-running `key:generate` on an existing production environment can invalidate encrypted data/cookies and SSI compatibility.
- [ ] Inventory encrypted database values and partner token dependencies before app-key rotation; plan re-encryption/old-key support explicitly.
- [ ] Rotate collector and notification credentials with named owners and a short dual-credential window implemented by the target. Legacy middleware accepts only one exact shared token, so a rolling rotation is not supported without code changes.
- [ ] Read environment variables only inside config files; validate missing required values at boot and test the fully cached configuration without printing resolved secrets.
- [ ] Build a release, install production Composer dependencies, compile assets, provision writable shared paths and take a database/file backup.
- [ ] Run reviewed target migrations with the deployment identity; publish compatibility routes and assets; build config/routes/views caches only after confirming they support the target route definitions.
- [ ] Atomically switch releases, restart workers/long-running services and verify canonical URL, HTTPS cookies, DB connectivity, storage and scheduler.
- [ ] Roll back code only against a compatible schema; document separate data-repair/restore steps where writes have already occurred. Never automatically reverse financial/leave transactions as part of code rollback.

## 10. Backup, observability and acceptance

**Proposed backup requirements:** encrypted off-host database snapshots plus incremental/PITR capability where available; versioned files/object storage; protected key/secret recovery; documented retention and restore access. Set RPO and RTO with the system owner rather than inventing them. A DB-only backup loses personnel documents and signatures; a file-only backup cannot reconstruct record ownership.

- [ ] Assign retention, RPO/RTO, backup owners and restore approvers; include uploaded files, DB, app key and release/manifest metadata in the recovery plan.
- [ ] Rehearse restoring database and files together in isolation, validate counts/hashes and representative reports, and prove credentials can be recovered securely.
- [ ] Exclude cache/temp directories from archival scope unless needed for investigation; distinguish cache loss from unrecoverable source-file loss.
- [ ] Centralize logs with environment, request/run/client IDs and audit events; redact passwords, bearer tokens, SSI URL tokens, SMTP auth, government IDs and location payloads.
- [ ] Monitor HTTP errors/latency, TLS expiry, DB capacity, storage space, queue lag/failure, provider failures, scheduler heartbeat and attendance source checkpoint lag.
- [ ] Audit privileged changes, account/role changes, approvals, leave/payroll adjustments, downloads of sensitive files and integration deliveries without recording full sensitive payloads.
- [ ] Implement a read-only liveness/readiness endpoint for the target; no observed legacy `/up` endpoint is assumed. Never use cron or mail routes as a health probe.

### Go-live verification checklist

- [ ] Hostnames/TLS/canonical URL, proxy headers, cookies, auth and password-reset links verified in the deployed target.
- [ ] All required secrets provisioned; hardcoded SMTP credential rotated; debug disabled; no secret-bearing config caches/dumps in public artifacts.
- [ ] Source/target schema and counts reconciled, attachment manifests verified, identity mappings approved and payroll/leave/DTR totals accepted.
- [ ] Approved SMTP sink/provider tested with a designated test account; queue/worker failures and retries exercised without duplicate delivery.
- [ ] SSI partner launches and DTS document navigation passed; every partner owner has rollout and rollback contacts.
- [ ] RFID collector points to the intended host with the intended token; replay and checkpoint reconciliation pass.
- [ ] Notification producer token, email mapping, target URL allowlist, deduplication and recipient isolation pass.
- [ ] Scheduler ownership transferred; old host/HTTP cron writers stopped; scheduled reconciliation completes once and is audited.
- [ ] Private document access, report/PDF/Excel/QR verification, upload limits and static assets pass on the real hosting platform.
- [ ] Monitoring alerts delivered to the configured operations destination, restore drill passed, and code/data rollback checkpoints documented.
- [ ] Legacy writes frozen or controlled through a recorded final-delta strategy; no split-brain attendance/leave/payroll writers remain.

### Unresolved operator inputs

Actual production hostnames, hosting OS/runtime, database version/schema/data volume, cron entries, Redis/queue usage, provider ownership/plans/quotas, partner app implementations/keys/identity mappings, collector code/source connectivity, SMTP account ownership/DNS access, backup retention, RPO/RTO, privacy retention and monitoring destination cannot be established from this repository alone. Resolve them in a protected environment inventory before deployment; none requires guessing existing secret values.
