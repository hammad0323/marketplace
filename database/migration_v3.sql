-- ============================================================================
-- MediConnect — incremental migration (v3)
-- Run this ONCE against your EXISTING live database. It does NOT touch any
-- existing data — it only adds one new column and one new table.
--
-- Do NOT re-import schema.sql or seed.sql over a live database — those files
-- DROP and recreate every table from scratch and would wipe your data. This
-- file is the safe alternative: apply it on top of what you already have.
--
-- Usage:
--   mysql -u youruser -p your_database_name < database/migration_v3.sql
--
-- What this adds:
--   1. chat_conversations.is_blocked — lets a doctor block a patient from
--      sending further messages in a conversation (Messaging feature).
--   2. medicine_info — a new table for doctor/admin-authored medicine
--      reference content (dosage, uses, side effects, SEO fields). This is
--      a brand new table, so CREATE TABLE IF NOT EXISTS is used instead of
--      the DROP-and-recreate pattern schema.sql uses for fresh installs —
--      running this twice is safe and won't erase anything.
--
-- If you are setting up a FRESH database instead (no existing data), just
-- import database/schema.sql + database/seed.sql as normal — both already
-- include everything in this file, so you do not need to also run this.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. Doctor-side per-conversation block flag.
ALTER TABLE chat_conversations
    ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'doctor blocked this patient from sending further messages'
    AFTER doctor_id;

-- 2. Informational medicine reference content (not for sale — see
--    doctor_products for the storefront). Authored by a doctor or admin,
--    purely to give the site something worth ranking for when someone
--    searches a medicine name.
CREATE TABLE IF NOT EXISTS medicine_info (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id        INT UNSIGNED NOT NULL COMMENT 'users.id — doctor or admin',
    name             VARCHAR(180) NOT NULL,
    slug             VARCHAR(200) NOT NULL,
    generic_name     VARCHAR(180) DEFAULT NULL,
    composition      VARCHAR(255) DEFAULT NULL,
    category         VARCHAR(120) DEFAULT NULL,
    uses             TEXT,
    dosage           TEXT,
    side_effects     TEXT,
    precautions      TEXT,
    content          LONGTEXT COMMENT 'full rich-text description',
    featured_image   VARCHAR(255) DEFAULT NULL,
    focus_keyword    VARCHAR(150) DEFAULT NULL COMMENT 'the search term this entry targets, e.g. the medicine name',
    meta_title       VARCHAR(200) DEFAULT NULL COMMENT 'blank = auto-generated from name',
    meta_description VARCHAR(300) DEFAULT NULL COMMENT 'blank = auto-generated from uses/content',
    seo_score        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100, computed by seo_score_for_medicine()',
    status           ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicine_info_slug (slug),
    FULLTEXT KEY ft_medicine_info_search (name, generic_name, uses, content),
    CONSTRAINT fk_medicine_info_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Note: no seed data is inserted here on purpose — this migration only
-- changes structure, never content, on a database that already has real data.
