# Fahim Store — eCommerce, Monthly Rashan Builder & POS

Fahim Store is a mobile-first online store for school books, stationery, groceries and general items. It also includes a **Build Your Monthly Rashan** grocery planner, an admin panel, and a **POS (point of sale)** for the physical shop. The website and the POS sell from the same inventory.

**Stack:** Core PHP (procedural) · MySQL / MariaDB via **MySQLi prepared statements** · HTML5, CSS3, Bootstrap 5, jQuery, AJAX · Apache `mod_rewrite` clean URLs · Bootstrap Icons. Bootstrap, jQuery and Chart.js are bundled in `assets/vendor/`, so the site needs no CDN. There is no framework, no PDO and no build step.

---

## 1. Requirements

| | |
|---|---|
| PHP | 7.4+ (tested on 8.3) with `mysqli`, `gd` (WebP support recommended), `mbstring`, `fileinfo`, `zip` (for Excel import), `curl` (for WhatsApp) |
| Database | MySQL 5.7+ / 8.0, or MariaDB 10.3+ |
| Web server | Apache 2.4 with `mod_rewrite` and `AllowOverride All`. `mod_headers`, `mod_expires` and `mod_deflate` are recommended. |

## 2. Installation (shared hosting / cPanel)

1. **Upload** the whole project folder to your web root, for example `public_html/`. Keep the hidden `.htaccess` files.
2. **Create a database** in cPanel → *MySQL Databases*: create the database and a user, then give that user *All Privileges* on it.
3. **Import the database:** open phpMyAdmin → select the database → *Import* → choose `database/fahim_store.sql` → *Go*. This creates every table and loads the sample categories, products, banners, settings and admin users.
4. **Configure the database connection.** Copy the lines below into a new file `includes/config.local.php`. This file is the recommended place for your settings because it is never overwritten by updates. You can also edit `includes/config.php` directly.
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_db_name');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('APP_URL', 'https://www.yourdomain.pk');   // no trailing slash
   define('APP_SECRET', 'put-a-long-random-string-here');
   ```
5. **Make folders writable** (permissions 755, or 775 if your host needs it): `uploads/` (and its subfolders) and `logs/`.
6. Visit your domain. The admin panel is at `https://yourdomain.pk/admin/`.

**Installing in a sub-folder** (for example `/shop`): set `APP_URL` to `https://domain.pk/shop`, set `RewriteBase /shop/` in `.htaccess`, and change `ErrorDocument` to `/shop/index.php`.

## 3. Initial admin login

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@fahimstore.pk` | `Admin@123` |
| Cashier (POS, orders, customers) | `cashier@fahimstore.pk` | `Cashier@123` |
| Sample customer (storefront) | `03211234567` | `Customer@123` |

> **Change these passwords right after installing.** Use *Admin → My password*, or *Admin Users & Roles*.

## 4. Configuration: email, WhatsApp, payments

All credentials live in `includes/config.php` / `config.local.php`. They are never stored in the database or in the code.

```php
// SMTP (leave SMTP_HOST empty to use PHP mail())
define('SMTP_HOST', 'smtp.gmail.com');  define('SMTP_PORT', 587);  define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'orders@yourdomain.pk');  define('SMTP_PASS', 'app-password');
define('MAIL_FROM', 'orders@yourdomain.pk');

// WhatsApp Cloud API (Meta)
define('WHATSAPP_PHONE_ID', '1234567890');
define('WHATSAPP_TOKEN', 'EAAG...');
```

After adding credentials, open **Admin → Website Settings → Notifications** and:
- set the email and WhatsApp number that should receive new orders;
- turn on WhatsApp sending.

Even without the API, every order screen has one-click **WhatsApp / Call** buttons.

**Payments:** Cash on Delivery, Cash on Pickup, Bank Transfer, Easypaisa and JazzCash are set up as manual methods. Edit the account details in **Admin → Payment Methods**. Customers never need to pay online to place an order. Connecting an automatic gateway is possible later, by adding the merchant keys in the config file.

## 5. Deploying to production

- Set `APP_ENV` to `'production'`. This is the default; `config.local.php` can override it. In production, PHP errors are written to `logs/php-error.log` and are never shown to visitors.
- Install SSL, then uncomment the **Force HTTPS** block in `.htaccess`.
- Minified CSS/JS files (`*.min.css`, `*.min.js`) are included, and production mode serves them automatically. After you edit `assets/css/style.css` or the JS files, run `php tools/minify.php` to regenerate them.
- Add your domain to Google Search Console and submit `https://yourdomain.pk/sitemap.xml`. The sitemap is generated live.
- Optional cron job (weekly) to clear old logs: `find /path/to/logs -name "*.log" -mtime +30 -delete`.

