# Marketplace — Complete Project Documentation

This document explains how the website actually works: the architecture, the
full database schema, every user role and their flows, everything the admin
panel can do, how to deploy it, and where to plug in future work. For a
quick-start deploy guide, see `README.md` — this file goes deeper.

---

## 1. Architecture at a Glance

- **Core PHP 8+, procedural only.** No framework, no classes, no ORM. Every
  page is a real, directly-reachable `.php` file — there is no router or
  front controller.
- **One database connection.** Every page starts with
  `require __DIR__ . '/../config/config.php';`, which bootstraps constants,
  the session, the single MySQLi connection, route constants, and then
  autoloads every file under `includes/helpers/`, `includes/middlewares/`,
  and `includes/functions/` via `glob()`.
- **Bootstrap guard.** Every non-entry PHP file starts with:
  ```php
  if (!defined('MP_BOOTSTRAP')) {
      exit('Direct access not permitted.');
  }
  ```
  `MP_BOOTSTRAP` is only ever defined inside `config/config.php`, so no file
  can be reached directly by URL except the real page files.
- **Module-per-folder.** Each feature area (`vendor/`, `customer/`,
  `admin/`, `cart/`, `checkout/`, `orders/`, `products/`, `store/`,
  `artisan/`, `business/`, `official-store/`, `categories/`) is a
  self-contained folder of page files. `config/routes.php` defines a
  `ROUTE_*` constant per module so links are never hand-typed strings.
- **Data layer.** `includes/functions/` has one file per database table
  (`vendors.php`, `products.php`, `orders.php`, `settings.php`, …), each
  exposing plain functions (`mp_find_vendor()`, `mp_all_products_admin()`,
  …) that call the `mp_db_*` helpers in `config/database.php`. All SQL is
  MySQLi prepared statements — nothing is string-concatenated.
- **No template engine.** `templates/header.php` / `footer.php` (public
  site) and `templates/admin-header.php` / `admin-footer.php` (admin panel)
  are plain `require`d PHP files that open/close the shared HTML shell.
  Page-specific logic and markup live together in one file, in classic PHP
  style.

### Request lifecycle

1. Browser requests a page, e.g. `/vendor/dashboard.php`.
2. The page's first line requires `config/config.php`.
3. `config.php` defines `MP_BOOTSTRAP`, loads constants, starts the
   session, opens the MySQLi connection, loads route constants, then
   autoloads every helper/middleware/function file.
4. The page calls a middleware guard if it needs one, e.g.
   `$vendor = mp_require_vendor();` (redirects to login if not authenticated).
5. The page calls data functions to fetch what it needs.
6. The page requires the shared header, prints its own HTML, then requires
   the shared footer.

---

## 2. Full Database Schema

All tables are created by `database.sql`, which is safe to re-run (uses
`CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`
throughout, plus `ON DUPLICATE KEY UPDATE` / `WHERE NOT EXISTS` guards on
every seed row). `database-demo-data.sql` layers realistic sample content
on top (vendors, products, customers, orders) and is optional.

### 2.1 Marketplace structure

**`marketplace_types`** — lookup table, not code. Rows: `artisan`,
`business`, `official`. Every vendor and category belongs to exactly one
marketplace type. Adding a fourth marketplace later is a new row here, not
a new set of `if` branches across the codebase.

**`categories`** — `id, marketplace_type_id, name, slug, sort_order,
is_active`. Categories share one auto-increment ID space across all
marketplace types, so a category ID always unambiguously identifies both
the category and which marketplace it belongs to. Deleting a category is
blocked while it still has products.

**`vendor_category_requests`** — Business Shop vendors must request access
to a category before listing products in it; an admin approves/rejects,
optionally with a `usage_limit` (max products the vendor may list in that
category).

### 2.2 Accounts

**`admin_users`** — `id, name, email, password_hash, role
(super_admin|admin), is_active, last_login_at, created_at`. Only a
`super_admin` can create other admins or disable/enable accounts (never
their own). `is_active` is re-checked on **every request**, not just at
login — disabling an admin logs them out immediately, even mid-session.

