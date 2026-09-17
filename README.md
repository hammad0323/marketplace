# Wedding Hall Management & Online Booking SaaS

A complete, production-ready Wedding Hall / Marriage Hall / Banquet Hall
management and online booking platform for Pakistan, built with **Core PHP
(procedural, no framework, no OOP)**, **MySQLi with prepared statements**,
plain **HTML5/CSS3/JavaScript/jQuery-free vanilla JS**, and **Bootstrap-free
custom design**. Runs on any shared-hosting PHP/MySQL stack (cPanel,
Hostinger, etc.) — no Composer, no Node.js, no build step.

## What's included

- Public marketing + booking website (premium, non-Bootstrap-template look)
  with a homepage built from 10 sections: an admin-managed hero banner
  carousel, a stats bar, featured halls, a "why choose us" feature grid,
  a live availability calendar, how-it-works steps, event types, latest
  blog posts, FAQ and a closing call-to-action
- Admin-managed homepage banner carousel (Admin → Website → Homepage
  Banners): unlimited slides, each with its own background image,
  heading, sub-heading and call-to-action button + link — autoplays with
  dots/arrows, falls back to a working default slide if none are added
- A real availability **calendar with color- and text-highlighted dates**
  (green/amber/red = available/partially booked/fully booked), embedded
  both on the homepage and the full `/availability` page — reads live
  from the same booking data as the admin panel, and honors the public
  availability on/off setting
- Multi-hall management with facilities, images, packages, pricing
- Admin-customizable time slots (Morning/Evening/Night or anything else)
- **Database-level conflict-proof booking** — a hall can never be
  double-booked for the same date + time slot, even under a simultaneous
  double-submit (enforced by a `UNIQUE KEY` in `slot_locks`, not just
  application logic)
- Full admin dashboard with an interactive booking calendar
- Booking management: statuses, payments, invoices, printable reports
- Customer database with booking history
- Gallery, blog, FAQ, static pages (About/Privacy/Terms/Contact) — all
  editable from the admin panel
- SEO: per-page meta tags, Open Graph, sitemap.xml, robots.txt, clean URLs
- Rule-based Database Assistant chatbot — answers booking/availability/
  payment questions in **English, Urdu and Roman Urdu**, strictly from
  real database queries (never invents data, and never requires an
  external AI API)
- Role-based admin access: Super Admin, Admin, Manager, Staff
- Lightweight multi-business (SaaS) layer: a Super Admin can add more
  wedding-hall businesses, each fully data-isolated

## Tech stack

Core PHP 8+ (procedural), MySQLi (prepared statements throughout,
`$conn` connection variable), HTML5, CSS3, vanilla JavaScript (`fetch`
for AJAX), Font Awesome, Google Fonts. No Laravel/CodeIgniter/WordPress/
React/Vue/Node — everything here is plain files you can upload as-is.

## 1. Installation (shared hosting / cPanel)

1. **Upload the whole project** to your hosting's document root (e.g.
   `public_html/`), keeping the folder structure intact (`admin/`, `ajax/`,
   `assets/`, `uploads/` must stay where they are).
