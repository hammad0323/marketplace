# MediConnect — Doctor Association & Telemedicine Platform

A telemedicine booking platform built in procedural **Core PHP + MySQL (mysqli)**,
with a custom-built, no-framework HTML/CSS/vanilla-JS/jQuery front end. No
Laravel, no Bootstrap, no admin template — every line is hand-written for
this project.

## What's in this build

This is a **complete, working core platform** — guest browsing, patient and
doctor auth, doctor verification, appointment booking with live availability,
doctor-patient messaging, a doctor storefront, a blog, transactional email,
and admin oversight — built to a production security bar (CSRF, prepared
statements everywhere, hashed passwords, rate-limited login, validated
uploads, role-based access control on every protected page) and designed
mobile-first: every page, dashboard, and modal is usable down to a 375px
phone viewport.

It is **not** the entire feature wishlist a platform like this could
eventually have (paid doctor memberships/subscription billing, a support
ticket system). Those are real, multi-week efforts in their own right.
Rather than fake them, the database schema was designed to already support
them (see `database/schema.sql`, tables marked "PHASE 2+"), so they can be
built later as pure additions — new pages and endpoints — without touching
what's already here.

### Implemented and fully working
- **Public site**: homepage, doctor directory with filters/search, doctor
  profiles (respecting doctor-controlled privacy settings), specializations,
  about/contact/FAQ/privacy/terms, SEO meta tags + Open Graph + JSON-LD +
  sitemap.xml + robots.txt. All URLs are extension-free (`/doctors`, not
  `/doctors.php`) — see "Clean URLs" below.
- **Auth**: patient self-registration, doctor application (pending admin
  review, multi-specialization), admin login, a "smart" login/register modal
  that reopens guests' intended action after they sign in, session-based role
  guards on every protected page.
- **Booking**: live per-doctor weekly availability + blocked dates, real-time
  slot generation, online/physical consultation types, booking, cancel,
  reschedule.
- **Doctor-patient messaging**: each doctor opts messaging on/off from their
  own dashboard, optionally restricts it to a daily time window, and
  optionally allows logged-out visitors to see the option; a pulsing green
  "online now" indicator shows on the public profile whenever the doctor is
  enabled, within their hours, and has been active recently. Patients and
  doctors can both start a conversation; messages arrive via short-interval
  polling (no page refresh) and generate a notification + email to whoever
  didn't send the message. See "Messaging" below.
- **Notifications**: an in-header bell (patient/doctor/admin) polls for new
  activity, plays a short generated chime and does a wobble animation the
  moment unread count increases, and shows a live dropdown of recent items.
- **Email**: every significant activity (registration, doctor application,
  appointment booked/approved/rejected, new chat message, contact form) sends
  an email to the relevant party — the patient, the doctor, and/or every
  admin, depending on the event — through a built-in SMTP client configured
  from the admin panel. See "Email" below.
- **Patient dashboard**: appointments (upcoming/completed/cancelled),
  messaging, profile + medical details, password change, avatar upload.
- **Doctor dashboard**: appointment approve/reject/complete, weekly
  availability editor, blocked-date manager, patient list, messaging settings,
  profile with multi-specialization selection + privacy settings + multiple
  certificate uploads (single click, many files), analytics charts.
- **Admin dashboard**: doctor verification queue, patient management,
  all-appointments view, specialization CRUD, SMTP/email settings with a
  send-test-email button, site settings, CMS content editor (About/Privacy/
  Terms), FAQ manager, contact-message inbox, activity log.
- **Theme**: light mode is the fixed, unconditional default for every new
  visitor — the OS/browser dark-mode preference is never read. A visitor who
  explicitly toggles dark mode has that remembered (localStorage) for their
  next visit only; nothing is ever pushed into dark mode automatically.
- **Doctor storefront**: Premium doctors can list physical products and
  bookable service packages on their public profile; patients request to buy
  and the doctor confirms/completes/cancels the request from their own
  dashboard. See "Doctor storefront" below.
- **Blog**: admin writes posts with a built-in rich text editor (bold/italic/
  underline, headings, lists, quotes, links, inline images) — no external
  editor library. Published posts appear on a public, paginated `/blog` with
  SEO metadata and JSON-LD; drafts stay admin-only. See "Blog" below.
- **Mobile responsive**: every public page, patient/doctor/admin dashboard,
  chat screen, and modal collapses correctly down to a 375px viewport — a
  slide-out sidebar nav, a stacked mobile menu with the auth buttons folded
  in, tables that scroll horizontally instead of breaking layout, and no
  page that scrolls sideways. See "Mobile responsiveness" below.

