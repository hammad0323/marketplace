# Urban Collections — Premium Men's Fashion Store

A complete, database-backed ecommerce platform for **Urban Collections**, built with procedural
**Core PHP + MySQLi**, Bootstrap 5, jQuery, AJAX and SweetAlert2. It runs on standard cPanel
shared hosting — no framework, no Composer, no build step.

- Storefront: editorial homepage, mega-menu navigation, category / collection / search listings
  with AJAX filters, product pages with variants, zoom, lightbox and quick view, wishlist, bag,
  guest & account checkout, order tracking, customer accounts, reviews, newsletter, CMS pages.
- Admin panel: dashboard, orders, products & variants, categories, collections, inventory,
  customers, reviews, coupons, homepage builder & hero slides, navigation, content pages, delivery
  zones, payment methods, SEO & redirects, store settings, theme designer, admin users & roles,
  audit log.

## Quick start (local)

```bash
cp config/config.sample.php config/config.php      # then edit DB credentials, SITE_URL, APP_KEY
mysql -u root -p -e "CREATE DATABASE urban CHARACTER SET utf8mb4"
mysql -u root -p urban < database/urban_collections.sql
php -S localhost:8080 tools/dev-router.php          # Apache uses .htaccess instead
```

Open <http://localhost:8080> and the admin at <http://localhost:8080/admin/>.

**First admin sign-in:** `admin@urbancollections.pk` / `ChangeMe@2026` — you are forced to set a new
password immediately. Change the email under *Admin Users*, or create your own Super Admin with
`php tools/create-admin.php you@example.com "Your Name"`.

Full deployment instructions: **[docs/INSTALL.md](docs/INSTALL.md)**.

## Documentation

| Document | Contents |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | Requirements, local setup, cPanel deployment, sub-folder installs, admin setup, email, cron |
| [docs/PAYMENTS.md](docs/PAYMENTS.md) | Cash on Delivery, JazzCash, Easypaisa and card (Stripe) configuration, testing and go-live |
| [docs/CHECKLISTS.md](docs/CHECKLISTS.md) | Functional testing checklist and launch checklist |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Folder structure, request flow, database tables, security model, customising |

## What is complete vs. what needs your credentials

| Area | Status |
|---|---|
| Catalogue, variants, inventory, cart, coupons, delivery zones, checkout, orders, customer accounts, reviews, wishlist, newsletter, CMS, SEO, homepage builder, theme, admin roles | **Complete and working** out of the box |
| Cash on Delivery | **Complete** — enabled by default; COD availability per delivery zone, optional COD fee, cash-collected recording |
| JazzCash (hosted checkout) | **Implemented, awaiting merchant credentials + sandbox testing.** Disabled until you enter credentials, test in sandbox and tick *Testing completed* |
| Easypaisa / Easypay (hosted checkout) | **Implemented, awaiting merchant credentials + sandbox testing** (same go-live gate) |
| Debit / credit cards (Stripe Checkout) | **Implemented, awaiting credentials.** Requires a Stripe account available in your business's country; otherwise accept cards via the JazzCash / Easypaisa hosted pages |
| Email (order confirmations, status updates, password reset) | **Implemented, awaiting SMTP details** (Admin » Store Settings » Email). Without email, everything still works; customers use their private order links |
| Gateway refunds | Refunds are **recorded** in admin (amounts, stock return, history); the money itself is returned via the provider's merchant portal |

Unconfigured gateways never appear as selectable to customers; in *Sandbox* mode they are visible
only to signed-in admins so you can place test orders.

## Sample content & photography

The database ships with a realistic sample catalogue (40 categories/sub-categories, 31 products
with 289 size/colour variants, 9 collections, homepage content, 12 policy/content pages, delivery
zones for Pakistan and three demo coupons).

Sample product and lifestyle photos are **remote placeholder images from Unsplash** (free licence),
referenced by URL so the repository stays small. If any image fails to load, a branded Urban
Collections placeholder is shown automatically. **Replace them with your own product photography
before launch** — upload images in the product editor (they are resized to 2000px and converted to
WebP). The policy pages contain sensible starting text marked *“review before launch”*; adapt them to
your actual business terms.

## Requirements

PHP 8.1+ with `mysqli`, `gd` (WebP support), `fileinfo`, `openssl`, `curl`, `mbstring`, `json`;
MySQL 5.7+ or MariaDB 10.3+; Apache with `mod_rewrite` (any cPanel host).
