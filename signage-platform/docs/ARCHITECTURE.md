# System Architecture

Signal Signage is a mobile-first digital signage SaaS platform: business owners design screens on their phone, combine them into playlists, and push them to Android TV displays. The system has three parts that share one backend.

```
┌──────────────────────────────── Flutter (one codebase, two entry points) ────────────────────────────────┐
│                                                                                                         │
│  lib/main.dart  ── Management app (phones / tablets)          lib/main_player.dart ── Signage Player   │
│  ┌────────────────────────────┐ ┌─────────────────────────┐  ┌──────────────────────────────────────┐ │
│  │  User experience           │ │ Super Admin experience  │  │ Android TV / box / tablet            │ │
│  │  Home · Screens · Playlists│ │ Dashboard · Users ·     │  │ Register → pair → sync → play        │ │
│  │  Devices · Profile         │ │ Plans · Templates · …   │  │ Offline cache · schedule engine      │ │
│  │  Editor · Media · Schedules│ │ (role-gated routes)     │  │ Heartbeat · command queue · logs     │ │
│  └─────────────┬──────────────┘ └────────────┬────────────┘  └──────────────────┬───────────────────┘ │
│                │ Bearer user token (Dio)      │                                  │ Bearer device token  │
└────────────────┼──────────────────────────────┼──────────────────────────────────┼─────────────────────┘
                 ▼                              ▼                                  ▼
        ┌─────────────────────────────────────────────────────────────────────────────────────┐
        │  PHP 8 REST API (procedural, MySQLi prepared statements)        HTTPS + CORS          │
        │  /api/auth  /api/admin  /api/screens  /api/widgets  /api/templates  /api/media        │
        │  /api/playlists  /api/schedules  /api/devices  /api/notifications  /api/settings      │
        │  includes/: auth · permissions · validation · response · upload · rate-limit          │
        └───────────────┬───────────────────────────────────────────────┬─────────────────────┘
                        ▼                                               ▼
              ┌───────────────────┐                       ┌───────────────────────────┐
              │ MySQL 8 (InnoDB)  │                       │ File storage              │
              │ 39 tables, 2 views│                       │ uploads/ (local) or S3    │
              └───────────────────┘                       │ images·videos·thumbs·docs │
                                                          └───────────────────────────┘
```

## 1. Key decisions

| Decision | Choice | Why |
|---|---|---|
| Backend | Core PHP 8.1+, procedural, MySQLi only | Required. Runs on any shared host or VPS. |
| API style | One PHP file per endpoint, JSON in/out, `{status, message, data}` envelope | Easy to deploy on Apache with no router. |
| Auth (users) | 64-hex random bearer token. Only the SHA-256 is stored (`api_tokens`). Expires after 30 days; can be revoked | Stateless for the client; a database leak exposes no usable tokens. |
| Auth (players) | A separate device token per player (`devices.device_token_hash`) | A TV never holds a user's credentials. |
| Pairing | 6-character code shown on the TV, stored as a SHA-256 hash (`device_pairing_codes`). Expires in 15 min; attempts are rate-limited | Easy to type on a phone, and short-lived. |
| Flutter state | **Riverpod 2** | Compile-safe, testable, and works without BuildContext (useful for the player engine). |
| HTTP | Dio with interceptors for auth headers, 401 → logout, and retry | |
| Local storage | `flutter_secure_storage` for tokens. **Hive** for the player's offline cache and the queued logs | Hive is fast and pure Dart, and runs well on low-end TV boxes. |
| Layout model | Normalised coordinates (0–1) plus a design resolution | Renders the same on 720p, 1080p and 4K, in any orientation. |
| Publishing | Immutable versioned snapshots (`screen_publications`, `playlist_publications`) | The player never sees half-saved drafts, and rollback is easy. |
| Sync | Version-based polling, with the heartbeat carrying a "changed" hint | Works behind NAT and firewalls without WebSockets. |

## 2. Roles and access control

