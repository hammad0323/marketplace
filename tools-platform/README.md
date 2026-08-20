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
- **Clean URLs with no `.php` anywhere** (`/salary-calculator`, not
  `/salary-calculator.php`, and no `?id=`) — handled entirely by
  `.htaccess`; see "How routing works" below.
- **Deploys correctly at the domain root OR a subfolder** (e.g.
  `www.example.com/tools/`) **with zero configuration** — the base path
  is auto-detected on every request by diffing this project's real
  folder against the server's document root, so CSS/JS/links never
  break after upload just because the folder moved. See "Subfolder
  deployment" below.
- Dark/light/system theme (localStorage), parallax hero + scroll-reveal
  animations that respect `prefers-reduced-motion`, AJAX search overlay.

## Deploy in 3 steps

1. **Upload** the `tools-platform/` folder — as-is, folder name doesn't
   matter — to your document root, an addon domain, or a subfolder like
   `public_html/tools/`. Nothing needs editing for the URL to work; see
   "Subfolder deployment" below only if something looks off.
2. **Edit `includes/config.php`** with your real DB credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
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

Then, once the site is reachable at its real URL, go to **Admin →
Settings → SEO & Sitemap → "Regenerate Sitemap Now"** — the placeholder
`sitemap.xml`/`robots.txt` shipped in this zip need to be regenerated
once so they contain your real domain.

## Subfolder deployment (e.g. `www.example.com/tools/`)

`includes/config.php` auto-detects the base path by comparing this
project's real filesystem location against `$_SERVER['DOCUMENT_ROOT']`
— no manual edit, no env var, no hardcoded domain. Every internal link,
asset tag, canonical URL, and the sitemap all read from that one
detected value, so CSS/JS/tool links keep working no matter which
folder you upload into or rename it to.

If your host ever reports `DOCUMENT_ROOT` in a way that breaks
detection (rare — some unusual proxy/CDN setups), force it manually by
uncommenting one line near the top of `includes/config.php`:
```php
// define('TOOLS_PLATFORM_URL', '/tools'); // <- manual override, else auto-detected
```

**One real limitation to know about:** `robots.txt` and `sitemap.xml`
only matter to search engines when served from the actual domain root
(`https://www.example.com/robots.txt`). A copy living at
`.../tools/robots.txt` is not the authoritative one — search engines
won't fetch it there. If you deploy into a subfolder and want the
`Disallow` rules or sitemap picked up, either merge them into whatever
`robots.txt` already exists at your domain root, or deploy this project
at a subdomain (`tools.example.com`) instead, where the subdomain root
*is* this project's root.

## How routing works (no `.php`, no `?id=`, no router file)

Every tool/category/page still exists as a real, tiny **file** at the
project root — Apache/PHP require a real `.php` file to execute — but
`.htaccess` rewrites the public, extension-less URL to it, so visitors
and search engines never see `.php`:

```php
<?php
$toolSlug = 'salary-calculator';
require __DIR__ . '/includes/tool-page.php';
```

The Admin Panel writes this file automatically when you save a tool
(`tp_write_tool_route()` in `includes/functions.php`), and deletes/
recreates it (plus a 301 redirect row, stored extension-less and
relative to the site root so it survives a subfolder move) if the slug
changes. `tool-page.php`, `category-page.php`, `static-page.php` and
`blog-post.php` are the four generic renderers — they pull all
breadcrumb/SEO/schema/FAQ/related-tools content from the database, so
the thin file never needs to change.

The `.htaccess` rewrite chain (in order): (1) a request that still
literally ends in `.php` 301-redirects to its clean form — except
`/admin/` and `/api/`, which intentionally keep `.php` since they're
not public/indexed pages; (2) real files are served as-is; (3) a clean
URL internally maps to its matching `<slug>.php` file; (4) a real
directory with no matching `.php` file (like `assets/`) is served
as-is; (5) anything left over hits the custom 404 page. Nothing in
these rules hardcodes a domain or folder name — every rule resolves
relative to wherever `.htaccess` physically lives, which is exactly
what makes the subfolder deployment above "just work."

**Requirement:** your host needs `mod_rewrite` enabled and
`AllowOverride All` (or at least `FileInfo`) for this directory — true
on effectively all shared hosting (cPanel, Plesk) by default. If clean
URLs 404 after upload, that's the first thing to check with your host.

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
MariaDB 10.11 instance and PHP 8.4's built-in server: `schema.sql` imports cleanly, `seed.php`
creates the admin account, 12 categories, 55 tools with
content/FAQs/examples/SEO/related-tools and their route files, all
without a single PHP warning/notice. The homepage, a tool page (with
its FAQ accordion, related tools rail and JSON-LD schema), a category
page, global search (page + AJAX API), static pages, the sitemap/robots
endpoints, the full admin login flow, the admin dashboard, the tools
list, the tool builder form, and the live AJAX SEO-score endpoint were
all fetched and checked for correct output and view-tracking behavior.

The subfolder auto-detection and clean-URL behavior was specifically
verified by copying the project into a simulated
`www.example.com/tools/`-style install (a subfolder one level under a
different `DOCUMENT_ROOT`), with a small PHP-built-in-server router
that mirrors the `.htaccess` rewrite rules exactly (this sandbox
couldn't install a real Apache+PHP module — its package mirror was
blocked — so the *rule logic* was verified this way rather than by a
live Apache; the rules themselves are standard, well-documented
`mod_rewrite` patterns). Confirmed: the base path auto-detects with no
configuration, CSS/JS load correctly, clean URLs
(`/salary-calculator`) serve the right tool, an old-style
`/salary-calculator.php` request 301-redirects to the clean URL,
`/admin/*.php` keeps working unchanged, canonical/OG/JSON-LD URLs come
out fully-qualified and correctly prefixed, and — the trickiest case —
`/blog` correctly serves the blog listing page rather than colliding
with the `blog/` directory that holds individual posts.
Renaming a tool's slug and a category's slug through the Admin Panel
were both confirmed to 301-redirect the old URL to the new one. This
same pass also caught and fixed two real bugs in the admin forms (a
`bind_param` type-string length mismatch in the blog and category
"create new" forms, and a `NOT NULL` SEO column that wasn't defaulted
for brand-new SEO rows) that predated this URL-format change.

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
