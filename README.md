# Marketplace — Core PHP Multi-Tenant Marketplace SaaS

Built entirely in **Core PHP 8+, procedural, no framework, no classes**.
MySQL via MySQLi prepared statements, one centralized database
connection, a modular folder structure (one folder per feature), and
centralized configuration. No Laravel/CodeIgniter/Symfony/Yii/etc, and
no MVC/OOP layer of any kind.

This is a **multi-tenant SaaS**: different business owners sign up at
`signup/start.php` and each gets their own fully isolated marketplace
instance — own vendors, customers, products, orders, branding — reachable
at their own subdomain (`{subdomain}.yourdomain.com`), all running on one
shared codebase and one shared database. Each tenant's own marketplace runs
the same **Artisan Marketplace** / **Business Shops** / platform-owned
**Official Store** structure as before, now seeded per-tenant instead of
once globally. A separate **Platform Control Panel** (`platform/`, its own
login, at `platform.yourdomain.com`) lets the SaaS operator see every
tenant and suspend/reactivate one without touching any tenant's own data.

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
   Also edit `APP_BASE_DOMAIN` in `config/constants.php` to your real
   domain (e.g. `'marketplace.com'`), and point a **wildcard DNS record**
   (`*.marketplace.com`) at this same server — no per-tenant vhost or
   database is needed, the app resolves which tenant a request belongs to
   from the subdomain itself.
3. **Import `database.sql`** — cPanel → phpMyAdmin → select your
   database → Import tab → choose `database.sql` → Go. This also seeds
   tenant #1 (`demo.yourdomain.com`) with the same content the single-
   tenant version of this project used to ship with.

