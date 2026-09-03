# Beglet — Multi-Vendor E-Commerce Marketplace

Beglet is a full multi-vendor marketplace platform built with **Core PHP + MySQLi**
(no framework, no ORM, no OOP application architecture) — designed to run in a
`/beta/` subdirectory, e.g. `https://www.beglet.com/beta/`.

## What's included

- **Four roles**: Admin, Shop Owner, Shop Staff (with granular permissions), Customer
- Category/subcategory catalog with per-category commission rates
- Product catalog: simple/variable/digital/physical products, galleries, variations
- Cart → Checkout → **automatic multi-vendor order splitting** (one parent order,
  one shop-order per vendor, per-line-item commission calculated by category)
- Commission system: invoices, due dates, paid/pending/overdue tracking, a
  30-day-overdue shop lockout, and a manual payment-proof submission + admin
  approval workflow
- Shop Staff accounts with a permission matrix and a global (admin-configurable)
  employee limit per shop
- Shop builder: homepage-style sections per shop (featured/latest/best-selling/
  custom product carousels) with product selection
- Admin dashboard with Chart.js graphs (sales, orders, commission), reports with
  CSV export, banners, homepage section builder, payment method configuration
- SEO: per-entity meta/OG/Twitter fields, a transparent 0–100 SEO score with
  actionable tips, FAQ (AEO) fields with FAQPage JSON-LD, GEO fields (entity
  description/key facts), `sitemap.php`/`robots.php`, and JSON-LD on
  homepage/product pages
- Google OAuth sign-in for **customers only** (admin/vendor/staff always use
  email+password)
- CSRF protection, prepared statements everywhere, image upload validation
  (extension + MIME + `getimagesize`), session-based role guards

## Folder structure

```
beglet/
├── beta/                 ← point your domain/subdirectory here
│   ├── config/            config.php, database.php, constants.php, functions.php
│   ├── includes/          header/footer/navbar/alerts/seo + dashboard chrome
│   ├── actions/           POST/AJAX endpoints (auth, cart, checkout, wishlist, review...)
│   ├── admin/              admin panel (categories, products, shops, orders,
│   │                       commissions, payments, banners, seo, settings, reports...)
│   ├── shop/               shop owner + staff panel (products, orders, staff,
│   │                       commissions, payments, shop design/sections)
│   ├── employee/           thin staff-facing wrapper around shop/ pages
│   ├── customer/           customer dashboard, orders, addresses, reviews
│   ├── uploads/            product/shop/category/banner images (PHP execution disabled)
│   ├── assets/             css/, js/, images/
│   └── index.php, product.php, category.php, shop.php, cart.php, checkout.php, ...
└── database/
    └── beglet.sql          full schema + demo data (import this one file)
```

## Installation

1. Upload the whole project (or just the `beta/` and `database/` folders) to your host.
2. Create a MySQL database and import `database/beglet.sql` (phpMyAdmin → Import,
   or `mysql -u youruser -p yourdb < database/beglet.sql`).
3. Edit `beta/config/database.php` with your DB host/name/user/password.
4. Edit `beta/config/constants.php` and set `BASE_URL` to your real domain, e.g.
   ```php
   define('BASE_URL', 'https://www.beglet.com/beta/');
   ```
   (On `localhost`, the app auto-detects its own path so it works out of the box
   for local testing — you only need to set `BASE_URL` for the live/production URL.)
5. Make sure `beta/uploads/` is writable by the web server.
6. Open `https://www.beglet.com/beta/`.

No other file needs to be touched to change the install path — every internal
link is built from `base_url()` / `asset_url()` / `admin_url()` / `shop_url()` /
`employee_url()` / `customer_url()` in `config/functions.php`.

### Demo accounts (password for all: `Demo@1234`)

| Role | Email |
|---|---|
| Admin | `admin@beglet.com` |
| Shop Owner (Tech Store) | `tech@beglet.com` |
| Shop Owner (Fashion Hub) | `fashion@beglet.com` |
| Shop Owner (Home Store) | `home@beglet.com` |
| Shop Owner (Beauty Store — payment overdue) | `beauty@beglet.com` |
| Shop Staff (Tech Store) | `usman.staff@beglet.com` |
| Shop Staff (Fashion Hub) | `hina.staff@beglet.com` |
| Customer | `ali.khan@example.com` |

Admin panel: `/beta/admin/login.php` · Vendor/Staff: `/beta/shop/login.php` ·
Customer: `/beta/login.php`

**Change these passwords before deploying publicly.**

## Configuring things from the Admin panel

- **Site name, logo, favicon, description, colors, currency, footer/copyright**:
  Admin → Settings
- **Employee limit per shop** and **commission due days**: Admin → Settings
  (a per-shop override is stored in `shops.employee_limit_override`, settable
  directly in the database if you need a one-off exception)
- **Commission rate per category** (percentage or fixed): Admin → Categories → Edit
- **Payment methods** (enable/disable, instructions, bank details): Admin → Payments → Manage Payment Methods
- **Homepage sections** (enable/disable, reorder, heading, item count): Admin → SEO → *(Homepage Sections card)* or Admin sidebar → Homepage Sections
- **SEO / AEO / GEO** per homepage/category/shop/product, with a live-computed
  score and tips: Admin → SEO
- **Sitemap / robots.txt**: Admin → SEO → Sitemap & Robots card (or visit
  `/beta/sitemap.php` and `/beta/robots.php` directly)
- **Google OAuth** (customer login only): Admin → Settings → Google OAuth —
  set the Client ID/Secret and whitelist the redirect URI shown on that page
- **Maintenance mode**: Admin → Settings

## Notes on scope

This is a complete, working implementation of the full spec's core flows
(auth for all 4 roles, catalog, cart/checkout with real multi-vendor order
splitting and per-category commission math, commission payment + overdue
shop lockout, staff permissions, shop builder, SEO/AEO/GEO with a real
scoring algorithm, sitemap/robots, admin CSV exports, Chart.js dashboards).
A few pieces are intentionally simple rather than exhaustive given the size
of the spec — e.g. the SEO score uses a transparent, documented heuristic
rather than a black-box algorithm; the shop-section builder's drag-reorder
is a lightweight HTML5 drag/drop rather than a full library; live gateway
integrations (Stripe/JazzCash/Easypaisa) are wired into the payment-methods
architecture (enable/configure per method) but only Cash on Delivery, Self
Collection and Bank Transfer are usable end-to-end out of the box, since the
others need real merchant credentials.

## Tested

`database/beglet.sql` was imported into a real MariaDB 10.11 instance and the
full app was exercised end-to-end against PHP's built-in server: admin login →
dashboard stats/graphs; customer login → add products from two different
shops to cart → checkout → verified the parent order was split into two
`shop_orders` with correct per-item, per-category commission amounts and
vendor earnings; shop owner and shop staff login with permission-gated
access; SEO score page; sitemap/robots output.
