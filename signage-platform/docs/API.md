# API Reference

The API is procedural PHP 8.1+ using MySQLi prepared statements, with JSON over HTTPS. It has **111 endpoints**, each implemented as one PHP file under `backend/api/`. Every example on this page is exercised by `backend/tests/api_test.php` (246 checks).

## Conventions

| | |
|---|---|
| Base URL | `https://<host>/api/`. Clean URLs (`/api/screens/list`) and `.php` paths both work. |
| Auth | `Authorization: Bearer <token>`. **U** = user token from `auth/login`, **A** = super-admin user token, **D** = device token from `devices/register`, **P** = public. |
| Body | `application/json`. Uploads use `multipart/form-data` with the field `file`. |
| Envelope | `{"status": true\|false, "message": "...", "data": ..., "code"?: "ERROR_CODE", "errors"?: {"field": "msg"}, "meta"?: {"page","per_page","total","total_pages"}}` |
| Pagination | `?page=1&per_page=20` (maximum 100). |
| IDs | `user_id` always comes from the token. Every other ID is checked for ownership; a resource owned by someone else returns **404**. |
| Status codes | 200, 201 created, 400 validation, 401 unauthenticated, 403 forbidden or limit reached, 404, 405, 409 conflict / in use, 413 too large, 415 bad media type, 422 business rule, 429 rate limited, 500 (generic message with a reference id; details go to `logs/error.log`), 503 |
| Error codes | `VALIDATION` `UNAUTHENTICATED` `INVALID_CREDENTIALS` `FORBIDDEN` `NOT_FOUND` `LIMIT_SCREENS` `LIMIT_PLAYLISTS` `LIMIT_DEVICES` `LIMIT_STORAGE` `FILE_TOO_LARGE` `BAD_MEDIA` `VIDEO_NOT_ALLOWED` `WIDGET_NOT_ALLOWED` `PREMIUM_REQUIRED` `CONFLICT` `IN_USE` `RATE_LIMITED` `ACCOUNT_SUSPENDED` `ACCOUNT_INACTIVE` `ACCOUNT_EXPIRED` `PAIRING_INVALID` `ALREADY_PAIRED` `UNSUPPORTED_COMMAND` `RESET_INVALID` `MAINTENANCE` `SERVER_ERROR` |

## Authentication

| Method | Endpoint | Auth | Body / query → result |
|---|---|---|---|
| POST | `auth/login.php` | P | `{email, password, device_label?}` → `{token, expires_at, user, permissions[], limits, usage}`. Rate limit: 10 per 15 min per email, 30 per IP. |
| POST | `auth/logout.php` | U | Revokes the current token. |
| GET | `auth/profile.php` | U | `{user, permissions, limits, usage, settings, unread_notifications}` |
| POST | `auth/update-profile.php` | U | `{full_name?, phone?, business_name?, timezone?}`, or multipart `profile_image` |
| POST | `auth/change-password.php` | U | `{current_password, new_password}`. Signs out your other sessions. |
| POST | `auth/forgot-password.php` | P | `{email}` → emails an 8-character code that is valid for 30 minutes. The reply is the same whether or not the account exists. |
| POST | `auth/reset-password.php` | P | `{token, new_password}`. Single use. Revokes all sessions. |
| GET/POST | `auth/sessions.php` | U | Lists active sessions. `POST {token_id}` revokes one. |

**Example**

```http
POST /api/auth/login.php
{"email":"bilal@demo.local","password":"Demo@12345","device_label":"Pixel 8"}
```
```json
{"status":true,"message":"Signed in successfully","data":{"token":"9d97…b27c","expires_at":"2026-12-05 13:18:42",
 "user":{"user_id":3,"role_key":"user","full_name":"Bilal Hussain (User B)", "...":"..."},
 "permissions":["screens.view","screens.create","..."],
 "limits":{"plan_name":"Starter","max_screens":5,"max_playlists":3,"max_devices":3,"max_storage_mb":1024,"allowed_widgets":["image","..."],"can_upload_video":true,"can_use_premium_templates":false},
 "usage":{"screens_used":5,"playlists_used":2,"devices_used":2,"storage_used_bytes":187547}}}
```

