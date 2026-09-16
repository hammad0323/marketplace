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
├── includes/             Shared PHP: functions.php, header/footer, section renderer,
│                          seo_head.php, mailer.php, email_templates.php
├── uploads/              User-uploaded images (products, categories, banners, blog, logo/favicon)
├── logs/                 mail.log — email delivery failures land here, never crash checkout
├── index.php, shop.php, product.php, cart.php, checkout.php, ...
├── robots.php, ads.php, sitemap.php   Dynamic, admin-editable SEO files (see below)
├── .htaccess             Clean-URL rewrite rules (see "Clean URLs" below)
├── database.sql          Full schema + seed data
└── README.md
```

## Installation (shared hosting / cPanel)

1. **Upload** the `fashion-store` folder to your web root (or a subdomain's document root).
2. **Create a MySQL database** in cPanel → MySQL Databases, and a user with full privileges on it.
3. **Edit `config/config.php`** — set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to your real values. Nothing else in this file needs to change.
4. **Import `database.sql`** via phpMyAdmin (or `mysql -u user -p dbname < database.sql`). This creates every table and seeds demo categories, products, ten homepage designs, payment/shipping methods and site settings.
5. **Make `uploads/` and `logs/` writable** by the web server (`chmod 755` is normally enough; `775`/`777` if your host requires it).
6. **Confirm `mod_rewrite` is enabled** and `AllowOverride All` is set for the folder (standard on cPanel/Apache) so `.htaccess` can serve clean URLs — see below.
7. Visit your domain. The storefront and admin panel are both live immediately.

### Local development

```bash
php -S localhost:8000
```
Point `DB_HOST` etc. at a local MySQL/MariaDB instance and import `database.sql`.
Note: PHP's built-in server does not read `.htaccess`, so clean URLs (see
below) only work under real Apache — use a local Apache/XAMPP/MAMP stack, or
a Docker `php:apache` image, to test them.

## Default Logins

| Area  | URL             | Email                     | Password   |
|-------|-----------------|----------------------------|-----------|
| Admin | `/admin/login` | `admin@fashionstore.pk` | `Admin@123` |

**Change this password immediately after first login** (Admin Users page), and
create additional admin accounts with scoped roles (Manager, Content Manager,
Order Manager) from **Admin Users**.

## Clean URLs

Every page is reachable without a `.php` extension — `/shop`, `/cart`,
`/checkout`, `/blog`, `/account/dashboard`, etc. — via `.htaccess` rewrite
rules, and product/category/blog/page links use pretty slugs:

```
/product/embroidered-lawn-suit
/category/women
/category/women-lawn      (subcategories work the same way)
/blog/style-guide-2026
/page/about-us
/account/order/1042
/invoice/1042
```

Any old `/something.php` link (bookmarked, indexed by Google, or typed
manually) gets a permanent **301 redirect** to its clean equivalent, so
nothing breaks and search engines consolidate to the new URLs. All internal
links across the storefront are generated through a small helper
(`url()`, `product_url()`, `category_url()`, `blog_url()`, `page_url()` in
`includes/functions.php`) — never hardcode a `.php` link when adding pages.

## Complete SEO

- **Per-page meta**: every product, category, page and blog post has its own
  SEO title, meta description, keywords, Open Graph image, canonical URL,
  and robots directive — all editable from the admin (Products, Categories,
  Pages, Blog). `includes/seo_head.php` renders title/description/keywords/
  canonical/OG/Twitter Card tags centrally, plus **JSON-LD structured data**:
  Organization (site-wide), Product (with price/availability/rating),
  BreadcrumbList, and Article schema — so rich results (star ratings, price,
  breadcrumbs) can show up directly in Google search.
- **`sitemap.php`** (served at `/sitemap.xml`) is a full XML sitemap with
  `lastmod`, `changefreq`, `priority`, and an **image sitemap extension**
  (`<image:image>`) listing every product's gallery photos, category images
  and blog featured images — so Google Images can index your catalog too.
- **`robots.php`** (served at `/robots.txt`) and **`ads.php`** (served at
  `/ads.txt`) are dynamic — edit their content from **Admin → Settings →
  robots.txt & ads.txt** without touching a file. `robots.txt` always
  references the live sitemap.
- **Google/Bing site verification** and a **Twitter handle** can be set from
  Settings and are emitted as meta tags automatically.

## Ten Selectable Homepage Designs

**Admin → Website → Homepage Selector** picks which of **ten** homepage
designs is live — no hard-coded templates. Each homepage has its own:

- **Hero style** — slider (full-bleed carousel), split (image one side, text
  the other), centered (statement text over a dark gradient), or collage (a
  4-tile image grid) — set per homepage in `homepage_configs`.
- **Banners** and an ordered list of **sections**, each independently
  add/edit/remove/reorder-able: category grid, product collection (grid or
  slider, filtered by featured/new/best-seller/trending/sale/category/manual
  picks), promo banner, image+text split, brand story, **text banner**,
  **two-column** (text/text or text/image), **brand strip**, **features/
  overview** (icon + heading + text, e.g. "Free Shipping"), **stats/
  counters** (e.g. "5000+ Happy Customers"), testimonials, Instagram feed,
  newsletter signup, or raw custom HTML.

The ten seeded designs (`admin/homepage_settings.php`) are named after their
structural inspiration — Al Imran Edit, Gul Ahmed Edit, Alkaram Studio Edit,
Zeen Edit — plus six original layouts (Editorial Luxe, Boutique Minimal,
Festive Grand, Modern Grid, Heritage Weave, Studio Mono) built from the same
section library. Because everything is data-driven
(`includes/section_renderer.php` + `homepage_sections`/`homepage_section_items`
tables), an admin can fully re-arrange any of the ten without a developer,
and a developer can add an eleventh by inserting one `homepage_configs` row.

## Branding

**Admin → Settings → Branding**: upload a logo (shown in the header instead
of the text wordmark) and a site icon/favicon, and pick two theme colors
(accent + dark/ink) with color pickers — they're injected as CSS custom
properties (`--clr-accent`, `--clr-ink`) on every page, so the whole site's
color scheme updates instantly. Store name, tagline, support email, phone
and address are also editable there and flow through to the header, footer,
invoices, emails and structured data.

## Email

`includes/mailer.php` is a small, dependency-free **SMTP client** (STARTTLS/
SSL, AUTH LOGIN) that reads credentials from **Admin → Settings → Email /
SMTP**; if no SMTP host is configured it falls back to PHP's `mail()`. Every
send is wrapped so a bad or missing mail config **never breaks checkout or
registration** — failures are written to `logs/mail.log` instead of
throwing. A **Send Test Email** button on the Settings page lets you verify
credentials immediately. Wired-up emails (`includes/email_templates.php`):

- Order confirmation to the customer + new-order alert to the store owner (checkout)
- Order status update to the customer, with an admin opt-out checkbox per update (Admin → Orders)
- Welcome email on account registration
- Contact form submissions, emailed to the store's support address

## What's implemented

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
  refunded), status history log, admin notes, printable invoice, automatic
  customer email on every status change.
- **Customers** — registration/login, **Google OAuth login** (toggle +
  credentials in Settings; standard authorization-code flow in
  `google_login.php` / `google_callback.php`, clean callback URL at
  `/google-callback`), account dashboard, order history, saved addresses,
  wishlist, password change, admin-side reset.
- **Marketing** — coupons, product reviews (moderated), newsletter capture +
  CSV export, blog with categories and SEO fields.
- **Security** — MySQLi prepared statements everywhere, `password_hash`/
  `password_verify` for all accounts, CSRF tokens on every state-changing
  form, session-based auth with `session_regenerate_id` on login, upload
  validation (MIME + extension allow-list, PHP execution disabled under
  `uploads/`), `.htaccess` denial on `config/`, `includes/` and `logs/`.

## What you'll want to configure before going live

- **Live payment gateway integration** — the DB, admin UI and order model
  are ready for EasyPaisa/JazzCash/Stripe/Square/Moneris; the gateway-specific
  redirect/webhook code is a focused follow-up per gateway once you have real
  merchant credentials.
- **Real SMTP credentials** — the mailer works out of the box once you enter
  a host/port/username/password in Settings; use the Send Test Email button
  to confirm.
- **Google OAuth credentials** — create a project in Google Cloud Console,
  add an OAuth 2.0 Client ID (Web application), set the authorized redirect
  URI to `https://yourdomain.com/google-callback`, and paste the client
  ID/secret into Settings → Google Login.

## Admin Panel Overview

`admin/` — Dashboard (stats + 7-day sales chart) · Products · Categories ·
Brands · Attributes · Orders · Customers · Coupons · Reviews · Newsletter ·
Homepage Selector (10 designs) · Banners · Homepage Sections (+ per-section
Items) · Pages (CMS, CKEditor) · Blog · Social Links · Payment Methods ·
Shipping · Settings (branding, contact info, guest checkout, Google login,
tax, SMTP, SEO defaults, robots.txt/ads.txt) · Admin Users (roles: Super
Admin / Manager / Content Manager / Order Manager).
