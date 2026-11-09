# Test Report

Every result below comes from a run in the development container. Nothing here is estimated.

| Suite | Command | Result |
|---|---|---|
| Database: structure, seed, JSON, limits, pairing, cross-user guards, cascades | `backend/database/tests/verify_database.sh` | **59 / 59 passed** (MariaDB 10.11) |
| API end to end: every workflow, security, uploads, sync | `php backend/tests/api_test.php http://127.0.0.1:8080` | **290 / 290 passed**, nothing written to `error.log` |
| Flutter tests: schedule engine, layout model, feed parser, JSON path, playback items, screen-card layout at 144/173/184/230 px and 130% font size | `flutter test` | **24 / 24 passed** |
| Flutter static analysis | `flutter analyze` | **No issues found** |
| Management app build (web) | `flutter build web` | Built |
| Player build (web) and live run against the API | `flutter build web -t lib/main_player.dart` + Playwright | Registered → pairing code shown → paired → content downloaded → playlist rotating → remote command executed → emergency override shown → logs uploaded |

| Offline display (integration test on the Dart VM against the live API) | `SIGNAGE_API=http://127.0.0.1:8080 flutter test test/integration/offline_display_test.dart` | **Passed.** Content and media downloaded with checksums verified, offline mode switched on, then a "reboot" with the server unreachable plays from the device with no network request. Turning offline mode on with no internet and no saved content gives a clear error. |
| PHP web app in Chromium (Playwright) | `node web1.js / web2.js / web3.js` (scripts not shipped) | Restricted user sees only Restaurant & Menu templates (premium one locked, screen limit enforced) → create from a menu template → edit text, font, text shadow, drag, undo/redo, add widget → save & publish (live playlist republished) → layers/background panels → preview → display code with QR → playlist create/publish → browser TV player plays the code → media, displays, dashboard, profile. Admin: dashboard, templates gallery, new template (gradient, shape with shadow, heading, menu, lock design, save), edit an existing template, user form with template access, plans, settings, shared media. Phone (Pixel 7) layout: no horizontal overflow, editor drawers. **No console errors.** |
| Show Display flow in a browser at TV size | Playwright | Login → Show Display → `STORE1` → playing. A restart reopens display mode. A wrong code shows a clear error. The remote menu works. |

## What the API suite covers (290 checks)

* **Display codes**: public content for a playlist code and a screen code (wrapped as one item), unchanged content returns no document, unknown code returns 404, code auto-created for the owner, another user cannot read it, choose-your-own code normalised (`grand-open` → `GRANDOPEN`), duplicate and too-short codes rejected, the new code plays, regenerating kills the old code, playlist codes, deleted screen.

* **Template access**: a restricted user lists, opens and creates only allowed templates (others 404), category counts follow the restriction, the admin sets access per category and per template, empty selection rejected, own templates always allowed, professional menu templates seeded with shared artwork, admin-only global uploads.
* **Web app**: signed-out redirect, internal files blocked, public TV player page, CSRF on login, cookie login → API session, every user page loads, customers cannot open the admin area or the template designer, logout revokes the token, `categories?all=1` is admin-only.

* **Auth**: wrong password, validation, suspended account, missing or garbage token, method not allowed, blocked internal paths (`/config`, `_common.php`, raw `/uploads`), logout revocation, sessions, password change, and the forgot/reset flow (code by email, weak password rejected, single-use code).
* **Workflow A (create a screen)**: premium template blocked, screen limits enforced for User A (2) and User B (5), admin raises a limit, create from template / blank portrait / custom 3840×1080, save-layout with version check (409 conflict), unknown, planned and foreign-media widgets rejected, publish, duplicate (also limit-checked), delete, search, rename, favourite.
* **Single-widget API**: add with merged defaults, partial config update, duplicate, list, reorder layers, delete.
* **Cross-user access (IDOR)**: reading, editing or deleting another user's screen, reading their playlist, commanding their device, editing their widget, adding their screen to your playlist, a user calling the admin API, "all" lists scoped for non-admins, and SQL injection attempts in search and sort.
* **Media**: PNG upload with dimensions and thumbnail, server paths hidden, signed URL serves bytes, HTTP Range returns 206, tampered signature returns 403, PHP code disguised as JPG rejected (415), video blocked by a per-user permission override, folders, move, rename, in-use delete protection (409), and another user's media rejected.
* **Workflow B (playlist)**: create with items, playlist limit, reorder, a draft screen skipped with a warning, `publish_screens` includes everything, preview with signed media, live playlist republished automatically when an item changes.
* **Scheduling**: create, invalid time, end date before start date, update, list, and resolution at three times of day in the device's time zone.
* **Workflow C (connect a TV)**: register, pairing code, status, wrong code, pairing with a lower-case code containing spaces, code reuse rejected, device limit, cross-user playlist assignment rejected, default playlist, heartbeat `changed`, full sync with layouts, signed media and schedules, checksum verified on download, incremental sync sends nothing new, republish triggers `changed`, only the changed playlist is re-sent.
* **Remote control and monitoring**: unsupported command rejected, command delivered exactly once and acknowledged, batched logs, error report, proof of play (another user's IDs silently dropped), device details with logs, emergency create → delivered → cancel, CSV report, uptime report, rename, unpair (sync returns nothing), and a new pairing code afterwards.
* **Workflow D (admin creates a user)**: dashboard, create user with a generated password and share text, duplicate email, `must_change_password`, limit of 1 enforced then raised to unlimited, user details, search, suspend (session revoked) and reactivate, admin password reset, admin cannot suspend themselves, plans (create, and a plan in use cannot be deleted), widget availability, templates, devices, media, activity, settings, delete user (login then blocked).
* **Workflow E (background)**: image background with blur and overlay, video background given an image rejected, global background usable, publish.
* **Rate limiting**: login locked (429) after 10 failures.

## Not run in this environment

* **Android and iOS native builds.** The container's network policy blocks the Android SDK download host (`dl.google.com`), and iOS needs macOS and Xcode. The Gradle flavors, manifests and boot receiver are written but have not been compiled here. Build them as described in `DEPLOYMENT.md` and test on an Android TV before rollout.
* **MySQL 8.** The schema is written for MySQL 8.0.16+ and was verified on MariaDB 10.11. Run `verify_database.sh` once against your MySQL server.
* **Real devices.** Small and large phones, tablets and Android TV boxes were not tested. The web build was screenshot at 412×915 (phone) and 960×540 (TV).
* **Video playback.** The player uses `video_player` with locally cached MP4 files. No real H.264 file was played in the container; the API tests use a stub MP4 header.