### Clean URLs
Every internal link is written and rendered without `.php` (`/doctors`,
`/doctor/dashboard`, `/admin/settings`). Apache's `mod_rewrite` (see
`.htaccess`) transparently serves the matching `.php` file for these paths,
and 301-redirects any request that still hits a `.php` URL directly (so old
bookmarks/search-engine links land on the clean URL instead of getting a
duplicate-content page). `/ajax/*.php` endpoints are deliberately excluded
from both rules and keep their `.php` extension, since they're only ever
called from JavaScript, never linked or bookmarked.

This requires `mod_rewrite` and `AllowOverride All` (or equivalent) on the
web server — see "Setup" below. PHP's built-in `php -S` dev server does
**not** process `.htaccess`, so during local testing without Apache, visit
pages by their real `.php` path (e.g. `/doctor/dashboard.php`); this is a
dev-server limitation only, not an application bug.

### Messaging
- A doctor turns messaging on from **Doctor Dashboard → Profile → Messaging**,
  optionally sets a daily availability window (e.g. 09:00–18:00) and whether
  logged-out visitors can see the "Message" button on their public profile.
- The green "online now" dot on a doctor's profile/chat means: messaging is
  enabled, the current time is inside their window (if one is set), and
  they've been active on the site within the last 15 minutes — not merely
  that messaging is turned on.
- Either side can start a new conversation; both `patient/messages.php` and
  `doctor/messages.php` use the same polling driver (`assets/js/chat.js`,
  ~4s interval) so new messages appear without a manual refresh.
- Every new message calls `notify_user()`, which both creates an in-app
  notification and, if email is configured and enabled, emails the recipient.

### Email
- Configured entirely from **Admin → Site Settings → Email (SMTP)**: host,
  port, username, password, encryption (`none`/`tls`/`ssl`), from
  address/name, and a master on/off switch. A "Send Test Email" button
  verifies the configuration without leaving the page.
- The SMTP client (`includes/mailer.php`) is hand-written over raw sockets
  (`stream_socket_client`/`fsockopen`) — STARTTLS, implicit SSL, and AUTH
  LOGIN are all supported, no Composer/PHPMailer dependency.
- Sending never blocks or fails the triggering request: socket timeouts are
  short (8s) and every failure is caught, so a misconfigured or unreachable
  SMTP server degrades to "no email sent," not a broken booking/registration/
  chat action. If email is left disabled (the default), the app behaves
  exactly as it did before this feature existed — in-app notifications only.
- `notify_user()` is the single choke point used everywhere an activity
  email should fire (appointments, chat, registration, doctor applications);
  `notify_admins()` fans the same event out to every active admin.

### Doctor specializations
A doctor is no longer limited to one specialization. `doctors.specialization_id`
was replaced by a `doctor_specializations` pivot table (many-to-many); doctor
registration and profile editing use a checkbox grid, and every place that
used to show a single specialization (directory filters, profile pages,
admin lists) now shows/filters on the full set.

### Doctor storefront
- Selling products/services is a **Premium** perk, gated on `doctors.is_premium`
  (the same flag admin already toggles from **Admin → Doctors**). A
  non-premium doctor visiting **Doctor Dashboard → My Store** sees an upgrade
  message instead of the management UI, and the save endpoint re-checks the
  flag server-side regardless of what the client sends.
- A listing is either a `product` (has stock, decremented on each request) or
  a `service` (no stock, an optional free-text duration/session label like
  "3 sessions"). Doctors manage their catalog and incoming orders from two
  tabs on the same page (`doctor/products.php`).
- On the doctor's public profile, active listings appear under a "Products &
  Services" tab (only when the doctor is Premium **and** their `show_store`
  privacy toggle is on). A logged-in patient clicks "Request This," fills in
  quantity/phone/notes, and that creates an `orders` row — this is a request/
  inquiry flow, not a payment checkout (no payment gateway is wired up,
  consistent with the rest of the app: appointments record a `fee` the same
  way without processing a real charge).
- The doctor confirms, completes, or cancels each request from the Orders
  tab; every transition notifies + emails the patient via the same
  `notify_user()` pipeline chat and appointments use. Patients see their full
  order history at `/patient/orders`.

### Blog
- The rich text editor (`assets/js/rich-editor.js`) is a small, dependency-
  free `contenteditable` component with its own toolbar (bold/italic/
  underline, H2/H3/paragraph, bullet/numbered lists, blockquote, link,
  inline image, clear formatting) — consistent with the project's policy of
  vendoring or hand-writing everything instead of pulling in a CDN library.
  Inline images upload immediately via AJAX and get inserted at the cursor.
- Admin manages posts from **Admin → Blog** (`admin/blog.php`): title,
  excerpt, featured image, rich content, and a draft/published status. Only
  published posts are queryable from the public `/blog` (paginated listing)
  and `/blog-post?slug=…` (detail + "more from the blog"); a draft's detail
  URL 404s for everyone except through the admin editor.