**`vendors`** — `id, marketplace_type_id, store_name, slug, email,
password_hash, phone, tax_id, bank_name, bank_account_title,
bank_account_number, status (pending|approved|rejected), terms_accepted_at,
email_verified_at, verification_token, verification_token_expires_at,
created_at`. A vendor cannot create products until `status = approved`
(enforced server-side in `vendor/product-form.php`, not just hidden in the
UI).

**`artisan_profiles`** / **`business_profiles`** — one-to-one extension
tables holding marketplace-type-specific fields (bio/craft story for
artisans; business hours/registration info for shops) so the core
`vendors` table stays lean and shared.

**`customers`** — `id, name, email, password_hash, phone,
email_verified_at, verification_token, verification_token_expires_at,
created_at`.

**`vendor_follows`** — customer ↔ vendor many-to-many, backs "Follow this
Artisan/Shop."

**`vendor_ratings`** — customer ratings/reviews of a vendor (1–5 stars +
optional comment), one row per (customer, vendor) pair.

### 2.3 Catalog

**`products`** — `id, vendor_id, category_id, title, slug, description,
price, stock_quantity, sku, status (active|draft|out_of_stock), is_featured,
is_best_seller, created_at, updated_at`.

### 2.4 Commerce

**`addresses`** — customer's saved shipping addresses.

**`cart_items`** — persisted per-customer cart (`customer_id, product_id,
quantity`), not session-only — a customer's cart survives across devices
and logins.

**`orders`** — `id, customer_id, address_id, status
(pending|processing|completed|cancelled), total_amount, payment_method
(cod|manual|stripe|paypal), created_at`. One `orders` row can contain items
from multiple vendors.

**`order_items`** — one row per (order, product): `order_id, product_id,
vendor_id, quantity, unit_price, item_status`. Carrying `vendor_id` on
every line item is what lets a single multi-vendor cart check out as one
order while each vendor still only ever sees and updates their own items.

**`order_status_history`** — append-only audit trail: every time an
order's or an item's status changes, a row is written here (who, what,
when). `mp_recompute_order_status()` rolls per-item statuses up into the
parent order's overall status automatically (e.g. an order isn't
"completed" until every vendor's items in it are).

**`transactions`** — payment record per order: `order_id, amount, method,
gateway (cod|manual|stripe|paypal), status, created_at`. The `stripe` and
`paypal` gateway values exist today even though no real payment gateway is
wired up yet — swapping in real Stripe/PayPal processing later is a new
code path, not a schema change.

`checkout/place.php` calls `mp_create_order()`
(`includes/functions/orders.php`), which wraps stock validation, the
`orders` row, every `order_items` row, the `transactions` row, and clearing
the cart in **one real MySQLi transaction**
(`mp_db_begin_transaction()` / `mp_db_commit()` / `mp_db_rollback()`) — if
any step fails, nothing is left half-written.

### 2.5 Platform administration (new this phase)

**`settings`** — `setting_key (PK), setting_value, setting_type
(string|number|boolean|json), updated_at`. Every admin-editable
site-wide value (site name, contact info, currency, commission rates,
social links, feature toggles) lives here instead of being a hardcoded PHP
constant, so the admin panel can change it without a code deploy. Read with
`mp_get_setting($key, $default)`, written with `mp_set_setting($key,
$value)` — type is inferred/cast automatically.

**`activity_log`** — append-only audit trail of every meaningful admin,
vendor, or system action: `actor_type (admin|vendor|customer|system),
actor_id, action, entity_type, entity_id, description, ip_address,
created_at`. Indexed on actor, on entity, and on time, so it stays fast
even at high volume. Viewable in the admin panel at **Activity Log**.

**`cms_banners`** — admin-editable hero content: `marketplace_type_id`
(NULL = the main homepage, otherwise a specific marketplace's landing
page), `title, subtitle, cta_label, cta_url, sort_order, is_active`. The
homepage, Artisan Marketplace, and Business Shops landing pages all pull
their hero headline/subhead/CTA from here when an active banner exists,
falling back to sensible defaults if not.

