# Marketplace — Multi Marketplace Architecture

Vanilla PHP (no framework) + MySQL. This PR lays the **core architecture**
for running two distinct marketplace experiences — the **Artisan
Marketplace** and **Business Shops** — plus a single platform-owned
**Official Store**, under one site and one admin panel.

Out of scope for this PR (tracked as future work): the enterprise SEO
module, the centralized email notification engine, and the full
admin CRUD panel over every entity. See "What's deferred" below —
this PR builds the seams those systems will plug into, not the
systems themselves.

## Stack

- PHP, no framework — a ~150-line router (`app/core/Router.php`) and a
  small `View`/`Model` base are the only "framework" pieces.
- MySQL, schema organized **folder-wise** by domain under
  `database/schema/`, applied in numeric order by `database/migrate.php`.
- Plain CSS, one shared base (`global.css`) plus a theme file per
  marketplace (`artisan-theme.css`, `business-theme.css`) so the two
  marketplaces can look completely different while sharing structure.

## Folder structure

```
app/
  config/        config.php, database.php (PDO)
  core/          Router, View, Model, Auth, Notifier, helpers
  controllers/   one class per route group (+ Admin/ subfolder)
  models/        one class per table
  views/         layouts/ (main, artisan, business, admin) + per-feature views
database/
  schema/        01_*.sql .. 11_*.sql — one file per table/concern
  seeds/         marketplace types, categories, demo admin + official store
  migrate.php    runs schema/ then seeds/ (--seed flag) in filename order
public/          web root — index.php front controller, assets/
storage/         logs/ (Notifier output), uploads/ (reserved)
```

## Setup

1. Create a MySQL database and export connection details as env vars
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) — see defaults in
   `app/config/config.php`.
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
creation → store pages → global search all work.

## Architecture decisions worth knowing

- **Marketplace types are data, not code.** `marketplace_types` is a
  lookup table (`artisan` / `business` / `official`); a new marketplace
  type is a new row + a config entry, not a new set of `if` branches.
- **Categories are scoped per marketplace type** and share one
  auto-increment ID space. Anywhere a category ID comes from user
  input (vendor registration, category requests), it's filtered
  through `Category::filterIdsByMarketplace()` before being trusted —
  otherwise a business vendor could end up with a request against an
  artisan category.
- **URL structure**: the spec's example URLs (`/artisan/wooden-crafts`
  as a category and `/artisan/artist-name` as a vendor) share one
  path shape, which would collide. This PR resolves it as:
  - `/artisan/{vendorSlug}` — artisan store page
  - `/artisan/category/{slug}` — artisan category listing
  - `/business/{vendorSlug}` / `/business/category/{slug}` — same
    split for Business Shops
  - `/category/{slug}` — the spec's generic form, resolved by looking
    the slug up across marketplace types and redirecting into the
    correct themed listing above
  - `/product/{slug}`, `/store/official-store` — as specified
- **Vendor approval workflow** is enforced in the controllers, not
  just the UI: `VendorDashboardController` blocks product creation
  server-side unless `vendor.status = approved`, and `StoreController`
  404s a vendor's public page unless approved — so there's no way to
  make a pending store "go live" by hitting a URL directly.
- **Category approval workflow**: a Business Shop vendor's submitted
  product `category_id` is checked against
  `VendorCategoryRequest::approvedCategoryIds()` (status = approved
  **and** `is_enabled = 1`) and, if the admin set a `usage_limit`,
  against the vendor's current product count in that category — all
  enforced at product-creation time, verified in testing (limit of 2
  correctly blocked a 3rd product).
- **Notifier seam, not an email system**: every point the vendor
  onboarding spec says "send an email" calls `Notifier::log()`, which
  writes to `storage/logs/notifications.log`. It's a drop-in seam for
  the future centralized email engine — no controller will need to
  change when that's built.
- **Customers/follow/ratings**: a minimal `customers` table plus
  `vendor_follows` and `vendor_ratings` back the "Follow Artist" and
  "Artist/Store Ratings" features called out in the marketplace spec.
  Full customer commerce (orders, checkout, rewards) is not part of
  this PR.

## What's deferred to future PRs

- **Enterprise SEO module** — global + per-page meta management,
  sitemaps, JSON-LD structured data, vendor SEO Assistant/score.
- **Centralized email notification engine** — templates, admin-managed
  placeholders, actual sending (currently stubbed via `Notifier::log()`).
- **Full admin CRUD panel** — this PR ships only the two admin screens
  the architecture depends on (vendor approval, category approval).
  Products/orders/CMS/banners/reports/etc. admin management is a
  separate build.
- **Vendor logo/banner file uploads** — profile forms currently take
  text fields and (for products) pasted image URLs; a real upload
  pipeline is future work.
