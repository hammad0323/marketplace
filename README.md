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
- **Theme**: light mode only, sitewide — there is no dark mode toggle.
- **Doctor storefront**: Premium doctors can list physical products and
  bookable service packages; each listing gets its own public detail page
  with an inline checkout for registered patients. A site-wide `/products`
  page searches and filters the full catalog across every doctor. See
  "Doctor storefront" and "Product search" below.
- **SEO**: every doctor profile, blog post, and product listing gets its own
  correct canonical URL (a pre-existing bug made all of them canonicalize to
  one generic URL — now fixed), auto-generated meta title/description with
  manual override fields in the admin/doctor editors, a single `<h1>` on
  every public page, and a one-click static sitemap.xml generator in
  **Admin → Site Settings → SEO & Sitemap**. See "SEO" below.
- **Blog**: admin writes posts with a built-in rich text editor (bold/italic/
  underline, headings, lists, quotes, links, inline images) — no external
  editor library. Published posts appear on a public, paginated `/blog` with
  SEO metadata and JSON-LD; drafts stay admin-only. See "Blog" below.
- **Mobile responsive**: every public page, patient/doctor/admin dashboard,
  chat screen, and modal collapses correctly down to a 375px viewport — a
  slide-out sidebar nav, a stacked mobile menu with the auth buttons folded
  in, tables that scroll horizontally instead of breaking layout, and no
  page that scrolls sideways. See "Mobile responsiveness" below.
- **Site-wide currency symbol**: one setting in **Admin → Site Settings**
  controls the currency symbol used everywhere a price is shown — product
  listings, product detail/checkout, and orders. See "Currency" below.
- **Doctor patient-blocking**: a doctor can block a specific patient from
  sending further chat messages, from the chat window itself, without
  turning off messaging for everyone else. See "Messaging" below.
- **Medicine Info**: a new, separate content type (not a storefront product)
  that doctors and admin write purely so the site has real, indexable
  information about specific medicines for search/SEO — with its own SEO
  form and a live scoring progress bar. See "Medicine Info" below.

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
- Either side can genuinely start a new conversation. A doctor gets a
  **Message** button next to every patient on **Doctor Dashboard → My
  Patients**, which opens `/doctor/messages?patient_id=…` and creates the
  conversation lazily on the first message sent — mirroring exactly how a
  patient starts a conversation with `?doctor_id=…` from a doctor's profile.
  (An earlier version of this feature only let doctors *reply* to patients
  who had already messaged first, with no way to initiate — that asymmetry
  is what "messaging isn't working" was pointing at; `ajax/chat-send.php`
  and `ajax/chat-messages.php` are now symmetric between the two roles.)
  A doctor can only message a patient who has an appointment with them —
  enforced server-side, not just hidden in the UI. This check only applies
  to **starting a brand-new conversation**: once a conversation already
  exists (started by either side), both a doctor and a patient can keep
  replying in it regardless of appointment status, so a doctor can still
  reply to a patient who never booked, if they choose to engage.
- Both `patient/messages.php` and `doctor/messages.php` use the same polling
  driver (`assets/js/chat.js`, ~4s interval) so new messages appear without
  a manual refresh.
- Every new message calls `notify_user()`, which both creates an in-app
  notification and, if email is configured and enabled, emails the recipient.
- **Blocking**: if a doctor would rather not engage with a particular
  patient (e.g. one who keeps messaging without ever booking), they can hit
  **Block** in that patient's chat window (`doctor/messages.php`). This sets
  `chat_conversations.is_blocked` for that one conversation — the patient
  can no longer send new messages (`ajax/chat-send.php` rejects them with a
  clear notice) but can still see the existing history, and the doctor can
  still send messages and unblock at any time. Blocking is scoped to a
  single conversation, not a sitewide ban.

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

