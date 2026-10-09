# Beglet — Premium Crafted Leather Store

A complete ecommerce platform for **Beglet**, built with **procedural Core PHP 8, MySQL and MySQLi** (prepared statements throughout), Bootstrap 5, jQuery, SweetAlert2 and Bootstrap Icons. All front-end libraries and fonts are bundled in `assets/vendor/`, so the site runs on standard cPanel shared hosting with no CDN or build step.

| Guide | What's inside |
|---|---|
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | cPanel installation, configuration, cron, HTTPS, email, sub-folder installs |
| [docs/PAYMENT-GATEWAYS.md](docs/PAYMENT-GATEWAYS.md) | COD, JazzCash, Easypaisa and hosted card setup, callback URLs, go-live steps |
| [docs/TESTING.md](docs/TESTING.md) | Automated smoke test, manual test plan, **pre-launch checklist** |

## What's included

**Storefront**
- Nine-section homepage driven entirely by the admin homepage builder: announcement bar and sticky header, hero carousel, shop by category, new arrivals, brand story, best sellers, featured collection, craftsmanship benefits, testimonials and newsletter, then the footer.
- Animations: reveal-on-scroll, transform-only parallax, Ken Burns hero, staggered hero text, image swap and zoom on hover, mega menu, offcanvas mobile navigation, mini-cart drawer and skeleton loaders. All motion is switched off for visitors with `prefers-reduced-motion`, and the admin can switch it off for everyone.
- Category, collection, shop and search listings with filters for price, colour, availability, material and wallet type, sorting by featured, newest, popularity, price and relevance, and SEO-safe pagination.
- Product page with a gallery, thumbnails, hover zoom and lightbox, variant (colour) selection with live price and stock, quantity selector, gift packaging, add to bag, buy now, wishlist, specifications, care and shipping panels, moderated reviews with a verified-purchase badge, related, cross-sell and upsell products, recently viewed items, and a sticky add-to-bag bar on mobile.
- Quick view, AJAX cart, coupons, a free-delivery progress bar, and live search suggestions.
- Guest and registered checkout: saved addresses, optional account creation, live delivery and COD-fee quotes, and duplicate-submit protection.
- Customer accounts (orders, addresses, profile, password reset), guest order tracking (order number plus email, or the emailed secure link), contact form, WhatsApp link and newsletter with consent records and unsubscribe.

**Admin panel** (`/admin`)
- Dashboard: order counts by status, revenue, pending and completed payments, a 14-day chart, top sellers, low-stock alerts and pre-launch warnings.
- Products: full editor with variants, per-variant stock and pricing, multiple images with alt text and captions, related, cross-sell and upsell products, collections, SEO and Open Graph fields, plus duplicate and delete. Products that have orders are archived instead of deleted.
- Categories with nested subcategories and safe deletion (products are moved, never orphaned), collections, inventory with a movement log, and review moderation.
- Orders: filters, status workflow with a history log, customer-visible and internal notes, courier tracking, COD collection, provider verification, refunds, invoices, packing slips and CSV export.
- Customers, newsletter (CSV export), contact messages and coupons.
- Homepage builder with draft, **preview** and publish; hero slides with every visual property; testimonials; pages; and theme settings (colours, fonts, spacing, layouts).
- General settings, shipping zones and rates, payment gateways (secrets encrypted at rest), transactions and reconciliation, SEO (robots.txt, ads.txt, verification, analytics) and redirects.
- Administrators; roles and permissions (Super Admin, Store Manager, Order Manager and Content Manager, plus custom roles); and an audit log.

**Security:** `password_hash` / `password_verify`, session ID regeneration at login, CSRF tokens on every POST, MySQLi prepared statements everywhere, output escaping, an HTML sanitiser for rich text, rate limiting (logins, resets, checkout, coupons, reviews, contact and newsletter), uploads re-encoded through GD into a no-execute folder, server-side permission checks on every admin page and action, and an audit trail.

**SEO:** clean URLs, canonical tags, per-entity meta and Open Graph/Twitter tags, Product, Breadcrumb, Organization and WebSite+SearchAction JSON-LD, a live XML sitemap, editable robots.txt and ads.txt with validation, automatic 301s when a slug changes, noindex on cart, checkout, account, search and filtered pages, a custom 404, and lazy-loaded images.

## Folder structure

```
index.php              Front controller (all storefront URLs)
.htaccess              Rewrites, security headers, caching
admin/                 Admin panel (one file per screen, clean URLs via admin/.htaccess)
  _inc/                Admin bootstrap, layout and form helpers (web access denied)
app/                   Application code (web access denied)
  bootstrap.php        Loads config, includes, session
  config.sample.php    Copy to app/config.php or ../beglet-config.php
  includes/            db, helpers, settings, security, auth, catalog, cart, wishlist,
                       shipping, coupons, orders, payments, mailer, seo, homepage, uploads
  gateways/            jazzcash.php, easypaisa.php
  pages/               Storefront page controllers (+ account/)
  ajax/                JSON endpoints (/api/*)
  views/               partials/, sections/ (homepage), emails/
assets/                css/, js/, img/ (brand + sample imagery), vendor/ (Bootstrap, jQuery, fonts…)
uploads/               Admin uploads (script execution disabled)
install/               schema.sql, seed.sql, sample-data.sql, database.sql (all-in-one)
storage/               logs/, cache/ (web access denied)
tools/                 cron.php, smoke-test.php, sample data and image generators
docs/                  Deployment, payments and testing guides
```

## Quick start (local)

```bash
mysql -u root -e "CREATE DATABASE beglet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root beglet < install/database.sql
cp app/config.sample.php app/config.php   # set DB_*, APP_URL, APP_KEY
```

Point an Apache vhost with `AllowOverride All` at the project folder. You can then sign in at `/admin` as `admin@example.com` with the password `ChangeMe@2026`. You will be required to set a new password.

## Sample content: please read

- **Product imagery** in `assets/img/sample/` consists of studio-style placeholder renders generated by `tools/generate-sample-images.php`. Replace them with real Beglet photography from the product editor.
- **Testimonials** in the demo data are marked as samples and appear only while *Settings → Show sample content* is on. Publish genuine customer feedback only.
- **Copy** avoids unverified claims (leather grade, handmade production, origin, warranty). Review the product materials, About page and policies, and publish only claims you can substantiate.
- **Online payments** (JazzCash, Easypaisa, cards) are integration-ready but **disabled and unconfigured**. They will not appear at checkout until you enter merchant credentials and enable them, and they must be tested in each provider's sandbox before you go live.