## 6. Backup & restore

**Backup.** Back up both the database and the `uploads/` folder.

- In cPanel: phpMyAdmin → *Export* → *Quick* → SQL. Also download `uploads/` with the File Manager (*Compress*, then download).
- From the command line:
  ```bash
  mysqldump -u USER -p --single-transaction --routines DB_NAME > fahim-backup-$(date +%F).sql
  ```
  ```bash
  tar czf fahim-uploads-$(date +%F).tar.gz uploads/
  ```

**Restore.**
1. Create an empty database.
2. Import your `.sql` backup with phpMyAdmin, or run `mysql -u USER -p DB_NAME < backup.sql`.
3. Upload and extract the `uploads/` archive.
4. Update the database credentials in `config.local.php`.

**Daily automatic backup (cron example):**
```bash
0 3 * * * mysqldump -u USER -pPASS --single-transaction DB_NAME | gzip > ~/backups/fahim-$(date +\%F).sql.gz
```

## 7. Project structure

```
index.php              Front controller (clean-URL router)
.htaccess              Rewrites, security rules, caching, compression
favicon.ico
includes/              Core library (not web accessible)
  bootstrap.php        Loads everything below
  config.php           Settings & credentials (+ optional config.local.php)
  database.php         MySQLi prepared-statement helpers, transactions
  functions.php        Escaping, URLs, settings, formatting, pagination, safe_html
  security.php         Sessions, CSRF, login rate limiting, headers
  auth.php             Customer & admin auth, roles/permissions
  catalog.php          Category tree, product queries, pricing, sellable items
  search.php           Search engine (synonyms, typo tolerance, scoring)
  inventory.php        Central stock engine + movement log, purchases
  cart.php             Cart, delivery fees, coupons
  orders.php           Order creation, status workflow, stock policy, pickup slots
  notify.php           Admin notifications, SMTP email, WhatsApp Cloud API
  seo.php              Meta tags, OG/Twitter, JSON-LD schema, breadcrumbs, redirects
  upload.php           Secure image upload -> resized WebP + thumbnail
pages/                 Storefront pages (home, category, product, cart, checkout, rashan, account/...)
partials/              Header, footer, product card, listing/filter components
api/                   AJAX/JSON endpoints: search, cart, wishlist, rashan, newsletter, products, categories
admin/                 Admin panel (39 modules) + admin/ajax + admin/assets (POS, admin JS/CSS)
assets/                css, js, images (logo, favicon, OG image, category art), vendor libraries
uploads/               Uploaded images (PHP execution disabled)
database/              schema.sql (structure) and fahim_store.sql (structure + starter data)
tools/                 Developer tools: seed.php, build-database.sh, minify.php, sample-images.php
docs/ARCHITECTURE.md   Database design, flows, permissions, order & inventory logic
```

## 8. URLs

| Page | URL |
|---|---|
| Home | `/` |
| Shop / search | `/shop/`, `/search/?q=class+5+maths` |
| Categories (any depth) | `/category/books/`, `/category/books/school-books/class-5/` — `/books/...` redirects here |
| Product | `/product/english-text-book-class-5/` |
| Brand / offers | `/brand/dalda/`, `/offers/`, `/offers/school-reopening-offer/` |
| Monthly Rashan | `/monthly-rashan/` |
| Cart / checkout / tracking | `/cart/`, `/checkout/`, `/track-order/` |
| Account | `/login/`, `/register/`, `/my-account/` (orders, rashan-lists, addresses, wishlist, profile, password) |
| Content pages | `/about-us/`, `/faq/`, `/contact/` |
| SEO | `/sitemap.xml`, `/robots.txt` |
| Admin | `/admin/` (pages work with or without `.php`) |

## 9. Feature checklist