### Currency
The symbol used everywhere a price is displayed (product listings, product
detail/checkout total, order history) is read from a single `site_settings`
row (`currency_symbol`, defaults to `$`), editable at **Admin → Site
Settings**. `includes/functions.php`'s `format_currency()` is the one place
that formats a price server-side, and the client-side checkout total
(`assets/js/product-checkout.js`) reads the same value off `window.APP.currencySymbol`
(set in every panel's footer) instead of hardcoding `$` — so changing it in
one place changes it across the whole site, admin panel included.

### Medicine Info
This is deliberately **not** part of the doctor storefront (`doctor_products`)
— nothing here is for sale. It exists purely so the site has real, useful,
indexable content about specific medicines (uses, dosage, side effects,
precautions) for a patient who searches a medicine name and lands on the
site through search results.
- Both **Doctor Dashboard → Medicine Info** (`doctor/medicines.php`) and
  **Admin → Medicine Info** (`admin/medicines.php`) manage entries; a doctor
  only sees/edits their own, admin sees and can edit everyone's.
- Each entry has structured fields (generic name, composition, category,
  uses, dosage, side effects, precautions) plus a rich-text body, and its
  own SEO form: focus keyword, meta title, meta description.
- A **live SEO score progress bar** (0–100) updates as you type, scored on
  the same criteria as the auto-SEO elsewhere in the app — focus keyword
  present in the title/content/meta description, meta title/description
  length within recommended ranges, minimum content length, etc. The exact
  same scoring logic exists twice on purpose: `seo_score_for_medicine()` in
  `includes/functions.php` computes and persists the authoritative score on
  save, and `assets/js/seo-score.js` mirrors it in the browser so the bar
  you watch while typing matches what actually gets saved.
- Only `status = 'published'` entries are public. The public listing
  (`/medicines`) searches a MySQL `FULLTEXT` index (same boolean-mode,
  prefix-matching approach as product search) across name, generic name,
  uses, and content, plus a category filter. Each entry's detail page
  (`/medicine-detail?slug=…`) sets its own canonical URL, JSON-LD (`Drug`
  schema.org type), and is included in the sitemap.

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
- A listing is either a `product` (has stock, decremented on each order) or
  a `service` (no stock, an optional free-text duration/session label like
  "3 sessions"). Doctors manage their catalog and incoming orders from two
  tabs on the same page (`doctor/products.php`).
- Every listing gets its own public page at `/product-detail?slug=…` — full
  description, doctor mini-card, related listings from the same doctor, and
  an inline **checkout** box. Guests see a "Log In to Purchase" prompt;
  logged-in patients get a real checkout form (quantity for physical
  products, shipping address pre-filled from their profile, contact phone,
  notes, a live-updating total) that submits to `ajax/product-request.php`.
  This is a request/confirm flow, not a live payment checkout — see "What
  was deliberately left out" below for why that's a deliberate boundary, not
  an oversight.
- The doctor confirms, completes, or cancels each request from the Orders
  tab; every transition notifies + emails the patient via the same
  `notify_user()` pipeline chat and appointments use. Patients see their full
  order history (including shipping address) at `/patient/orders`.

### Product search
`/products.php` is a marketplace-style listing of every active listing across
every Premium doctor, independent of any single doctor's profile. Search
runs against a MySQL `FULLTEXT` index on `doctor_products(name, description)`
in boolean mode with each search word turned into a required prefix match
(`blood pressure` → `+blood* +pressure*`) — this scales as a real index
lookup instead of a `LIKE '%…%'` table scan, and still matches partial words
the way users actually type. Combine search with type (product/service),
category, and price-range filters, sorted by relevance/newest/price.

### SEO
- **Canonical URLs**: the shared default (`includes/header.php`) strips query
  strings, which is correct for filterable listing pages (`/doctors?…`) but
  was — before this pass — being applied unconditionally, so *every* doctor
  profile, blog post, and product page canonicalized to the same generic
  URL (`/doctor-profile`, `/blog-post`, `/product-detail`), telling search
  engines each type of page was one giant duplicate of itself. Each of the
  three detail pages now sets its own `$canonical` (including its `?slug=`)
  before including the header, so every individual doctor/post/product is
  indexable on its own URL.
- **Meta title / description**: blog posts and store listings both have
  optional `meta_title`/`meta_description` columns, editable from their
  respective admin/doctor forms; when left blank, the public page
  auto-generates them from the title/excerpt/description instead (CMS pages
  already had explicit meta fields from the original build).
- **Heading hierarchy**: every public page now has exactly one `<h1>` — a
  sweep found seven pages (specializations, blog, contact, FAQ, login,
  register, doctor-register) rendering their main heading as `<h2>` with no
  `<h1>` anywhere on the page at all; fixed by promoting each page's title
  to `<h1>` (visually identical, since `h1`/`h2` share the same base style
  in this design system — this was a pure semantics fix).
- **Structured data**: JSON-LD on doctor profiles (`Physician`), blog posts
  (`BlogPosting`), and products (`Product`/`Service` with `Offer` pricing/
  availability), plus Open Graph tags site-wide.
- **Sitemap**: `/sitemap.xml` is always live and dynamically generated
  (`build_sitemap_xml()` in `includes/functions.php`, covering static pages,
  every verified doctor, active specialization, published blog post, and
  active store listing). **Admin → Site Settings → SEO & Sitemap** also has
  a "Generate Sitemap" button that writes the same XML to a real static
  `sitemap.xml` file at the project root — useful for search-console
  verification or serving it with zero PHP overhead. The file is
  git-ignored (it bakes in `APP_URL`, which is deployment-specific).

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
/ajax               all AJAX endpoints (auth, booking, chat, notifications, store/orders, blog, sitemap, admin actions)
/admin, /doctor, /patient   role-specific dashboards, each with includes/, messages.php, products.php/orders.php
/products.php, /product-detail.php   site-wide storefront search + per-listing checkout
/assets/css        style.css (the entire design system, incl. .split-* responsive layout classes)
/assets/js         main.js, toast.js, auth-modal.js, booking.js, chat.js, notifications.js, rich-editor.js, product-checkout.js, per-panel scripts
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

1. **Fresh install** (no existing data): create a database and import the
   schema, then the seed data (both files set their own connection charset,
   so run each with the plain `mysql` client — no special flags needed):
   ```
   mysql -u youruser -p < database/schema.sql
   mysql -u youruser -p < database/seed.sql
   ```
   **Already have a live database from an earlier build of this project?**
   Do **not** re-run `schema.sql`/`seed.sql` against it — both files `DROP`
   and recreate every table, which would erase your real data. Instead run
   `database/migration_v3.sql` once against your existing database — it only
   adds the new `chat_conversations.is_blocked` column and the new
   `medicine_info` table, touches nothing else, and is safe even if run
   twice:
   ```
   mysql -u youruser -p your_database_name < database/migration_v3.sql
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
- **Real payment processing** for the doctor storefront — the checkout on
  `/product-detail` collects everything a real order needs (quantity,
  shipping address, contact, notes) and creates a pending order the doctor
  confirms manually, the same way booking an appointment records a `fee`
  without charging a card; wiring a real payment gateway (Stripe, etc.) at
  the point where the order is created is a separate, deployment-specific
  integration.
- **True real-time delivery** (WebSockets/SSE) for chat and notifications —
  both use short-interval AJAX polling (~4s for chat, ~20s for the
  notification bell) instead, which needs no persistent server process and
  works unmodified on ordinary shared hosting, at the cost of a few seconds
  of latency versus a socket-based push.