* **Roles**: `super_admin` and `user` (the `roles` table). Permissions are the keys in `permissions`, linked to roles in `role_permissions`.
* **Per-user overrides** (`user_permissions.allowed`) can grant or revoke single permissions. Example: User A cannot upload video.
* **Quantitative limits** are resolved in this order: `user_limits` column (when not NULL) → active plan (`user_subscriptions` → `plans`) → default plan. The view `v_user_effective_limits` implements this, and `-1` means unlimited.
* **Enforcement happens on the backend.** For example, `screens/create.php` runs:
  1. `BEGIN`
  2. `SELECT … FROM users WHERE user_id=? FOR UPDATE` (serialises concurrent creates for that user)
  3. compares `v_user_usage.screens_used` with `v_user_effective_limits.max_screens`
  4. on failure: HTTP **403** with `{"status":false,"code":"LIMIT_SCREENS","message":"You have used 2 of 2 screens on your plan. Ask your administrator to increase your limit."}`
  5. otherwise `INSERT …; COMMIT`
* **Ownership**: every query for a user-owned resource includes `AND user_id = <id from token>`. The IDs a client sends are never trusted. On top of that, composite foreign keys in the database stop a playlist from containing another user's screen, and stop a device from being given another user's playlist (see `DATABASE.md §4`).

## 3. Main data flows

### 3.1 Create and publish a screen
```
Template library ──(template_json)──► screens/create.php  ──► screens + screen_widgets (draft)
Editor (local undo/redo stack) ──autosave every 5 s / on exit──► screens/save-layout.php
      (full layout document; layout_version guards against stale writes → 409 CONFLICT)
Publish ──► screens/publish.php:
      BEGIN; build snapshot (layout + media manifest with checksums);
      mark the previous publication 'superseded'; INSERT screen_publications(version+1);
      screens.published_version = version; mark playlists that contain this screen as having changes;
      COMMIT
```

### 3.2 Playlist (carousel of complete screens)
`playlists/publish.php` embeds the **current publication** of every enabled screen into one `playlist_publications.published_json`, together with the schedules and a de-duplicated media manifest. Draft-only screens are skipped and the API returns a warning listing them.

> A **playlist** rotates whole screens. An **image carousel widget** rotates images inside one zone of one screen. These are separate tables and separate features.

### 3.3 Device lifecycle
```
Player first launch → POST devices/register.php {device_uid, model, resolution, …}
     ← {device_token (once), device_code}
Player → POST devices/generate-pairing-code.php      ← {pairing_code:"K7Q4ZP", expires_in:900}
         TV shows the code + QR
User app → POST devices/pair.php {pairing_code, device_name, location_id}
         backend: rate-limit, hash lookup, check device limit, set devices.user_id, mark code used
Player polls GET devices/sync.php  (every sync_interval_seconds, or right away when the heartbeat says changed=true)
     ← {assignments:[{playlist_id, version, is_default}], emergency:[…], commands:[…], server_time}
Player downloads any playlist_publication whose version it doesn't have, then any missing media
(verifies sha256), then swaps content atomically.
Player → POST devices/heartbeat.php every 60 s {state, current_playlist_id, current_screen_id, storage_free_mb, app_version}
     ← {changed: bool, pending_commands: n}
```

## 4. Schedule resolution (runs on the player, mirrored on the server for previews)

At any moment, for one device:

