# Signal Signage: Mobile-First Digital Signage Platform

Signal Signage is a complete digital-signage system:

* Business owners design screens on their phone, combine them into playlists and schedule them.
* Android TV players show the content, keep playing when offline, and update automatically.
* A super admin controls users, plans, limits, devices and **which templates each customer may use**, and designs professional templates in a visual editor.
* Everything is available twice: in the Flutter app **and** in a responsive core-PHP web app (`/web/`) that works on desktop, tablet and phone browsers.

| Part | Technology | Location |
|---|---|---|
| REST API (112 endpoints) | Procedural PHP 8.1+ with MySQLi prepared statements, JSON | `backend/` |
| Database (39 tables, 2 views) | MySQL 8 / MariaDB, InnoDB, utf8mb4 | `backend/database/` |
| Management app (users + super admin) | Flutter 3, Material 3, Riverpod, go_router, Dio | `flutter_app/lib/main.dart` |
| Web app (users + super admin + browser TV player) | Core PHP pages + vanilla JS, same REST API | `backend/web/` |
| TV display | **The same app**: tap *Show Display* on the sign-in page and enter a screen or playlist code | `flutter_app/lib/features/display/` |

![Home](docs/screenshots/02_home.png)

## Status

| Check | Result |
|---|---|
| Database suite | **59/59** checks pass |
| End-to-end API suite (workflows A–E, display codes, template access, animations, web app pages, limits, IDOR, uploads, pairing, sync, rate limits) | **299/299** checks pass |
| Flutter tests (incl. screen-card overflow at 144–230 px widths and 130% font size) | **31/31** pass (incl. animation maths), plus an offline-display integration test against the live API |
| `flutter analyze` | No issues |
| Web build of both apps | Done |
| Live player test (register → pair → sync → play → command → emergency) | Done |
| Web app in Chromium (user, admin, phone layout, TV browser player) | Done, no console errors |

Full details and the list of things that were **not** tested in this environment (native Android/iOS builds, MySQL 8, real devices) are in **[docs/TESTING.md](docs/TESTING.md)**.

## Screens

| Area | Screenshots |
|---|---|
| Management app (412×915) | `docs/screenshots/01_login.png` … `22_admin_plans.png`: dashboard, My Screens, template library, editor (select, drag, settings, layers, add widget), playlists, playlist builder, schedules, devices, device detail, media library, profile, admin dashboard, users, create user, plans |
| Web app | `50_web_new_screen_restricted_templates.png` … `58_web_mobile_editor.png`: template picker limited to a restaurant's templates, editor, TV browser player, playlist, display code, admin templates, admin template editor, per-user template access, phone editor |
| Menu templates in the app | `59_app_menu_templates.png` |
| Animations | `60`–`62`: frames over time (web player and app player), `63`–`64`: animation settings and *Animate whole screen* in the web editor |
| TV player (1920×1080) | `23_player_pairing.png`, `24`–`26_player_playing_*.png` (automatic rotation), `27_player_emergency.png` |

## Features

**User app**
* Dashboard with plan usage and quick actions.
* Screens: create from **33 templates in 14 categories** (including 7 photo menu boards: price badges, hand-lettered headings, dish photos with real drop shadows, textured backgrounds) or a blank canvas (landscape, portrait, square or custom size). Also folders, search, filters, favourites, duplicate and save-as-template.
* Visual editor:
  * drag, resize handles, snapping guides, zoom
  * layers, lock, front/back, align, multi-select
  * copy/paste, undo/redo, autosave with conflict detection
  * preview, publish
* Widgets: **31 renderable types**, each with a settings panel generated from its configuration:
  * media: image, carousel, video, logo, background image, shape
  * text: heading, rich text, ticker
  * time: digital and analog clock, world clock, countdown, count-up, calendar, daily schedule
  * data: weather (Open-Meteo), RSS, live JSON data, QR code
  * business: menu board, price list, product showcase, contact, opening hours, location
  * messages: announcements, quotes, daily motivation, emergency
  * embeds: web page and custom HTML (WebView on Android/iOS)