- Stored content is trusted HTML, the same pattern already used for the
  About/Privacy/Terms CMS pages — safe because only the admin role can write
  it, not because it's sanitized.

### Mobile responsiveness
- Public nav collapses to a hamburger menu below 860px; the dropdown panel
  contains the nav links **and** the Log In/Get Started actions (previously
  those two buttons had nowhere to go on narrow screens and overflowed the
  header — now confirmed clipped correctly at a 375px viewport).
- Every two/three-column layout built with an inline `grid-template-columns`
  (doctor directory sidebar, doctor profile, dashboards, login/register
  cards, the contact page) was moved to a small set of reusable `.split-*`
  utility classes in `style.css` so they have an actual responsive collapse
  rule instead of silently overflowing on phones.
- All `.data-table` tables are wrapped in a `.table-scroll` container so wide
  tables (appointments, doctor lists, orders) scroll horizontally inside
  their own box on narrow screens instead of blowing out the page width.
- Chart.js canvases are capped at `max-width:100%` inside an
  `overflow:hidden` card, since Chart.js's own responsive sizing can
  otherwise briefly render wider than its container on first paint.
- Verified with real mobile-viewport automation (375×812, device-emulated,
  including a full page scroll to trigger the same scroll-in animations a
  real visitor would see) across all public pages, both dashboards' key
  pages, and the new store/blog/orders pages — zero horizontal overflow
  anywhere in the app.

### Schema-ready for phase 2 (not yet wired to UI)
Paid doctor memberships/subscription billing and a support ticket system.
Tables: `membership_plans`, `doctor_memberships`, `support_tickets`.

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
/includes          shared PHP helpers: functions.php, auth.php, mailer.php (SMTP client), header/footer
/ajax               all AJAX endpoints (auth, booking, chat, notifications, store/orders, blog, admin actions)
/admin, /doctor, /patient   role-specific dashboards, each with includes/, messages.php, products.php/orders.php
/assets/css        style.css (the entire design system, incl. .split-* responsive layout classes)
/assets/js         main.js, toast.js, auth-modal.js, booking.js, chat.js, notifications.js, rich-editor.js, per-panel scripts
/assets/js/vendor   self-hosted jQuery + Chart.js
/assets/fonts/remixicon   self-hosted icon font
/uploads            avatars/ certificates/ products/ blog/ reports/ (never executes PHP — see .htaccess)
/database          schema.sql, seed.sql
/logs              php-error.log (git-ignored)
```

Every page is a real, directly-requestable `.php` file underneath — the
clean URLs described above are an Apache rewrite layer on top, so the site
still works with `.php` in the URL if `mod_rewrite` isn't available.

## Setup

1. Create a database and import the schema, then the seed data (both files
   set their own connection charset, so run each with the plain `mysql`
   client — no special flags needed):
   ```
   mysql -u youruser -p < database/schema.sql
   mysql -u youruser -p < database/seed.sql
   ```
2. Set your DB credentials, either as environment variables (`DB_HOST`,
   `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`) or by editing the defaults at
   the top of `config/config.php`.
3. Point your web server's document root at the project root, with
   `mod_rewrite` enabled and `AllowOverride All` (or equivalent) so
   `.htaccess` can apply the clean-URL rules. For local testing without
   Apache: `php -S localhost:8000` from the project root — clean URLs won't
   resolve under this dev server (see "Clean URLs" above), so browse using
   the real `.php` paths instead.
4. Visit the homepage (`/` or `/index.php`).
5. Optional: configure outbound email at **Admin → Site Settings → Email
   (SMTP)** if you want registration/booking/chat activity to send real
   emails. Everything works with email left off — it only adds emails on top
   of the existing in-app notifications.

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

- **Password reset / forgot-password flow** — the schema (`password_resets`)
  is ready and a real SMTP transport now exists to deliver it, but the
  request/reset UI itself wasn't built in this pass; the login page has no
  dead "forgot password" link pointing nowhere.
- **Paid doctor memberships / subscription billing, support tickets** — see
  "Schema-ready for phase 2" above.
- **Real payment processing** for the doctor storefront — requesting a
  product/service creates a pending order the doctor confirms manually,
  the same way booking an appointment records a fee without charging a
  card; wiring a real payment gateway is a separate, deployment-specific
  integration.
- **True real-time delivery** (WebSockets/SSE) for chat and notifications —
  both use short-interval AJAX polling (~4s for chat, ~20s for the
  notification bell) instead, which needs no persistent server process and
  works unmodified on ordinary shared hosting, at the cost of a few seconds
  of latency versus a socket-based push.
