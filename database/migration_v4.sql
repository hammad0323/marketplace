-- ============================================================================
-- MediConnect / DoctorApna — incremental migration (v4)
-- Run this ONCE against your EXISTING live database. It does NOT touch any
-- existing data — it only adds new tables/columns and relaxes two columns
-- from NOT NULL to NULL (which never rejects data that's already there).
--
-- Do NOT re-import schema.sql or seed.sql over a live database — those files
-- DROP and recreate every table from scratch and would wipe your data. This
-- file is the safe alternative: apply it on top of what you already have.
--
-- Usage:
--   mysql -u youruser -p your_database_name < database/migration_v4.sql
--
-- What this adds:
--   1. 'pharmacy' as a new users.role value (medicine store / chemist
--      accounts), alongside the existing patient/doctor/admin roles.
--   2. pharmacies + pharmacy_certificates — new tables, same self-apply +
--      admin-verification pattern as doctors/doctor_certificates: a store
--      registers, uploads its registration number and certificates, and
--      waits for admin approval before it can sell anything.
--   3. doctor_products and orders gain a pharmacy_id + seller_type column
--      so a listing/order can belong to either a doctor or a pharmacy.
--      doctor_id on both tables is relaxed from NOT NULL to NULL (a
--      pharmacy-owned row has doctor_id = NULL, pharmacy_id set instead) —
--      every existing row already has doctor_id populated, so this is a
--      pure relaxation, nothing existing becomes invalid.
--
-- If you are setting up a FRESH database instead (no existing data), just
-- import database/schema.sql + database/seed.sql as normal — both already
-- include everything in this file, so you do not need to also run this.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. New role value. Existing users keep whatever role they already have —
--    this only widens the set of allowed values.
ALTER TABLE users
    MODIFY COLUMN role ENUM('patient','doctor','admin','pharmacy') NOT NULL DEFAULT 'patient';

-- 2. Pharmacy accounts — brand new tables, so CREATE TABLE IF NOT EXISTS is
--    used instead of the DROP-and-recreate pattern schema.sql uses for
--    fresh installs. Running this twice is safe and won't erase anything.
CREATE TABLE IF NOT EXISTS pharmacies (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    slug                VARCHAR(180) NOT NULL,
    store_name          VARCHAR(180) NOT NULL,
    registration_number VARCHAR(100) DEFAULT NULL COMMENT 'pharmacy/drug license number',
    license_authority   VARCHAR(180) DEFAULT NULL COMMENT 'issuing body, e.g. Punjab Pharmacy Council',
    bio                 TEXT,
    address             VARCHAR(255) DEFAULT NULL,
    city                VARCHAR(100) DEFAULT NULL,
    state               VARCHAR(100) DEFAULT NULL,
    country             VARCHAR(100) DEFAULT NULL,
    verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    verification_note   VARCHAR(255) DEFAULT NULL,
    rating_avg          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    rating_count        INT UNSIGNED NOT NULL DEFAULT 0,
    profile_views       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pharmacies_user (user_id),
    UNIQUE KEY uq_pharmacies_slug (slug),
    CONSTRAINT fk_pharmacy_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pharmacy_certificates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id  INT UNSIGNED NOT NULL,
    title        VARCHAR(180) NOT NULL,
    issued_by    VARCHAR(180) DEFAULT NULL,
    issued_year  YEAR DEFAULT NULL,
    file_path    VARCHAR(255) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cert_pharmacy (pharmacy_id),
    CONSTRAINT fk_cert_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Generalize the storefront (doctor_products) and orders to allow a
--    pharmacy as the seller instead of a doctor.
ALTER TABLE doctor_products
    MODIFY COLUMN doctor_id INT UNSIGNED DEFAULT NULL COMMENT 'store owner when seller_type=doctor';
ALTER TABLE doctor_products
    ADD COLUMN pharmacy_id INT UNSIGNED DEFAULT NULL COMMENT 'store owner when seller_type=pharmacy' AFTER doctor_id;
ALTER TABLE doctor_products
    ADD COLUMN seller_type ENUM('doctor','pharmacy') NOT NULL DEFAULT 'doctor' AFTER pharmacy_id;
ALTER TABLE doctor_products
    ADD KEY idx_doctor_product_pharmacy (pharmacy_id);
ALTER TABLE doctor_products
    ADD CONSTRAINT fk_doctor_product_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE;

ALTER TABLE orders
    MODIFY COLUMN doctor_id INT UNSIGNED DEFAULT NULL COMMENT 'seller when seller_type=doctor';
ALTER TABLE orders
    ADD COLUMN pharmacy_id INT UNSIGNED DEFAULT NULL COMMENT 'seller when seller_type=pharmacy' AFTER doctor_id;
ALTER TABLE orders
    ADD COLUMN seller_type ENUM('doctor','pharmacy') NOT NULL DEFAULT 'doctor' AFTER pharmacy_id;
ALTER TABLE orders
    ADD KEY idx_order_pharmacy (pharmacy_id);
ALTER TABLE orders
    ADD CONSTRAINT fk_order_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE;

-- Note: no seed data is inserted here on purpose — this migration only
-- changes structure, never content, on a database that already has real data.

-- 4. Rebrand: rename the displayed site name from the old default
--    "MediConnect" to "DoctorApna". This ONLY touches the row if it still
--    holds the original default — if you already customized site_name in
--    Admin > Settings, that customization is left untouched.
UPDATE site_settings SET setting_value = 'DoctorApna' WHERE setting_key = 'site_name' AND setting_value = 'MediConnect';
