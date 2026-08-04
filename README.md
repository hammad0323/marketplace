# Marketplace — Multi Marketplace Architecture

Vanilla PHP (no framework, no classes). **Every page is its own real
`.php` file sitting directly in the project root** — there are no
subfolders to upload correctly, no router, no build step. Upload the
whole folder, edit four lines in `config.php`, import `database.sql`,
done.

This build lays the **core architecture** for running two distinct
marketplace experiences — the **Artisan Marketplace** and **Business
Shops** — plus a single platform-owned **Official Store**, under one
site and one admin panel.

Out of scope (tracked as future work): the enterprise SEO module, the
centralized email notification engine, and the full admin CRUD panel
over every entity. See "What's deferred" below.

## Deploy in 3 steps

1. **Upload everything** — the whole project as one folder, to your
   site's document root (e.g. `public_html/`, or an addon domain's
   folder). There is nothing to extract into a subfolder and nothing
   to point a document root at — `index.php` is right there.
2. **Edit `config.php`** — open it and change these four lines to your
   real database details (get them from cPanel → MySQL Databases):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   That is the only file you need to edit.
3. **Import `database.sql`** — cPanel → phpMyAdmin → select your
   database → Import tab → choose `database.sql` → Go. One file, every
   table and all seed data, done in one import.

Visit your domain. If something's wrong, `config.php` shows a plain-
English error (bad DB credentials, or the import didn't run) instead
of a blank page or PHP warning wall.

### Demo logins (change before anyone else can reach the site)

- Admin: `admin@marketplace.test` / `admin123` at `/admin-login.php`
- Official Store vendor: `store@marketplace.test` / `admin123`

There's no "change password" screen yet — generate a new hash with
`php -r "echo password_hash('yournewpassword', PASSWORD_DEFAULT);"`
and update the `password_hash` column for that row via phpMyAdmin.

## Why it's flat

Earlier versions of this project split code across `pages/`, `admin/`,
`includes/`, `data/`, etc., with a router matching clean URLs like
`/artisan/wooden-crafts` to files in those folders. That broke on
upload — if even one subfolder didn't transfer completely (which is
easy to have happen with a File Manager zip-extract or a partial FTP
upload), the router would throw a fatal error trying to `require` a
file that wasn't there, taking down pages that had nothing to do with
the missing file.

This version trades pretty URLs for reliability: every page is
reachable at its own literal filename
(`/artisan.php`, `/product.php?slug=...`, `/vendor-dashboard.php`,
...), so there's no routing layer that depends on the whole folder
tree being intact. If a file is ever missing, only that one page 404s
— nothing else breaks.

## What's in the project

```
index.php            → requires home.php (the front page)
config.php            THE file you edit — DB credentials + bootstrap
                       (starts the session, connects to the DB, loads
                       functions.php). Refuses direct access on its own.
functions.php          every function used site-wide, in one file:
                       generic helpers, session/auth, the notification
                       log seam, and all database queries (grouped by
                       table with a comment divider) — all prefixed
                       mp_ to avoid name collisions
header.php, footer.php public-site chrome (nav, flash messages,
                       footer), theme-aware (main / artisan / business)
admin-header.php, admin-footer.php   admin panel chrome
product-card.php       small reusable product tile, used by every
                       listing page
404.php                 fully self-contained — no dependency on
                       config.php or anything else, so it can never
                       itself be the thing that's broken

home.php, artisan.php, artisan-category.php, artisan-store.php,
business.php, business-category.php, business-store.php,
official-store.php, product.php, category.php, search.php
                        public pages

vendor-login.php, vendor-register.php, vendor-logout.php,
vendor-dashboard.php, vendor-profile.php, vendor-categories.php,
vendor-products.php, vendor-product-form.php
                        vendor auth + the vendor's own dashboard

customer-login.php, customer-register.php, customer-logout.php,
follow.php              customer auth + following a vendor

admin-login.php, admin-logout.php, admin-dashboard.php,
admin-vendors.php, admin-vendor-approve.php, admin-vendor-reject.php,
admin-category-requests.php, admin-category-decide.php,
admin-category-toggle.php
                        the admin panel

database.sql            every CREATE TABLE + all seed data, one file
assets/                 css/, img/ — the only other web-facing folder
uploads/                reserved for future file-upload features
logs/                   mp_notify() writes notifications.log here
```

