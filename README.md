# ScopeSync

Multi-tenant SaaS for construction submittal automation. Reads spec sections and product cut sheets, extracts requirements via the Claude API, matches them against products, and assembles a branded submittal package PDF.

**Launch vertical:** electrical (CSI Division 26)
**Target customer:** subcontractors in commercial and industrial construction

---

## Repo contents

- `CLAUDE.md` — instruction file for Claude Code agent. Read first.
- `phases.md` — phased build plan (Weeks 1–3 detailed)
- `schema.sql` — full MariaDB schema, ready for phpMyAdmin or `mysql` CLI
- `prompts/` — versioned Claude API prompts (text/markdown, loaded at runtime)
- `migrations/` — numbered SQL migrations (start with `0001_initial_schema.sql`)
- `scripts/` — operational scripts (worker, deploy)

---

## First-time setup — local

Prerequisites:
- PHP 8.3 with extensions: mysqli, mbstring, curl, gd, json, xml
- MariaDB 10.6+
- phpMyAdmin (recommended for schema management)
- A working Anthropic API key (set as `ANTHROPIC_API_KEY` in `application/config/secrets.php`)
- **No composer required.** If you've installed it, that's fine — but don't use it for this project.

Steps:

1. **Clone the repo**
   ```bash
   git clone git@github.com:<your-user>/scopesync.git ~/scopesync
   cd ~/scopesync
   ```

2. **Download CodeIgniter 3**
   - Get the latest CI3 release zip from https://github.com/bcit-ci/CodeIgniter/releases
   - Extract `application/`, `system/`, `index.php`, and `.htaccess` into the repo root
   - Move `index.php` and `.htaccess` into `public/` (create the directory if it doesn't exist)
   - Edit `public/index.php` to point at the parent `application/` and `system/` paths

3. **Create the database**
   - Open phpMyAdmin
   - Click "New" in the left sidebar
   - Create database `scopesync` with collation `utf8mb4_unicode_ci`
   - Select `scopesync`, click "SQL" tab, paste contents of `schema.sql`, click Go
   - Verify 12 tables exist (tenants, users, ci_sessions, industries, tenant_settings, projects, divisions, submittal_jobs, documents, extractions, audit_log)
   - Verify 4 rows in `industries`

4. **Create secrets file**
   ```bash
   cp application/config/secrets.php.example application/config/secrets.php
   ```
   Edit `application/config/secrets.php` and fill in:
   - DB credentials
   - Encryption key (generate via `php -r "echo bin2hex(random_bytes(16));"`)
   - `ANTHROPIC_API_KEY`

5. **Start the dev server**
   ```bash
   php -S localhost:8080 -t public/
   ```
   Visit `http://localhost:8080`. You should see the CodeIgniter welcome page.

6. **Create a dev tenant + user** via phpMyAdmin
   - Uncomment the seed-data block at the bottom of `schema.sql` and re-run
   - Login at `http://localhost:8080/auth/login` with `admin@acme-electric.test` / `changeme`

---

## First-time setup — server (RHEL/Rocky/AlmaLinux)

```bash
# As root or sudo user
sudo dnf install -y httpd mod_ssl php php-mysqlnd php-mbstring php-xml php-curl php-json php-gd mariadb-server
sudo systemctl enable --now httpd mariadb
sudo mysql_secure_installation

# Create app directory
sudo mkdir -p /var/www/scopesync
sudo chown -R apache:apache /var/www/scopesync

# Clone repo
cd /var/www
sudo -u apache git clone git@github.com:<your-user>/scopesync.git
cd scopesync

# Create the database (or use phpMyAdmin if installed)
sudo mysql -e "CREATE DATABASE scopesync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'scopesync'@'localhost' IDENTIFIED BY '<strong-password>';"
sudo mysql -e "GRANT ALL PRIVILEGES ON scopesync.* TO 'scopesync'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql scopesync < schema.sql

# Create secrets.php (copy from example, fill in real values — do NOT commit)
sudo -u apache cp application/config/secrets.php.example application/config/secrets.php
sudo -u apache nano application/config/secrets.php

# Apache vhost
sudo nano /etc/httpd/conf.d/scopesync.conf
# ... (vhost config — see CLAUDE.md for template)
sudo systemctl reload httpd

# HTTPS via Let's Encrypt
sudo dnf install -y certbot python3-certbot-apache
sudo certbot --apache -d scopesync.app -d www.scopesync.app

# Worker cron (extraction worker runs every minute)
sudo crontab -u apache -e
# Add line:
# * * * * * /usr/bin/php /var/www/scopesync/scripts/worker.php >> /var/log/scopesync-worker.log 2>&1

# Ensure ownership
sudo chown -R apache:apache /var/www/scopesync
sudo chmod -R 750 /var/www/scopesync/storage
```

---

## Daily workflow

```bash
# 1. Pull latest before starting work
cd ~/scopesync
git pull origin main

# 2. Make changes locally, test at localhost:8080

# 3. Apply any schema changes
# Edit schema.sql in place AND add a new migration:
#   migrations/000N_what_changed.sql
# Apply locally via phpMyAdmin

# 4. Commit
git add -A
git commit -m "feat(projects): add archive action with audit log"
git push origin main

# 5. Deploy to server
./scripts/deploy.sh
# This runs:  ssh server "cd /var/www/scopesync && git pull && sudo chown -R apache:apache storage/"

# 6. If schema changed, apply on server
ssh user@server "mysql -u scopesync -p scopesync < /var/www/scopesync/migrations/000N_what_changed.sql"
```

---

## Working with Claude Code

This project includes a `CLAUDE.md` file in the repo root. Claude Code reads it automatically when you run commands from the project directory.

Common patterns:

```bash
# Add a new feature
claude "Implement Phase 2.4 — Project CRUD per phases.md. Follow the multi-tenant rules and audit log every state change."

# Debug an issue
claude "The extraction worker is stuck on submittal_job_id 42. Check the extractions table, worker log, and storage directory."

# Refactor
claude "Refactor the ClaudeClient library to add exponential backoff retry on 429 responses."
```

Claude Code will respect the constraints in `CLAUDE.md`: no composer, apache user (not www-data), git commit + push on every meaningful change, tenant isolation enforced.

---

## What's where

- **The current schema:** `schema.sql` (root)
- **Phased plan with weekly goals:** `phases.md`
- **Prompts being sent to Claude API:** `prompts/spec_section_extraction.md`, `prompts/cut_sheet_extraction.md`
- **Worker script (cron-driven):** `scripts/worker.php` (built in Phase 3)
- **Deploy script:** `scripts/deploy.sh` (built in Phase 1)
- **Code conventions and hard rules:** `CLAUDE.md`

---

## Status

- **Phase 1 — Foundation:** in progress
- **Phase 2 — Auth & Tenant Foundation:** not started
- **Phase 3 — Upload & Extraction:** not started