2. **Create a MySQL database** in cPanel → MySQL Databases (or your
   host's equivalent), and a database user with full privileges on it.
3. **Import `database.sql`** via phpMyAdmin → Import (or
   `mysql -u USER -p DBNAME < database.sql` if you have CLI access). This
   creates all 23 tables and loads demo data (one business, 3 halls in
   Karachi/Lahore/Islamabad, time slots, event types, sample bookings,
   settings, pages, FAQs, one blog post).
4. **Edit `config.php`** — the only file you must change:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   `BASE_URL` is auto-detected from the request; you don't need to hard-code it.
5. **Enable `mod_rewrite`** (on by default on virtually all shared
   hosting). The included `.htaccess` gives you clean URLs (`/halls`,
   `/hall/royal-banquet-hall-karachi`, `/booking`, `/admin/bookings`,
   etc.), blocks direct access to `config.php`/`functions.php`/`.sql`
   files, disables directory listing, and adds security headers. If
   `mod_rewrite` isn't available, the site still works at the literal
   `.php` filenames — nothing is hard-dependent on rewriting.
6. **Set folder permissions** so PHP can write to `uploads/halls`,
   `uploads/gallery`, `uploads/logos`, `uploads/blog` and `logs/`
   (usually `755`/`775` is enough on shared hosting; avoid `777`).
7. **Log in to the admin panel** at `/admin/login.php` (see credentials
   below) — you'll be forced to set a new password on first login.
8. **Configure your business**: Settings → site identity, contact
   details, social links, booking rules, payment settings. Then add your
   real Halls, Time Slots, and Event Types (the demo ones are safe to
   edit or delete).
9. **Configure SEO**: Admin → SEO for per-page meta titles/descriptions,
   or just rely on the sensible defaults already seeded.
10. **Test an online booking end-to-end** on the public site
    (`/availability`, then `/booking`) before going live.

### Demo admin logins (change immediately — forced on first login)

| Role | Email | Password |
|---|---|---|
| Super Admin (platform) | `superadmin@weddinghallsaas.test` | `Admin@12345` |
| Business Admin | `admin@royalbanquet.test` | `Admin@12345` |
| Manager | `manager@royalbanquet.test` | `Manager@12345` |

All demo accounts have `must_change_password` set — the first login
redirects straight to **Change Password**. There is no email service
wired up in this build, so **Forgot Password** (`/admin/forgot-password.php`)
displays the one-time reset link directly on screen instead of emailing
it (clearly labelled as a stand-in for a real mailer — swap in your SMTP/
transactional email provider by editing `admin/forgot-password.php`).

### Default URL structure

Public site:
```
/                              Home
/about  /contact  /faq  /privacy  /terms
/halls                         Hall listing
/hall/{slug}                   Hall detail
/gallery
/availability                  Public availability checker (togglable)
/booking                       Online booking form
/booking-confirmation/{code}   Booking confirmation
/blog   /blog/{slug}
/sitemap.xml   /robots.txt
```
Admin panel:
```
/admin/login.php
/admin/                        Dashboard
/admin/bookings  /admin/booking-form.php  /admin/booking-view.php?id=
/admin/calendar  /admin/date-search
/admin/halls  /admin/hall-form.php  /admin/time-slots  /admin/event-types
/admin/customers  /admin/customer-view.php?id=
/admin/payments  /admin/reports
/admin/gallery  /admin/pages  /admin/blog  /admin/seo  /admin/settings
/admin/chatbot                 Database Assistant
/admin/users                   Admin users & roles
/admin/businesses.php          Super Admin only — manage tenants
```

## 2. Architecture notes

- **`$conn`** is the single mysqli connection created in `config.php`
  and used everywhere via prepared statements (`functions.php`'s
  `wh_stmt()`/`wh_fetch_all()`/`wh_execute()`, plus the generic
  `wh_insert()`/`wh_update()` helpers that infer bind types from PHP
  value types so no call site hand-counts a `'iissd...'` string).
- **Booking conflict prevention is enforced at the database level.**
  `slot_locks` has `UNIQUE KEY (business_id, hall_id, booking_date,
  time_slot_id)`. `wh_create_booking()` inserts the booking row and the
  lock row inside one transaction; a duplicate slot causes the lock
  insert to fail on the unique key, the transaction rolls back, and the
  caller gets a `conflict` error — this is safe even if two people
  submit at the exact same instant, which a prior `SELECT`-then-`INSERT`
  check alone would not guarantee. Cancelling a booking deletes its lock
  row, immediately freeing the slot; confirming a `pending` booking (or
  reactivating a cancelled one) re-acquires the lock and can itself be
  rejected if another booking grabbed it first.
- **Multi-tenant by design, single-tenant by default.** Every table
  carries `business_id`. The public website always serves
  `DEFAULT_BUSINESS_ID` (1) from `config.php`; the admin panel scopes
  every query to the logged-in admin's `business_id`. A Super Admin
  (`business_id IS NULL`) can create additional businesses from
  `/admin/businesses.php`, which auto-seeds sensible time slots, event
  types and settings for the new tenant and can create its first Admin
  login in one step.
- **Roles**: `super_admin` (platform), `admin` (full access within their
  business), `manager` (bookings/halls/payments/customers/gallery/
  reports/calendar — no settings/users), `staff` (bookings/customers/
  calendar only). Enforced server-side per page via
  `wh_require_page_access()` in `auth.php`, not just hidden in the UI.
- **Security**: `password_hash()`/`password_verify()`, CSRF tokens on
  every state-changing form (`wh_csrf_field()`/`wh_csrf_verify()`),
  login-attempt lockout (5 failed attempts / 15 minutes), session
  regeneration on login, `HttpOnly`/`SameSite=Lax` session cookies with
  a 30-minute idle timeout, MIME-sniffed image upload validation
  (JPEG/PNG/WEBP only, 3MB max), and output escaping via `e()`
  everywhere user-influenced data is printed.
- **Chatbot**: `chatbot-functions.php` is a self-contained, rule-based
  (keyword/intent + regex date parsing) natural-language layer over
  real SQL queries — it recognizes dates, halls, time slots and a
  handful of intents (availability, which-hall, bookings-on-a-date,
  booking counts, revenue/advance, pending payments, next booking) in
  English/Urdu/Roman Urdu, and always answers from an actual query
  result. If nothing is recognized, it says so explicitly rather than
  guessing. No external AI API is called or required; one could be added
  later as a clearly separate, optional fallback without touching this
  file's contract.
- **Availability toggle**: `ajax/check-availability.php` — used by both
  the public site and the admin panel — checks
  `show_public_availability` in Settings for anonymous visitors (admins
  always see live data). When off, the public endpoint returns
  `{"public_visible": false}` with no hall/slot data at all, so nothing
  about the calendar leaks even via direct API calls.
- **Privacy**: the public booking confirmation page
  (`/booking-confirmation/{code}`) only shows customer name/amount to
  the browser session that just created that booking; anyone else
  opening the same (sequential, guessable) URL sees booking code, hall,
  date, time and status only — never phone/CNIC/amount.

## 3. What's simplified vs. the full spec (documented, not hidden)

This build prioritizes a fully working core over shallow coverage of
every listed feature. Real, functional, but intentionally lighter-touch
than a mature SaaS product:

- **Super Admin / multi-business layer** is real (isolated data per
  business, tenant creation with auto-seeding, plan/status/expiry
  fields) but has no subscription billing/payment-gateway integration —
  exactly as the spec allows ("can be added later").
- **Blog** supports title/slug/excerpt/content/category/status/SEO and a
  featured image; there's no rich-text WYSIWYG editor (content is HTML
  in a textarea) and no comments/tags system.
