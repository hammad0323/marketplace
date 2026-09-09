-- ============================================================================
-- DoctorApna — incremental migration (v5)
-- Run this ONCE against your EXISTING live database. It does NOT touch any
-- existing data — it only adds a new table and two new nullable columns.
--
-- Do NOT re-import schema.sql or seed.sql over a live database — those files
-- DROP and recreate every table from scratch and would wipe your data. This
-- file is the safe alternative: apply it on top of what you already have.
--
-- Usage:
--   mysql -u youruser -p your_database_name < database/migration_v5.sql
--
-- What this adds:
--   1. patient_medical_history — a new table so a doctor can log multiple,
--      timestamped clinical notes (title/description/status/visit date) per
--      patient, separate from appointments.
--   2. doctors.meta_title / doctors.meta_description — optional SEO override
--      columns for a doctor's public profile page (blank = auto-generated,
--      same pattern already used by doctor_products/blog_posts/medicine_info).
--
-- If you are setting up a FRESH database instead (no existing data), just
-- import database/schema.sql + database/seed.sql as normal — both already
-- include everything in this file, so you do not need to also run this.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. Doctor-authored patient history — brand new table, so CREATE TABLE IF
--    NOT EXISTS is used instead of the DROP-and-recreate pattern schema.sql
--    uses for fresh installs. Running this twice is safe.
CREATE TABLE IF NOT EXISTS patient_medical_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED NOT NULL,
    patient_id  INT UNSIGNED NOT NULL,
    title       VARCHAR(180) NOT NULL,
    description TEXT,
    status      ENUM('ongoing','improving','stable','recovered','critical') NOT NULL DEFAULT 'ongoing',
    visit_date  DATE DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_history_doctor_patient (doctor_id, patient_id),
    CONSTRAINT fk_history_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Doctor profile SEO override fields — pure addition, NULL by default so
--    every existing doctor keeps auto-generated titles/descriptions exactly
--    as before until they (or an admin) explicitly set one.
ALTER TABLE doctors
    ADD COLUMN meta_title VARCHAR(200) DEFAULT NULL COMMENT 'blank = auto-generated from name/specialization' AFTER chat_end_time;
ALTER TABLE doctors
    ADD COLUMN meta_description VARCHAR(300) DEFAULT NULL COMMENT 'blank = auto-generated from bio' AFTER meta_title;

-- Note: no seed data is inserted here on purpose — this migration only
-- changes structure, never content, on a database that already has real data.
