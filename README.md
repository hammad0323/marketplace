# Wanderly — Trip Planning & Multi-Service Travel Marketplace

Vanilla, procedural **PHP 8 + mysqli + MySQL**. No framework, no Composer,
no build step, no classes. Every request is a real `.php` file.

This is being built **phase by phase** (the project is genuinely large —
see [Roadmap](#roadmap) below). This README describes what exists today.

## Phase 1 — what's included

- Full MySQL schema (`database/schema.sql`) — 60+ tables covering users,
  roles, providers, categories (with dynamic per-category custom fields),
  cities, services, bookings, payments, commissions, reviews, favorites,
  messaging, notifications, the trip planner, blog/CMS, SEO, and audit
  logs. Seeded with roles, an admin account, demo cities/categories, and
  site settings.
- `config/` — mysqli connection + app bootstrap (sessions, constants).
- `includes/functions.php` — prepared-statement DB helpers (`db_select`,
  `db_insert_get_id`, `db_execute`, …), CSRF protection, output escaping,
  slugs, file uploads, flash messages, settings lookup.
- `includes/auth.php` — login/register/logout for customers, providers
  and admins; role guards; rate-limited login attempts; password reset.
- Shared front-end shell (`includes/header.php` / `navbar.php` /
  `footer.php`) and a custom purple SaaS design system
  (`assets/css/style.css`, `assets/css/admin.css`) with scroll-reveal,
  parallax hero blobs, animated counters, and skeleton/toast utilities
  (`assets/js/main.js`) — Bootstrap is used only for its grid, everything
  visible is custom CSS.
- Homepage pulling live data from the database (cities, categories,
  featured providers, stats).
- Auth pages for all three roles, an admin dashboard shell with real
  stats and a pending-provider queue, and a provider dashboard shell.
- 404/500 pages, `.htaccess` hardening.

Everything above was tested end-to-end against a live MySQL instance
(registration, login, CSRF, rate limiting, password reset, admin login,
provider approval queue) before being committed.

### What's *not* here yet

Category/city pages currently show an honest empty state instead of
listings — there's nothing to list yet, because **admin CRUD for
providers/categories/cities/services, search & filters, availability
calendars, reservations, dashboards, messaging, reviews, the trip
planner, payments, and the CMS/blog are separate phases** (see
Roadmap). Nav/footer links only point at pages that exist today.

## Setup

1. **Create a database** and import the schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE wanderly CHARACTER SET utf8mb4"
   mysql -u root -p wanderly < database/schema.sql
   ```
2. **Configure the connection** — `config/database.php` reads from
   environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`),
   falling back to `localhost` / `wanderly` / `root` / *(empty)*. Either
   export those env vars for PHP/Apache, or edit the fallbacks directly
   in `config/database.php`.
3. **Point your web server's document root at the project root** (where
   `index.php` lives) and make sure PHP 8+ with the `mysqli` extension
   is enabled. With Apache, `.htaccess` is already set up. To try it
   locally with PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
4. Visit `/index.php`.

### Demo login

- Admin: `admin@wanderly.test` / `Admin@12345` at `/admin/login.php`
  **— change this password before deploying anywhere reachable.**

## Folder structure

```
config/       database.php, config.php — bootstrap, never requested directly
includes/     functions.php, auth.php, header/footer/navbar.php
admin/        admin auth + dashboard (_layout_top/_bottom.php are shared chrome)
provider/     provider auth + dashboard
customer/     customer auth + dashboard
pages/        public content pages (city, category, search, static CMS pages)
ajax/         AJAX endpoints (newsletter today; search/booking/etc. land later)
assets/       css/, js/, img/
uploads/      user-uploaded files (git-ignored contents)
database/     schema.sql
```

## Roadmap

- **Phase 1 — done.** Architecture, database, config, auth, roles,
  sessions, initial frontend + admin shell.
- **Phase 2.** Full admin CRUD: providers, customers, categories, cities.
- **Phase 3.** Services, dynamic category fields, provider profiles, maps.
- **Phase 4.** AJAX search, filters, geolocation, discovery.
- **Phase 5.** Availability, calendars, reservations/booking workflow.
- **Phase 6.** Customer & provider dashboards (full).
- **Phase 7.** Messaging, notifications, email (SMTP + templates).
- **Phase 8.** Reviews, favorites, ratings.
- **Phase 9.** Memberships, verified/premium providers, payments, commission.
- **Phase 10.** Trip Planner, budget calculator, itinerary builder.
- **Phase 11.** Blog, travel guides, SEO tooling.
- **Phase 12.** Security/performance hardening pass, polish.

## Security notes

- All queries go through mysqli prepared statements via the `db_*()`
  helpers in `includes/functions.php` — no string-concatenated SQL.
- CSRF tokens on every state-changing form (`csrf_field()` /
  `verify_csrf()`).
- Passwords hashed with `password_hash()` / verified with
  `password_verify()`.
- Login attempts are rate-limited per email+IP (5 attempts / 15 minutes).
- Sessions: `httponly`, `SameSite=Lax`, `secure` when served over HTTPS,
  strict mode, regenerated on login.
- Uploads (`upload_file()`) validate extension, MIME type (via
  `finfo`), and size, and are written under `uploads/` with randomized
  filenames.
- `config/`, `includes/`, `database/`, `*.sql`, and the admin layout
  partials are denied at the web server level via `.htaccess`.