## User dashboard and settings

| GET | `users/dashboard.php` | U | Stats (total, published and draft screens, playlists, devices online), limits, usage, recent screens (with layouts), recent activity |
|---|---|---|---|
| GET/POST | `users/settings.php` | U | `POST {settings:{theme, language, brand_primary_color, …}}` |

## Screens

| Method | Endpoint | Body / query |
|---|---|---|
| GET | `screens/list.php` | `q, status, orientation, folder_id (0 = none), favorites, sort=updated_at\|created_at\|screen_name, order, with_layout=1, page`. Admin only: `all=1&user_id=` |
| POST | `screens/create.php` | `{screen_name, template_id?}`, or blank: `{screen_name, orientation: landscape\|portrait\|square\|custom, width?, height?, background_color?}`. **Checks the screen limit and premium access.** |
| GET | `screens/details.php?screen_id=` | Screen + `layout` + `media` map (signed URLs) + playlists that use it |
| POST | `screens/update.php` | `{screen_id, screen_name?, description?, default_duration?, is_favorite?, folder_id?, expires_at?, status?: archived\|draft, background?: {type, value, media_id, config}}` |
| POST | `screens/save-layout.php` | `{screen_id, layout_version, layout: {canvas, background, widgets[]}, publish?: bool, force?: bool}`. Replaces the whole layout atomically. Returns **409 CONFLICT** if `layout_version` is out of date. |
| POST | `screens/publish.php` | `{screen_id}` → `{version, republished_playlists[]}`. Live playlists that contain the screen are republished automatically. |
| POST | `screens/duplicate.php` | `{screen_id, screen_name?}`. **Checks limits.** |
| POST | `screens/delete.php` | `{screen_id}`. Soft delete; removes the screen from playlists and republishes the affected live playlists. |
| GET | `screens/preview.php?screen_id=` | The draft rendered as a publication document |
| GET/POST | `screens/folders.php` | `POST {action: create\|rename\|delete, folder_id?, folder_name?, color?}` |
| GET | `screens/templates.php` | Alias of `templates/list.php` |

**Layout document** (`docs/ARCHITECTURE.md §6`): geometry is normalised to the 0–1 range of the canvas.

```json
{"screen_id":12,"layout_version":3,"layout":{
  "canvas":{"orientation":"landscape","width":1920,"height":1080},
  "background":{"type":"gradient","config":{"gradient":{"type":"linear","angle":90,"colors":["#008C86","#202124"]}}},
  "widgets":[{"widget_uid":"5b0c…","widget_type":"heading","position_x":0.05,"position_y":0.1,"width":0.9,"height":0.16,
              "z_index":1,"configuration":{"text":"Grand Opening","font_size":110,"color":"#FFFFFF"}}]}}
```

## Templates and widgets

| Method | Endpoint | |
|---|---|---|
| GET | `templates/categories.php` | Categories with counts of templates **this user may use**; empty categories are hidden. Admins can pass `all=1` to get every category. |
| GET | `templates/list.php` | `category_id, category, orientation, q, favorites, mine`. Each template has `template_json`, a `media` map (signed URLs of the images it uses), `is_premium`, `is_locked` (premium template not in your plan) and `is_favorite`. **Only templates allowed for the user** are returned (see `admin/template-access.php`); `details.php` and `screens/create.php` return 404 for others. |
| GET | `templates/details.php?template_id=` | |
| POST | `templates/favorite.php` | `{template_id}`: toggles the favourite |
| POST | `templates/save-as-template.php` | `{screen_id, template_name?, category_id?}`: saves a private template (admins can pass `is_public`) |
| GET | `widgets/types.php` | `{types: [{widget_key, default_config_json, availability, is_allowed, …}], recent: [keys]}`. Planned widgets are hidden. |
| GET | `widgets/list.php?screen_id=` | Widgets of a screen |
| GET | `widgets/details.php?widget_id=` | |
| POST | `widgets/add.php` | `{screen_id, widget_type, position_x?, position_y?, width?, height?, configuration?}`. Defaults are merged in; the allowed-widget list is enforced. |
| POST | `widgets/update.php` | `{widget_id, …partial fields, configuration?: partial}`. The configuration is merged into the existing one. |
| POST | `widgets/delete.php` · `widgets/duplicate.php` | `{widget_id}` |
| POST | `widgets/reorder.php` | `{screen_id, widget_ids: [bottom … top]}` |

