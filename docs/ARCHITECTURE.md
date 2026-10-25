# Architecture

## Folder structure

```
/                     index.php (front controller), .htaccess
/admin/               admin panel (one PHP file per screen)
  includes/           admin bootstrap (init.php), layout & form helpers (functions.php)
  products/ categories/ collections/ catalog/ inventory/ orders/ customers/ reviews/ coupons/
  homepage/ content/ newsletter/ shipping/ payments/ seo/ settings/ users/
/ajax/                JSON endpoints: cart, wishlist, quick view, search, newsletter, reviews,
                      delivery estimate, checkout quote
/api/payments/        gateway callbacks: JazzCash return/IPN, Easypaisa return/IPN, Stripe return/webhook
/assets/              css/ (store.css, admin.css), js/ (store.js, admin.js), images/, vendor/
                      (Bootstrap 5.3.3, Bootstrap Icons, jQuery 3.7.1, SweetAlert2, Summernote — bundled locally)
/config/              config.sample.php → config.php (git-ignored, web-blocked)
/database/            schema.sql, seed.sql, urban_collections.sql (both combined)
/docs/                documentation
/includes/            shared modules (web-blocked)
  bootstrap.php       loads config + every module, starts the secure session
  db.php              MySQLi prepared-statement helpers (db_all, db_row, db_val, db_exec, db_insert, db_update, db_transaction)
  security.php        sessions, CSRF, escaping e(), rate limiting, HTML sanitiser, secret encryption
  helpers.php         URLs, money, slugs, input, flash, pagination, navigation
  settings.php        site_settings access + theme CSS variables
  auth.php            customers, admins, permissions, password resets, audit log
  uploads.php         validated image uploads (MIME sniffing, decode + re-encode to WebP)
  catalog.php         categories, collections, listings & filters, product data, wishlist
  cart.php            DB-backed cart (guest token cookie / customer), totals
  shipping.php        zones, rates, free-delivery, delivery estimates
  coupons.php         coupon validation
  orders.php          order placement (transactional), inventory ledger, cancellation, status helpers
  payments.php        payment methods, go-live gate, mark paid/failed, transaction log
  gateways/           jazzcash.php, easypaisa.php, stripe.php
  mailer.php          SMTP / mail() and email templates
  seo.php             meta tags, Open Graph, JSON-LD, analytics, breadcrumbs, redirects
  sections.php        homepage section registry (fields → admin editor + validation)
  content.php         CMS pages, newsletter subscriptions
  header.php footer.php
  components/         product card, carousel, filters, buy form, cart contents, account nav
  sections/           one template per homepage section type
/pages/               storefront page controllers (web-blocked; loaded by index.php)
/tools/               CLI: create-admin.php, cron.php, generate-seed.php, dev-router.php
/uploads/             uploaded images (script execution disabled)
/logs/                PHP / mail logs (web-blocked)
```

## Request flow

Apache serves real files directly (assets, `admin/*.php`, `ajax/*.php`, `api/...`). Everything else
is rewritten to `index.php`, which matches clean URLs (`/shop`, `/category/{slug}`,
`/collections/{slug}`, `/product/{slug}`, `/cart`, `/checkout`, `/account/...`, `/{page-slug}`, …)
and includes the controller from `pages/`. Unmatched paths are checked against the `redirects`
table (301/302) before showing the 404 page.

## Database

Main tables (see `database/schema.sql`):

- **Admin:** `admins`, `admin_roles`, `admin_permissions`, `admin_role_permissions`, `admin_audit_logs`
- **Customers:** `customers`, `customer_addresses`, `password_resets`
- **Catalogue:** `categories` (self-referencing tree), `products`, `product_images`, `product_variants`,
  `product_variant_values` (option name/value per variant — Size, Color, Waist, Length, Shoe Size…),
  `product_attributes` (extra specification rows), `product_relations` (related / cross-sell / upsell),
  `product_inventory` (stock ledger), `sizes`, `colors`, `collections`, `collection_products`, `reviews`
- **Shopping:** `carts`, `cart_items`, `wishlists`, `wishlist_items`, `coupons`, `coupon_usage`
- **Orders:** `orders`, `order_items` (immutable snapshots: name, SKU, variant, price, customisation),
  `order_addresses`, `order_status_history`, `payments`, `payment_transactions`, `payment_methods`
- **Delivery:** `shipping_zones`, `shipping_zone_locations`, `shipping_rates`, `urgent_delivery_rates` (city-wise)
- **Custom Size Studio:** `custom_garments`, `custom_size_chart`, `custom_size_fit`, `custom_style_options`,
  `customer_measurements`; measurement fields and style groups are defined in `includes/studio.php`, the
  chosen spec is stored as JSON in `cart_items.custom_spec` / `order_items.custom_spec` (`is_custom = 1`) and
  re-validated and re-priced server-side on every cart read
- **Alterations:** `alteration_services` (price list), `alteration_requests`, `alteration_request_items`,
  `alteration_request_photos`, `alteration_status_history` (see `includes/alterations.php`)
- **Content & SEO:** `homepage_sections`, `banner_slides`, `navigation_items`, `pages`, `site_settings`,
  `redirects`, `newsletter_subscribers`, `contact_messages`, `rate_limits`

Stock lives on `products.stock_qty` (simple products) or `product_variants.stock_qty`; every change is
written to `product_inventory` with the reason (order, cancel, refund, manual, return, correction).
Homepage section settings are JSON validated against the field definitions in `includes/sections.php`;
core commerce data is fully relational.

## Security model

- Prepared MySQLi statements everywhere; identifiers in dynamic SQL come from code, never input.
- Output escaped with `e()`; admin rich text passes through an allow-list HTML sanitiser.
- CSRF token on every form and AJAX write (header `X-CSRF-Token`); admin POSTs are checked centrally.
- Sessions: strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS, regenerated on login/logout/password
  change, bound to the user agent; admin idle timeout 2 hours.
- Passwords with `password_hash()` / `password_verify()` (auto-rehash); single-use, hashed, 1-hour reset tokens.
- Role-based permissions enforced server-side on every admin request (`admin_require()`); cost prices
  need a separate permission.
- Rate limits (MySQL-backed) on sign-in, registration, password reset, checkout, coupons, reviews,
  newsletter, contact, search, cart.
- Uploads: size limit, `finfo` MIME detection, image decode + re-encode (strips payloads/metadata),
  random names, no script execution in `uploads/`.
- Payments: hosted pages only, signature verification, server-side inquiry before marking paid,
  amount matching, idempotent updates, full transaction log; gateway secrets encrypted at rest.
- Checkout: server recalculates every price, discount, delivery fee and stock inside a transaction
  with row locks; idempotency key prevents duplicate orders.
- Audit log for important admin actions; security headers (nosniff, frame-options, referrer-policy, HSTS on HTTPS).

## Customising

- **New homepage section type:** add its fields to `section_types()` in `includes/sections.php` and a
  template `includes/sections/<type>.php`. The admin editor is generated automatically.
- **Styles:** `assets/css/store.css` uses CSS variables set from *Theme & Design*.
- **New gateway:** add credential fields in `payment_gateway_fields()`, a driver in `includes/gateways/`,
  a `payment_methods` row, and callback endpoint(s) in `api/payments/` that call `payment_mark_paid()`
  only after server-side verification.
