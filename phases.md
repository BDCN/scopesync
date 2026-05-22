# ScopeSync — Phased Build Plan (Weeks 1–3)

The foundation phase. By the end of Week 3 we have a multi-tenant app that authenticates users, manages projects and submittal jobs, accepts PDF uploads, and runs Claude API extraction in the background.

---

## Phase 1 — Foundation (Week 1)

**Goal:** working CodeIgniter 3 application running locally and on the production server with the database schema in place. No business logic yet — just plumbing.

### Tasks

**1.1 Repository & local environment**
- Create GitHub repo `scopesync` (private)
- Clone locally to `~/scopesync`
- Commit `CLAUDE.md`, `README.md`, `phases.md`, `schema.sql`
- Add `.gitignore` covering: `storage/`, `application/config/secrets.php`, `logs/`, `*.log`, `.DS_Store`

**1.2 CodeIgniter 3 setup**
- Download CI3 latest release zip, extract into repo (do NOT use composer)
- Configure `application/config/config.php`: base_url, csrf_protection=TRUE, encryption_key
- Configure `application/config/database.php` (pull credentials from secrets.php)
- Configure `application/config/routes.php`: home route → Dashboard, auth routes
- Create `application/config/secrets.php` locally (gitignored), template committed as `secrets.php.example`
- Verify "Welcome to CodeIgniter" page renders at `http://localhost:8080`

**1.3 Database**
- Apply `schema.sql` to local MariaDB via phpMyAdmin (SQL tab → paste → Go)
- Verify all tables exist: `tenants`, `users`, `user_sessions`, `industries`, `tenant_settings`, `projects`, `divisions`, `submittal_jobs`, `documents`, `extractions`, `audit_log`, `ci_sessions`
- Verify seed data: 4 rows in `industries`
- Create a dev tenant + admin user via phpMyAdmin INSERT for testing

**1.4 Server provisioning**
- DNS: point `scopesync.app` (or chosen domain) at server IP
- Install on RHEL/Rocky/AlmaLinux:
  ```bash
  sudo dnf install -y httpd mod_ssl php php-mysqlnd php-mbstring php-xml php-curl php-json php-gd mariadb-server
  sudo systemctl enable --now httpd mariadb
  sudo mysql_secure_installation
  ```
- Create MariaDB db + user: `scopesync` (database), `scopesync` (user), strong password stored in secrets.php
- Apply schema via `mysql -u scopesync -p scopesync < schema.sql` OR via phpMyAdmin if installed
- Apache vhost at `/etc/httpd/conf.d/scopesync.conf`, DocumentRoot `/var/www/scopesync/public`
- Issue Let's Encrypt cert via certbot
- `sudo chown -R apache:apache /var/www/scopesync`
- Set up SSH key-based deployment

**1.5 Deployment workflow**
- Script `scripts/deploy.sh` (run from local): commits, pushes, then SSHs and pulls + fixes ownership
- Verify: change a string in a view locally, deploy, see change live

**Deliverable:** CodeIgniter 3 welcome page at `https://scopesync.app/` with HTTPS, schema applied, deploy pipeline working.

---

## Phase 2 — Auth & Tenant Foundation (Week 2)

**Goal:** users can sign up (creating a new tenant), log in, see a tenant-isolated dashboard, and create projects and submittal jobs (without uploading files yet).

### Tasks

**2.1 TenantContext library**
- `application/libraries/TenantContext.php`
- Methods: `id()`, `slug()`, `name()`, `userId()`, `userRole()`, `loadFromSession()`
- Loaded via `autoload.php` so every controller has access

**2.2 Auth controller**
- `Auth::login`, `Auth::register`, `Auth::logout`, `Auth::forgotPassword`, `Auth::resetPassword`
- Register flow creates: new `tenants` row + first `users` row with role='owner' in one transaction
- Login validates against `users.password_hash`, creates session via CI3 DB sessions
- Password reset uses signed tokens with 1-hour expiry (no separate table; use signed JWT or HMAC)
- All auth actions write to `audit_log`

**2.3 Dashboard**
- `Dashboard::index` — list projects for current tenant
- Top nav: tenant name (from TenantContext), user name, logout
- Empty state: "Create your first project"

**2.4 Project CRUD**
- `Projects::create`, `Projects::index`, `Projects::view($id)`, `Projects::edit($id)`, `Projects::archive($id)`
- Every query filters by `tenant_id = TenantContext::id()`
- Form validation via CI3 form_validation library
- Audit log on create / edit / archive