## Media library

| Method | Endpoint | |
|---|---|---|
| GET | `media/list.php` | `file_type, folder_id (0 = root), q, sort, include_global=1, page`. The response also includes `folders[]` and `storage{used_bytes, limit_mb}`. |
| POST | `media/upload.php` | multipart: `file`, `folder_id?`, `as_logo?`, `duration_ms?`, `global?` (super admin only: share with every user, e.g. template photos). Checks: extension, `finfo` MIME, image integrity, per-plan upload size, storage quota, video permission. Generates a random file name and a GD thumbnail. |
| GET | `media/details.php?media_id=` | Includes `used_in[]` (the screens that use it) |
| POST | `media/rename.php` | `{media_id, original_name}` |
| POST | `media/move.php` | `{media_ids[], folder_id}` |
| POST | `media/delete.php` | `{media_id \| media_ids[], force?}`. **409 IN_USE** lists the screens using it unless `force`. |
| POST | `media/create-folder.php` · `rename-folder.php` · `delete-folder.php` | Nested folders. Deleting a folder keeps its files. |
| GET | `media/usage.php` | Storage use by type |
| GET | `media/file.php?id=&v=file\|thumb&exp=&sig=` | **Signed, expiring** download URL (HMAC-SHA256 with APP_SECRET). Supports `Range` and `ETag`. The API never returns server paths; it returns these URLs. |

## Playlists (carousel of complete screens) and schedules

| Method | Endpoint | |
|---|---|---|
| GET | `playlists/list.php` | Includes `item_count`, `total_duration`, `device_count` and `schedule_count` |
| GET | `playlists/details.php?playlist_id=` | Items, schedules, devices |
| POST | `playlists/create.php` | `{playlist_name, transition_type, transition_duration, loop_enabled, shuffle, default_duration, priority, items:[{screen_id, duration?, transition_type?, is_enabled?, valid_from?, valid_until?}] \| screen_ids:[…], publish?}`. **Checks the playlist limit.** |
| POST | `playlists/update.php` | Same fields plus `playlist_id`. Replaces the items when they are sent. |
| POST | `playlists/add-screen.php` | `{playlist_id, screen_id \| screen_ids[], duration?}` |
| POST | `playlists/remove-screen.php` | `{playlist_id, item_id \| screen_id}` |
| POST | `playlists/reorder.php` | `{playlist_id, item_ids[]}` |
| POST | `playlists/update-item.php` | `{item_id, duration?, transition_type?, is_enabled?, valid_from?, valid_until?}` |
| POST | `playlists/publish.php` | `{playlist_id, publish_screens?}` → `{version, skipped_screens[], device_count}` |
| POST | `playlists/duplicate.php` · `delete.php` | |
| GET | `playlists/preview.php?playlist_id=&draft=1` | The playable document with signed media |
| GET | `schedules/list.php?playlist_id=` | All of your schedules when `playlist_id` is omitted |
| POST | `schedules/create.php` | `{playlist_id, schedule_name?, schedule_type: recurring\|one_time\|special_event, start_date?, end_date?, start_time?, end_time?, days_of_week?: [1..7], timezone?, priority?, status?}` |
| POST | `schedules/update.php` · `delete.php` | `{schedule_id, …}` |
| GET | `schedules/resolve.php?device_id=&at=ISO8601` | `{playlist_id, playlist_name, schedule_id, reason}`. Uses the same algorithm as the player. |

Changes to a published playlist (its items, order or schedules) are **republished automatically**, so displays update without a separate publish step.

## Display codes ("Show Display", no pairing)

