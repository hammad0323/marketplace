# The Hashir — existing website & database audit

Source: SQL export `u925768674_thehashir` (phpMyAdmin, 26 Nov 2026, MariaDB 11.8, PHP 7.2), the uploaded beta build, and the reference homepage screenshot.
The live site (thehashir.com) could not be fetched from the build environment (outbound access blocked), so the URL patterns below come from the beta build, which was written to match the live URLs.

## 1. Table inventory (45 tables)

| Group | Tables (rows) |
|---|---|
| Directory | `th_venue` (2,700), `organization_roles` (9), `th_key_bodies` (41), `th_people` (0), `venue_people` (0), `th_venue_old` (0, legacy copy) |
| Programs / services / events | `th_details` (1,731), `th_categories` (23), `th_grand_category` (3), `th_gender` (3), `th_age_group` (5) |
| Prayer | `prayer_timings` (2,657: 11 mosques, Feb–Dec 2026), `th_jummah_prayers` (3,144: 1,502 mosques), `th_daily_prayers` (0, unused), `th_hijri_adjustment` (1) |
| Content | `blogs` (29 city guides), `th_announcement` (210), `gallery_images` (0), `th_carousel_images` (0) |
| Locations | `th_country` (2), `th_state` (62), `th_city` (1,213) |
| Accounts | `th_admin_table` (1), `th_venue.username/password` (2,700 venue logins), `ev_users` (0), `hashir_auth_tokens` (0), `device_tokens` (0), `notification_preferences` (1) |
| App / followers | `mosque_followers` (14), `mosque_follow_notifications` (14) |
| Display screens | `th_display_*` (12 tables: screens, grids, templates, widgets, media, settings, auth tokens, `th_display_audit_logs` (461)), `screens`, `screen_boxes` |
| Analytics | `th_page_views` (859) |

No static-page table exists. The legal pages are not in the database.

## 2. Relationships (by id; the schema declares primary keys and indexes, no foreign-key constraints)

- `th_venue.role_id` → `organization_roles.role_id`
- `th_venue.city_id` → `th_city.city_id` → `th_state.state_id` → `th_country.country_id`
- `th_details.venue_id` → `th_venue.venue_id`; `th_details.event_city` → `th_city`
- `th_details.grand_category_id` → `th_grand_category.gr_ct_id`
- `th_details.category_id` → `th_categories.category_id`: **comma-separated list** (e.g. `"18,21"`); each category belongs to one grand category
- `th_details.gender_id` / `age_group_id` → comma-separated ids of `th_gender` / `th_age_group`
- `prayer_timings.venue_id`, `th_jummah_prayers.venue_id`, `th_announcement.venue_id`, `th_people.venue_id`, `venue_people.venue_id` → `th_venue`
- `th_key_bodies.venue_id` (mosque) ↔ `th_key_bodies.organization_id` (organization): both `th_venue` ids
- `blogs.city_id` → `th_city`; `blogs.featured_mosque_id` (text, mostly empty)
- `mosque_followers.venue_id` (varchar) → `th_venue`; `user_id` = device id or user id
- `th_display_audit_logs.admin_id` → `th_admin_table`, `.venue_id` → `th_venue`

## 3. Mosque vs organization

`th_venue.role_id = 1` → **mosque** (2,619: 2,606 verified, 13 unverified). `role_id` 2–9 → **organization**: Key bodies & Associations (2), Islamic Schools (3), Charities (4), Outreach (5), Halal Certification (6), Food Banks (7), Student Associations (8), Islamic Education Institution (9). 81 organizations.
`verify_status = 1` = published. Mosque profiles use `/mosques-details/…`, organizations `/organization-details/…`; prayer and Jumu'ah management only applies to mosques.

## 4. Programs, services, events

