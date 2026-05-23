# ScopeSync — Project Instructions for Claude Code

## Project Overview

ScopeSync is a multi-tenant SaaS for construction submittal automation. The product reads spec sections and product cut sheets, extracts requirements using the Claude API, matches them against products, and assembles a branded submittal package PDF.

**Target customer:** subcontractors in commercial and industrial construction. Launch vertical: electrical (CSI Division 26). Future verticals: mechanical (23), plumbing (22), fire protection (21).

**Domain:** scopesync.app
**Repo:** github.com/BDCN/scopesync

---

## Tech Stack — DO NOT DEVIATE WITHOUT EXPLICIT PERMISSION

- **Language:** PHP 8.3
- **Framework:** CodeIgniter 3.x (**NO COMPOSER** — see Hard Constraints)
- **Database:** MariaDB 10.6+
- **Web server:** Apache 2.4 on **Debian** (user/group is `apache:apache`)
- **PDF generation:** TCPDF (drop-in, no composer required)
- **AI provider:** Anthropic Claude API via direct cURL (no SDK install)
- **File storage:** Local filesystem under `storage/tenants/{tenant_id}/...`
- **Background jobs:** cron + DB-backed job polling (no Redis, no daemon)
- **Frontend:** server-rendered PHP views + vanilla JS + Bootstrap 5 (CDN)

---

## Hard Constraints (Non-Negotiable)

1. **NEVER use composer.** No `composer install`, no `composer require`, no `vendor/` directory managed by Composer. Any third-party library must be drop-in: download the release zip, extract into `application/third_party/<libname>/`, and load via CI3's `$this->load->library()` mechanism or a manual `require_once`.

2. **Apache user is `apache`, group is `apache`.** Server is Debian but Apache runs as a custom `apache` user (not the Debian default `www-data`). Every `chown`, `chmod`, systemd reference, vhost config, and permission instruction must use `apache:apache`. If you ever see `www-data` in generated code or docs, fix it before suggesting.

3. **Git workflow is mandatory.** Every meaningful change is committed to GitHub. GitHub Actions auto-deploys to the server on every push to `main` (`.github/workflows/deploy.yml`). Workflow per change:
   ```bash
   # local
   git add -A
   git commit -m "<conventional commit message>"
   git push origin main
   # GitHub Actions SSHes into 45.79.181.107 and runs git pull automatically
   ```
   GitHub Secrets required: `SSH_PRIVATE_KEY`, `SERVER_HOST`, `SERVER_USER`.

4. **Tenant isolation is non-negotiable.** Every query against multi-tenant tables MUST filter by `tenant_id`. Build it once via a `TenantContext` library and enforce at the model layer — never trust client input for `tenant_id`.

5. **Secrets never enter git.** Create `application/config/secrets.php` (gitignored). It holds `ANTHROPIC_API_KEY`, DB password, session encryption key. Reference via `config_item('anthropic_api_key')` after loading.

6. **No localStorage/sessionStorage in any client-side code** until the user explicitly opts in. Server-side sessions only.

---

## File Structure