Every screen and playlist can have a short code. Open the app on any TV, box or tablet, tap **Show Display** on the sign-in page, enter the code, and the content plays. No account or pairing is needed on the TV. Only **published** content is served, and the display checks for updates every 60 seconds.

| Method | Endpoint | Auth | |
|---|---|---|---|
| GET | `display-codes/get.php?screen_id= \| playlist_id=` | U | The item's code. A random 6-character code is created on first request. → `{code, kind, name, is_published, view_count, last_used_at}` |
| POST | `display-codes/set.php` | U | `{screen_id \| playlist_id, code}`: choose your own code (4–12 letters or numbers, stored upper-case, unique; **409** if already taken) |
| POST | `display-codes/regenerate.php` | U | New random code. The old one stops working immediately. |
| GET | `display/content.php?code=&have=<content_hash>` | P | `{kind, name, content_hash, doc, emergency[], refresh_seconds}`. `doc` is a playable playlist document with signed media (a single screen is wrapped as a one-item playlist). It is `null` when `have` matches. Errors: **404** `CODE_NOT_FOUND`, **409** `NOT_PUBLISHED`, **403** `CODE_INACTIVE`. Rate limited per IP, with extra limits on unknown codes to slow guessing. |

## Devices: management side (U)

| Method | Endpoint | |
|---|---|---|
| POST | `devices/pair.php` | `{pairing_code, device_name?, location_id?, playlist_id?}`. **Checks the device limit.** Rate limited. Codes are single use and expire after 15 minutes. |
| GET | `devices/list.php` | `q, location_id, connection=online\|offline`. Each device has `is_online` (based on the heartbeat), its assignments and what it is playing now. |
| GET | `devices/details.php?device_id=` | Adds telemetry, the last 30 logs, recent commands and 24 h playtime |
| POST | `devices/update.php` | `{device_id, device_name?, location_id?, orientation?, timezone?}` |
| POST | `devices/assign-playlist.php` | `{device_id, playlist_ids[], default_playlist_id?}` |
| POST | `devices/command.php` | `{device_id, command: restart\|pause\|resume\|sync\|clear_cache\|identify}`. Only commands in the device's `capabilities` are accepted. Queued if the device is offline. |
| POST | `devices/unpair.php` | `{device_id, remove?}`: disconnect, or remove from the list |
| GET | `devices/logs.php?device_id=` | Paginated logs |

## Devices: player side (D)

| Method | Endpoint | |
|---|---|---|
| POST | `devices/register.php` | P (rate limited). `{device_uid, device_type, model, manufacturer, os_version, resolution, orientation, app_version, timezone, capabilities}` → `{device_token, device_code, is_paired, heartbeat_interval_seconds, sync_interval_seconds}` |
| POST | `devices/generate-pairing-code.php` | → `{pairing_code: "K7Q4ZP", expires_in: 900}` |
| GET | `devices/pairing-status.php` | `{is_paired, device_name}` |
| POST | `devices/heartbeat.php` | `{sync_token, playback_state, current_playlist_id, current_screen_id, current_content_version, storage_*_mb, app_version}` → `{changed, sync_token, pending_commands, is_paired}` |
| GET | `devices/sync.php?have=12:4,15:1` | `{device, assignments[{playlist_id, version, is_default}], playlists[full signed publication documents, only for versions the player lacks], emergency[], commands[], settings, sync_token}` |
| GET | `devices/playlist.php?playlist_id=` | Fetches one publication again |
| POST | `devices/command-ack.php` | `{command_id, status: completed\|failed, result_message}` |
| POST | `devices/logs.php` | `{logs: [{log_type, message, context, logged_at}]}`, up to 500 per batch (from the offline queue) |
| POST | `devices/report-error.php` | One log entry |
| POST | `devices/playback.php` | `{entries: [{playlist_id, screen_id, playlist_version, screen_version, started_at, duration_ms, completed}]}` (proof of play) |

## Notifications, emergency, locations, reports, settings

