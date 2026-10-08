# Eashal Mirha — Luxury Pret & Bridal E-commerce Store

A complete online store in **core PHP + MySQL**. There is no framework and no build step, and it runs on any cPanel / shared hosting.
The theme is gold and black, with parallax banners, scroll animations, carousels, a fully editable homepage and a strong admin panel.

---

## 1. Install (5 minutes)

1. **Create a database** — cPanel → *MySQL Databases*: create a database and a user, and give the user *All Privileges*.
2. **Upload** the contents of this folder to `public_html/` (or a sub-folder) and extract the zip there.
3. Open **`https://yourdomain.com/install/`** in your browser, then enter:
   - your database name, user and password
   - your store name, admin email and admin password
4. Click **Install Store**. When it finishes, **delete the `/install` folder** from the server.
5. Log in to the admin panel at **`https://yourdomain.com/admin`**.

**Prefer phpMyAdmin?** Import `install/database.sql`, then put your database details into `config.php`.
The default login is then `admin@eashalmirha.com` / `Admin@123`. Change it right away under *Admin Users*.

> **Requirements:** PHP 7.4+ (8.x recommended), MySQL 5.7+ / MariaDB 10.3+, and Apache with `mod_rewrite` (standard on cPanel).
> The folders `uploads/` and the root folder (for sitemap/robots files) should be writable (755).

---

## 2. Clean URLs (no `.php`)

`.htaccess` serves every page without `.php`:

| Page | URL |
|---|---|
| Home | `/` |
| Shop / filters / search | `/shop`, `/shop?filter=new`, `/shop?q=bridal` |
| Category / sub-category | `/category/bridal`, `/category/bridal/bridal-lehenga` |
| Product | `/product/noor-e-shab` |
| Cart / checkout | `/cart`, `/checkout` |
| Account | `/login`, `/register`, `/account`, `/wishlist`, `/track-order` |
| Pages | `/page/about-us`, `/contact` |
| Admin | `/admin`, `/admin/products`, `/admin/orders` … |

If someone opens an old `.php` link, they are redirected automatically.

---

## 3. Storefront features

- **Homepage with 10 sections.** Each one can be switched on/off, re-ordered and re-titled from the admin:
  1. Hero banner carousel (fade/slide, Ken Burns zoom, animated text)
  2. Shop by Category (6 categories with their sub-categories)
  3. New Arrivals carousel
  4. Parallax banner
  5. Best Sellers carousel
  6. Two promo banners
  7. Shop the Collection (tabs by category)
  8. Brand promises / features strip
  9. Testimonials carousel (from approved reviews)
  10. Newsletter signup
