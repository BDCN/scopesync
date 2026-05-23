# ScopeSync — Phase 5: PDF Submittal Package Assembly

## Project context

ScopeSync is a multi-tenant SaaS for construction submittal automation (PHP 8.3, CodeIgniter 3, MariaDB, Apache/Debian). Read `CLAUDE.md` completely before writing any code — it contains non-negotiable hard constraints (no Composer, apache:apache user, git-only deploys, tenant isolation on every query, etc.).

**Phases 1–4 are complete and deployed.** The full flow works end-to-end:
1. User uploads a spec section PDF and one or more manufacturer cut sheet PDFs to a submittal job.
2. A background worker (cron + `Cron.php`) sends each PDF to the Claude API and stores structured JSON in `extractions.structured_data`.
3. `MatchingEngine.php` compares spec requirements against cut sheet attributes and writes rows to `match_results`.
4. The user reviews each match result in the Review Queue (`submittals/{id}/review`) and records Approve / Override / Reject decisions.
5. When all decisions are recorded with no rejections, `Submittals::decide()` advances the submittal to **`status = 'assembling'`**.

**Phase 5 goal:** when a submittal reaches `status = 'assembling'`, the user can click a button to generate and download a complete branded submittal package PDF. This is the primary deliverable of ScopeSync — everything built so far leads here.

---

## Current state of the submittal at 'assembling'

All of the following are available in the database for a submittal that has reached 'assembling':

| Source | What it contains |
|---|---|
| `submittal_jobs` row | name, spec_section, status, matching_status, tenant_id, project_id |
| `documents` rows | storage_path to each uploaded PDF (spec + cut sheets), document_type |
| `extractions` rows | structured_data JSON (spec requirements, cut sheet attributes), model_used, confidence |
| `match_results` rows | per-variant match outcome (overall_result, attribute_results JSON, listing_results JSON) |
| `review_decisions` rows | decision (approved/overridden/rejected), override_notes, decided_at |
| `tenant_settings` | primary_color, company_name, logo_path (branding for cover page) |
| `projects` | name, division (for page headers) |

---

## Phase 5 — what to build

### 5.1 TCPDF installation

TCPDF must be a drop-in (no Composer). Download the latest stable release zip from the TCPDF GitHub releases page, extract to `application/third_party/tcpdf/`. Load in PHP via `require_once APPPATH . 'third_party/tcpdf/tcpdf.php'`. Do not use an autoloader. Verify it loads without errors before writing PDF generation code.

### 5.2 SubmittalAssembler library

Create `application/libraries/SubmittalAssembler.php`. Its job is to accept a submittal ID + tenant ID, gather all data, and write a PDF to `storage/tenants/{tenant_id}/projects/{project_id}/submittals/{submittal_id}/output/package.pdf`.

**PDF structure:**
1. **Cover page** — tenant company name (from `tenant_settings.company_name`), tenant logo if present (`tenant_settings.logo_path`), submittal job name, project name, spec section number, date generated, ScopeSync watermark in footer.
2. **Table of contents** — one line per product reviewed (catalog number, product category, decision: Approved / Approved with Override / Rejected).
3. **Per-product sections** (one section per `match_results` row that was approved or overridden; skip rejected):
   - Section header: catalog number + product category + overall result badge text
   - Attribute comparison table: spec requirement vs product value, result (pass/fail/missing)
   - Listing results table
   - If decision = 'overridden': show override notes in a clearly labelled box
   - Source citation: which cut sheet document provided this data
4. **Appendix: Rejected products** — list any catalog numbers the user rejected, with their rejection notes. Required for the submittal record even if excluded from the package.

**Branding:**
- Use `tenant_settings.primary_color` (hex) for section headers and table header backgrounds.
- If `tenant_settings.logo_path` is set and the file exists under `storage/tenants/{tid}/branding/`, embed it on the cover page (TCPDF supports PNG/JPEG via `Image()`).
- Default to ScopeSync brand teal (`#0d9488`) if no tenant color is set.

**Output path:** `storage/tenants/{tenant_id}/projects/{project_id}/submittals/{submittal_id}/output/package.pdf`. Create the `output/` directory with `mkdir($path, 0755, TRUE)` if it does not exist.

### 5.3 Controller method

Add `Submittals::assemble($id)` and `Submittals::download($id)` to `application/controllers/Submittals.php`.