**Storefront**
- [x] Sticky responsive header; delivery-area picker; live cart and wishlist counts
- [x] Desktop mega menu; mobile accordion menu and bottom navigation
- [x] AJAX smart search: instant suggestions with thumbnails and prices, category suggestions, recent and popular searches, mobile search overlay, highlighting, synonyms (atta → flour, surf → detergent), typo tolerance, partial words, SKU/barcode/ISBN, Urdu names
- [x] Homepage with 13 admin-managed sections: hero slider, categories, featured, rashan CTA, best sellers, promotions, new arrivals, promo banners, weekly deals, brands, recommended, testimonials, newsletter
- [x] Category pages with subcategory cards and product counts; filters (price, brand, stock, sale); sorting; SEO pagination
- [x] Product page: gallery, variations, stock status, min/max quantity, reviews, related products, recently viewed
- [x] Cart: live totals, coupons, stock validation; guest checkout; account creation at checkout; saved addresses
- [x] Delivery (area-based fees, free-delivery threshold, minimum order) or store pickup (time slots)
- [x] Payment by COD, cash on pickup, bank transfer, Easypaisa or JazzCash, with transaction ID
- [x] Order success page, guest order tracking, email and WhatsApp notifications
- [x] Customer account: dashboard, order history with timeline, reorder, addresses, wishlist, rashan lists, profile, password

**Monthly Rashan builder**
- [x] Grocery items grouped by category; search and category filter; size/unit selection; +/- quantities
- [x] Live budget, total, remaining (server-verified via AJAX), total items and units
- [x] Save, edit, multiple lists, duplicate for this month, delete, notes; drafts kept in the browser for guests
- [x] Add everything to the cart, or order directly (pickup or delivery). Rashan orders are flagged for admin, with a dashboard alert and a printable picking list.

**Admin**
- [x] Dashboard: KPIs, 30-day chart, best sellers, low stock, recent orders, pending-rashan alert
- [x] Categories with unlimited levels: drag ordering, images, SEO, enable/disable, automatic redirects
- [x] Products: full editor (variations, multiple images with preview, SEO, related products, flags), quick add, duplicate, bulk actions, bulk price/stock editing, duplicate SKU/barcode detection
- [x] Bulk import from CSV or Excel with validation preview, bulk price/stock update mode, CSV export
- [x] Brands, featured/best sellers, homepage sections (enable, reorder, configure), banners with schedules, promotions, coupons, testimonials, newsletter
- [x] Orders: filters, search, bulk status, status workflow, internal and customer notes, payment status, picking checklist, invoice, picking list, WhatsApp/call/email, CSV export
- [x] Inventory: stock list with valuation, adjustments, purchases (stock-in from suppliers), suppliers, full movement history with date, quantity, reason and admin
- [x] POS: barcode scanning, search, customer search and quick add, discounts, cash and change, walk-in/pickup/delivery, pending rashan orders, drafts, thermal receipt, register open/close with cash reconciliation, keyboard shortcuts
- [x] Delivery areas, pickup settings, payment methods, content pages with FAQs, SEO management (global, static pages, redirects, synonyms, health check, search analytics)
- [x] Reviews moderation, contact messages, notifications with live polling, reports (sales, products, categories, inventory valuation, product movement, customers, POS sessions) with CSV export
- [x] Admin users and roles with 16 permission types; website settings

**Security & performance**
- [x] MySQLi prepared statements everywhere; `password_hash`; CSRF tokens on every form and AJAX call; output escaping; HTML sanitising
- [x] Session hardening; login rate limiting; role checks on every admin page and endpoint
- [x] Server-side price, stock and coupon recalculation; transactional, row-locked stock changes with a double-deduction guard
- [x] Uploads validated by content, re-encoded to WebP, no script execution in `uploads/`; sensitive folders blocked
- [x] Lazy-loaded images, WebP, compression, long-term caching, minified assets, indexed queries, local vendor assets

## 10. Developer notes

- Rebuild the bundled database from the schema and seed script:
  ```bash
  DB_USER=root tools/build-database.sh
  ```
  This regenerates the sample SVG images and `database/fahim_store.sql`.
- Brand assets: the SVG sources are `assets/images/logo.svg`, `logo-white.svg`, `logo-icon.svg` and `favicon.svg`. The PNG/ICO versions are rendered from them with `tools/render-brand.js`.
- See `docs/ARCHITECTURE.md` for the database design and business rules.
