# QualityCore — FMCG Quality Management, QMS & Continuous Improvement Platform

A multi-tenant SaaS platform for FMCG quality management, food safety, statistical process
control, CAPA/NCR workflow, supplier quality, audits, lean/continuous improvement and an
AI Quality Assistant — built in **Core PHP 8+ / MySQLi** with **no framework, no ORM, no PDO**.

This is a self-contained application. It does not touch or depend on any other code in this
repository.

## Architecture

Rather than one-off CRUD pages per feature, the platform is built around reusable **engines**
(see `includes/`):

| Engine | File | Responsibility |
|---|---|---|
| Tool Engine | `tool_engine.php` | Renders/saves any dynamic quality tool from config (`tools` + `tool_fields` + `tool_field_options`) |
| Threshold Engine | inside `tool_engine.php` / `calculations.php` | Detects out-of-spec / warning / critical readings |
| Issue Engine | `issue_engine.php` | Auto-creates quality issues from deviations; workflow state machine |
| CAPA/NCR Engine | `capa_engine.php` | NCR and CAPA lifecycle |
| Calculation Engine | `calculations.php` | OEE, Cp/Cpk/Pp/Ppk, RPN, control limits, AQL, weighted scores, safe formula evaluator |
| KPI Engine | `kpi_engine.php` | Defect rate, FPY, compliance, RAG status, weighted Company Quality Score |
| Notification Engine | `notifications.php` | Bell notifications |
| Email Engine | `email.php` | Dynamic `{{variable}}` templates, queue + delivery |
| AI Engine | `ai.php` | Company-scoped AI assistant with deterministic rule-based fallback |
| Reporting Engine | `reporting.php` | Shared report queries used by both the viewer and CSV export |
| Permission Engine | `permissions.php` | Role + company-scoped access control |
| Audit Trail | `audit.php` (+ `log_activity()` in `auth.php`) | Every create/update/delete/login is logged |

New quality tools (5 Whys, Fishbone, HACCP checklists, EMP, checkweigher, sensory, etc.) are
added through the **Dynamic Tool Builder** (Super Admin → Quality Tool Engine → Dynamic Tool
Builder) — no code changes required. 24 tools spanning the platform's tool library ship
pre-configured (see `database/tool_library.sql`).

## Folder Structure

```
fmcg-qms/
├── admin/            Super Admin panel
├── manager/           Company Manager panel
├── employee/           Employee panel (incl. Floor Mode)
├── includes/           Engines (see table above)
├── ajax/{admin,manager,employee,common}/   AJAX endpoints
├── api/v1/            Read-only Bearer-token API (ERP/MES/BI integration ready)
├── cron/             daily-deadline-check.php, kpi-recalc.php
├── database/           schema.sql, tool_library.sql, seed_demo.sql
├── assets/{css,js}       Design system + shared JS
├── uploads/           User-uploaded files (created at runtime)
├── index.php           Marketing landing page
├── login.php / logout.php
└── 404.php / 403.php / 500.php
```

## Requirements

- PHP 8.1+ with `mysqli`, `curl`, `fileinfo` extensions
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/Nginx) or `php -S` for local testing

## Setup

1. Create the database and load the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p fmcg_qms < database/tool_library.sql   # dynamic tool library (recommended)
   mysql -u root -p fmcg_qms < database/seed_demo.sql       # OPTIONAL: demo company + sample data
   ```
   Skip `seed_demo.sql` for a completely empty installation — `schema.sql` and
   `tool_library.sql` contain only reference/configuration data (subscription plans, the
   tool library, default email templates, default KPI weights, compliance frameworks and
   the Super Admin account), never business data.

2. Configure the database connection via environment variables (or edit the defaults
   directly in `includes/config.php`):
   ```
   QMS_DB_HOST=localhost
   QMS_DB_USER=your_user
   QMS_DB_PASS=your_password
   QMS_DB_NAME=fmcg_qms
   QMS_BASE_URL=/fmcg-qms      # sub-path the app is served from, or "" for root
   ```

3. Point your web server's document root at the `fmcg-qms/` folder, or run locally:
   ```bash
   php -S localhost:8000
   ```

4. Schedule the cron jobs (every 15–30 min for deadlines, daily for KPI snapshots):
   ```
   */15 * * * * php /path/to/fmcg-qms/cron/daily-deadline-check.php
   0    1 * * * php /path/to/fmcg-qms/cron/kpi-recalc.php
   ```

## Demo Login (after loading `seed_demo.sql`)

| Role | Email | Password |
|---|---|---|
| Super Admin | `superadmin@qualitycore.app` | `SuperAdmin@123` |
| Company Manager (Golden Harvest Foods) | `manager@goldenharvest.demo` | `Manager@123` |
| Employee — QC Inspector | `priya.rao@goldenharvest.demo` | `Employee@123` |
| Employee — Line Operator | `vikram.singh@goldenharvest.demo` | `Employee@123` |
| Employee — Food Safety Officer | `sunita.patil@goldenharvest.demo` | `Employee@123` |
| (all other demo employees use the same password) | `*.@goldenharvest.demo` | `Employee@123` |
| Second tenant (proves data isolation) | `manager@everfresh.demo` | `Manager@123` |

Without `seed_demo.sql`, only the Super Admin account exists — sign in and create your
first company from the Super Admin dashboard.

## AI Assistant

AI is disabled by default (Super Admin → AI Settings). Configure a provider, endpoint,
model and API key to enable live AI responses. With AI disabled, or if the provider is
unreachable, every AI-powered feature (form suggestions, root-cause assistance, dashboard
insights, chat) automatically falls back to deterministic, rule-based output computed from
each company's own real data — the assistant never goes silent, and no company's data is
ever sent to another company's context.

## Security Notes

- All queries use MySQLi prepared statements (`includes/db.php` — `db_exec()/db_all()/db_one()`
  auto-infer bind types).
- Every company-owned table carries `company_id`; every controller-level query and every
  detail page (`assert_company_owns()`) re-validates the record belongs to the logged-in
  company before rendering — verified against a live two-tenant dataset during development.
- CSRF tokens on every state-changing form/AJAX call, password hashing via `password_hash()`,
  session regeneration on login, rate-limited login attempts, and a full `activity_logs` audit
  trail (login/logout/create/update/delete/settings changes).
