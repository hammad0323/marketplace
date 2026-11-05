# API Structure

This is the plan for the Phase 2 REST API. Phase 2 implements every endpoint below and adds a request and response example for each.

**Base URL**: `https://<host>/api/`. On Apache, `.htaccess` maps clean paths such as `/api/screens/list` to `list.php`, so both forms work.

## Conventions

| Item | Rule |
|---|---|
| Auth header | `Authorization: Bearer <token>`. User tokens come from `auth/login`; device tokens from `devices/register`. |
| Request body | `application/json` (uploads use `multipart/form-data`) |
| Response envelope | `{"status": bool, "message": string, "data": object\|array\|null, "code"?: string, "errors"?: {field: msg}, "meta"?: {page, per_page, total, total_pages}}` |
| Pagination | `?page=1&per_page=20` (max 100). `meta` is returned on list endpoints. |
| Search and sort | `?q=`, `?status=`, `?sort=updated_at&order=desc`. Sort fields come from an allow-list. |
| Status codes | 200 OK · 201 Created · 400 Validation · 401 Unauthenticated · 403 Forbidden or limit reached · 404 Not found (also returned for other users' resources) · 409 Conflict (stale `layout_version`, duplicate) · 413 File too large · 415 Bad media type · 422 Business rule · 429 Rate limited · 500 Server error (generic message) |
| Error codes | `VALIDATION`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `LIMIT_SCREENS`, `LIMIT_PLAYLISTS`, `LIMIT_DEVICES`, `LIMIT_STORAGE`, `WIDGET_NOT_ALLOWED`, `PREMIUM_REQUIRED`, `VIDEO_NOT_ALLOWED`, `CONFLICT`, `RATE_LIMITED`, `ACCOUNT_SUSPENDED`, `ACCOUNT_EXPIRED`, `PAIRING_INVALID` |
| IDs | The server derives `user_id` from the token. It never reads `user_id` from the request, except for admin endpoints acting on a target user. |

Example:
```json
{ "status": true, "message": "Screen created successfully",
  "data": { "screen_id": 101, "screen_uid": "4f0c…", "screen_name": "Welcome Screen" } }
```

## Endpoints

Legend for the Auth column:

* **P**: public
* **U**: user token
* **A**: super admin token
* **D**: device token

### auth
| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | auth/login.php | P | `{email,password,device_label}` → `{token, expires_at, user, limits, permissions}` |
| POST | auth/logout.php | U | Revoke the current token |
| POST | auth/forgot-password.php | P | Email a reset token. Always returns 200 so account existence isn't revealed. |
| POST | auth/reset-password.php | P | `{token,new_password}`. Revokes all sessions. |
| GET | auth/profile.php | U | Profile, effective limits, usage, permissions |
| POST | auth/update-profile.php | U | Name, phone, timezone, profile image |
| POST | auth/change-password.php | U | `{current_password,new_password}` |
| GET | auth/sessions.php | U | List the user's own active tokens |

### admin (all A)
| Method | Path | Purpose |
|---|---|---|
| GET | admin/dashboard.php | User, screen, playlist and device counts; storage; recent activity |
| GET | admin/users.php | Search, filter (status, plan), paginate |
| GET | admin/user-details.php?user_id= | Profile, limits, usage, screens, devices, activity |
| POST | admin/create-user.php | Details, plan, limits, permissions. Can generate a password and return it once or email it. |
| POST | admin/update-user.php | Edit details or reset the password |
| POST | admin/delete-user.php | Soft-delete and revoke the user's tokens |
| POST | admin/update-user-status.php | active / inactive / suspended |
| POST | admin/update-user-limits.php | `user_limits` overrides and `user_permissions` |
| GET | admin/activity-logs.php | Filter by user, action, date |
| GET/POST | admin/plans.php · admin/save-plan.php · admin/delete-plan.php | Plan CRUD |
| GET/POST | admin/templates.php · admin/save-template.php · admin/delete-template.php | Template CRUD |
| GET/POST | admin/widget-types.php · admin/save-widget-type.php | Widget registry, including `availability` |
| GET | admin/screens.php · admin/playlists.php · admin/devices.php · admin/media.php | Views across all users |
| GET/POST | settings/app.php · settings/save-app.php | `app_settings` |

### screens (U)
`list` (search, folder, status) · `create` (`template_id` or a blank orientation/size; **checks limits**) · `details` (with widgets) · `update` (name, folder, background, duration) · `delete` (soft) · `duplicate` (**checks limits**) · `save-layout` (full layout document plus `layout_version`) · `publish` · `templates` (alias for templates/list) · `folders`, `save-folder`, `delete-folder` · `preview` (draft rendered as a publication document)

### templates (U)
`categories` · `list` (category, orientation, premium; flags locked premium templates) · `details` · `favorite` (toggle) · `save-as-template` (private)

### widgets (U)
`types` (registry filtered by plan) · `list?screen_id=` · `details` · `add` (**checks allowed widgets**) · `update` · `delete` · `reorder` (z-index) · `duplicate`

### media (U)
`list` (type, folder, search) · `upload` (multipart; **checks MIME, size and storage**; creates a thumbnail) · `details` · `rename` · `move` · `delete` (soft; 409 if in use unless `force`, which reports where it is used) · `create-folder` · `rename-folder` · `delete-folder` · `usage` (storage used vs limit) · `file?media_id=&sig=` (signed streaming for players and apps)

### playlists (U)
`list` · `create` (**checks limits**) · `details` · `update` · `delete` · `duplicate` · `add-screen` · `remove-screen` · `reorder` · `update-item` (duration, transition, validity) · `publish` · `preview` (resolved document)

### schedules (U)
`list?playlist_id=` · `create` · `update` · `delete` · `resolve?device_id=&at=` (which playlist would play at a given time; uses the same algorithm as the player)

### devices
| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | devices/register.php | P (rate-limited) | Player first launch → device token (returned once) and device_code |
| POST | devices/generate-pairing-code.php | D | New 6-character code with a 15-minute time-to-live |
| GET | devices/pairing-status.php | D | Player polls until it is paired |
| POST | devices/pair.php | U | `{pairing_code, device_name, location_id}`; **checks the device limit** |
| GET | devices/list.php | U | Status is computed from `last_heartbeat` |
| GET | devices/details.php | U | Telemetry, assignments, recent logs |
| POST | devices/update.php | U | Rename, location, orientation |
| POST | devices/assign-playlist.php | U | Set or replace assignments and the default |
| POST | devices/command.php | U | restart / pause / resume / sync / clear_cache / identify (only if the device supports it) |
| POST | devices/unpair.php | U | Transaction described in DATABASE.md §4 |
| POST | devices/heartbeat.php | D | Telemetry in → `{changed, pending_commands}` |
| GET | devices/sync.php | D | Assignments plus versions, emergency messages, pending commands |
| GET | devices/playlist.php?playlist_id=&version= | D | A publication document with signed media URLs |
| POST | devices/command-ack.php | D | Command result |
| POST | devices/report-error.php · devices/logs.php · devices/playback.php | D | Batched uploads of the offline queue |
| GET | devices/logs.php | U | Device logs for the owner |

### notifications · emergency · locations · reports (U)
`notifications/list`, `mark-read`, `mark-all-read` · `emergency/list`, `create`, `cancel` · `locations/list`, `save`, `delete` · `reports/playback` (CSV export), `reports/uptime`

## Backend folder layout (Phase 2)

```
backend/
├── config/      config.php (env-driven) · database.php (mysqli connect, utf8mb4, UTC) · constants.php
├── includes/    functions.php · response.php · validation.php · auth.php · permissions.php
│                upload.php · rate_limit.php · schedule.php · publish.php · mailer.php
├── api/         auth/ admin/ users/ screens/ templates/ widgets/ media/ playlists/ schedules/
│                devices/ notifications/ emergency/ locations/ reports/ settings/
├── uploads/     images/ videos/ thumbnails/ documents/  (+ .htaccess: no script execution)
├── cron/        purge_deleted.php · mark_offline_devices.php · expire_subscriptions.php
├── database/    schema.sql · seed.sql · tools/ · tests/
└── .htaccess    clean URLs, CORS preflight, security headers
```
