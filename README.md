# Marketplace — Core PHP Multi-Marketplace Platform

Built entirely in **Core PHP 8+, procedural, no framework, no classes**.
MySQL via MySQLi prepared statements, one centralized database
connection, a modular folder structure (one folder per feature), and
centralized configuration. No Laravel/CodeIgniter/Symfony/Yii/etc, and
no MVC/OOP layer of any kind.

This build lays the **core architecture** for running two distinct
marketplace experiences — the **Artisan Marketplace** and **Business
Shops** — plus a single platform-owned **Official Store**, under one
site and one admin panel.

## Deploy in 3 steps

1. **Upload everything** — the whole project folder to your site's
   document root (e.g. `public_html/`).
2. **Edit `config/database.php`** — change the four DB_* constants to
   your real database details (get them from cPanel → MySQL Databases):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   That is the only file you need to edit.
3. **Import `database.sql`** — cPanel → phpMyAdmin → select your
   database → Import tab → choose `database.sql` → Go.

Visit your domain. If something's wrong, `config/database.php` shows a
plain-English error (bad DB credentials, or the import didn't run)
instead of a blank page or PHP warning wall.

### Demo logins (change before anyone else can reach the site)

- Admin: `admin@marketplace.test` / `admin123` at `/admin/login.php`
- Official Store vendor: `store@marketplace.test` / `admin123`

## Folder structure

Every module lives in its own top-level folder, and every page inside
a module is a real, directly reachable `.php` file — there's no router
and no front controller, so a missing file only 404s that one page
instead of taking down the site.

```
admin/            admin panel: dashboard, vendor approvals, category approvals
vendor/           vendor auth + the vendor's own dashboard/profile/products
customer/         customer auth
store/            marketplace-wide pages: homepage, global search, follow action
artisan/          Artisan Marketplace: index, category, store pages
business/         Business Shops: index, category, store pages
official-store/   the platform's own Official Store
categories/       generic ?slug= category resolver (redirects into artisan/business)
products/         product detail page + the shared product-card partial

orders/ checkout/ cart/ wishlist/ payments/ pickup/ shipping/ wallet/
rewards/ notifications/ support/ reports/ analytics/ blog/ cms/ api/ cron/
                  scaffolded for future development — each has a README
                  describing what will live there; not yet implemented

assets/           css/, js/, images/, icons/, fonts/, uploads/
includes/
    helpers/      generic helpers (escaping, slugs, redirects, flash, CSRF)
    middlewares/  session-based auth guards (mp_require_vendor, mp_require_admin)
    functions/    one file per database table — all queries live here
config/
    config.php    bootstrap — every page requires this ONE file first
    database.php  the ONE MySQLi connection + query helpers (mp_db_*)
    constants.php site-wide constants (SITE_NAME, etc.)
    session.php   session_start() + cookie hardening
    routes.php    per-module base-path constants (ROUTE_VENDOR, ROUTE_ADMIN, ...)
templates/        header.php, footer.php, admin-header.php, admin-footer.php
logs/             mp_notify() writes notifications.log here
cache/ storage/ temp/   reserved, not web-accessible
database.sql      every CREATE TABLE + all seed data, one file
index.php         front door — requires store/home.php
404.php           fully self-contained — no dependency on config/ at all
```

A page looks like a classic PHP script — no template layer beyond a
shared header/footer, logic and HTML together in one file:

```php
<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
$products = mp_products_by_vendor($vendor['id']);

$pageTitle = 'My Products';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>
<h1>My Products</h1>
<?php foreach ($products as $product): mp_render_product_card($product); endforeach; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
```

## Database access

Every query in the project goes through `config/database.php`'s
`mp_db_*` helpers, which wrap MySQLi prepared statements
(`mysqli_prepare` / `mysqli_stmt_bind_param` / `mysqli_stmt_execute`) —
no string-concatenated SQL anywhere:

```php
mp_db_fetch_one('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$id]);
mp_db_fetch_all('SELECT * FROM products WHERE category_id = ? LIMIT ?', [$categoryId, $limit]);
mp_db_insert('products', $data);          // builds INSERT from an assoc array
mp_db_update('vendors', $data, 'id = ?', [$vendorId]);
```

Only one connection is ever opened (`mp_db()`, a static-cached MySQLi
handle) — every `includes/functions/*.php` file calls into these
helpers instead of touching `mysqli_connect()` directly.

## What's in the codebase today

- **Marketplace types are data, not code.** `marketplace_types` is a
  lookup table (`artisan` / `business` / `official`); a new marketplace
  type is a new row, not a new set of `if` branches.
- **Categories are scoped per marketplace type** and share one
  auto-increment ID space. Anywhere a category ID comes from user
  input, it's filtered through `mp_filter_category_ids_by_marketplace()`
  before being trusted.
- **Vendor approval workflow** is enforced server-side, not just the
  UI: `vendor/product-form.php` blocks product creation unless
  `vendor.status = approved`, and the store pages 404 for a vendor
  that isn't approved.
- **Category approval workflow**: a Business Shop vendor's submitted
  product `category_id` is checked against
  `mp_approved_category_ids_for_vendor()` and, if the admin set a
  `usage_limit`, against the vendor's current product count in that
  category.
- **Notifier seam, not an email system**: every point the vendor
  onboarding flow should "send an email" calls `mp_notify()`
  (`includes/functions/notifications.php`), which writes to
  `logs/notifications.log`. Swapping this for real PHPMailer/SMTP
  sending later won't require touching any page.
- **Customers/follow/ratings**: a minimal `customers` table plus
  `vendor_follows` and `vendor_ratings` back the "Follow Artist" and
  "Artist/Store Ratings" features.

Verified end-to-end against a real MariaDB instance and PHP's built-in
server: vendor registration → admin approval → category approval →
product creation (including per-category limit enforcement) → store
pages → global search → follow → CSRF protection — all pass.

## What's deferred to future work

The `orders/`, `checkout/`, `cart/`, `wishlist/`, `payments/`,
`pickup/`, `shipping/`, `wallet/`, `rewards/`, `notifications/`,
`support/`, `reports/`, `analytics/`, `blog/`, `cms/`, `api/`, and
`cron/` folders are scaffolded (each with a short README) so these
modules can be built without restructuring the project, but none of
them are implemented yet. Also deferred: enterprise SEO module, the
real PHPMailer-backed email engine, full admin CRUD over every entity,
vendor logo/banner file uploads (`assets/uploads/` is reserved for
this), and clean/pretty URLs (every page is still reachable at its own
literal filename by design — see the folder structure above).