A page looks like a classic PHP script — no template layer, no
separate "view", logic and HTML together in one file:

```php
<?php
require __DIR__ . '/config.php';

$vendor = mp_require_vendor();
$products = mp_products_by_vendor($vendor['id']);

$pageTitle = 'My Products';
$theme = 'main';
require __DIR__ . '/header.php';
?>
<h1>My Products</h1>
<?php foreach ($products as $product): mp_render_product_card($product); endforeach; ?>
<?php require __DIR__ . '/footer.php'; ?>
```

## What's actually web-facing

Since every page is a real file, there's no "internal" folder that
routing keeps hidden. Two things are worth knowing:

- `config.php` refuses to run if it's requested directly (checked in
  PHP itself, at the top of the file — this works even if `.htaccess`
  isn't respected by your host, unlike relying on server config alone).
- `.htaccess` adds an optional second layer blocking direct access to
  `config.php`, `functions.php`, and any `.sql` file (so `database.sql`
  isn't downloadable once imported) — but the site works correctly
  even if `.htaccess`/`mod_rewrite` isn't honored at all, since nothing
  here depends on URL rewriting.

Verified end-to-end against a real MariaDB 10.11 instance and PHP's
built-in server: `database.sql` imports cleanly with a single
`mysql < database.sql` (equivalent to phpMyAdmin's Import), and the
full flow — vendor registration → admin approval → category approval
→ product creation (including per-category limit enforcement) → store
pages → global search → follow → CSRF protection — all pass. Also
caught and fixed a real encoding bug this way: emoji in the marketplace
badges (🏺 🏪 ⭐) got corrupted on import by clients that don't default
to `utf8mb4` (including the plain `mysql` CLI) — fixed by adding
`SET NAMES utf8mb4;` as the first line of `database.sql`.

## Architecture decisions worth knowing

- **Marketplace types are data, not code.** `marketplace_types` is a
  lookup table (`artisan` / `business` / `official`); a new marketplace
  type is a new row, not a new set of `if` branches.
- **Categories are scoped per marketplace type** and share one
  auto-increment ID space. Anywhere a category ID comes from user
  input (vendor registration, category requests), it's filtered
  through `mp_filter_category_ids_by_marketplace()` before being
  trusted — otherwise a business vendor could end up with a request
  against an artisan category.
- **Vendor approval workflow** is enforced in the pages themselves,
  not just the UI: `vendor-product-form.php` blocks product creation
  server-side unless `vendor.status = approved`, and the store pages
  show a 404 for a vendor that isn't approved — so there's no way to
  make a pending store "go live" by hitting a URL directly.
- **Category approval workflow**: a Business Shop vendor's submitted
  product `category_id` is checked against
  `mp_approved_category_ids_for_vendor()` (status = approved **and**
  `is_enabled = 1`) and, if the admin set a `usage_limit`, against the
  vendor's current product count in that category — enforced at
  product-creation time, verified in testing (limit of 1 correctly
  blocked a 2nd product).
- **Notifier seam, not an email system**: every point the vendor
  onboarding flow should "send an email" calls `mp_notify()`, which
  writes to `logs/notifications.log`. It's a drop-in seam for a future
  centralized email engine — no page will need to change when that's
  built.
- **Customers/follow/ratings**: a minimal `customers` table plus
  `vendor_follows` and `vendor_ratings` back the "Follow Artist" and
  "Artist/Store Ratings" features. Full customer commerce (orders,
  checkout, rewards) is not part of this build.

## What's deferred to future work

- **Enterprise SEO module** — meta management, sitemaps, structured
  data, vendor SEO score.
- **Centralized email notification engine** — templates, actual
  sending (currently stubbed via `mp_notify()`).
- **Full admin CRUD panel** — this build ships only the two admin
  screens the architecture depends on (vendor approval, category
  approval). Products/orders/CMS/banners/reports admin management is
  separate work.
- **Vendor logo/banner file uploads** — profile forms currently take
  text fields and (for products) pasted image URLs; a real upload
  pipeline is future work (`uploads/` is reserved for it).
- **Clean URLs** — traded away for deployment reliability, see "Why
  it's flat" above. Can be reintroduced later via `.htaccess` rewrites
  once the site is confirmed working, without changing any PHP logic.
