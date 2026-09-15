# Noor Couture — Premium Pakistani Fashion E-Commerce Platform

A complete, dynamic e-commerce platform built in **Core PHP (procedural) + MySQLi**,
inspired by the visual language of premium Pakistani fashion retailers
(Al Imran Fabrics, Gul Ahmed, Alkaram Studio, Zeen Woman). No framework,
no ORM, no build step — upload, import the database, and go.

## Technology

PHP 8 (procedural) · MySQL/MariaDB (MySQLi, prepared statements) · Bootstrap 5 ·
jQuery · vanilla JS + AJAX (`fetch`) · SweetAlert2 · Select2 · CKEditor 5 ·
Bootstrap Icons · AOS (scroll animations) · Chart.js (admin dashboard)

All third-party libraries load from CDN — there is nothing to `npm install`.

## Folder Structure

```
fashion-store/
├── admin/              Admin panel (auth-gated) — catalog, orders, homepage builder, settings
│   └── includes/        Shared admin layout (sidebar/header/footer)
├── ajax/                JSON endpoints: cart, wishlist, search, newsletter
├── account/              Customer account area (orders, addresses, wishlist, profile)
├── assets/
│   ├── css/              style.css (storefront), admin.css (admin panel)
│   ├── js/                main.js (storefront), admin.js (admin panel)
│   └── img/               Placeholder/fallback images (SVG, no external assets)
├── config/               config.php (edit DB credentials here), db.php
├── includes/             Shared PHP: functions.php, header/footer, section renderer
├── uploads/              User-uploaded images (products, categories, banners, blog)
├── index.php, shop.php, product.php, cart.php, checkout.php, ...
├── database.sql          Full schema + seed data
└── README.md
```

## Installation (shared hosting / cPanel)

1. **Upload** the `fashion-store` folder to your web root (or a subdomain's document root).
2. **Create a MySQL database** in cPanel → MySQL Databases, and a user with full privileges on it.
3. **Edit `config/config.php`** — set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to your real values. Nothing else in this file needs to change.
4. **Import `database.sql`** via phpMyAdmin (or `mysql -u user -p dbname < database.sql`). This creates every table and seeds demo categories, products, homepage sections, payment/shipping methods and site settings.
5. **Make `uploads/` writable** by the web server (`chmod 755` is normally enough; `775`/`777` if your host requires it).
6. Visit your domain. The storefront and admin panel are both live immediately.

### Local development

```bash
php -S localhost:8000
```
Point `DB_HOST` etc. at a local MySQL/MariaDB instance and import `database.sql`.

## Default Logins

| Area  | URL             | Email                     | Password   |
|-------|-----------------|----------------------------|-----------|
| Admin | `/admin/login.php` | `admin@fashionstore.pk` | `Admin@123` |

**Change this password immediately after first login** (Admin Users page), and
create additional admin accounts with scoped roles (Manager, Content Manager,
Order Manager) from **Admin Users**.

## What's implemented

- **Four selectable homepage styles** — one admin-managed Homepage Selector
  (`admin/homepage_settings.php`) picks which style (1–4) is live. Each style
  has its own **banners** and its own ordered **sections** (categories grid,
  product collections, promo banners, image+text splits, brand story,
  newsletter, testimonials, Instagram feed, custom HTML), all editable from
  the admin without touching code. This is a genuine homepage *builder*
  rather than four hard-coded templates — an admin can reorder, add, remove
  or reconfigure sections on any of the four styles independently.
- **Catalog** — unlimited-depth-1 categories/subcategories, brands, products
  with image galleries, rich-text descriptions (CKEditor), tags, flags
  (featured/new/best-seller/trending), and full **variations** (Size, Color,
  or any custom attribute you define) with per-variation SKU/price/stock.
- **Shopping** — DB-backed cart (works for guests via session and merges into
  the account cart on login/register), wishlist, AJAX add-to-cart, live
  search suggestions, filtering (price/size/color/brand/sale) and sorting on
  the shop page.
- **Checkout** — guest checkout (togglable) or account checkout, coupon codes
  (fixed/percentage, min order, expiry, usage limits), city-based shipping
  rates with a free-shipping threshold, and order confirmation + printable
  invoice.
- **Payments** — Cash on Delivery is fully wired end-to-end. EasyPaisa,
  JazzCash, Stripe, Square and Moneris are modeled as first-class payment
  methods (admin can enable/disable each and store credentials), and orders
  placed through them are recorded with `payment_status = pending` for
  manual/offline confirmation. **Wiring a specific gateway's live API** (redirect
  flow, webhook/IPN handling) is a config-only follow-up once you have real
  merchant credentials — the credential storage, UI toggles and order model
  are already in place; see `admin/payments.php`.
- **Orders** — full status pipeline (pending → confirmed → processing →
  packed → shipped → out for delivery → delivered / cancelled / returned /
  refunded), status history log, admin notes, printable invoice.
- **Customers** — registration/login, **Google OAuth login** (toggle +
  credentials in Settings; standard authorization-code flow in
  `google_login.php` / `google_callback.php`), account dashboard, order
  history, saved addresses, wishlist, password change, admin-side reset.
- **Marketing** — coupons, product reviews (moderated), newsletter capture +
  CSV export, blog with categories and SEO fields.
- **SEO** — per-product/category/page meta title/description/keywords,
  `sitemap.php` (dynamic XML sitemap) and `robots.txt`.
- **Security** — MySQLi prepared statements everywhere, `password_hash`/
  `password_verify` for all accounts, CSRF tokens on every state-changing
  form, session-based auth with `session_regenerate_id` on login, upload
  validation (MIME + extension allow-list, PHP execution disabled under
  `uploads/`), `.htaccess` denial on `config/` and `includes/`.

## What you'll want to configure before going live

- **SMTP / transactional email** — credentials are stored under Settings →
  Email, but no mailer is wired up yet (no external SMTP library is bundled,
  by design, to keep the project dependency-free). Drop in your preferred
  mailer (PHPMailer, or raw `mail()`/SMTP socket calls) in a small
  `includes/mailer.php` and call it from `checkout.php` and
  `admin/order_view.php` for order/status emails.
- **Live payment gateway integration** — see above; the DB, admin UI and
  order model are ready, the gateway-specific redirect/webhook code is a
  focused follow-up per gateway.
- **Google OAuth credentials** — create a project in Google Cloud Console,
  add an OAuth 2.0 Client ID (Web application), set the authorized redirect
  URI to `https://yourdomain.com/google_callback.php`, and paste the client
  ID/secret into Settings → Google Login.

## Admin Panel Overview

`admin/` — Dashboard (stats + 7-day sales chart) · Products · Categories ·
Brands · Attributes · Orders · Customers · Coupons · Reviews · Newsletter ·
Homepage Selector · Banners · Homepage Sections (+ per-section Items) ·
Pages (CMS, CKEditor) · Blog · Social Links · Payment Methods · Shipping ·
Settings (store info, guest checkout, Google login, tax, SMTP, default SEO) ·
Admin Users (roles: Super Admin / Manager / Content Manager / Order Manager).

## Notes on the four homepages

Rather than four separate, hard-coded PHP templates, `index.php` renders the
**currently selected** homepage (1–4) by reading its banners and ordered
section list from the database and delegating each section to
`includes/section_renderer.php`. This is what makes the homepages a true
*builder* — the seed data configures four visually distinct starting layouts
(mirroring the structural DNA of the four reference brands), and every piece
of it — order, content, images, filters — is editable from
**Admin → Website → Homepage Sections** without a developer.