### 2.6 Email verification (simulated)

`vendors` and `customers` both carry `email_verified_at`,
`verification_token`, and `verification_token_expires_at` columns. Tokens
are generated by `includes/functions/verification.php`
(`mp_generate_verification_token()`, 48-hour TTL) and consumed by
`{vendor,customer}/verify.php?token=...`. Since no real SMTP is configured,
the verify link is shown directly on-screen right after registration
(`{vendor,customer}/registered.php`) with an explicit note explaining why —
this is a deliberate, honest stand-in for real email delivery, not a bug.
Swapping in PHPMailer later means changing `mp_notify()`
(`includes/functions/notifications.php`) only; no page needs to change.

---

## 3. User Roles and Flows

### 3.1 Customer

1. **Register** (`customer/register.php`) — name, email, password, phone.
   On success, redirected to `customer/registered.php`, which shows the
   email-verification link on-screen (see §2.6) and a "continue browsing"
   link. A small "!" badge appears next to the account name in the header
   until verified; the account dropdown offers a one-click "Resend
   verification link."
2. **Browse** — homepage (`store/home.php`), Artisan Marketplace
   (`artisan/index.php`), Business Shops (`business/index.php`), the
   Official Store (`official-store/index.php`), category pages
   (`categories/index.php` resolves a slug into the right marketplace),
   product detail pages (`products/details.php`), global search, and
   following a vendor.
3. **Cart** (`cart/`) — add/update/remove; persisted server-side per
   customer, not just in a browser session.
4. **Checkout** (`checkout/index.php`, `checkout/place.php`) — pick/add a
   shipping address, choose a payment method, place the order.
5. **Orders** (`customer/orders.php` list, `orders/details.php` shared
   invoice view) — track status per vendor's items within an order.
6. **Account** — login/logout, verify email, resend verification.

### 3.2 Vendor (Artisan or Business)

1. **Register** (`vendor/register.php`) — a **4-step wizard**:
   - Step 1: vendor type (artisan/business), store name, email, password.
   - Step 2: phone (required), tax ID (optional), and — for Business
     vendors only — which categories to request access to.
   - Step 3: banking details (bank name, account title, account number) —
     all optional at registration, can be added later.
   - Step 4: a live-rendered review of everything entered, plus required
     terms-acceptance checkbox.
   
   Step navigation and the review summary are pure client-side JS (no
   framework) using the browser's native `checkValidity()` /
   `reportValidity()` so a step can't be skipped with invalid data. This
   whole flow can be turned off platform-wide from **Admin → Settings**
   (`vendor_registration_enabled`).
2. **Await approval** — new vendors start `status = pending`. An admin
   approves or rejects from **Admin → Vendor Approvals**. A Business
   vendor's requested categories are separately approved/rejected from
   **Admin → Category Approvals**, optionally with a per-category product
   limit.
3. **Verify email** — same on-screen-link pattern as customers; a reminder
   banner appears on the vendor dashboard until verified, with a resend
   button.
4. **Manage products** — vendor's own product CRUD, gated on
   `status = approved` at the server level (not just hidden in the UI).
5. **Fulfill orders** (`vendor/orders.php`) — a vendor only ever sees and
   updates the `order_items` rows that belong to them, never another
   vendor's items in the same order.
6. **Profile** — store info, artisan bio / business hours (via the
   `artisan_profiles` / `business_profiles` extension tables).

### 3.3 Admin

Logs in at `admin/login.php`. `admin_users.is_active` is checked on every
request (not just at login), and every meaningful action anywhere in the
admin panel is written to `activity_log`. See §4 for the full list of
capabilities.

---

## 4. Admin Panel — Full Capability List

Reachable from the admin nav bar once logged in (`templates/admin-header.php`):