1. **Emergency**: an `emergency_messages` row that is active, inside its time window and targeting this device overrides everything else.
2. Collect every assigned playlist (`device_playlists`) whose publication is not revoked.
3. For each playlist, find its **active schedules**. A schedule is active when all of these hold:
   * `status='active'`
   * today is within `start_date…end_date` (a NULL date means no bound on that side)
   * the ISO weekday is in `days_of_week_json` (NULL means every day)
   * the local time (in the schedule's `timezone`, falling back to the device timezone) is within `start_time…end_time`. When `end_time < start_time` the window crosses midnight.
4. Effective priority = `schedule.priority` + **1000** if `schedule_type='special_event'`, + **500** if `one_time`.
5. The highest effective priority wins. Ties go to the higher `playlists.priority`, then the shorter time window (more specific), then the lower `schedule_id` (older and stable).
6. If no schedule matches, the **default playlist** plays (`device_playlists.is_default=1`). If there is no default, the player shows its branded idle screen (clock and logo). It never shows an error screen.
7. Inside a playlist, an item is skipped when `is_enabled=0`, when the time is outside its `valid_from/valid_until` window, or when its screen has passed `expires_at`.

The engine re-evaluates every minute and at each item boundary. A switch only happens at the next item boundary, unless the change comes from an emergency message, which switches immediately.

## 5. Synchronisation and offline playback

* **The version is the source of truth.** Every publish increments `version` and stores `content_hash`.
* The player keeps `cache/current/` (the playlist that is playing) and `cache/staging/` (a download in progress). Media files are content-addressed by `media_id + checksum`. Files that are already present are not downloaded again.
* The swap happens only once **all** media in the new manifest have been verified. Until then the old version keeps playing.
* With no network the player keeps playing from Hive and the media cache. Logs and playback records queue locally (capped at 5,000 entries, oldest dropped first) and upload in batches when the connection returns.
* After a reboot the player starts with **BOOT_COMPLETED**, opens the last good cache straight away, and syncs in the background.
* **Fault isolation**: every widget renders inside an error boundary. If a widget fails (missing media, a corrupt video, a weather timeout), it renders transparent, a `playback_error` is logged, and the rest of the screen keeps playing. An empty or fully failing playlist falls through to the next candidate from §4.

## 6. Layout document (the shared contract)

The same JSON shape is used in `screen_templates.template_json`, by the `save-layout` API payload, and inside the publications. **Field names match the database columns.**

```json
{
  "schema_version": 1,
  "canvas":     { "orientation": "landscape", "width": 1920, "height": 1080 },
  "background": {
    "type": "gradient",                     // color | gradient | image | video | preset
    "value": null,                          // "#17191C" for color, preset key, or media_id as string
    "media_id": null,
    "config": {
      "gradient": { "type": "linear", "angle": 135, "colors": ["#006B66", "#17191C"], "stops": [0, 1] },
      "fit": "cover", "position": "center", "opacity": 1.0, "blur": 0,
      "overlay_color": "#000000", "overlay_opacity": 0.0
    }
  },
  "widgets": [
    {
      "widget_uid": "8c1d…",                // client-generated UUID (stable across undo/redo)
      "widget_type": "heading",             // FK → widget_types.widget_key
      "widget_name": "Welcome",
      "position_x": 0.08, "position_y": 0.32,   // left/top as fraction of canvas
      "width": 0.84,      "height": 0.18,       // fraction of canvas
      "rotation": 0, "z_index": 3, "is_locked": false, "is_visible": true,
      "configuration": { "text": "Welcome", "font_size": 110, "color": "#FFFFFF", "animation": "fade_in" }
    }
  ]
}
```

**Rendering rule.** Given an output box of `W×H`:

* fit the canvas aspect ratio (`width/height`) inside the box, letter-boxed with the background colour
* scale `s = renderedWidth / canvas.width`
* a widget's rect is `(x·rw, y·rh, w·rw, h·rh)`
* sizes inside `configuration` (font_size, padding, border_radius, gap, speed) are **design pixels** and are multiplied by `s`

This keeps the editor on a 390-pt phone and a 4K TV pixel-identical in proportion.

Publications add `screen_id`, `screen_uid`, `version`, `default_duration` and a `media` manifest of `[{media_id, file_path, mime_type, file_size, checksum_sha256}]`. The API turns `file_path` into a signed download URL. Hostnames are never stored in the database.

## 7. Widget system (extensible)

* `widget_types` is the registry: `widget_key`, `default_config_json`, default size, `availability` (`stable` / `beta` / `planned`) and `is_premium`.
* In Flutter, `WidgetRegistry` maps `widget_key → (Renderer, SettingsPanel, icon)`. To add a widget: insert one row in the database, write one renderer and one settings panel, then register them. The editor, the player, the layers panel and the publication code don't change.
* An unknown `widget_key` on an older player renders as nothing and is logged. It never crashes the player.
* Media referenced by a widget is mirrored into `widget_assets`. This gives integrity (`ON DELETE RESTRICT`), ordering, and cleanup of unused files.
* `availability='planned'` widgets (YouTube, social feed, PowerPoint) are hidden from the editor until they are implemented and tested. Admins can switch availability in the admin panel.

## 8. Security controls

| Threat | Control |
|---|---|
| SQL injection | MySQLi prepared statements only. Identifiers (sort columns) come from allow-lists. |
| Credential theft | `password_hash` (bcrypt or argon2). Tokens, reset tokens and pairing codes are stored as SHA-256 hashes. |
| Brute force | `rate_limits` fixed window: login 10 per 15 min per IP plus per email; pairing 10 per 15 min per user; reset 3 per hour. |
| Insecure direct object references (IDOR) / cross-tenant access | Ownership predicate in every query, plus composite foreign keys. |
| Malicious upload | Extension allow-list, `finfo` MIME sniffing, a size cap per plan, random file names, an `.htaccess` that turns off PHP execution in `uploads/`, and image re-encoding through GD. |
| Account takeover by reset | Single-use token, 30-minute expiry. All API tokens are revoked on reset. |
| Token misuse | Expiry, revocation on logout, list and revoke sessions, and `last_used_at` auditing. |
| Path disclosure | API errors are generic, and details go to the server log only. `display_errors=Off`. |
| Transport | HTTPS required in production. HSTS header. CORS allow-list from config. |
| Audit | `activity_logs` records every admin action and publish, pair and delete. |

## 9. Flutter project structure (Phase 3+)

```
lib/
├── main.dart                     # management app entry
├── main_player.dart              # signage player entry (separate APK flavour / launcher)
├── core/  constants · theme (M3 light/dark, teal palette) · network (Dio client, interceptors, ApiResponse)
│          services (auth, sync, scheduler) · storage (secure storage, Hive boxes) · utils · widgets (shared UI kit)
├── models/            # 1:1 with API payloads (json_serializable), field names = DB columns
├── providers/         # Riverpod providers / notifiers
├── repositories/      # API calls per module
├── features/
│   ├── authentication  dashboard  admin  users  screens  editor  templates  widgets_library
│   ├── media_library  playlists  scheduling  devices  notifications  profile
│   └── signage_player  (engine/, renderers/, cache/, pairing/)
└── routes/            # go_router, role-guarded
```

Packages: `flutter_riverpod`, `dio`, `go_router`, `flutter_secure_storage`, `hive`/`hive_flutter`, `image_picker`, `file_picker`, `video_player`, `cached_network_image`, `qr_flutter`, `intl`, `timezone`, `uuid`, `crypto`, `connectivity_plus`, `wakelock_plus`, `device_info_plus`, `package_info_plus`, `webview_flutter` (beta widgets), `pdfx` (PDF widget), `google_fonts` (bundled offline for the player).

## 10. Development roadmap

| Phase | Scope | Exit criteria |
|---|---|---|
| **1. Architecture and database** ✅ | Architecture, ER diagram, schema (39 tables + 2 views), 26 templates, 36 widget types, seed, docs | `tests/verify_database.sh` → 57/57 pass |
| 2. PHP backend | config/includes, all endpoints in `API_STRUCTURE.md`, upload pipeline, rate limiting, API docs + Postman collection | API test script covers auth, limits, IDOR, pairing and sync |
| 3. Flutter foundation | Theme, auth, navigation, Dio and Riverpod, user and admin dashboards | Login and the dashboard run against a live API |
| 4. Screen builder | Template library, editor (drag, resize, guides, layers, undo/redo, zoom), widget panels, background, preview, publish | Workflows A and E end to end |
| 5. Playlist builder | Screen multi-select, reorder, durations, transitions, schedules, preview | Workflow B end to end |
| 6. Signage player | Register, pair, sync, cache, render, schedule engine, heartbeat, commands, offline | Workflow C, plus offline and reboot tests on Android TV |
| 7. Admin and finalisation | Users, plans, templates, widgets, devices, settings, reports, hardening, deployment guide | Full test matrix (§22 of the brief) |
