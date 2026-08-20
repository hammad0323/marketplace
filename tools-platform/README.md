# ToolStack — Online Tools Platform

Core PHP 8+ / MySQLi / Bootstrap 5 / jQuery / AJAX. No framework, no ORM,
no MVC layer — procedural PHP with a reusable **Tool Engine** and a
database-driven **Admin CMS**. Lives in its own `tools-platform/` folder
so it never collides with the marketplace project also in this repo.

## What this is

A scalable SaaS-style tools platform (calculators, converters,
generators, developer tools) architected so it can grow from the 55
seeded tools to 1,000+ **without touching the architecture** — new
tools are metadata rows in the database plus one small PHP file for
the calculation UI, added entirely from the Admin Panel.

Ships with:
- 12 categories, 55 fully working tools (real formulas, real content —
  see `database/seed-data.php`), spanning corporate/office, supply
  chain, finance, audit/BA, developer, design, video, student,
  construction, carpenter, logistics and universal daily tools.
- A complete Admin Panel: dashboard with Chart.js analytics, category
  CRUD, a full Tool Builder (Basic/Content/SEO/Related/Display tabs,
  FAQ/example/formula repeaters, live SEO score), Pages CMS (CKEditor),
  Blog CMS, redirect manager, media library, global settings.
- An SEO engine: per-entity SEO fields, live 0–100 SEO score with a
  checklist, schema.org JSON-LD (WebApplication/SoftwareApplication/
  FAQPage/HowTo/BreadcrumbList/WebSite/Organization), dynamic sitemap.xml
  and robots.txt generation, automatic 301 redirects on slug changes.
- Literal SEO-friendly `.php` URLs for every tool/category/page (no
  `?id=`) — the Admin Panel writes/removes these thin route files for
  you; see "How routing works" below.
- Dark/light/system theme (localStorage), parallax hero + scroll-reveal
  animations that respect `prefers-reduced-motion`, AJAX search overlay.

## Deploy in 3 steps

1. **Upload** the `tools-platform/` folder to your document root (or an
   addon domain folder).
2. **Edit `includes/config.php`** (or set the equivalent environment
   variables) with your real DB credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   Also set `TOOLS_PLATFORM_URL` to your real site URL (e.g.
   `https://tools.example.com`) — this feeds every canonical URL, OG
   tag, and the sitemap.
3. **Import the schema, then seed it**:
   ```bash
   mysql -u youruser -p your_database < database/schema.sql
   php database/seed.php
   ```
   `seed.php` creates the super-admin account, prints its password once
   in the terminal, and is safe to re-run (it skips anything that
   already exists by slug).

Log in at `/admin/login.php` and **change the seeded admin password
immediately** (Admin → Settings has no self password-change screen yet
— update the `admins.password_hash` column directly, the same
`password_hash()` approach documented in the marketplace project's
README, until a dedicated screen is added).

## How routing works (no `?id=`, no router file)

Every tool/category/page is a real, tiny file at the project root:

```php
<?php
$toolSlug = 'salary-calculator';
require __DIR__ . '/includes/tool-page.php';
```

The Admin Panel writes this file automatically when you save a tool
(`tp_write_tool_route()` in `includes/functions.php`), and deletes/
recreates it (plus a 301 redirect row) if the slug changes. `tool-page.php`,
`category-page.php`, `static-page.php` and `blog-post.php` are the four
generic renderers — they pull all breadcrumb/SEO/schema/FAQ/related-tools
content from the database, so the thin file never needs to change.

## Adding tool #56 (and #500)

1. Write the calculator's input fields + client-side JS in one file
   under `tools/<category-folder>/<slug>.php` (see any existing file
   for the pattern — it only needs to call the shared
   `tpValidateNumber()` / `tpShowResult()` / `tpShowError()` helpers
   from `assets/js/tp-calculator.js`).
2. In Admin → Tools → Add New Tool, fill in the metadata, pick that
   file from the "Calculator Logic File" dropdown, add content/FAQ/SEO,
   save.
3. That's it — the route file, sitemap entry (next regeneration) and
   related-tools links are all handled by the engine.

No PHP file is ever generated *from* admin input — that would mean
executing arbitrary code entered through a form, which this project
deliberately does not do. The calculation logic is always a real file
a developer wrote; the database only ever holds content and metadata.

## Currency Converter — a note on honesty

The Currency Converter tool reads rates from the `currency_rates`
table and clearly displays "rates last updated: …". Until an admin
configures a real currency API under Settings → Currency API (or seeds
`currency_rates` manually), the tool shows an explicit "no rates
configured yet" message instead of fabricating numbers. It will never
claim real-time rates it can't back up.

## What was verified during this build

This isn't a paper design — it was run end-to-end against a real
MariaDB 10.11 instance and PHP 8.4's built-in server:
`schema.sql` imports cleanly, `seed.php` creates the admin account,
12 categories, 55 tools with content/FAQs/examples/SEO/related-tools
and their route files, all without a single PHP warning/notice. The
homepage, a tool page (with its FAQ accordion, related tools rail and
JSON-LD schema), a category page, global search (page + AJAX API),
static pages, the sitemap/robots endpoints, the full admin login flow,
the admin dashboard, the tools list, the tool builder form, and the
live AJAX SEO-score endpoint were all fetched and checked for correct
output and view-tracking behavior.

## What's intentionally out of scope for this initial build

Being upfront about this rather than silently shipping thin versions:

- **Real image processing** (compressor/resizer/cropper tools from the
  spec) — these need a server-side image library (GD/Imagick) wired up
  per tool; the Media Library's upload validation (real MIME sniffing,
  size caps) is in place and ready for that work.
- **Tool versioning/content-history and CSV import** — export to CSV
  is implemented (Admin → Tools → Export CSV); re-import and version
  history are not.
- **Ad placement manager and Meta Pixel field** — the settings schema
  (`site_settings`) can hold these; only the specific UI fields weren't
  added to keep the Settings page focused.
- **A dedicated admin "change my password" screen** — reset-via-email
  flow exists (`forgot-password.php` / `reset-password.php`, token
  logged server-side since there's no mail sender configured yet).

None of these affect the core claim: the architecture — Tool Engine +
SEO engine + Admin CMS + literal `.php` routing — scales from 55 tools
to 1,000+ by adding rows and small logic files, never by restructuring
the app.