| Page | What it does |
|---|---|
| **Dashboard** | High-level snapshot on login. |
| **Reports** | Total revenue/orders/customers/approved-vendors stat cards; a 14-day revenue+orders dual-axis line chart and an orders-by-status doughnut chart (Chart.js); top 5 vendors by revenue; top 5 products by units sold; products at 5 or fewer units in stock. |
| **Orders** | Platform-wide order list and detail view. |
| **Products** | Every product across every vendor: activate/deactivate, edit (title, category, price, stock, SKU, description, status, featured/best-seller flags), or delete. |
| **Categories** | Add a category (name, marketplace type, sort order); edit, activate/deactivate, or delete (blocked while it still has products) any category; see live product counts per category. |
| **Vendor Approvals** | Approve/reject pending vendor registrations. |
| **Category Approvals** | Approve/reject a Business vendor's requested category access, optionally capping how many products they may list in it. |
| **Customers** | Every customer, with order count and lifetime spend. |
| **Banners** | Add/edit/activate/deactivate/delete the hero content shown on the homepage or a specific marketplace's landing page (see §2.5). |
| **Settings** | Edit every platform-wide setting: site name/tagline/footer text, contact email/phone, currency code & symbol, artisan/business commission rates, social links, and two feature toggles — **vendor registration open/closed** and **maintenance mode**. |
| **Admin Users** | (Super admin only) Create new admin accounts, assign role (`admin`/`super_admin`), disable/enable any admin except themselves. |
| **Activity Log** | The most recent 100 platform events across admins, vendors, customers, and system actions — who did what, to what, and when. |

### Maintenance mode

When **Settings → Maintenance Mode** is on, every visitor except a logged-in
admin sees a plain 503 "under maintenance" page (`templates/header.php`
checks this before any other page content renders); admins keep full,
normal access so they can keep working while it's on.

### Currency

Every price shown anywhere on the site (`mp_currency()`,
`includes/helpers/general.php`) is formatted using the
**admin-configurable** `currency_symbol` setting — changing it in **Admin →
Settings** updates every price display platform-wide immediately, with no
code change.

---

## 5. Deployment

1. Upload the whole project folder to your document root.
2. Edit the four `DB_*` constants in `config/database.php` — this is the
   only file that needs editing.
3. Import `database.sql` (creates every table and seeds default settings,
   the Official Store vendor, and one demo admin — **change the demo admin
   password before going live**). Optionally also import
   `database-demo-data.sql` for realistic sample content.
4. Visit the domain. `config/database.php` shows a plain-English error
   instead of a blank page if the DB credentials are wrong or the import
   didn't run.

No build step, no Composer dependencies, no `.env` file — it's plain PHP
files served directly by Apache/Nginx/PHP's built-in server.

---

## 6. Extension Points (scaffolded, not yet implemented)

The following top-level folders exist with a short README describing their
intended contents, so these can be built later without restructuring the
project: `wishlist/`, `payments/`, `pickup/`, `shipping/`, `wallet/`,
`rewards/`, `notifications/`, `support/`, `reports/`, `analytics/`,
`blog/`, `cms/`, `api/`, `cron/`.

Also deliberately deferred: enterprise SEO tooling, a real PHPMailer/SMTP
email engine (the seam is `mp_notify()` — see §2.6), real Stripe/PayPal
payment processing (the `transactions.gateway` column already accepts
these values — see §2.4), vendor logo/banner file uploads
(`assets/uploads/` is reserved), and clean/pretty URLs (every page is
reachable at its own literal filename by design).

---

## 7. Where Things Live (cheat sheet)

- Add a new admin-editable setting → insert a row into `settings`, then add
  a field for it in `admin/settings.php`'s form.
- Add a new page to an existing module → drop a `.php` file in that
  module's folder; it's reachable immediately, no router config needed.
- Add a new database table → add its `CREATE TABLE IF NOT EXISTS` to
  `database.sql`, then create `includes/functions/<table>.php` for its
  query functions.
- Change what counts as "low stock" → `mp_low_stock_products($threshold,
  $limit)` in `includes/functions/products.php`.
- Change the audit trail format → `mp_log_activity()` in
  `includes/functions/activity.php`.
