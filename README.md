# Marketplace — Multi Marketplace Architecture

Vanilla PHP (no framework), organized **folder-wise by feature** — not
MVC. There's no controller/model/view class hierarchy; each URL maps
to one plain PHP file that queries the database and outputs its own
HTML, grouped into feature folders. Shared plumbing (DB connection,
session helpers, query functions) lives in small `include`d function
libraries, not classes.

This PR lays the **core architecture** for running two distinct
marketplace experiences — the **Artisan Marketplace** and **Business
Shops** — plus a single platform-owned **Official Store**, under one
site and one admin panel.

Out of scope for this PR (tracked as future work): the enterprise SEO
module, the centralized email notification engine, and the full admin
CRUD panel over every entity. See "What's deferred" below — this PR
builds the seams those systems will plug into, not the systems
themselves.

## Stack

- PHP, no framework, no classes for app logic — plain functions and
  page scripts throughout. The only "routing" is a ~40-line regex
  match-and-`require` loop in `index.php`, at the project root so it
  can be hit directly by hosts that only let you point a domain at one
  fixed folder.
- MySQL, schema organized **folder-wise** by domain under
  `database/schema/`, applied in numeric order by `database/migrate.php`.
- Plain CSS, one shared base (`global.css`) plus a theme file per
  marketplace (`artisan-theme.css`, `business-theme.css`) so the two
  marketplaces can look completely different while sharing structure.

## Folder structure

```
index.php          front controller, at the project root — matches the
                    URL against a route table and requires the
                    matching file in modules/
.htaccess           rewrites clean URLs to index.php, and blocks direct
                    web access to includes/ modules/ partials/
                    database/ storage/ (see "Deploying" below)
assets/             css/, js/, img/ — the only other web-facing folder
includes/
  config.php, database.php   plain config array + db() PDO helper
  helpers.php                e(), slugify(), csrf, flash, redirect, ...
  auth.php                   session helpers: current_vendor(), require_admin(), ...
  notifier.php               notify() — logs "would send email" events
  queries/                   plain query functions, one file per table
                              (mirrors database/schema/ folder-wise)
partials/
  header.php, footer.php          public-site chrome (theme-aware)
  admin-header.php, admin-footer.php
  nav.php, flash.php, product-card.php, 404.php
modules/                     one folder per feature area — the actual
                              pages. Each file handles its own
                              GET display + POST logic + HTML.
  home/, artisan/, business/, official-store/, product/, category/,
  search/, vendor/, customer/, admin/
database/
  schema/          01_*.sql .. 11_*.sql — one file per table/concern
  seeds/           marketplace types, categories, demo admin + official store
  migrate.php      runs schema/ then seeds/ (--seed flag) in filename order
  full-install.sql schema/ + seeds/ concatenated in order, for hosts
                   with no CLI access — import this one file via
                   phpMyAdmin instead of running migrate.php
storage/           logs/ (notify() output), uploads/ (reserved)
```

A page file looks like a classic PHP script:

```php
<?php
$vendor = require_vendor();               // includes/auth.php
$products = products_by_vendor($vendor['id']); // includes/queries/products.php

$pageTitle = 'My Products';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>
<h1>My Products</h1>
<?php foreach ($products as $product): render_product_card($product); endforeach; ?>
<?php require __DIR__ . '/../../partials/footer.php'; ?>
```

## Setup

### Option A — VPS / SSH access

1. Create a MySQL database and export connection details as env vars
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) — see defaults in
   `includes/config.php`.
2. Apply schema + seed data:
   ```
   php database/migrate.php --seed
   ```
3. Upload the whole project as one folder and point your domain /
   virtual host at its root — `index.php` sits right there, so there's
   no subdirectory to configure. Just make sure `mod_rewrite` is on and
   `.htaccess` overrides are allowed (`AllowOverride All`); nearly all
   PHP hosts support this by default.

### Option B — Shared hosting (cPanel, Hostinger, etc.), no terminal access

1. **Database**: in cPanel → *MySQL Databases*, create a database and
   a user, add the user to the database with **All Privileges**. Note
   the full names — shared hosts prefix them, e.g.
   `cpaneluser_marketplace` / `cpaneluser_dbuser`.
2. **Configure credentials**: since env vars usually aren't available
   on shared hosting, edit `includes/config.php` directly and hardcode
   your real `db.host` (usually `localhost`), `db.name`, `db.user`,
   `db.pass` in place of the `getenv(...) ?:` defaults, before
   uploading (or edit it afterwards via cPanel's File Manager code
   editor).