| Method | Endpoint | |
|---|---|---|
| GET | `notifications/list.php?unread=1` | |
| POST | `notifications/mark-read.php` | `{notification_id \| notification_ids[] \| all: true}` |
| POST | `notifications/delete.php` | `{notification_id}` |
| GET | `emergency/list.php` | |
| POST | `emergency/create.php` | `{title, body?, background?, color?, flash?, device_ids?: [] (empty = all), duration_minutes?}`. Overrides every schedule on the target displays. |
| POST | `emergency/cancel.php` | `{message_id}` |
| GET | `locations/list.php` · POST `locations/save.php` · POST `locations/delete.php` | Multi-location management |
| GET | `reports/playback.php?from=&to=&device_id=&format=json\|csv` | CSV export guards against formula injection |
| GET | `reports/uptime.php?days=7` | Hours played and errors per display |
| GET | `settings/public.php` | P: app name, branding, client timings |
| GET/POST | `settings/app.php` | A: `{settings: {key: value}}` (existing keys only, typed) |

## Super admin (A)

| Method | Endpoint | |
|---|---|---|
| GET | `admin/dashboard.php` | Users (total, active, inactive), screens (total, published, draft), playlists, connected and offline devices, storage, users per plan, offline list, recent activity |
| GET | `admin/users.php` | `q, status, role, plan_id, page`. Each user has plan, limits, overrides and usage. |
| GET | `admin/user-details.php?user_id=` | Adds permissions, overrides, screens, playlists, devices and activity |
| POST | `admin/create-user.php` | `{full_name, email, phone?, password? (generated if empty), role?, status?, business_name?, timezone?, account_expires_at?, plan_id?, max_screens?, max_playlists?, max_devices?, max_storage_mb?, max_upload_mb?, allowed_widgets?, can_upload_video?, can_use_premium_templates?, permissions?: {key: bool\|null}, send_email?}` → `{user, credentials: {email, password, share_text}}`. The password is shown **once**. |
| POST | `admin/update-user.php` | Details and role, `reset_password: true` (generates a password and revokes sessions), or `new_password`, plus every limit field |
| POST | `admin/update-user-limits.php` | Plan and limits only. `null` = inherit from the plan, `-1` = unlimited. |
| POST | `admin/update-user-status.php` | `{user_id, status: active\|inactive\|suspended}`. Suspending revokes sessions. |
| POST | `admin/delete-user.php` | Soft delete, revokes sessions, disconnects the user's displays |
| GET | `admin/activity-logs.php` | `user_id, action (prefix), from, to, page` |
| GET/POST | `admin/plans.php` | `POST {action: save\|delete, plan_id?, plan_name, max_*, features{video_upload, premium_templates, allowed_widgets, …}, is_default, status}` |
| GET/POST | `admin/templates.php` | GET: every template with its `media` map; `?template_id=` returns one. `POST {action: save, template_id?, category_id, template_name, description?, orientation, template_json (layout document), tags?, is_premium?, status: active\|inactive}`, `{action: toggle\|delete, template_id}`. Widgets with `is_locked: true` cannot be moved, resized or deleted by users. |
| GET/POST | `admin/template-access.php` | GET `?user_id=` → `{mode: all\|selected, category_ids, template_ids}`. POST `{user_id, mode, category_ids[], template_ids[]}`; `selected` needs at least one. The same object can be sent as `template_access` to `create-user.php` / `update-user.php`. |
| GET/POST | `admin/widget-types.php` | `POST {widget_key, availability: stable\|beta\|planned, status, is_premium, default_config_json}` |
| GET | `admin/devices.php` · `admin/media.php` | All users' devices and media. `POST admin/media.php {media_id, action: delete\|make_global\|make_private}` |
| GET | `admin/permissions.php` | Permission catalogue with the default for the user role |

## Cron jobs (CLI only)

```bash
*/5 * * * * php /var/www/signage/backend/cron/mark_offline_devices.php
```

```bash
15 3 * * * php /var/www/signage/backend/cron/expire_subscriptions.php
```

```bash
30 3 * * * php /var/www/signage/backend/cron/cleanup.php
```