**2.5 Division & Submittal CRUD**
- Divisions are nested under projects (CSI MasterFormat codes like "26", "23", "22")
- Submittal jobs are nested under divisions
- Both follow the same tenant-filtered pattern
- Submittal job initial status: `draft`

**2.6 Layout & styling**
- Bootstrap 5 via CDN — single layout template `application/views/layouts/main.php`
- Brand colors from tenant_settings (default: ScopeSync brand teal)
- Responsive nav, breadcrumbs, flash messages

**Deliverable:** user can sign up, log in, create a project, add divisions, add submittal jobs. Every action is tenant-isolated and audit-logged.

---

## Phase 3 — Upload & Extraction (Week 3)

**Goal:** users can upload spec sections and cut sheets to a submittal job, the system runs Claude API extraction in the background, and structured results are displayed.

### Tasks

**3.1 File upload UI**
- `Submittals::upload($submittal_id)` — drag-and-drop UI (vanilla JS, no React)
- Accept multiple files, max 50MB each, PDF only
- Categorize each upload: spec section vs cut sheet (user picks per file)
- Show upload progress (XHR with progress events)
- On submit: save to `storage/tenants/{tid}/projects/{pid}/submittals/{sid}/input/spec|cutsheets/{document_id}.pdf`
- Create `documents` row + `extractions` row with `status='pending'`

**3.2 File validation**
- Check MIME via `finfo_file()`, NOT just extension
- Reject anything not `application/pdf`
- Page count via TCPDF or pdfinfo binary (whichever drops in cleanly)
- SHA-256 hash stored on documents row for dedup checks
- Reject upload if file exceeds tenant plan limits (Starter: 25/month, Pro: 100/month, Team: 400/month)

**3.3 ClaudeClient library**
- `application/libraries/ClaudeClient.php` — cURL wrapper
- Method: `extract($promptName, $documentPath, $options = [])`
- Loads prompt from `prompts/{$promptName}.md` via PromptLoader
- Base64-encodes PDF, sends as `document` content block
- Returns: `['status', 'model', 'input_tokens', 'output_tokens', 'raw_response', 'structured_data']`
- 60-second timeout, 2 retries with exponential backoff on 429/503

**3.4 PromptLoader library**
- Reads prompt markdown file, splits into SYSTEM + USER sections (delimited by `## SYSTEM` / `## USER`)
- Returns version (parsed from frontmatter or filename) for logging
- Caches in-memory per request

**3.5 Worker script**
- `scripts/worker.php` — runs on cron every minute
- Selects up to 5 `extractions` rows where `status='pending'`, oldest first
- Acquires a lock via `UPDATE ... WHERE status='pending' AND id=? ` (returns affected_rows)
- Calls ClaudeClient, saves results, updates status to `completed` or `failed`
- Logs to `logs/worker.log`
- Cron entry on server: `* * * * * apache /usr/bin/php /var/www/scopesync/scripts/worker.php >> /var/log/scopesync-worker.log 2>&1`

**3.6 Results view**
- `Submittals::view($id)` shows: documents uploaded, extraction status per doc, structured data once complete
- Render structured_data JSON as a table (key/value with source page citations)
- Show confidence indicator: GREEN (high), YELLOW (medium), RED (low)
- "Re-run extraction" button if a result looks wrong

**3.7 Audit log + observability**
- Every upload, every extraction start, every extraction complete writes to `audit_log`
- Simple admin page (`Admin::extractions`) listing recent extractions with token costs

**Deliverable:** end-to-end happy path. User uploads a spec PDF + 3 cut sheets, the worker extracts within ~2 minutes, structured requirements and product attributes appear in the UI with source page citations.

---

## What's intentionally NOT in Weeks 1–3

- Matching engine (deterministic spec ↔ product comparison) — Phase 4
- Compliance matrix generation — Phase 4
- Review queue UI with approve/reject — Phase 4
- PDF assembly with TCPDF (cover, TOC, bookmarks) — Phase 5
- Stripe billing, plan limits enforcement — Phase 6
- Multi-vertical templates (mechanical, plumbing) — Phase 7
- Procore / ACC integrations — Phase 8+

---

## Risk watch items during Weeks 1–3

- **PHP memory limit on large PDF base64:** a 25MB PDF base64-encoded is ~34MB. Set `memory_limit=512M` and `post_max_size=64M` in php.ini.
- **Apache upload size:** `LimitRequestBody` in vhost should match.
- **Claude API rate limits:** worker should respect 429s with backoff, never parallel-call beyond your tier.
- **File permissions:** every server deploy must `chown -R apache:apache storage/` or uploads silently fail.
- **CSRF on multipart uploads:** CI3 CSRF token must be passed as form field; AJAX uploads need explicit token in FormData.