Visit your bare domain (`yourdomain.com`) and you'll be redirected to
`signup/start.php` where a new business can create their own tenant.
Visit `demo.yourdomain.com` to see the pre-seeded first tenant, or
`platform.yourdomain.com/login.php` for the platform operator's control
panel. If something's wrong, `config/database.php` shows a plain-English
error (bad DB credentials, or the import didn't run) instead of a blank
page or PHP warning wall.

### Testing multi-tenant locally without wildcard DNS

`php -S 127.0.0.1:8000` plus curl's `-H "Host: ..."` override exercises
the exact same tenant-resolution code path production uses — no debug
flags needed:

```bash
curl -H "Host: demo.marketplace.test" http://127.0.0.1:8000/store/home.php
curl -H "Host: platform.marketplace.test" http://127.0.0.1:8000/platform/login.php
```

For manual browser testing, add real `/etc/hosts` entries instead (e.g.
`127.0.0.1 demo.marketplace.test`).

### Upgrading an existing single-tenant install

Just re-run the updated `database.sql` against your existing database —
every `ALTER TABLE ... ADD COLUMN tenant_id ... DEFAULT 1` statement adds
the new multi-tenant columns *and* silently backfills every one of your
existing rows onto tenant #1 in the same step, so nothing is lost.

### Want to see it populated instead of empty?

Import `database-demo-data.sql` right after `database.sql` (same way —
phpMyAdmin Import, or `mysql -u user -p db < database-demo-data.sql`).
It adds 8 vendors, 20 products, 3 customers, and 3 orders in different
statuses (pending / processing / delivered) so every page — storefronts,
product pages, cart, checkout, "My Orders", the vendor order queue, and
the admin order list — has real content instead of empty states. Every
demo account (vendors and customers) uses password `admin123`. Safe to
re-run; entirely optional.

### Demo logins (change before anyone else can reach the site)

- Tenant #1 admin: `admin@marketplace.test` / `admin123` at `demo.yourdomain.com/admin/login.php`
- Tenant #1's Official Store vendor: `store@marketplace.test` / `admin123`
- Platform operator: `platform@marketplace.test` / `admin123` at `platform.yourdomain.com/login.php`

## Design system

`assets/css/global.css` defines the shared identity: warm ink-and-cream
neutrals (not stark white/slate), a signature coral brand color instead
of generic SaaS blue, `Playfair Display` for headings over `Inter` body
text, an asymmetric "blob" corner radius on buttons/pills, a subtle
SVG paper-grain texture on hero sections and the footer, status chips
with a colored dot for every order/payment state, and sticky account
menus with a cart badge. Each theme layers its own personality on top:
`artisan-theme.css` is warm terracotta/gold and organic;
`business-theme.css` is a deep indigo/teal duotone with a fine
structural grid texture and sharp corners — deliberately more
"engineered" to contrast with artisan's warmth; `admin.css` stays calm
and undecorated on purpose. Verified visually with real Chromium
screenshots (Playwright) against seeded data, not just read from source.

## Folder structure

Every module lives in its own top-level folder, and every page inside
a module is a real, directly reachable `.php` file — there's no router
and no front controller, so a missing file only 404s that one page
instead of taking down the site.

```
platform/         SaaS-operator control panel: login, dashboard, list every
                  tenant, suspend/reactivate a tenant. Separate login
                  (platform_admins table) from any tenant's own admin panel.
signup/           public tenant self-signup — pick a subdomain, create the
                  first admin account, get a 14-day trial marketplace
admin/            one tenant's own admin panel: dashboard, reports, orders,
                  products, categories, vendor/category approvals,
                  customers, banners, settings, admin users, activity log
vendor/           vendor auth + the vendor's own dashboard/profile/products
customer/         customer auth
store/            marketplace-wide pages: homepage, global search, follow action
artisan/          Artisan Marketplace: index, category, store pages
business/         Business Shops: index, category, store pages
official-store/   the platform's own Official Store
categories/       generic ?slug= category resolver (redirects into artisan/business)
products/         product detail page + the shared product-card partial

cart/             persisted cart: view/add/update/remove
checkout/         address + payment method -> places the order
orders/           shared order invoice view (details.php), any of the
                  three parties (customer/vendor/admin) involved may view it

wishlist/ payments/ pickup/ shipping/ wallet/ rewards/ notifications/
support/ reports/ analytics/ blog/ cms/ api/ cron/
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
    constants.php site-wide constants (SITE_NAME, APP_BASE_DOMAIN, etc.)
    session.php   session_start() + cookie hardening
    routes.php    per-module base-path constants (ROUTE_VENDOR, ROUTE_ADMIN, ...)
    tenant.php    resolves which tenant a request belongs to from its
                  subdomain — required last in config.php
templates/        header.php, footer.php, admin-header.php, admin-footer.php,
                  platform-header.php, platform-footer.php
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

**Multi-tenancy is ambient, not a parameter.** `mp_tenant_id()`
(`includes/middlewares/tenant.php`) is resolved once per request from the
subdomain and read directly inside query functions — the same way
`mp_current_admin()` is already an ambient, session-derived accessor
rather than something threaded through every function call. `mp_db_insert()`
auto-injects `tenant_id` into every insert unless the table is in
`MP_TENANT_EXEMPT_TABLES` (`tenants`, `platform_admins`), so almost none of
the ~150 page files needed to change — the invasive part of this design is
confined to `database.sql` and `includes/functions/*.php`.

## What's in the codebase today

- **Multi-tenant from the ground up.** Every business that signs up
  (`signup/start.php`) gets its own isolated marketplace instance — own
  vendors, customers, products, orders, settings, and even its own
  `marketplace_types` rows — sharing one codebase and one database, kept
  apart by a `tenant_id` column on every tenant-owned table and resolved
  per-request from the subdomain. The same email or store slug can be
  reused freely across different tenants (uniqueness constraints are all
  composite `(tenant_id, ...)`, not global).
- **Marketplace types are data, not code — now per-tenant.** Each
  tenant's own `marketplace_types` rows (`artisan` / `business` /
  `official`, seeded at signup) drive its nav labels; a new marketplace
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
- **Cart → checkout → order → fulfillment, for real.** `cart_items` is
  a persisted per-customer cart. `checkout/place.php` calls
  `mp_create_order()` (`includes/functions/orders.php`), which wraps
  stock validation, the `orders` row, every `order_items` row (each
  carrying its own `vendor_id` so a multi-vendor cart still ships as
  one checkout), a `transactions` row, and clearing the cart in **one
  real MySQLi transaction** (`mp_db_begin_transaction()` /
  `mp_db_commit()` / `mp_db_rollback()`, procedural `mysqli_*`, no ORM)
  — nothing is left half-written if any step fails. Every order-level
  status change is written to `order_status_history` as an audit
  trail. Vendors update only their own `order_items` (scoped by
  `vendor_id`); `mp_recompute_order_status()` rolls per-item statuses
  up into the order's own status automatically. `orders/details.php`
  is one shared invoice view — the customer who placed it, any vendor
  with items in it, and admins can all view it, each seeing only what
  they should.
- **Payments are gateway-ready, not gateway-integrated.** `transactions`
  already has `stripe`/`paypal` as valid `gateway` values alongside the
  `cod`/`manual` methods this build actually processes — wiring up real
  payment processing later needs a new code path, not a schema change.
- **Full admin CRUD**, dynamic settings, activity audit trail, admin-editable
  homepage banners, and detailed multi-step vendor registration with
  simulated email verification — see `DOCUMENTATION.md` for the complete
  breakdown of every admin capability and every table.

Verified end-to-end against a real MariaDB instance and PHP's built-in
server: vendor registration → admin approval → category approval →
product creation (including per-category limit enforcement) → store
pages → global search → follow → add to cart → checkout → order
placement (stock decremented, transaction recorded) → vendor status
update → order status roll-up → CSRF protection — all pass.

## What's deferred to future work

The `wishlist/`, `payments/`, `pickup/`, `shipping/`, `wallet/`,
`rewards/`, `notifications/`, `support/`, `reports/`, `analytics/`,
`blog/`, `cms/`, `api/`, and `cron/` folders are scaffolded (each with
a short README) so these modules can be built without restructuring
the project, but none of them are implemented yet. Also deferred:
enterprise SEO module, the real PHPMailer-backed email engine, real
Stripe/PayPal payment processing, vendor logo/banner file uploads
(`assets/uploads/` is reserved for this), and clean/pretty URLs (every
page is still reachable at its own literal filename by design — see the
folder structure above).

## Full documentation

See `DOCUMENTATION.md` for the complete database schema, every user
role's flow (customer/vendor/admin), the full list of admin panel
capabilities, and extension points for future modules.
