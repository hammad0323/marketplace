-- ============================================================================
-- DoctorApna — incremental migration (v6)
-- Run this ONCE against your EXISTING live database. It does NOT touch any
-- existing data — it only adds a new table and widens four text columns
-- (never shrinks or converts anything, so nothing currently stored can be
-- rejected or truncated).
--
-- Do NOT re-import schema.sql or seed.sql over a live database — those files
-- DROP and recreate every table from scratch and would wipe your data. This
-- file is the safe alternative: apply it on top of what you already have.
--
-- Usage:
--   mysql -u youruser -p your_database_name < database/migration_v6.sql
--
-- What this adds:
--   1. medicine_info.uses/dosage/side_effects/precautions widen from TEXT
--      (64KB) to MEDIUMTEXT (16MB) — these fields now hold rich HTML from
--      the admin/doctor editor (bullet lists, bold, etc.) instead of plain
--      text, and MEDIUMTEXT gives them the same generous headroom the
--      existing `content` column already has.
--   2. medicine_faqs — a new table so each medicine can have its own
--      admin/doctor-authored FAQ list, shown as an accordion on its public
--      page and fed into FAQPage structured data.
--
-- If you are setting up a FRESH database instead (no existing data), just
-- import database/schema.sql + database/seed.sql as normal — both already
-- include everything in this file, so you do not need to also run this.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. Widen columns. TEXT -> MEDIUMTEXT never rejects or truncates existing
--    data (it's a pure increase in the maximum stored size).
ALTER TABLE medicine_info MODIFY COLUMN uses MEDIUMTEXT COMMENT 'rich HTML from the admin/doctor editor';
ALTER TABLE medicine_info MODIFY COLUMN dosage MEDIUMTEXT COMMENT 'rich HTML from the admin/doctor editor';
ALTER TABLE medicine_info MODIFY COLUMN side_effects MEDIUMTEXT COMMENT 'rich HTML from the admin/doctor editor';
ALTER TABLE medicine_info MODIFY COLUMN precautions MEDIUMTEXT COMMENT 'rich HTML from the admin/doctor editor';

-- 2. Per-medicine FAQs — brand new table, so CREATE TABLE IF NOT EXISTS is
--    used instead of the DROP-and-recreate pattern schema.sql uses for
--    fresh installs. Running this twice is safe.
CREATE TABLE IF NOT EXISTS medicine_faqs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    question    VARCHAR(255) NOT NULL,
    answer      TEXT NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    KEY idx_medicine_faqs_medicine (medicine_id),
    CONSTRAINT fk_medicine_faqs_medicine FOREIGN KEY (medicine_id) REFERENCES medicine_info(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Note: no seed data is inserted here on purpose — this migration only
-- changes structure, never content, on a database that already has real data.