* **Animations on every element (text, images, shapes, menus, clocks, …)** so screens play like a looping video:
  * 17 entrance effects: fade, slide ×4, zoom in/out, pop, drop & bounce, flip ×2, spin in, blur in, wipe ×2, typewriter, each with duration and delay to stagger elements
  * 18 continuous loop effects: pulse, breathe, float, bounce, shake, wobble, swing, sway, spin, heartbeat, flash, tada, jello, rubber band, slow zoom (Ken Burns), glow, shine sweep, colour cycle, each with speed and intensity
  * *Replay every N seconds*: elements exit and come back in, so the scene loops forever
  * **Animate whole screen** in one tap (Smooth / Energetic / Elegant), then fine-tune per element; the 7 photo menu templates come animated
  * Identical on the app, the Android TV player, the web preview and the browser TV player
* Backgrounds: colour, gradient, image, video or preset, with opacity, blur, overlay, fit and position.
* Media library: uploads with progress, folders, rename, move, delete with in-use protection, storage meter.
* Playlists ("screen carousels"): pick screens, drag to reorder, set durations, 8 transitions, loop, shuffle, priority, preview, publish, assign to displays.
* Scheduling: time slots, days, date ranges, overnight slots, time zones, priorities and special events, plus a day timeline.
* Devices: pair with a 6-character code, assign playlists and a default, remote restart/pause/resume/sync/identify/clear cache, logs, live status, locations, emergency broadcast, reports.

**Super admin**
* Dashboard (users, screens, playlists, devices, storage, offline list, activity).
* Users: create with a generated password and share sheet, edit, reset password, suspend, delete. Per-user limits (`∞` / number / follow the plan) and permission overrides.
* Plans: Free, Starter, Professional, Business and Enterprise, all editable.
* **Template access per customer:** *All templates* or *Only selected*, by whole category (e.g. Restaurant & Cafe) and/or individual templates. A restaurant then sees only restaurant templates everywhere (app, web, API); templates they save themselves always stay available.
* **Template designer (web admin):** the same editor as for screens, plus category, tags, premium flag, active/hidden status and **Lock design for users** (users can still change text, prices and photos but cannot move or delete locked elements). Images uploaded while designing are shared with all users.
* Content overview, widget availability, all devices, media (shared library), audit log, app settings.

**Web app (`https://your-server/web/`)**
* Same features as the app: dashboard, screens with template picker, full editor (drag, resize, rotate, snapping, multi-select, align/distribute, layers, lock, undo/redo, copy/paste, keyboard shortcuts, zoom, backgrounds, every widget setting incl. fonts, colours, text effects and image/shape shadows, preview, autosave, publish with conflict handling), playlists with drag-and-drop and schedules, media library with drag-and-drop upload and folders, display codes with QR, managed displays (pair, assign, commands), profile and sessions.
* Admin: dashboard, users (limits, widgets, permissions, template access), templates gallery + designer, plans, shared media, settings.
* Responsive: sidebar on desktop, bottom navigation on phones; the editor's panels become bottom drawers.
* **Browser TV player** (`/web/display.php`): any smart TV or PC with a browser can show a display code full screen — no app needed. It re-checks for updates every minute and keeps playing the last content if the connection drops (offline storage of media is only in the app).
* Sessions use an HTTP-only cookie plus CSRF-protected login; the pages call the same API, so every limit, permission and template restriction applies identically.

**Show on a TV: one app, just a code**
* Every screen and playlist has a display code (shown under ⋮ → *Show on a TV* and the 📺 button on each card). Users can choose their own code (for example `LOBBY`) or generate a new one.
* On the TV, install the same app → *Show Display* on the sign-in page → type the code → it plays full screen.
* **Offline mode (on by default in the app):** videos and images download once with a progress bar and are checksum-verified. After that the TV plays from its own storage and **uses no internet at all**. Use *Download updates now* in the TV menu, or turn offline mode off to update automatically. It still works if the internet is cut and after a reboot.
* Without offline mode, it updates within a minute of publishing. Even then, files are only downloaded when they change; each check is a tiny request.
* OK, Enter or Back on the remote (or a long press) opens a menu: continue, check for updates, change code, exit.
* Emergency broadcasts sent to "all displays" also appear on code displays.

