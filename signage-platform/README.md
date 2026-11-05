# Signal Signage: Mobile-First Digital Signage Platform

Signal Signage is a SaaS platform for designing digital signage on a phone and playing it on Android TV.

* **Management app**: Flutter, Material 3, mobile-first. It has a user experience and a super-admin experience.
* **Signage player**: Flutter for Android TV, Android boxes and tablets. It plays offline, follows schedules and syncs automatically.
* **Backend**: procedural PHP 8 with MySQLi and a JSON REST API, on MySQL 8.

> **Status: Phase 1 of 7 is complete.** This covers the architecture, database, seed data and documentation. The roadmap is in `docs/ARCHITECTURE.md §10`.

## Contents

| Path | What |
|---|---|
| `docs/ARCHITECTURE.md` | System architecture, auth model, data flows, schedule priority algorithm, sync and offline design, the **layout JSON contract**, widget system, security, Flutter structure, roadmap |
| `docs/ER_DIAGRAM.md` | Mermaid ER diagram and relationship table |
| `docs/DATABASE.md` | Conventions, limit resolution, cross-user protection, publication JSON, seed overview, full column reference |
| `docs/API_STRUCTURE.md` | Endpoint map for Phase 2, response envelope, status and error codes |
| `backend/database/schema.sql` | 39 tables and 2 views: InnoDB, utf8mb4, 59 foreign keys, CHECK constraints |
| `backend/database/seed.sql` | Plans, roles, permissions, 26 templates, 36 widget types, demo users, screens, playlists, schedules and devices |
| `backend/database/tools/generate_seed.php` | Regenerates `seed.sql` and the sample images |
| `backend/database/tests/verify_database.sh` | 57 automated checks: structure, seed, JSON, limits, pairing, cross-user guards, cascades |
| `backend/uploads/images/samples/` | Sample media referenced by the seed |

## Install the database (local)

You need MySQL 8.0.16+ or MariaDB 10.6+.

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

4. Optional: run the verification suite. It uses its own throw-away database.

   ```bash
   MYSQL="mysql -u root -p<password>" backend/database/tests/verify_database.sh
   ```

On shared hosting (phpMyAdmin), import `schema.sql` first and then `seed.sql`.

### Development credentials

These are for local use only. Delete or change them before going to production.

| Role | Email | Password |
|---|---|---|
| Super admin | admin@signage.local | Admin@12345 |
| User A (2 screens max, at limit) | aisha@demo.local | Demo@12345 |
| User B (5 screens, full demo data) | bilal@demo.local | Demo@12345 |
| User C (10 screens) | chloe@demo.local | Demo@12345 |
| User D (unlimited) | daniel@demo.local | Demo@12345 |

The unpaired demo TV accepts pairing code **K7Q4ZP**.

## Regenerating the seed

```bash
php backend/database/tools/generate_seed.php
```

Run it after changing templates or widget defaults. It needs the PHP GD extension to generate the sample images.

## Design system

| Name | Hex |
|---|---|
| Teal | `#008C86` |
| Dark Teal | `#006B66` |
| Light Teal | `#E5F5F3` |
| Charcoal | `#202124` |
| Dark Background | `#17191C` |
| White | `#FFFFFF` |
| Light Grey | `#F5F6F7` |
| Medium Grey | `#858B93` |
| Border Grey | `#E6E8EB` |

Both light and dark themes are supported.

## Next: Phase 2, the PHP backend

Phase 2 will add:

* `config/`, `includes/` and every endpoint in `docs/API_STRUCTURE.md`
* the upload pipeline and rate limiting
* the publish and sync builders, which produce exactly the publication JSON already seeded here
* an API test script and a Postman collection