```
~/scopesync/                         # local dev copy (also /var/www/scopesync on server)
├── .git/
├── .gitignore                       # ignores: storage/, application/config/secrets.php, logs/
├── CLAUDE.md                        # this file
├── README.md                        # human setup guide
├── phases.md                        # phased build plan
├── schema.sql                       # full schema as of today
├── application/
│   ├── config/
│   │   ├── config.php
│   │   ├── database.php
│   │   ├── routes.php
│   │   ├── autoload.php
│   │   └── secrets.php              # GITIGNORED — DB pw, Anthropic API key
│   ├── controllers/
│   │   ├── Auth.php
│   │   ├── Dashboard.php
│   │   ├── Projects.php
│   │   ├── Divisions.php
│   │   ├── Submittals.php
│   │   ├── Cron.php                     # CLI worker — invoked by scripts/worker.php
│   │   └── Admin.php                    # Extractions cost dashboard (owner/admin only)
│   ├── models/
│   │   ├── Tenant_model.php
│   │   ├── User_model.php
│   │   ├── Project_model.php
│   │   ├── Division_model.php
│   │   ├── Submittal_model.php
│   │   ├── Document_model.php
│   │   └── Extraction_model.php
│   ├── libraries/
│   │   ├── TenantContext.php        # current tenant from session
│   │   ├── ClaudeClient.php         # cURL wrapper for Anthropic API
│   │   ├── PromptLoader.php         # load + version prompts from prompts/
│   │   └── AuditLog.php             # audit_log writer (tenant/user from session or explicit)
│   ├── helpers/
│   │   └── scopesync_helper.php
│   ├── views/
│   │   ├── layouts/
│   │   │   └── main.php             # Bootstrap 5 CDN layout with top nav
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── projects/
│   │   ├── submittals/
│   │   └── admin/
│   └── third_party/
│       └── tcpdf/                   # drop-in TCPDF, no composer
├── system/                          # CodeIgniter 3 core — DO NOT MODIFY
├── public/                          # Apache DocumentRoot
│   ├── index.php                    # CI3 front controller
│   ├── .htaccess                    # URL rewrites + security headers
│   └── assets/
│       ├── css/
│       └── js/
├── storage/                         # GITIGNORED — uploaded + generated files
│   └── tenants/
│       └── {tenant_id}/
│           ├── projects/
│           │   └── {project_id}/
│           │       └── submittals/
│           │           └── {submittal_id}/
│           │               ├── input/
│           │               │   ├── spec/
│           │               │   └── cutsheets/
│           │               └── output/
│           ├── branding/            # tenant logo, etc
│           └── temp/
├── prompts/                         # versioned Claude prompts (text/markdown)
│   ├── spec_section_extraction.md
│   └── cut_sheet_extraction.md
├── migrations/                      # numbered SQL migrations
│   └── 0001_initial_schema.sql
├── scripts/
│   ├── worker.php                   # cron shim: CI_ENV=production php index.php cron process
│   ├── setup-cron.sh                # installs /etc/cron.d/scopesync-worker on server
│   └── deploy.sh
└── logs/                            # GITIGNORED — app logs
```

---

## Server Layout (Debian)

- App lives at: `/var/www/scopesync`
- Apache DocumentRoot: `/var/www/scopesync/public`
- Apache vhost config: `/etc/apache2/sites-available/scopesync.conf` (SSL: `scopesync-le-ssl.conf`)
- Apache logs: `/var/log/apache2/scopesync_*.log`
- Owner: `apache:apache` recursive on `/var/www/scopesync` (especially `storage/`)
- SSL: Let's Encrypt via certbot, auto-renewing. Cert at `/etc/letsencrypt/live/scopesync.app/`
- `CI_ENV=production` set via `SetEnv` in the SSL vhost config
- MariaDB DB name: `scopesync`
- MariaDB user: `scope_sync` (NOT root; least-privilege)
- PHP: `mod_php` on Apache2
- Apache package: `apache2` — use `a2ensite`, `a2enmod`, `systemctl reload apache2`

---

## Coding Conventions

- PHP files always open with `<?php` (no short tags)
- Class names: `PascalCase`. File names match class names EXACTLY (CI3 enforces this)
- Method names: `camelCase`
- DB tables: `snake_case`, plural (`tenants`, `users`, `submittal_jobs`)
- Always use prepared statements via CI3 query builder bindings or `$this->db->query($sql, $params)`
- Sanitize input via CI3 form validation library; second arg `TRUE` on `$this->input->post()` for XSS clean
- Passwords via `password_hash()` / `password_verify()` — never store plaintext
- Sessions: CI3 native, **database driver** (`sess_driver = 'database'`), table `ci_sessions`, cookie name `ss_session`
  - `sess_expiration = 7200`, `sess_regenerate_destroy = TRUE`, `sess_save_path = 'ci_sessions'`
  - Session is populated via `TenantContext::setFromUser()` after login/register — never write session data directly in controllers
- HTTPS-only cookies: `cookie_secure = TRUE`, `cookie_httponly = TRUE`, `cookie_samesite = 'Lax'`
- CSRF protection enabled globally
- Errors logged to `logs/`, never echoed to the user in production

---

## Multi-Tenant Rules