- `assemble($id)` — POST; validates submittal belongs to current tenant and status = 'assembling'; calls `SubmittalAssembler::build($submittalId, $tenantId)`; on success updates `submittal_jobs.status = 'complete'` and stores the output path; redirects back to `submittals/{id}` with a flash message.
- `download($id)` — GET; validates ownership; streams `package.pdf` to browser with headers:
  ```
  Content-Type: application/pdf
  Content-Disposition: attachment; filename="submittal-{id}.pdf"
  ```
  Use `readfile()` — do not load the entire file into memory with `file_get_contents()`.

### 5.4 Schema migration

Create `migrations/0003_submittal_output.sql`. Add to `submittal_jobs`:

```sql
ALTER TABLE submittal_jobs
  ADD COLUMN output_path   VARCHAR(500)  NULL AFTER matching_status,
  ADD COLUMN assembled_at  DATETIME      NULL AFTER output_path;
```

Update `Submittal_model` accordingly.

### 5.5 View updates

In `application/views/submittals/view.php`, when `status = 'assembling'`:
- Show a prominent **"Generate Submittal Package"** button (POST to `submittals/{id}/assemble`).
- After `status = 'complete'`, replace with a **"Download PDF"** link (GET to `submittals/{id}/download`).
- While assembling is in progress (if you make it async later) show a spinner; for Phase 5 keep it synchronous.

### 5.6 Routes

Add to `application/config/routes.php`:
```
$route['submittals/(:num)/assemble'] = 'submittals/assemble/$1';
$route['submittals/(:num)/download'] = 'submittals/download/$1';
```

---

## Key files to read before writing any code

Read these in order to understand the data models and existing patterns:

1. `CLAUDE.md` — full project constraints and history
2. `application/controllers/Submittals.php` — existing methods (`compliance`, `review`, `decide`) show the data-loading pattern; `download` follows the same tenant-guard pattern
3. `application/models/Match_result_model.php` — `getBySubmittal($submittalId, $tenantId)` returns match rows; `attribute_results` is stored as a JSON string containing `{attribute_results, listing_results, unmatched_spec_attributes}`
4. `application/models/Review_decision_model.php` — `getBySubmittal()`, `mapByMatchResult()`; decision values are `approved`, `overridden`, `rejected`
5. `application/models/Submittal_model.php` — `update($id, $tenantId, $data)` for writing output_path and status
6. `schema.sql` — `tenant_settings` table columns (primary_color, logo_path, company_name)
7. `migrations/0002_match_results.sql` — exact column names in match_results and review_decisions

---

## Constraints to keep in mind

- **No Composer.** TCPDF must be manually extracted to `application/third_party/tcpdf/`.
- **apache:apache.** The `output/` directory and generated PDF must be writable/owned by `apache:apache` on the server. The mkdir call in SubmittalAssembler handles this locally; after deploy run `chown -R apache:apache storage/`.
- **Tenant isolation.** Every DB query uses `tenant_id`. The output file path is under `storage/tenants/{tenant_id}/...` — the tenant ID in the path IS the isolation boundary for files.
- **Memory.** TCPDF renders PDFs in memory. For large submittals (many cut sheets), set `memory_limit = 512M` in php.ini if not already set. Do not embed the original uploaded PDFs as full-page images — only use extracted text/data.
- **Git workflow.** Commit and push every meaningful change. GitHub Actions auto-deploys to `45.79.181.107`. After deploy, `chown -R apache:apache /var/www/scopesync/storage`.
- **Audit log.** Write an audit log entry when `assemble()` completes: `entity_type='submittal'`, `action='package_generated'`, `entity_id=$submittalId`, include `output_path` in metadata.

---

## Definition of done for Phase 5

1. `migrations/0003_submittal_output.sql` applied locally and committed.
2. TCPDF extracted to `application/third_party/tcpdf/`, added to `.gitignore` if too large (or committed if small enough).
3. `SubmittalAssembler` generates a valid PDF with cover, TOC, per-product sections, and rejected appendix.
4. `Submittals::assemble()` and `Submittals::download()` work end-to-end on the server.
5. `submittals/view.php` shows the Generate/Download buttons at the right status points.
6. Committed and pushed; smoke-tested on `scopesync.app` by completing a submittal review and downloading the package PDF.
7. Audit log entry written on package generation.
8. `CLAUDE.md` updated with Phase 5 complete section.
