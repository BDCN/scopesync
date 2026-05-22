# ScopeSync — Project Instructions for Claude Code

## Project Overview

ScopeSync is a multi-tenant SaaS for construction submittal automation. The product reads spec sections and product cut sheets, extracts requirements using the Claude API, matches them against products, and assembles a branded submittal package PDF.

**Target customer:** subcontractors in commercial and industrial construction. Launch vertical: electrical (CSI Division 26). Future verticals: mechanical (23), plumbing (22), fire protection (21).

**Domain:** scopesync.app (or scopesynce.app — confirm with the user)
**Repo:** github.com/<user>/scopesync (create on first commit)

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
│   │   ├── Submittals.php
│   │   └── api/
│   │       └── Webhooks.php
│   ├── models/
│   │   ├── Tenant_model.php
│   │   ├── User_model.php
│   │   ├── Project_model.php
│   │   ├── Submittal_model.php
│   │   ├── Document_model.php
│   │   └── Extraction_model.php
│   ├── libraries/
│   │   ├── TenantContext.php        # current tenant from session
│   │   ├── ClaudeClient.php         # cURL wrapper for Anthropic API
│   │   ├── PromptLoader.php         # load + version prompts from prompts/
│   │   └── AuditLog.php
│   ├── helpers/
│   │   └── scopesync_helper.php
│   ├── views/
│   │   ├── layouts/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   └── submittals/
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
│   ├── worker.php                   # cron-driven extraction worker
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
  - `content-type: application/json`
- Default model for extraction: `claude-sonnet-4-6`
- Escalation model (complex specs): `claude-opus-4-7`
- PDF input via document content block:
  ```json
  {"type": "document", "source": {"type": "base64", "media_type": "application/pdf", "data": "<base64>"}}
  ```
- All prompts live in `prompts/` directory, versioned per file (filename includes `_vN`)
- Every API call logs an `extractions` row: model, prompt_version, input_tokens, output_tokens, raw_response, structured_data, status
- Token budget per extraction: 16k output tokens default; raise to 32k only for known-large specs

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
| 3 | Upload & Extraction | Not started |

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

### Dev seed data

To get a working dev login, uncomment the seed block at the bottom of `schema.sql` and run it via phpMyAdmin:

```
admin@acme-electric.test  /  changeme
```

Or run the SQL manually:
```sql
INSERT INTO `tenants` (`slug`,`name`,`plan`,`industry_default`)
VALUES ('acme-electric','Acme Electric','pro','electrical');

INSERT INTO `users` (`tenant_id`,`email`,`password_hash`,`name`,`role`)
VALUES (LAST_INSERT_ID(),'admin@acme-electric.test',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Acme Admin','owner');

INSERT INTO `tenant_settings` (`tenant_id`,`company_name`,`primary_color`)
VALUES (LAST_INSERT_ID()-1, 'Acme Electric Corp', '#1A73E8');
```

The password hash above is `changeme` — change it after first login or just register a new account via `/register`.

---

## Things Claude Code Should NEVER Do

- Run `composer install` or `composer require`
- Use `www-data` user/group anywhere
- Commit `.env`, `secrets.php`, DB dumps, or `storage/` contents
- Disable CSRF protection
- Trust `$_POST['tenant_id']` or `$_GET['tenant_id']`
- Write Claude API logic that "infers" missing values — extraction returns null when uncertain
- Modify `system/` CI3 core files