- Every multi-tenant table has `tenant_id INT UNSIGNED NOT NULL` with FK to `tenants(id)`
- Composite indexes start with `tenant_id` for query performance
- The `TenantContext` library loads on every request from the session; expose `TenantContext::id()`
- Model methods accept `tenant_id` implicitly via TenantContext — NEVER trust client-submitted `tenant_id`
- Cross-tenant queries are forbidden in application code (only admin scripts may bypass)

---

## Claude API Integration

- Endpoint: `https://api.anthropic.com/v1/messages`
- Required headers:
  - `x-api-key: <key>` (from secrets.php)
  - `anthropic-version: 2023-06-01`
  - `anthropic-beta: pdfs-2024-09-25` (required for PDF document blocks)
  - `content-type: application/json`
  - `Expect:` (empty — disables HTTP 100-continue, prevents large-payload stalls)
- Default model for extraction: `claude-sonnet-4-6`
- Escalation model (complex specs): `claude-opus-4-7`
- PDF input via document content block:
  ```json
  {"type": "document", "source": {"type": "base64", "media_type": "application/pdf", "data": "<base64>"}}
  ```
- All prompts live in `prompts/` directory, versioned per file (filename includes `_vN`)
- Every API call logs an `extractions` row: model, prompt_version, input_tokens, output_tokens, raw_response, structured_data, status
- Token budget per extraction: 16k output tokens default; raise to 32k only for known-large specs
- **Response format gotcha:** Claude wraps JSON output in ` ```json ` code fences even when the prompt says not to. `ClaudeClient::_parseSuccess()` strips fences before `json_decode()`, then belt-and-suspenders extracts from first `{` to last `}`. Never remove this stripping — it will silently cause all `structured_data` to be NULL.

---

## Definition of Done — every feature

1. Schema changes applied to local AND committed as `migrations/NNNN_description.sql`
2. Code committed with conventional commit message (e.g., `feat(submittals): add extraction worker`)
3. Pushed to GitHub
4. Pulled to server, file ownership reset to `apache:apache`
5. Manually smoke-tested on the server (browser or curl)
6. Audit log entries verified for any state-changing action

---

## Things Claude Code Should Ask Before Doing

- Any deployment to a new server (confirm hostname, SSH user)
- Any package install via `apt` (confirm vs check existing)
- Any schema migration that drops a column or table
- Any change to `system/` CI3 core (should be never — flag if proposed)
- Any new third-party library (must be drop-in, must justify why needed)

---

## Build Status

| Phase | Description | Status |
|---|---|---|
| 1 | Foundation — CI3 setup, schema, deploy pipeline | **Complete** |
| 2 | Auth & Tenant Foundation | **Complete** (2026-05-22) |
| 3 | Upload & Extraction | **Complete** (2026-05-22) |
| 4 | Matching Engine & Review Queue | **Complete** (2026-05-22) |
| 5 | PDF Assembly — submittal package generation via TCPDF | **Pending** |

### Phase 2 — what was built (2026-05-22)

- `application/core/MY_Controller.php` — base controller: `requireLogin()` auth gate, `loadView()` layout helper
- `application/libraries/TenantContext.php` — autoloaded; populates from session after login; exposes `id()`, `slug()`, `name()`, `userId()`, `userRole()`, `isLoggedIn()`, `setFromUser()`
- `application/libraries/AuditLog.php` — autoloaded; `log($entity_type, $action, $entity_id, $metadata, $tenant_id, $user_id)` — last two args override session values for pre-login auth events
- `Auth.php` — login, register (tenant+user+settings in one transaction, 14-day trial), logout, forgot password (HMAC token, invalidates on password change, 1-hour expiry), reset password
- `Projects.php` — index, create, view (with divisions + submittals), edit, archive; all tenant-filtered
- `Divisions.php` — create (duplicate code guard), delete
- `Submittals.php` — create, view (Phase 3 stub)
- Models: `User_model`, `Tenant_model`, `Project_model`, `Division_model`, `Submittal_model`
- Views: Bootstrap 5 CDN layout (`views/layouts/main.php`), auth forms, dashboard, project list/detail/edit, submittal detail
- Every state-changing action writes to `audit_log`

### Phase 3 — what was built (2026-05-22)

- `application/models/Document_model.php` — tenant-scoped document CRUD; `getByIdRaw()` (no tenant filter, for CLI worker); `findBySha256()` for dedup; `countThisMonth()` for plan limits
- `application/models/Extraction_model.php` — `getPending(limit)`, `claimById()` (atomic UPDATE + affected_rows), `markComplete()`, `markFailed()`, `recentForAdmin()`
- `application/libraries/PromptLoader.php` — loads `prompts/{name}.md`, strips YAML frontmatter, splits on `\n## SYSTEM` / `\n## USER` using `strpos()` (safe with nested `###` and JSON code blocks inside sections), in-memory cache per request
- `application/libraries/ClaudeClient.php` — cURL wrapper; `extract($promptName, $documentPath, $options)` base64-encodes PDF as `document` content block; 300s timeout; 2 retries on 429/503; **requires `CURLOPT_IPRESOLVE_V4` (IPv6 stalls on large POST bodies from this server)** and **`Expect:` empty header** (disables 100-continue for large payloads)
- `Submittals.php` — `upload()` XHR endpoint (finfo MIME check, SHA-256 dedup, plan limits, move_uploaded_file, creates documents + extractions rows); `view()` (documents list + extraction results cards); `rerun()` (re-queues extraction)
- `application/controllers/Cron.php` — CLI-only; `process()` claims up to 5 pending, calls ClaudeClient, writes audit_log with explicit tenant_id; `_syncSubmittalStatus()` updates submittal to extracting/review/failed
- `application/controllers/Admin.php` — `extractions()` (last 100 extractions with token costs, owner/admin only)
- `application/views/submittals/view.php` — drag-drop upload zone (vanilla JS XHR, sequential, CSRF refresh); documents table with status/confidence badges; extraction results cards (spec: products accordion with source citations; cut sheet: variants accordion)
- `application/views/admin/extractions.php` — token cost dashboard
- `scripts/worker.php` — thin shim: `CI_ENV=production php index.php cron process`
- `scripts/setup-cron.sh` — installs `/etc/cron.d/scopesync-worker`, fixes ownership, smoke-tests