- **Email** is not wired to a real SMTP/transactional provider anywhere
  (password reset, booking notifications) — everything that would be an
  email is either shown on-screen (password reset link) or logged to
  the in-app Notifications bell + `chatbot_logs`/`login_attempts`
  tables. Swapping in a real mailer only touches a few call sites.
- **Structured data (schema.org)** is not emitted as JSON-LD on every
  page; canonical URLs, Open Graph, Twitter cards, per-page meta
  title/description/robots and the auto-generated `sitemap.xml`/
  `robots.txt` are fully implemented.
- **Testimonials module** from the spec's admin nav list was left out
  entirely to keep scope focused on the booking/payment/availability
  core — everything else in the spec's admin sidebar exists as a real,
  working page.

Nothing above is a stub or a dead button — every included feature reads
and writes real data through MySQLi prepared statements.

## 4. Verified test scenarios

The following were run against a **freshly imported** `database.sql` on
a real MariaDB instance (not just eyeballed):

1. Create Hall A, Create Hall B — both persist correctly.
2. Time slots (Morning/Evening/Night) come seeded and are fully editable.
3. Book Hall A · 25 Dec 2026 · Night → succeeds.
4. Book Hall A · 25 Dec 2026 · Night again → **rejected**
   ("this hall is already booked...").
5. Book Hall A · 25 Dec 2026 · Morning → succeeds (different slot).
6. Book Hall B · 25 Dec 2026 · Night → succeeds (different hall).
7. Turn OFF "Show Availability on Website" → public
   `/ajax/check-availability.php` returns `public_visible:false` and no
   booking data.
8. Turn it back ON → availability data returns correctly.
9. Chatbot: "25 dec ko Hall A ki night booking hai?" → correctly answers
   "Night: Booked ❌", read live from `slot_locks`.
10. Add a PKR 100,000 payment to a PKR 200,000 booking → balance becomes
    exactly PKR 100,000, `payment_status` becomes `partial`.
11. Cancel that booking → the Hall A / 25 Dec / Night slot immediately
    shows `available` again, and a new booking for the exact same
    hall+date+slot is then accepted.

## 5. File structure

```
config.php, functions.php, auth.php, chatbot-functions.php   core includes (procedural, wh_ prefixed)
header.php, footer.php                                        public site chrome
index.php, about.php, halls.php, hall.php, gallery.php,
availability.php, booking.php, booking-confirmation.php,
contact.php, faq.php, privacy.php, terms.php, blog.php,
blog-post.php                                                  public pages
admin/                                                          full admin panel (31 pages, incl. banners.php)
ajax/                                                           JSON endpoints (availability, calendar, payments,
                                                                 booking status, chatbot, notifications)
assets/css/global.css, admin.css                                design systems (public / admin)
assets/js/main.js, availability.js, calendar-widget.js,
           hero-carousel.js, admin.js
uploads/halls, uploads/gallery, uploads/logos, uploads/blog,
uploads/banners                                                 writable upload targets
database.sql                                                    full schema + seed data
.htaccess, robots.php, sitemap.php, 404.php, 403.php, 500.php   routing, SEO, error pages
```