**Managed displays (optional, same app)**
* Registration and pairing screen with code and QR.
* Version-based sync with **SHA-256-verified** media cache. A new version goes live only when all its media is ready.
* Offline playback from the persisted cache.
* Schedule engine identical to the server's (unit tested).
* Transitions, heartbeats, command queue, proof-of-play and logs queued offline.
* Emergency override, idle clock screen, info panel on the remote's OK button, boot auto-start, wakelock.

**Security**
* bcrypt passwords. Tokens, reset codes and pairing codes are stored only as SHA-256 hashes.
* Role and permission checks on the server, ownership checks on every query, plus composite foreign keys that prevent cross-tenant links.
* Rate limits, MIME-sniffed uploads, signed expiring media URLs, generic error messages, audit log.

## Quick start

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for full and production instructions.

1. Create the database:

   ```bash
   mysql -u root -p -e "CREATE DATABASE signage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```

2. Load the schema:

   ```bash
   mysql -u root -p signage < backend/database/schema.sql
   ```

3. Load the seed data:

   ```bash
   mysql -u root -p signage < backend/database/seed.sql
   ```

4. Start the API:

   ```bash
   DB_NAME=signage DB_USER=root DB_PASS=secret php -S 0.0.0.0:8080 -t backend backend/router.php
   ```

5. Run the management app (from `flutter_app/`):

   ```bash
   flutter run --flavor manager -t lib/main.dart --dart-define=API_BASE_URL=http://10.0.2.2:8080
   ```

6. Or open the web app in a browser: <http://localhost:8080/web/> (same logins as below).

7. On a TV (or a second emulator), run the same app, tap **Show Display** and enter a code. The demo data includes `STORE1` and `EVENING` (playlists) and `WELCOME` and `PROMO1` (screens).

Development logins (delete them before production):

| Role | Email | Password |
|---|---|---|
| Super admin | admin@signage.local | Admin@12345 |
| User A (restaurant: 2 screens, at limit, only Restaurant & Menu templates) | aisha@demo.local | Demo@12345 |
| User B (full demo data) | bilal@demo.local | Demo@12345 |
| User C (10 screens) | chloe@demo.local | Demo@12345 |
| User D (unlimited) | daniel@demo.local | Demo@12345 |

## Documentation

| File | Contents |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design, auth, data flows, schedule priority, sync and offline, the layout JSON contract, widget system, security |
| [docs/ER_DIAGRAM.md](docs/ER_DIAGRAM.md) | Mermaid ER diagram and relationships |
| [docs/DATABASE.md](docs/DATABASE.md) | Every table and column, limit rules, cross-user protection, publication format, seed data |
| [docs/API.md](docs/API.md) | All 111 endpoints with request and response formats |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Local setup, Apache/Nginx, cron, hardening, building and signing the apps, installing on TVs |
| [docs/TESTING.md](docs/TESTING.md) | Test results and coverage, and what still needs on-device testing |

## Known limitations

* **Widgets not yet available.** PDF viewer, PowerPoint, YouTube, social feeds and the battery indicator are registered but marked *planned* and hidden from users. They need the extra integrations described in the architecture before they can be enabled.
* **Web-only widget behaviour.** In the Flutter web build the web page and custom HTML widgets show a placeholder (they play on Android/iOS via WebView). The PHP web app and browser TV player show them in an iframe, but many sites forbid being framed. RSS feeds and live-data URLs are fetched by the browser, so feeds without CORS headers only work on the Android/iOS player.
* **Template artwork.** The dish photos, decorations and textures in the 7 menu templates are illustrations generated on the server (image sites were not reachable from the build environment). Replace them with your own photos in *Admin → Shared media* / the template designer; transparent PNG cut-outs get the drop shadow automatically.
* **Browser TV player.** It does not store media for offline use; for offline playback use the app's Show Display.
* **Email.** Password-reset and credential emails use PHP `mail()` when `MAIL_ENABLED=1`, and are otherwise written to `backend/logs/mail.log`. Configure an SMTP relay for production.
* **Media storage.** Media is stored on local disk. The schema already supports S3 (`storage_driver`), but there is no S3 adapter yet.