**cURL lesson:** this server reaches `api.anthropic.com` via IPv6 only (for small requests), but IPv6 TCP stalls on large POST bodies (multi-MB base64 PDFs). Fix: `CURLOPT_IPRESOLVE_V4`. Also add empty `Expect:` header to disable HTTP 100-continue for large payloads.

### Phase 4 — what was built (2026-05-22)

- `migrations/0002_match_results.sql` — adds `match_results` table, `review_decisions` table, and `matching_status` column to `submittal_jobs`
- `application/libraries/MatchingEngine.php` — pure-computation library; `run($specExtraction, $cutsheetExtractions)` returns match-result arrays; numeric ±2% tolerance for `voltage_rating`; falls back to `common_attributes` when a variant lacks an attribute; handles `nema_configuration` top-level field on cut sheet variants
- `application/models/Match_result_model.php` — CRUD for `match_results`; `existsForSubmittal()` guard check
- `application/models/Review_decision_model.php` — CRUD for `review_decisions`; `save()` is an upsert; `allDecided()` and `hasRejections()` for status transitions; `mapByMatchResult()` for view lookup
- `Cron.php` — `_triggerMatching()` called from `_syncSubmittalStatus()` when status hits 'review'; atomic `matching_status` claim via `UPDATE … WHERE matching_status IS NULL` + `affected_rows` (prevents duplicate runs); resets claim to NULL if not enough completed extractions
- `Submittals.php` — three new methods: `compliance()` builds the attribute×catalog matrix, `review()` loads match results + decision map, `decide()` XHR POST endpoint validates and persists decisions; advances submittal to 'assembling' when all decided with no rejections
- Routes: `submittals/(:num)/compliance`, `submittals/(:num)/review`, `submittals/(:num)/decide`
- `application/views/submittals/compliance.php` — Bootstrap 5 color-coded compliance matrix grouped by product category; legend strip; spec value / product value tooltip on hover
- `application/views/submittals/review.php` — per-product accordion cards; Approve / Override / Reject XHR buttons; override requires notes field; all-decided banner; auto-reload after decision saved
- `application/views/submittals/view.php` — Compliance Matrix and Review Queue buttons appear in the status bar once `matching_status = 'complete'`; "Matching…" spinner badge while running
- `ClaudeClient.php` (bug fix) — `_parseSuccess()` strips ` ```json ` fences before `json_decode()`; also extracts from first `{` to last `}` as belt-and-suspenders. Root cause: Claude wraps its JSON in markdown code fences despite prompt instruction; this silently caused all `structured_data` to be NULL until fixed.
- `Cron.php` — added `rematch()` public CLI method; resets `matching_status=NULL` then re-runs `_triggerMatching()`. Usage: `CI_ENV=production php public/index.php cron rematch <submittal_id> <tenant_id>`. Required to recover submittals that completed extraction before Phase 4 was deployed.

---

### Matching Engine — Known Gotchas

**Attribute name consistency:** `MatchingEngine` matches spec `attribute` (lowercased) against cut sheet `name` (lowercased). Both prompts define the same canonical names (`voltage_rating`, `amperage`, `aic_rating`, `number_of_poles`, etc.). If Claude uses a different name in one extraction vs the other (e.g., `poles` vs `number_of_poles`), the attribute shows as `missing` rather than `fail`. Watch extraction output carefully after any prompt change.

**AIC unit consistency:** The engine compares `value` strings numerically and ignores `unit`. If the spec extraction produces `{"value": "10", "unit": "kA"}` and the cut sheet produces `{"value": "10000", "unit": "A"}`, they will NOT match (10 ≠ 10000). Write spec section documents and prompt guidance so both sides consistently use the same unit (kA or A) for interrupting capacity.

**`standard` attribute is always `missing`:** Cut sheet extraction puts standards in the top-level `applicable_standards` array, not in `variants[].attributes[]` or `common_attributes[]`. Any spec attribute named `standard` will therefore always return `missing` in match results. This is intentional — standards verification is a separate concern from attribute matching. Do not add `applicable_standards` to the variant attribute map without a deliberate decision to do so.

**`matching_status` claim reset:** If `_triggerMatching()` exits early (not enough completed extractions), it resets `matching_status` to NULL so a future worker run can retry. Consequence: do not interpret `matching_status=NULL` as "never ran" — it may mean "ran but could not proceed."

**Test spec section:** `test_spec_section.html` in the repo root is an HTML file covering 5 products (1-pole 120V 15A / 2-pole 240V 20A / 2-pole 240V 40A @22kA / 3-pole 480V 1200A / 3-pole 480V 250A bus plug). Open in Chrome/Edge → Ctrl+P → Save as PDF to generate a test PDF for uploading to ScopeSync. The 40A @22kA product is the most useful case — it differentiates BL-series (10kA, FAIL) from BLH-series (22kA, PASS).

---

### Dev seed data

The seed block at the bottom of `schema.sql` is live SQL (not commented out). Run the entire `schema.sql` file in phpMyAdmin with `scopesync` selected, or paste just the seed block.

Login: `admin@acme-electric.test` / `changeme`

Alternatively, register a new account via `/register` — that flow creates a fresh tenant automatically.

**To re-run the seed** (e.g. after a data wipe), the seed block includes cleanup DELETEs that run first. If you need to manually clean up, delete in this order — `users` must be deleted before `tenants` because `fk_users_tenant` is `ON DELETE RESTRICT` (intentional, prevents accidental cascade deletes in prod):

```sql
DELETE FROM users   WHERE email = 'admin@acme-electric.test';
DELETE FROM tenants WHERE slug  = 'acme-electric';
-- tenant_settings cleans itself up via ON DELETE CASCADE on fk_settings_tenant
```

> **phpMyAdmin tip:** run all queries with **scopesync** selected in the left sidebar, not `information_schema`. Error `#1109 - Unknown table '...' in information_schema` means the wrong database is active.

---

## Things Claude Code Should NEVER Do

- Run `composer install` or `composer require`
- Use `www-data` user/group anywhere
- Commit `.env`, `secrets.php`, DB dumps, or `storage/` contents
- Disable CSRF protection
- Trust `$_POST['tenant_id']` or `$_GET['tenant_id']`
- Write Claude API logic that "infers" missing values — extraction returns null when uncertain
- Modify `system/` CI3 core files
