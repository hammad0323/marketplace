# MediConnect — Doctor Association & Telemedicine Platform

A telemedicine booking platform built in procedural **Core PHP + MySQL (mysqli)**,
with a custom-built, no-framework HTML/CSS/vanilla-JS/jQuery front end. No
Laravel, no Bootstrap, no admin template — every line is hand-written for
this project.

## What's in this build

This is a **complete, working core platform** — guest browsing, patient and
doctor auth, doctor verification, appointment booking with live availability,
and admin oversight — built to a production security bar (CSRF, prepared
statements everywhere, hashed passwords, rate-limited login, validated
uploads, role-based access control on every protected page).

It is **not** the entire feature wishlist a platform like this could
eventually have (chat, a medicine store, paid memberships, a CMS page
builder, a blog). Those are real, multi-week efforts in their own right.
Rather than fake them, the database schema was designed to already support
them (see `database/schema.sql`, tables marked "PHASE 2+"), so they can be
built later as pure additions — new pages and endpoints — without touching
what's already here.

### Implemented and fully working
- **Public site**: homepage, doctor directory with filters/search, doctor
  profiles (respecting doctor-controlled privacy settings), specializations,
  about/contact/FAQ/privacy/terms, SEO meta tags + Open Graph + JSON-LD +
  sitemap.xml + robots.txt.
- **Auth**: patient self-registration, doctor application (pending admin
  review), admin login, a "smart" login/register modal that reopens guests'
  intended action after they sign in, session-based role guards on every
  protected page.
- **Booking**: live per-doctor weekly availability + blocked dates, real-time
  slot generation, online/physical consultation types, booking, cancel,
  reschedule.
- **Patient dashboard**: appointments (upcoming/completed/cancelled),
  profile + medical details, password change, avatar upload.
- **Doctor dashboard**: appointment approve/reject/complete, weekly
  availability editor, blocked-date manager, patient list, profile +
  privacy-settings + certificate uploads, analytics charts.
- **Admin dashboard**: doctor verification queue, patient management,
  all-appointments view, specialization CRUD, site settings, CMS content
  editor (About/Privacy/Terms), FAQ manager, contact-message inbox, activity
  log.

### Schema-ready for phase 2 (not yet wired to UI)
Chat, medicine store/orders, paid doctor memberships, blog. Tables:
`chat_conversations`, `chat_messages`, `medicines`, `medicine_categories`,
`orders`, `order_items`, `membership_plans`, `doctor_memberships`,
`blog_posts`, `support_tickets`.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Procedural PHP 8.x, `mysqli` with prepared statements only |
| Database | MySQL / MariaDB, InnoDB, utf8mb4 |
| Frontend | HTML5, hand-written CSS3 (custom properties, no framework), vanilla JS + jQuery for AJAX |
| Charts | Chart.js (vendored locally, see below) |
| Icons | Remix Icon (vendored locally) |
| Fonts | Plus Jakarta Sans / Inter via Google Fonts (non-blocking load, degrades to system sans-serif) |

### Why jQuery/Chart.js/Remix Icon are vendored instead of CDN-linked
During testing, a hanging (not merely failing) request to an external CDN
was found to freeze **all** JavaScript execution on the page — a browser
correctly waits for a blocking `<link rel="stylesheet">` before running
scripts that follow it, and if that stylesheet never resolves, nothing after
it ever runs either. Self-hosting the libraries that core functionality
(booking, dashboards) depends on removes that failure mode entirely, which
matters more for a healthcare booking tool than saving ~400KB. Google Fonts
is left external, but loaded as a non-blocking `rel=preload`, so even a dead
fonts CDN can no longer take the rest of the page down with it.

## Folder structure

```
/config           bootstrap (config.php: mysqli connection, sessions)
/includes          shared PHP helpers: functions.php, auth.php, header/footer
/ajax               all AJAX endpoints (auth, booking, dashboards, admin actions)
/admin, /doctor, /patient   role-specific dashboards, each with includes/
/assets/css        style.css (the entire design system)
/assets/js         main.js, toast.js, auth-modal.js, booking.js, per-panel scripts
/assets/js/vendor   self-hosted jQuery + Chart.js
/assets/fonts/remixicon   self-hosted icon font
/uploads            avatars/ certificates/ reports/ (never executes PHP — see .htaccess)
/database          schema.sql, seed.sql
/logs              php-error.log (git-ignored)
```

Every page is a real, directly-requestable `.php` file — no URL rewriting is
required for the site to function, which keeps it deployable on ordinary
shared hosting.

## Setup

1. Create a database and import the schema, then the seed data:
   ```
   mysql -u youruser -p < database/schema.sql
   mysql -u youruser -p < database/seed.sql
   ```
2. Set your DB credentials, either as environment variables (`DB_HOST`,
   `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`) or by editing the defaults at
   the top of `config/config.php`.
3. Point your web server's document root at the project root. For local
   testing: `php -S localhost:8000` from the project root.
4. Visit `/index.php`.

### Seeded logins (change or remove before any real deployment)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@mediconnect.test` | `Admin@12345` |
| Doctor (verified) | `sarah.chen@mediconnect.test` (and 6 other seeded doctors) | `Doctor@12345` |
| Doctor (pending review) | `fatima.alsayed@mediconnect.test` | `Doctor@12345` |
| Patient | `patient@mediconnect.test` | `Patient@12345` |

## Security

- Every SQL query uses `mysqli` prepared statements with bound parameters —
  no string-concatenated user input in SQL anywhere.
- CSRF tokens on every state-changing form/AJAX call, verified with
  `hash_equals`.
- Passwords hashed with `password_hash()` / verified with `password_verify()`.
- Login attempts are rate-limited by IP **and** by email (5 failures / 15
  min).
- File uploads are validated by extension **and** actual MIME content (via
  `finfo`), renamed to random filenames, and the `/uploads` directory is
  configured to never execute PHP even if a malicious file slipped through.
- Every protected page calls a role guard (`require_patient_page()` /
  `require_doctor_page()` / `require_admin_page()`) before rendering
  anything.
- All output is escaped with `htmlspecialchars()` (the `e()` helper) except
  admin-authored CMS/blog HTML content, which is trusted because only the
  admin role can write it.
- Sessions: `HttpOnly`, `SameSite=Lax`, regenerated on login, 30-minute idle
  timeout.

## What was deliberately left out of this pass

- **Password reset / forgot-password flow** — needs a real outbound email
  system to be meaningful; the schema (`password_resets`) is ready, the UI
  link was removed rather than shipped as a dead end.
- **Real-time chat, medicine store, paid memberships, blog** — see "Schema-
  ready for phase 2" above.
- **Transactional emails** (booking confirmations, verification, etc.) —
  same reasoning as password reset; needs a real mail transport configured
  per deployment.