- Mega-menu with categories and sub-categories, a mobile drawer menu, live search, and a sticky header.
- Shop page with filters (category, collection, size, price, in stock), sorting and pagination.
- Product page with a gallery, hover zoom, sizes, colours, quantity, Buy Now, wishlist, reviews, a size guide, a WhatsApp order button and related products.
- Slide-out mini cart, coupons, and delivery charges calculated by city.
- **Guest checkout and logged-in checkout** (a guest can also create an account during checkout).
- Order confirmation page, order tracking (order # + phone), and customer accounts with order history.
- Order e-mails to the customer and the admin.
- Fully responsive, with a floating WhatsApp button, preloader and back-to-top.

## 4. Payments

Turn each method on or off in **Admin → Payments**:

| Method | Manual mode | Gateway mode |
|---|---|---|
| Cash on Delivery | Optional COD fee and max order amount | — |
| JazzCash | Shows your JazzCash number; the customer enters a TID and can upload a screenshot | JazzCash Page Redirection v1.1 (Merchant ID, Password, Integrity Salt, Sandbox toggle) |
| EasyPaisa | Shows your EasyPaisa number + TID | Easypay hosted checkout (Store ID, Hash Key, Sandbox toggle) |
| Debit / Credit Card | Bank account / IBAN details | Processed through your JazzCash **or** EasyPaisa merchant account |

Manual payments arrive as **"Awaiting Verification"**. Check your account, then set the order's payment status to *Paid*.

JazzCash responses are verified with the secure hash and marked *Paid* automatically.
EasyPaisa's redirect is not signed, so those payments are marked *Awaiting Verification*. Confirm them in your Easypay merchant portal.

**Before going live with a gateway:** test with your sandbox credentials, and register the return URLs shown on the Payments page in your merchant portal.

## 5. Admin panel

- **Dashboard:** today / month / lifetime sales, a 14-day sales chart, orders by status, revenue by payment method, recent orders, best sellers and low stock.
- **Orders:**
  - Filters (status, payment, method, dates, search) and status tabs
  - Bulk status updates and CSV export
  - Order detail: status, payment status, courier and tracking #, timeline notes, private notes, restock on cancel, e-mail the customer, WhatsApp the customer, edit the address
  - Printable invoice
- **Products:**
  - Add / edit / delete / duplicate, plus bulk publish, hide and flag actions
  - Multiple images with drag-to-reorder (the 2nd image shows on hover)
  - Sale price, stock, sizes, colours, fabric and pieces
  - New Arrival / Best Seller / Featured flags
  - **Full SEO:** meta title and description with counters, Google preview, focus keyword, keywords, canonical URL, social share image, noindex
- **Categories & sub-categories:** image, banner, description, homepage/menu visibility, sort order, SEO fields.
- **Hero Banners:**
  - Unlimited slides, each with small text, heading, sub-heading, 2 buttons, desktop and mobile images, text colour, overlay darkness and alignment, with a live preview
  - Global height (vh or px, separately for desktop and mobile), full or boxed width, heading sizes, transition, autoplay speed, Ken Burns zoom
- **Homepage Sections:** enable, order, titles, subtitles and item counts; parallax and promo banners (image, text, button, height, colour, overlay); feature icons.
- **Delivery Charges:** default charge, free-delivery threshold, and per-city rates (each with its own free threshold and delivery time).
- **Coupons:** percent or fixed, minimum order, usage limit, expiry.
- **Pages:** About, policies, FAQs and any new page, with a built-in rich-text editor and SEO fields.
- **Reviews:** approve, hide, delete, or add reviews manually.
- **Messages & Subscribers:** contact form inbox, newsletter list, CSV export.
- **Site Settings:**
  - Logo (with height), favicon / site icon, store name, tagline
  - Phone, WhatsApp, email, address, hours, Google Map
  - Social links, announcement bar, footer text, currency
  - Guest checkout on/off, order prefix, theme colours
- **SEO Tools:** default meta tags, OG image, Google verification, Google Analytics, Meta Pixel, custom head/body code, **robots.txt editor**, **ads.txt editor**, and a **one-click sitemap.xml generator** (a live sitemap is always available at `/sitemap.xml` too).
- **Admin Users:** add admins with roles (Super / Manager / Staff) and change your password.

## 6. Replacing the demo content

The store ships with illustrated demo artwork in `assets/images/demo/`.
Upload your real product photos from **Admin → Products** (portrait 3:4, e.g. 1200×1600), your banners from **Admin → Hero Banners** (1920×1000), and category images from **Admin → Categories**.
Delete the demo products whenever you're ready.

## 7. Security notes

- Passwords are hashed (bcrypt), every query uses prepared statements, and all forms have CSRF protection.
- Login attempts are rate-limited, and the uploads folder cannot run scripts.
- Delete `/install` after installing. Keep `DEBUG` set to `false` in `config.php` on the live site.
- Once SSL is active, uncomment the HTTPS redirect in `.htaccess`.

## 8. Local testing (optional)

```
php -S localhost:8000 dev-router.php
```
`dev-router.php` mimics the `.htaccess` rules for PHP's built-in server. It is not needed on real hosting.
