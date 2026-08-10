# Wanderly — Trip Planning & Multi-Service Travel Marketplace

Vanilla, procedural **PHP 8 + mysqli + MySQL**. No framework, no Composer,
no build step, no classes. Every request is a real `.php` file.

All 12 build phases are complete. This is a full trip-planning and
multi-service travel marketplace: public discovery (cities, categories,
search with filters/geolocation), provider-managed listings with dynamic
per-category fields, an availability calendar and full reservation
workflow, customer/provider dashboards, messaging, notifications, a
template-driven email system, reviews, favorites, provider memberships
with payments and commission, a day-by-day trip planner with a live
budget calculator, a blog/CMS, and SEO (sitemap, canonical/OG tags).

## What's included, by area

- **Database** (`database/schema.sql`) — 60+ tables: users/roles,
  providers (with privacy toggles and badges), dynamic categories
  (parent/child + per-category custom fields), cities, services
  (images/amenities/dynamic fields/availability), bookings, payments/
  commissions, reviews, favorites, conversations/messages,
  notifications, memberships, the full trip-planner schema, blog/CMS,
  SEO, and audit/activity logs. Seeded with roles, an admin account,
  demo cities/categories, membership plans, badges, and default email
  templates.
- **Core** (`config/`, `includes/`) — mysqli connection, prepared-
  statement DB helpers, CSRF protection, output escaping, file uploads,
  flash messages, auth (login/register/logout, rate-limited, password
  reset), booking pricing engine, messaging/review/membership/trip
  helpers, and a raw-SMTP mailer (no external library) that always logs
  to `email_log` even when SMTP isn't configured yet.
- **Public site** — homepage, city/category pages, AJAX-filtered search
  with geolocation ("near me"), destination autocomplete, service and
  provider profile pages (maps via Leaflet/OpenStreetMap, no API key
  needed), blog, static CMS pages, trip planner + public trip sharing.
- **Customer dashboard** — bookings, favorites, saved trips, profile,
  messaging, review submission on completed bookings.
- **Provider dashboard** — services (two-step category-aware form),
  availability calendar (click-to-toggle + date-range block), bookings
  (accept/reject/confirm/complete), analytics (views, revenue, a
  canvas-drawn bookings chart), reviews (public responses), membership
  plans + payment requests, business profile with privacy controls.
- **Admin panel** — providers/customers/categories/cities/services/
  bookings/reviews/payments/membership-plans/commissions/blog/email-
  templates/settings, all with real actions (approve/reject/verify/
  feature/suspend/etc.), backed by a generic settings key-value store.

Every feature above was built and then verified end-to-end against a
live MySQL instance (not just linted) as each phase landed — see the
commit history for the specific flows tested per phase.

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
   is enabled. With Apache, `.htaccess` is already set up (routing,
   `sitemap.xml`, security headers, upload folder script-execution
   lockdown). To try it locally with PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
4. Visit `/index.php`. To actually send email, set SMTP host/port/
   username/password under **Admin → Settings → Email/SMTP** — until
   then, triggered emails are logged (not lost) in **Admin → Email
   Templates → Recent send log**, and password reset links are shown
   inline instead of emailed.

### Demo login

- Admin: `admin@wanderly.test` / `Admin@12345` at `/admin/login.php`
  **— change this password before deploying anywhere reachable.**

## Folder structure

```
config/       database.php, config.php — bootstrap, never requested directly
includes/     functions.php, auth.php, mailer.php, booking/trip/review/
              messaging/membership-functions.php, header/footer/navbar.php
admin/        admin auth + full management panel (_layout_top/_bottom.php
              are the shared sidebar/topbar chrome)
provider/     provider auth + dashboard (services, bookings, availability,
              analytics, reviews, membership, profile, messages)
customer/     customer auth + dashboard (bookings, trips, favorites,
              profile, messages, review-form)
pages/        public pages (home sections live in index.php; city, category,
              search, service, provider, trip planner, blog, static CMS)
ajax/         AJAX endpoints (search, autocomplete, favorites, messaging,
              notifications, availability, trip builder, newsletter)
assets/       css/, js/, img/
uploads/      user-uploaded files (git-ignored contents; script execution
              disabled via uploads/.htaccess)
database/     schema.sql
sitemap.php   served at /sitemap.xml
robots.txt
```

## Security notes

- All queries go through mysqli prepared statements via the `db_*()`
  helpers in `includes/functions.php` — no string-concatenated SQL
  anywhere in the codebase (checked, not just assumed).
- CSRF tokens (`hash_equals` comparison) on every state-changing form
  and AJAX endpoint.
- Passwords hashed with `password_hash()` / verified with
  `password_verify()`.
- Login attempts are rate-limited per email+IP (5 attempts / 15 minutes).
- Sessions: `httponly`, `SameSite=Lax`, `secure` when served over HTTPS,
  strict mode, regenerated on login.
- Uploads (`upload_file()`) validate extension, real MIME type (via
  `finfo`, not just the filename), and size; files are written under
  `uploads/` with randomized filenames, and `uploads/.htaccess` denies
  script execution there as defense-in-depth even if a bad file got
  through.
- Password reset never reveals account existence once SMTP is
  configured (the link is only shown inline as a fallback when no SMTP
  is set up, which is disclosed in the UI).
- Every resource-modifying endpoint scopes its query to the acting
  user (`WHERE id = ? AND provider_id = ?` / `customer_id = ?` / trip
  ownership, etc.) rather than trusting a submitted ID alone.
- `config/`, `includes/`, `database/`, `*.sql`, and the admin layout
  partials are denied at the web server level via `.htaccess`.
