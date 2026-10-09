# Ebaya — Premium Handcrafted Abaya Store

A complete ecommerce platform for **Ebaya**, built in procedural Core PHP + MySQLi, Bootstrap 5, jQuery, AJAX and SweetAlert2. It runs on standard cPanel shared hosting and needs no frameworks, Composer or build step.

| Guide | What's in it |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | Local setup, cPanel deployment, `.htaccess`, admin account setup |
| [docs/PAYMENTS.md](docs/PAYMENTS.md) | COD, JazzCash, Easypaisa and card configuration, callback URLs, go-live testing |
| [docs/LAUNCH-CHECKLIST.md](docs/LAUNCH-CHECKLIST.md) | Testing and launch checklist |
| [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) | Where every setting lives in the admin panel |

## Feature status

**Complete and working (tested end-to-end):**

- **Storefront.** 9-section homepage driven by the database, shop, nested categories and collections, filters (price, size, colour, sleeve style, abaya type, craft, occasion, availability, ready-to-ship or made-to-order), sorting and pagination. Also: search with live suggestions, product page (gallery, hover zoom, lightbox, variant matrix, size-guide popup, customisation, reviews, related/cross-sell/upsell, recently viewed), quick view, wishlist, mini cart.
- **Cart and checkout.** Persistent guest cart that merges at login, server-side price, stock and coupon recalculation, and delivery zones with live quotes. Also: COD fees and COD availability by area, made-to-order lead times, optional account creation, duplicate-submit protection, an immutable order snapshot, and stock locking with `SELECT … FOR UPDATE`.
- **Customer accounts.** Registration, login, password reset, profile, saved addresses, order history, order tracking, reviews, and guest order lookup.
- **Admin panel (23 modules).** Dashboard, orders (status flow, payment recording, COD collection, courier/tracking, internal and customer-visible notes, refunds, invoice and packing slip, CSV export), customers, coupons, products (variants, per-variant stock, images, craft details, customisation, SEO, relations, duplicate), categories, collections, inventory and movement history, attributes and size guides, reviews and testimonials. Also: homepage builder with draft → preview → publish, hero slides, navigation, content pages, theme with draft preview, store settings, shipping, payment methods, SEO and redirects, newsletter and messages, admin users and roles, and the audit log.
- **SEO.** Clean URLs, canonical and Open Graph tags, JSON-LD (Product, BreadcrumbList, Organization, WebSite, FAQPage), dynamic `sitemap.xml`, `robots.txt` and `ads.txt`, automatic redirects when a slug changes, manual redirects, noindex controls, a custom 404, and analytics IDs.
- **Security.** Prepared statements everywhere, CSRF on every form and AJAX call, output escaping, an HTML sanitizer for admin-written HTML, and hardened sessions with ID regeneration. Also: role permissions enforced on the server, secure image uploads (MIME sniffing and GD re-encoding), rate limiting, an audit log, encrypted gateway secrets, and no PHP execution in `/uploads`.

**Integrations awaiting merchant credentials:**

- **JazzCash, Easypaisa and card (Stripe Checkout).** The full flows are implemented: signed requests, callback and IPN handlers, signature checks, server-to-server verification, idempotent payment marking, refunds and logs. Each gateway stays **unavailable to customers** until you add credentials, test in sandbox and tick *Testing completed*. Confirm endpoint details against the integration guide your provider issues. See [docs/PAYMENTS.md](docs/PAYMENTS.md).
- **Email.** Order and status notifications, password reset and contact alerts are built in. Sending is **off** until you configure it under *Store settings → Email*.

**Sample content:**

- The product and banner imagery are **illustrated SVG placeholders** in a consistent style. Replace them with your own editorial photography from the admin panel.
- Product fabric and material details are sample text. Testimonials are seeded **inactive** and must be replaced with genuine reviews.
- Policy pages are starter text and must be reviewed before launch.

## Project layout

```
index.php            Storefront front controller (clean URLs)
.htaccess            Rewrites + protection of internal folders
config/              config.sample.php → copy to config.php (blocked from web)
includes/            Core libraries: db, auth, cart, checkout, orders, payments, seo, …
includes/gateways/   jazzcash.php, easypaisa.php, card.php
pages/               Storefront page templates
templates/           Shared partials: header, footer, product card, cart, …
ajax/                AJAX endpoints (cart, wishlist, search, quick view, newsletter, …)
admin/               Admin panel (one file per module) + partials/
assets/              css, js, img (sample SVG art in assets/img/sample)
uploads/             Admin uploads (PHP execution disabled)
database/ebaya.sql   Single import file: schema + sample data
storage/logs/        Error, payment and security logs (blocked from web)
docs/                Guides
```

Default admin: `admin@ebaya.test` / `Ebaya@Admin2026`. You are forced to change this password at first sign-in. See [docs/INSTALL.md](docs/INSTALL.md#admin-account-setup).