`th_details.grand_category_id`: **1 = Event, 2 = Program, 3 = Service** (`th_grand_category`). Categories: Events 19–23 (Eid Bazaar, Ramadan Events, Eid ul Adha, Community Events); Programs 1–9, 17 (Islamic Education, Hifdh, Nadirah, Tajweed, Family Halaqah, Youth, Seniors, Language, Sports, Sisters); Services 10–16, 18, 21 (Nikah, Matrimonial, Event spaces, Funeral, Counseling, Food Banks, Seniors, Outreach, Other).
`status_verify = 1` = published (135 programs, 1,593 services published; 3 services pending; no events in the export). Events are hidden after `expire_date` / `end_date`. `0000-00-00` means "no date".

## 5. Prayer & Jumu'ah data

- `prayer_timings`: one row per venue per date; times stored as text `"6:07 AM"`; columns for Fajr/Dhuhr/Asr/Maghrib/Isha Azan + Iqama, sunrise, sunset, `islamic_date`, `day`. Some dates have duplicate rows (newest id is used). `00:00` is a placeholder for "not provided".
- `th_jummah_prayers`: sessions valid `from_date`–`to_date`; `azan_time` = khutbah, `iqama_time` = salah (often `00:00` khutbah), `place`, `imam_name`, `type` 0 = daylight-saving season, 1 = standard-time season.
- `th_daily_prayers` is empty and unused.

## 6. Blogs & static pages

`blogs` holds 29 **city guides** with a fixed structure: heading, url slug, author, quick answer (HTML), population, number of mosques, Google Maps iframe, summary (HTML), profile image (empty), city. Mosque lists, comparison tables and FAQs are generated live from the city's records. There are no static-page tables.

## 7. URL patterns (preserved)

`/mosques`, `/mosques-details/{slug}/{id}`, `/organization-details/{slug}/{id}`, `/organizations-catalog`, `/organizations/{type}/{id}`, `/program-catalog`, `/services-catalog`, `/events-catalog`, `/programs|services|events/{category}/{id}`, `/details/{slug}/{id}`, `/details-list/{venue}/{type}/{id}`, `/prayer-times/{country}/{city}/{id}`, `/blogs`, `/blog/{slug}/{id}`, `/about`, `/support`, `/solution-mosque`, `/sign-in`, `/editor`.

## 8. Gaps found

- No admin panel or portal in the new build; the old system stores **plain-text passwords** (2,700 venues, 1 admin; 7 MD5, 1 bcrypt).
- **No coordinates columns**; only 6 mosques have parseable coordinates in `address_iframe`, so maps and "near me" can't cover the directory yet.
- No blog editor beyond the fixed city-guide fields; no static pages in the database; no settings table for the website.
- Prayer data is sparse (11 mosques with daily times); 1,502 have Jumu'ah, many schedules expire in March 2027.
- No events published at export time.
- No indexes on `prayer_timings(venue_id, georgian_date)` and similar hot paths (optional SQL in README §8).

## 9. Decisions (database kept exactly as is)

| Need | Solution without schema changes |
|---|---|
| Activity log | Existing `th_display_audit_logs` (`actor_type` admin/venue) |
| Password hashing | bcrypt hash fits `admin_password varchar(100)` and `password varchar(200)`; migrated on login |
| Portal password reset | Existing `th_venue.reset_token` (stores a SHA-256 of the token) + `token_expiry` |
| Approval workflow | `th_details.status_verify` and `th_venue.verify_status`; pending profile edits in `storage/pending/` |
| Coordinates | Read from / written to `address_iframe` as a Google Maps embed |
| Follow button | Existing `mosque_followers` (`user_type = 'device'`, anonymous browser id) |
| Blog builder, SEO, scheduling | Layout JSON in `storage/blogs/{id}.json`; drafts get their `blogs` row only when published |
| Static pages, site settings, admin roles, calc settings, prayer-time sources, subscribers, login throttling | JSON in `storage/` |

**Migration SQL required: none.** Optional performance indexes are listed in README §8.