3. **Upload files**: cPanel *File Manager* → go to `public_html` (or
   your subdomain's folder) → *Upload* → upload the zip → select it →
   *Extract*. (Or upload via FTP instead.) When done, `index.php`
   should sit directly inside `public_html`, not in a subfolder.
4. **Import the database**: cPanel → *phpMyAdmin* → select your new
   database → *Import* tab → choose file → pick
   **`database/full-install.sql`** (one file, applies the whole schema
   + seed data in the correct order — no need to import the 15 files
   under `database/schema/` and `database/seeds/` one at a time) →
   *Go*.
5. **Folder permissions**: make `storage/logs/` and `storage/uploads/`
   writable (755, or 775 if 755 isn't enough) — File Manager → right
   click each folder → *Permissions*.
6. Visit your domain. If you get a blank page or 500 error, check
   cPanel's *Errors* log (or `Metrics → Errors`) — it's almost always
   a wrong DB credential in step 2.

### Demo logins (change before any shared/production use)

- Admin: `admin@marketplace.test` / `admin123` at `/admin/login`
- Official Store vendor: `store@marketplace.test` / `admin123`

There's no "change password" screen yet (out of scope for this PR) —
to change one, generate a new hash locally with
`php -r "echo password_hash('yournewpassword', PASSWORD_DEFAULT);"`
and update the `password_hash` column for that row via phpMyAdmin.

### Deploying: what's actually web-facing

Since `index.php` lives at the project root instead of behind a
`public/` boundary, `.htaccess` explicitly blocks direct HTTP access
to `includes/`, `modules/`, `partials/`, `database/`, and `storage/` —
otherwise things like `/database/seeds/03_admin_seed.sql` (which
contains a password hash) or `/database/migrate.php` (which would
re-run schema/seed SQL if hit over HTTP) would be directly reachable.
Only `index.php`, `.htaccess`, and `assets/` are meant to be served
directly; everything else is `require`d internally by `index.php`. If
you deploy behind Nginx or another server instead of Apache, replicate
that same deny rule for those five folders before going live.

Verified end-to-end against a real MariaDB 10.11 instance and PHP's
built-in server during development: schema + seeds apply cleanly,
vendor registration → admin approval → category approval → product
creation (including limit enforcement) → store pages → global search
→ follow/CSRF all work.

## Architecture decisions worth knowing

- **Marketplace types are data, not code.** `marketplace_types` is a
  lookup table (`artisan` / `business` / `official`); a new marketplace
  type is a new row + a config entry, not a new set of `if` branches.
- **Categories are scoped per marketplace type** and share one
  auto-increment ID space. Anywhere a category ID comes from user
  input (vendor registration, category requests), it's filtered
  through `filter_category_ids_by_marketplace()` before being trusted —
  otherwise a business vendor could end up with a request against an
  artisan category.
- **URL structure**: the spec's example URLs (`/artisan/wooden-crafts`
  as a category and `/artisan/artist-name` as a vendor) share one path
  shape, which would collide. This PR resolves it as:
  - `/artisan/{vendorSlug}` — artisan store page
  - `/artisan/category/{slug}` — artisan category listing
  - `/business/{vendorSlug}` / `/business/category/{slug}` — same
    split for Business Shops
  - `/category/{slug}` — the spec's generic form, resolved by looking
    the slug up across marketplace types and redirecting into the
    correct themed listing above
  - `/product/{slug}`, `/store/official-store` — as specified
- **Vendor approval workflow** is enforced in the page scripts, not
  just the UI: `modules/vendor/product-form.php` blocks product
  creation server-side unless `vendor.status = approved`, and the
  store pages 404 a vendor's public page unless approved — so there's
  no way to make a pending store "go live" by hitting a URL directly.
- **Category approval workflow**: a Business Shop vendor's submitted
  product `category_id` is checked against
  `approved_category_ids_for_vendor()` (status = approved **and**
  `is_enabled = 1`) and, if the admin set a `usage_limit`, against the
  vendor's current product count in that category — all enforced at
  product-creation time, verified in testing (limit of 2 correctly
  blocked a 3rd product).
- **Notifier seam, not an email system**: every point the vendor
  onboarding spec says "send an email" calls `notify()`
  (`includes/notifier.php`), which writes to
  `storage/logs/notifications.log`. It's a drop-in seam for the future
  centralized email engine — no page script will need to change when
  that's built.
- **Customers/follow/ratings**: a minimal `customers` table plus
  `vendor_follows` and `vendor_ratings` back the "Follow Artist" and
  "Artist/Store Ratings" features called out in the marketplace spec.
  Full customer commerce (orders, checkout, rewards) is not part of
  this PR.

## What's deferred to future PRs

- **Enterprise SEO module** — global + per-page meta management,
  sitemaps, JSON-LD structured data, vendor SEO Assistant/score.
- **Centralized email notification engine** — templates, admin-managed
  placeholders, actual sending (currently stubbed via `notify()`).
- **Full admin CRUD panel** — this PR ships only the two admin screens
  the architecture depends on (vendor approval, category approval).
  Products/orders/CMS/banners/reports/etc. admin management is a
  separate build.
- **Vendor logo/banner file uploads** — profile forms currently take
  text fields and (for products) pasted image URLs; a real upload
  pipeline is future work.
