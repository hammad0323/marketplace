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
  match-and-`require` loop in `public/index.php`.
- MySQL, schema organized **folder-wise** by domain under
  `database/schema/`, applied in numeric order by `database/migrate.php`.
- Plain CSS, one shared base (`global.css`) plus a theme file per
  marketplace (`artisan-theme.css`, `business-theme.css`) so the two
  marketplaces can look completely different while sharing structure.

## Folder structure

```
public/
  index.php        front controller — matches the URL against a route
                    table and requires the matching file in modules/
  assets/           css/, js/, img/
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

1. Create a MySQL database and export connection details as env vars
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) — see defaults in
   `includes/config.php`.
2. Apply schema + seed data:
   ```
   php database/migrate.php --seed
   ```
3. Point your web server's document root at `public/` (preferred), or
   at the repo root — a fallback root `.htaccess` rewrites into
   `public/` for hosts that can't be pointed at a subdirectory.
4. Demo logins (**change before any shared/production use**):
   - Admin: `admin@marketplace.test` / `admin123` at `/admin/login`
   - Official Store vendor: `store@marketplace.test` / `admin123`

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
