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

New quality tools (5 Whys, Fishbone, FTA, HACCP checklists, EMP, checkweigher, sensory, APQP,
PPAP, QFD, DOE, VSM, SMED, Poka-Yoke, Kanban, TPM, BRCGS/SQF/ISO22000 checklists, etc.) are
added through the **Dynamic Tool Builder** (Super Admin → Quality Tool Engine → Dynamic Tool
Builder) — no code changes required. **43 tools** spanning the platform's tool library ship
pre-configured (see `database/tool_library.sql` and `database/tool_library_2.sql`).

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
├── database/           schema.sql, tool_library.sql, tool_library_2.sql, seed_demo.sql
├── assets/{css,js}       Design system + shared JS
├── uploads/           User-uploaded files (created at runtime)
├── index.php           Marketing landing page
├── login.php / logout.php
└── 404.php / 403.php / 500.php
```

## Requirements

- PHP 8.1+ with `mysqli`, `curl`, `fileinfo`, `openssl` extensions (`openssl` is needed for STARTTLS/SSL SMTP delivery)
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/Nginx) or `php -S` for local testing

## Setup

1. Create the database and load the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p fmcg_qms < database/tool_library.sql     # dynamic tool library batch 1 (recommended)
   mysql -u root -p fmcg_qms < database/tool_library_2.sql   # dynamic tool library batch 2 (recommended)
   mysql -u root -p fmcg_qms < database/seed_demo.sql        # OPTIONAL: demo companies + sample data
   ```
   Skip `seed_demo.sql` for a completely empty installation — `schema.sql` and the
   `tool_library*.sql` files contain only reference/configuration data (subscription plans,
   the tool library, default email templates, default KPI weights, compliance frameworks and
   the Super Admin account), never business data.

2. Set the database credentials via environment variables, or edit the defaults directly in
   `includes/config.php`:
   ```
   QMS_DB_HOST=localhost
   QMS_DB_USER=your_user
   QMS_DB_PASS=your_password
   QMS_DB_NAME=fmcg_qms
   ```
   **You do not need to set anything for the URL/path.** `BASE_URL` is auto-detected on every
   request by comparing the app's real folder to your server's document root — so uploading
   this folder (as-is, unzipped) to your site root, to `/beta`, to `/qms/beta`, or renaming the
   folder entirely all work identically with zero config: every asset link, form action and
   AJAX call resolves correctly wherever it lands. (`QMS_BASE_URL` env var still exists as a
   manual override for unusual hosting setups where auto-detection gets it wrong.)

3. Point your web server's document root at the `fmcg-qms/` folder (or the subfolder you
   uploaded it into, e.g. `public_html/beta`), or run locally:
   ```bash
   php -S localhost:8000
   ```
   On Apache/LiteSpeed hosting, the included `.htaccess` files block direct access to
   `database/*.sql`, `includes/`, `logs/`, `cron/` and PHP execution inside `uploads/` — verify
   they're honored (`AllowOverride All`) or, on Nginx, add the equivalent `location` deny rules.

4. In **Super Admin → Platform Settings**, set your **Site URL** (e.g.
   `https://www.beglet.com/beta`) — this is used for links inside emails and any cron-generated
   link, where the domain can't be auto-detected from a browser request. Everything you browse
   to resolves its own links automatically without this.

5. Schedule the cron jobs (every 15–30 min for deadlines, daily for KPI snapshots):
   ```
   */15 * * * * php /path/to/fmcg-qms/cron/daily-deadline-check.php
   0    1 * * * php /path/to/fmcg-qms/cron/kpi-recalc.php
   ```

## Clean URLs (no `.php`)

Every link, form, redirect and AJAX call the app generates uses a clean, extensionless URL
(`/manager/dashboard` rather than `/manager/dashboard.php`) — `base_url()` strips `.php` when
building any URL, and the `.htaccess` rewrite rule maps the clean URL back to the real `.php`
file on the server side. This is on by default on Apache/LiteSpeed with `AllowOverride All`
(the default on most shared hosting); an Nginx equivalent is included as a comment at the
bottom of `.htaccess` for hosts that don't read it. Requesting a `.php` URL directly still
works too (nothing is broken or redirected away from it), so there's no risk from old links.

## Branding

Everything is changeable from **Super Admin → Platform Settings** with no code or redeploy:
platform name, logo (shown in the sidebar, login page and landing page), favicon, and primary
brand color (applied via a CSS custom-property override across every screen). Changes apply
immediately to every company on the platform.

## Email Delivery

Configure real SMTP delivery in **Super Admin → Email Settings** (host, port, encryption,
username/password, from name/email) — the platform includes its own pure-PHP SMTP client
(`includes/smtp.php`, no external library) supporting STARTTLS, implicit SSL and AUTH LOGIN,
which is what actually gets email delivered on shared hosting where PHP's `mail()` is often
disabled, unauthenticated, or silently dropped. Use the **Send Test Email** button on that page
to confirm delivery before relying on it; recent send attempts and their status are logged
right below it. If no SMTP host is configured, the platform falls back to `mail()`.

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
ever sent to another company's context. The assistant is available to both managers
(Insights → AI Assistant) and employees (Insights → AI Assistant), each scoped to that
user's own company data.

## Analytics (Power BI-style dashboard)

Manager → Insights → Analytics gives a filterable (7/30/90-day) drill-down view on top of
the main Dashboard: a KPI scorecard with RAG status and trend-vs-prior-period deltas, a
multi-line quality-events trend chart, issue severity donut, a department quality
comparison chart (click a bar to jump straight into that department's filtered issue
list), a Pareto chart with a cumulative-percentage line, and supplier/OEE comparison
charts.

## Department Data Sharing (read-only)

Manager → Settings → Department Data Sharing lets a manager grant one department
read-only visibility into another department's quality data (e.g. Supply Chain can view
Production's numbers without being able to edit anything). Shared departments appear
under the employee's Insights → Department Data page, which contains no editable forms —
access is enforced server-side (`includes/data_sharing.php`) and is fully revocable from
the same Settings tab.

## Root Cause Analysis

Manager → Quality Operations → Root Cause Analysis renders 5 Whys, Fishbone/Ishikawa
(as an actual server-generated SVG cause-and-effect diagram, not just raw form fields),
Fault Tree Analysis, 8D and A3 submissions as structured, readable analyses. Employees
can review everything they personally submitted, across every tool, under My Work →
My Submissions.

## Security Notes

- All queries use MySQLi prepared statements (`includes/db.php` — `db_exec()/db_all()/db_one()`
  auto-infer bind types).
- Every company-owned table carries `company_id`; every controller-level query and every
  detail page (`assert_company_owns()`) re-validates the record belongs to the logged-in
  company before rendering — verified against a live two-tenant dataset during development.
- CSRF tokens on every state-changing form/AJAX call, password hashing via `password_hash()`,
  session regeneration on login, rate-limited login attempts, and a full `activity_logs` audit
  trail (login/logout/create/update/delete/settings changes).
