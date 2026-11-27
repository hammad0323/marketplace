# The Hashir — website, Super Admin panel & mosque portal

Core PHP (procedural) · MySQLi prepared statements · Bootstrap 5 · jQuery · Select2 · SweetAlert2 · Quill editor · Leaflet/OpenStreetMap · Apache `.htaccess`.
Runs on shared hosting / cPanel, PHP 7.2+ (tested on PHP 8.3 + MariaDB 10.11 with the export `u925768674_thehashir`, 26 Nov 2026).

**The database structure is not changed.** No tables or columns are added, renamed or removed. The system reads and writes the
existing tables (`th_venue`, `th_details`, `prayer_timings`, `th_jummah_prayers`, `blogs`, …) exactly as they are. Data that has no
column in the existing schema (site settings, blog page-builder layouts, static pages, drafts, approval queue, login throttling,
newsletter sign-ups) is stored as JSON files in the private `storage/` folder. See [AUDIT.md](AUDIT.md) for the full database audit.

---

## 1. What's included

| Area | URL | Highlights |
|---|---|---|
| Public website | `/` | Homepage (hero search, city chips, featured mosques, mosques near you, programs, services, upcoming events, organizations, blogs, newsletter: every section can be switched on/off), directory, profiles, catalogs, prayer times by city, blogs, search, map |
| Mosque & organization profiles | `/mosques-details/{slug}/{id}`, `/organization-details/{slug}/{id}` | Cover + logo header, verified badge, AKA, Follow (uses `mosque_followers`), Share, Directions, quick contact tiles, sticky section tabs, About (read more), announcements, programs/services/events, people, member mosques/affiliations, map, sticky prayer card (Adhan + Iqamah, next prayer and live countdown in the mosque's own time zone, sunrise/sunset), separate Jumu'ah card (multiple sessions, khutbah/salah, khateeb), related mosques |
| Map discovery | `/mosque-map` | Leaflet map of mosques with a confirmed location + "Use my location" nearest list |
| Super Admin | `/manage` | Dashboard, approvals, mosques, organizations, programs/services/events, announcements, map locations, prayer times, Jumu'ah, blog builder, pages, users & roles, settings, subscribers, activity log |
| Mosque & organization portal | `/portal` | Own dashboard (profile completion, prayer/Jumu'ah status), profile editing (with approval), programs/services/events, announcements, prayer times, Jumu'ah, password |

The existing `/admin` and `/sign-in` of the current system are left untouched and keep working side by side.

### Prayer management (Super Admin for any mosque, portal for its own mosque)
- **Calendar**: month grid, missing days highlighted, source tags (manual / imported / calculated); click a day to edit.
- **Daily entry**: Fajr, Sunrise, Dhuhr, Asr, Sunset, Maghrib, Isha: Adhan + Iqamah; copy from the previous day; delete.
- **Bulk upload**: CSV or Excel `.xlsx`, flexible column names, preview with validation (invalid dates/times, duplicate dates in the file, dates that already have times), optional replace, downloadable template.
- **Calculate**: angle-based (ISNA, MWL, Egypt, Umm al-Qura, Karachi, Tehran, custom angles), Asr Standard/Hanafi, high-latitude rule, time zone (DST-aware), coordinates, per-prayer minute adjustments, Iqamah rules. Preview first; by default only empty days are filled. Manually entered/imported days are only replaced if you choose so **and** type `REPLACE`.
- **Copy & bulk adjust**: copy one day's times to a range; shift selected prayers by ± minutes over a range.
- **Export**: CSV in the same format as the upload.
- **Jumu'ah**: multiple sessions, valid from–to (repeats every Friday) or a single Friday, khutbah time, salah time, khateeb, location/topic/notes, season (auto from DST), copy sessions to a new season.

### Blog builder (`/manage/blogs`)
23 section types: main heading, subheading, rich text, paragraph, image, gallery, video, YouTube, quote, highlighted info,
bullet list, numbered list, FAQ (adds FAQ schema), accordion, call to action, custom HTML (sanitised), **mosque map**,
**mosque cards carousel**, related blogs, related programs/services/events, and "City guide (original content)".
Add unlimited sections, drag & drop (or ↑/↓) to reorder, duplicate, remove, collapse; private preview; draft / published / scheduled;
duplicate whole blogs; SEO title, meta description, canonical, share image, noindex, live Google-result preview; Article schema.

- **Mosque map**: search existing mosques, add/remove/reorder, markers at their saved locations with a popup linking to the profile, starting view/zoom or "fit all", height. Mosques without a confirmed location are flagged with a link to set it, never placed at a guessed position.
- **Mosque carousel**: search, add, reorder, 2–4 visible cards, card or compact style. Cards are built live from `th_venue`, so profile changes show automatically.
- **Existing blogs** keep all their content: they open with the "City guide (original content)" section, and the original fields (quick answer, population, map, summary) are editable in the sidebar.

---

## 2. Folder structure

```
index.php            Front controller (all routes)
.htaccess            Clean URLs, blocks private folders
config/config.php    Database, URLs, paths, defaults            <- edit this
includes/            bootstrap, db (MySQLi), data queries, layout, partials,
                     auth.php (login, CSRF, throttling, activity log), storage.php (settings & JSON storage),
                     geo.php (coordinates), blocks.php (blog/page builder renderer),
                     backoffice.php (admin/portal layout), venue_admin.php (profile/detail/announcement forms),
                     prayer_admin.php (prayer & Jumu'ah manager), prayer_calc.php (calculation), uploads.php
pages/               Public pages
api/                 cities, contact, follow, newsletter, map (JSON)
app_admin/           Super Admin pages (served at /manage)
app_portal/          Portal pages (served at /portal)
assets/              css, js (app.js public, admin.js, blog-builder.js), vendor libraries (all local, no CDN)
content/pages/       Optional legal pages as .html (fallback; manage them in Admin → Pages instead)
storage/             PRIVATE, writable JSON storage (blocked by .htaccess)
```

## 3. Installation on cPanel

1. **Back up first**: cPanel → Backup → download a full backup *and* a database backup (phpMyAdmin → Export). Keep it.
2. Upload the contents of `thehashir/` (a zip is easiest: File Manager → Upload → Extract).
   - Beta: into `public_html/beta/`. Live: into `public_html/` (see "Going live").
   - Turn on *Show hidden files* and confirm `.htaccess` and `storage/.htaccess` were uploaded.
3. Edit `config/config.php`:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`: your existing database. The back office needs SELECT, INSERT, UPDATE, DELETE on it.
   - `SITE_URL` (live address), `CONTACT_EMAIL`, `MAIL_FROM` (an address on your domain).
   - `MEDIA_DIR`: the folder that **contains** your existing `images/` folder. Leave `''` when the site runs in `public_html` and images are in `public_html/images`. For the beta in `/beta`, set it to the full path of `public_html` (e.g. `/home/USER/public_html`) so uploads land in the same `images/` folders the current site and apps use.
   - `STORAGE_DIR`: keep the default or point it to a folder outside `public_html` (recommended, e.g. `/home/USER/thehashir-storage`).
   - `BETA_MODE`: `true` while testing at `/beta` (noindex everywhere), `false` when live.
4. Permissions: `storage/` (or your `STORAGE_DIR`) must be writable (755/775). `images/` and its sub-folders must be writable for uploads; `images/blogs/` is created automatically.
5. Open `/manage/login` and sign in with an existing `th_admin_table` account (username or email + current password). Check the **Dashboard → Data health** box: storage and images must show "Yes".
6. Settings → General / Homepage / SEO / Maps / Approval workflow: review and save once.

### Admin account setup
- Existing admins sign in with their current credentials. On first successful login the password is converted to a secure hash automatically.
- Add more admins in **Users → Admins** (role *Super admin* or *Editor*). Editors manage content but not users, settings or logs.
- Forgotten admin password: `/manage/forgot` (email link, valid 1 hour).

### Mosque & organization logins
- Venues sign in at `/portal/login` with `th_venue.username` and their password. Set or reset logins in **Mosques → (profile) → Portal login** or **Users → Mosque & organization accounts**.
- Portal users only ever see and change their own profile: every query is bound to the venue in the session, and ids in URLs or forms are checked against it.

### Going live (replacing the old public pages)
1. Back up again. Keep `images/`, the old admin and app files.
2. Move the build into `public_html/`; in `.htaccess` set `RewriteBase /`, switch the "Pages of the current system" lines as the comments explain, uncomment the HTTPS/www block.
3. `config.php`: `BETA_MODE` → `false`, `MEDIA_DIR` → `''`.
4. Submit `https://www.thehashir.com/sitemap.xml` in Google Search Console. All existing public URLs are preserved (section 5).

## 4. Password migration (read before going live)

Existing passwords in `th_venue.password` / `th_admin_table.admin_password` are plain text (a few are MD5).
- **New and changed passwords are always stored with `password_hash()`** and checked with `password_verify()`.
- With `PASSWORD_MIGRATE_ON_LOGIN = true` (default), a legacy password is replaced by a hash right after its owner signs in successfully here.
- **Users → Password security → Hash all legacy passwords** converts all plain-text passwords at once (requires typing `HASH`). MD5 values are migrated at the next login instead.
- ⚠️ **Any other system that reads these columns** (the old `/admin`, the mobile app API, the display-screen system) **must also verify with `password_verify()`**, otherwise those logins stop working for migrated accounts. If you are not sure, set `PASSWORD_MIGRATE_ON_LOGIN` to `false` and don't run the bulk conversion until those systems are updated. (The export already contains one bcrypt venue password, which suggests part of the current system supports hashes.)
- Passwords are never shown, logged or emailed. Logins are throttled: 5 failures per 15 minutes per account and per IP.

## 5. URLs

Existing URLs are preserved; pages are resolved by ID and wrong slugs 301-redirect to the canonical URL.

| Page | URL |
|---|---|
| Home | `/` |
| Mosques | `/mosques?city=&state=&q=` · profile `/mosques-details/{slug}/{id}` · map `/mosque-map` |
| Organizations | `/organizations-catalog` · `/organizations/{type}/{role_id}` · profile `/organization-details/{slug}/{id}` |
| Programs / services / events | `/program-catalog`, `/services-catalog`, `/events-catalog` · `/programs\|services\|events/{category}/{id}` · detail `/details/{slug}/{id}` (`/programs`, `/services`, `/events` redirect to the catalogs) |
| Prayer times | `/prayer-times` · `/prayer-times/{country}/{city}/{id}` |
| Blogs | `/blogs` · `/blog/{slug}/{id}` |
| Static pages | `/about`, `/support`, `/solution-mosque`, any page created in Admin → Pages (`/privacy-policy`, `/terms`, …) |
| SEO | `/sitemap.xml` (published, indexable records only), `/robots.txt` |

SEO: unique titles and descriptions, canonical URLs, Open Graph/Twitter cards, breadcrumbs, JSON-LD (Organization, WebSite search, Mosque/Organization, Event, Article, FAQPage, BreadcrumbList), image alt text, GA4 and Search Console verification from Settings.

## 6. Security

Prepared MySQLi statements everywhere · output escaping · HTML sanitising of editor content · CSRF tokens on every POST ·
`session_regenerate_id()` on login, HttpOnly/SameSite cookies, idle timeout · role checks on every admin page · portal ownership checks ·
uploads: size limit, MIME + image-header check, random file names, JPG/PNG/WebP/GIF only · login throttling · private folders blocked ·
activity log for logins, profile, prayer, Jumu'ah, blog, user, password and settings changes (stored in the existing `th_display_audit_logs`, with sensitive fields removed).

## 7. External services & configuration

| Feature | Needs |
|---|---|
| Map tiles | OpenStreetMap by default (fair-use). For heavy traffic set a provider URL with your key in Settings → Maps. |
| "Find from address" / location look-ups | OpenStreetMap Nominatim from the admin's browser, max 1 request/second. No key needed. |
| Emails (contact form, password reset) | PHP `mail()` from `MAIL_FROM`; set SPF/DKIM for deliverability. |
| Excel import | PHP `zip` extension (CSV always works). |
| Hijri date on new prayer rows | PHP `intl` extension (optional). |

**Coordinates.** `th_venue` has no latitude/longitude columns. Coordinates are read from the existing `address_iframe` (Google Maps embed);
when an admin confirms a location the column is updated with a standard Google Maps embed (`q=lat,lng`), so the current site keeps showing a correct map.
In the export, only 6 mosques have readable coordinates. Use **Admin → Locations** to add and verify them (look-up suggestion → check pin → confirm).
Map features grow as locations are confirmed; no coordinates are ever guessed.

## 8. Backups & data safety

- Nothing in the existing data is deleted automatically. Deletions (a program, an announcement, a prayer day, a Jumu'ah session) only happen on an explicit admin/portal action with confirmation, and are written to the activity log with the old data.
- Back up the database **and** the `storage/` folder (cPanel → Backup). `storage/` holds settings, blog layouts and pages.
- No migration SQL is required. Optional indexes that speed up busy queries (they change no data; apply only after a backup):
  ```sql
  ALTER TABLE prayer_timings ADD INDEX idx_venue_date (venue_id, georgian_date);
  ALTER TABLE th_jummah_prayers ADD INDEX idx_venue_dates (venue_id, from_date, to_date);
  ALTER TABLE th_venue ADD INDEX idx_role_status_city (role_id, verify_status, city_id);
  ALTER TABLE th_details ADD INDEX idx_gc_status_venue (grand_category_id, status_verify, venue_id);
  ALTER TABLE th_announcement ADD INDEX idx_venue_created (venue_id, created_date);
  ```

## 9. Testing checklist

Verified locally against the SQL export (✓); repeat on your server after deployment.

- [✓] Every Super Admin page and every portal page loads without PHP or JavaScript errors
- [✓] Admin login, legacy password migrated to a hash on first login, logout
- [✓] Prayer: save a day; CSV import preview flags a duplicate date, an invalid time and an existing date; confirm imports only valid rows
- [✓] Prayer calculation matches published Toronto times; saving keeps manual/imported days
- [✓] Jumu'ah session added and shown on the profile
- [✓] Event created in the admin and shown on the profile
- [✓] Coordinates saved on a profile; the mosque appears on the blog map with a marker
- [✓] Blog builder: new blog with heading, rich text, mosque map, carousel, FAQ, CTA → published → public page renders all sections
- [✓] Settings: announcement bar saved and shown site-wide
- [✓] Portal: login; opening another venue's program redirects with an error; portal session can't open `/manage`; profile change goes to the approval queue
- [✓] Public pages: home, directory, profile (desktop + mobile), category filters, sitemap, search
- [ ] On the server: Dashboard → Data health shows storage and images writable
- [ ] Image upload (logo, cover, program image, blog image) appears on the site and in the existing app
- [ ] Contact form and password-reset emails arrive (check spam)
- [ ] Old URLs from Search Console open or 301-redirect
- [ ] Confirm other systems (old admin, app, screens) before enabling bulk password hashing
- [ ] Map tiles and address look-ups load (they need outbound access from the visitor's/admin's browser)
