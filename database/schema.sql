-- ============================================================================
-- MediConnect — Doctor Association & Telemedicine Platform
-- Full database schema (MySQL 5.7+/8.0, InnoDB, utf8mb4)
--
-- Import this single file to create the database, all tables, and seed
-- data. Tables are grouped by module. Tables marked "PHASE 2+" define the
-- storage shape for features not yet wired to UI in this build (paid doctor
-- memberships) — they exist so future work extends the schema instead of
-- redesigning it.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS mediconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediconnect;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Core identity
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role              ENUM('patient','doctor','admin','pharmacy') NOT NULL DEFAULT 'patient',
    full_name         VARCHAR(150) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    phone             VARCHAR(30)  DEFAULT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    avatar            VARCHAR(255) DEFAULT NULL,
    status            ENUM('active','pending','suspended','banned') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME DEFAULT NULL,
    last_login_at     DATETIME DEFAULT NULL,
    last_active_at    DATETIME DEFAULT NULL COMMENT 'refreshed on page loads while logged in; drives doctor online status',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pwreset_user (user_id),
    UNIQUE KEY uq_pwreset_token (token_hash),
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(150) NOT NULL COMMENT 'email attempted',
    ip_address   VARCHAR(45) NOT NULL,
    success      TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_identifier (identifier),
    KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Doctor directory
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS specializations;
CREATE TABLE specializations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    icon        VARCHAR(60)  DEFAULT 'ri-stethoscope-line',
    description VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_spec_slug (slug)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctors;
CREATE TABLE doctors (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                   INT UNSIGNED NOT NULL,
    slug                      VARCHAR(180) NOT NULL,
    qualification             VARCHAR(255) DEFAULT NULL,
    registration_number       VARCHAR(100) DEFAULT NULL,
    experience_years          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bio                       TEXT,
    consultation_fee_online   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    consultation_fee_physical DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    free_consultation         TINYINT(1) NOT NULL DEFAULT 0,
    clinic_name               VARCHAR(180) DEFAULT NULL,
    clinic_address            VARCHAR(255) DEFAULT NULL,
    clinic_city               VARCHAR(100) DEFAULT NULL,
    clinic_state              VARCHAR(100) DEFAULT NULL,
    clinic_country             VARCHAR(100) DEFAULT NULL,
    verification_status       ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    verification_note         VARCHAR(255) DEFAULT NULL,
    is_premium                TINYINT(1) NOT NULL DEFAULT 0,
    membership_plan_id        INT UNSIGNED DEFAULT NULL,
    rating_avg                DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    rating_count              INT UNSIGNED NOT NULL DEFAULT 0,
    profile_views             INT UNSIGNED NOT NULL DEFAULT 0,
    chat_enabled              TINYINT(1) NOT NULL DEFAULT 0,
    chat_visible_to_guests    TINYINT(1) NOT NULL DEFAULT 0,
    chat_start_time           TIME DEFAULT NULL COMMENT 'daily window start; NULL + chat_enabled = available anytime',
    chat_end_time              TIME DEFAULT NULL,
    meta_title                VARCHAR(200) DEFAULT NULL COMMENT 'blank = auto-generated from name/specialization',
    meta_description          VARCHAR(300) DEFAULT NULL COMMENT 'blank = auto-generated from bio',
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doctors_user (user_id),
    UNIQUE KEY uq_doctors_slug (slug),
    KEY idx_doctors_verification (verification_status),
    KEY idx_doctors_premium (is_premium),
    CONSTRAINT fk_doctors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A doctor can practice under more than one specialization.
DROP TABLE IF EXISTS doctor_specializations;
CREATE TABLE doctor_specializations (
    doctor_id          INT UNSIGNED NOT NULL,
    specialization_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (doctor_id, specialization_id),
    KEY idx_docspec_spec (specialization_id),
    CONSTRAINT fk_docspec_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_docspec_spec FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctor_privacy_settings;
CREATE TABLE doctor_privacy_settings (
    doctor_id             INT UNSIGNED NOT NULL PRIMARY KEY,
    show_certificates     TINYINT(1) NOT NULL DEFAULT 1,
    show_fees             TINYINT(1) NOT NULL DEFAULT 1,
    show_availability     TINYINT(1) NOT NULL DEFAULT 1,
    show_clinic_address   TINYINT(1) NOT NULL DEFAULT 1,
    show_phone            TINYINT(1) NOT NULL DEFAULT 0,
    show_email            TINYINT(1) NOT NULL DEFAULT 0,
    show_free_consultation TINYINT(1) NOT NULL DEFAULT 1,
    show_store            TINYINT(1) NOT NULL DEFAULT 1,
    show_reviews          TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_privacy_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctor_certificates;
CREATE TABLE doctor_certificates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED NOT NULL,
    title       VARCHAR(180) NOT NULL,
    issued_by   VARCHAR(180) DEFAULT NULL,
    issued_year YEAR DEFAULT NULL,
    file_path   VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cert_doctor (doctor_id),
    CONSTRAINT fk_cert_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Pharmacies (medicine stores) — a second seller type alongside doctors.
-- Registers the same way doctors do: self-apply, upload a registration
-- number + certificates, wait for admin verification, then sell on the
-- shared storefront (see doctor_products.seller_type further below).
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS pharmacies;
CREATE TABLE pharmacies (
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

DROP TABLE IF EXISTS pharmacy_certificates;
CREATE TABLE pharmacy_certificates (
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

DROP TABLE IF EXISTS doctor_availability;
CREATE TABLE doctor_availability (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id          INT UNSIGNED NOT NULL,
    day_of_week        TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday .. 6=Saturday',
    start_time         TIME NOT NULL,
    end_time           TIME NOT NULL,
    slot_duration_mins SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    consultation_type  ENUM('online','physical','both') NOT NULL DEFAULT 'both',
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_avail_doctor_day (doctor_id, day_of_week),
    CONSTRAINT fk_avail_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctor_blocked_dates;
CREATE TABLE doctor_blocked_dates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id    INT UNSIGNED NOT NULL,
    blocked_date DATE NOT NULL,
    reason       VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blocked_doctor_date (doctor_id, blocked_date),
    CONSTRAINT fk_blocked_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Patients
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS patients;
CREATE TABLE patients (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                  INT UNSIGNED NOT NULL,
    date_of_birth            DATE DEFAULT NULL,
    gender                   ENUM('male','female','other') DEFAULT NULL,
    blood_group              VARCHAR(5) DEFAULT NULL,
    address                  VARCHAR(255) DEFAULT NULL,
    city                     VARCHAR(100) DEFAULT NULL,
    state                    VARCHAR(100) DEFAULT NULL,
    country                  VARCHAR(100) DEFAULT NULL,
    emergency_contact_name   VARCHAR(150) DEFAULT NULL,
    emergency_contact_phone  VARCHAR(30) DEFAULT NULL,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patients_user (user_id),
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS favorite_doctors;
CREATE TABLE favorite_doctors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id  INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (patient_id, doctor_id),
    CONSTRAINT fk_fav_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A doctor's private clinical notes on a patient they've treated — separate
-- from appointments (one appointment can spawn several notes over time, and
-- a doctor may want to log a status update between visits). Only the doctor
-- who wrote an entry (not other doctors) can see/edit it; gated at the
-- application layer to patients the doctor has an appointment with.
DROP TABLE IF EXISTS patient_medical_history;
CREATE TABLE patient_medical_history (
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

-- ----------------------------------------------------------------------------
-- Appointments
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS appointments;
CREATE TABLE appointments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT UNSIGNED NOT NULL,
    doctor_id         INT UNSIGNED NOT NULL,
    appointment_date  DATE NOT NULL,
    start_time        TIME NOT NULL,
    end_time          TIME NOT NULL,
    consultation_type ENUM('online','physical') NOT NULL DEFAULT 'online',
    status            ENUM('pending','approved','rejected','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    fee               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reason            VARCHAR(500) DEFAULT NULL,
    doctor_notes      TEXT,
    cancelled_by      ENUM('patient','doctor','admin') DEFAULT NULL,
    cancel_reason     VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_appt_doctor_date (doctor_id, appointment_date, start_time),
    KEY idx_appt_patient (patient_id),
    KEY idx_appt_status (status),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED DEFAULT NULL,
    patient_id     INT UNSIGNED NOT NULL,
    doctor_id      INT UNSIGNED NOT NULL,
    rating         TINYINT UNSIGNED NOT NULL,
    comment        TEXT,
    status         ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_appt (appointment_id),
    KEY idx_review_doctor (doctor_id),
    CONSTRAINT fk_review_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    CONSTRAINT fk_review_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Notifications & platform activity
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(50) NOT NULL,
    title      VARCHAR(180) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(255) DEFAULT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user_read (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    role        VARCHAR(20) DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_user (user_id),
    KEY idx_log_action (action),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    phone      VARCHAR(30) DEFAULT NULL,
    subject    VARCHAR(180) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('new','read','replied') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_status (status)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Site content / CMS
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS site_settings;
CREATE TABLE site_settings (
    setting_key   VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT
) ENGINE=InnoDB;

DROP TABLE IF EXISTS cms_pages;
CREATE TABLE cms_pages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(120) NOT NULL,
    title            VARCHAR(180) NOT NULL,
    content          LONGTEXT,
    meta_title       VARCHAR(180) DEFAULT NULL,
    meta_description VARCHAR(255) DEFAULT NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cms_slug (slug)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS faqs;
CREATE TABLE faqs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question   VARCHAR(255) NOT NULL,
    answer     TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

DROP TABLE IF EXISTS testimonials;
CREATE TABLE testimonials (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    role       VARCHAR(150) DEFAULT NULL,
    avatar     VARCHAR(255) DEFAULT NULL,
    content    VARCHAR(500) NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ============================================================================
-- Doctor memberships (PHASE 2+, not yet wired to UI), messaging, store, blog
-- ============================================================================

DROP TABLE IF EXISTS membership_plans;
CREATE TABLE membership_plans (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(80) NOT NULL,
    slug           VARCHAR(80) NOT NULL,
    price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_cycle  ENUM('free','monthly','yearly') NOT NULL DEFAULT 'monthly',
    features       TEXT COMMENT 'JSON array of feature strings',
    priority_listing TINYINT(1) NOT NULL DEFAULT 0,
    store_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_plan_slug (slug)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctor_memberships;
CREATE TABLE doctor_memberships (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id  INT UNSIGNED NOT NULL,
    plan_id    INT UNSIGNED NOT NULL,
    starts_at  DATE NOT NULL,
    expires_at DATE DEFAULT NULL,
    status     ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dm_doctor (doctor_id),
    CONSTRAINT fk_dm_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_dm_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB;

ALTER TABLE doctors
    ADD CONSTRAINT fk_doctors_membership FOREIGN KEY (membership_plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL;

DROP TABLE IF EXISTS chat_conversations;
CREATE TABLE chat_conversations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT UNSIGNED NOT NULL,
    doctor_id      INT UNSIGNED NOT NULL,
    is_blocked     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'doctor blocked this patient from sending further messages',
    last_message_at DATETIME DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conv (patient_id, doctor_id),
    CONSTRAINT fk_conv_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS chat_messages;
CREATE TABLE chat_messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL COMMENT 'users.id',
    message         TEXT,
    attachment_path VARCHAR(255) DEFAULT NULL,
    message_type    ENUM('text','image','file','prescription','report') NOT NULL DEFAULT 'text',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_msg_conv (conversation_id),
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Doctor and pharmacy products/services store. Creating listings is gated
-- to premium doctors (doctors.is_premium) or verified pharmacies
-- (pharmacies.verification_status) at the application layer. seller_type
-- says which of doctor_id/pharmacy_id is populated (exactly one is set;
-- the table kept its original "doctor_products" name for historical
-- reasons even though it now covers both seller types).
DROP TABLE IF EXISTS product_categories;
CREATE TABLE product_categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_prodcat_slug (slug)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS doctor_products;
CREATE TABLE doctor_products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT UNSIGNED DEFAULT NULL COMMENT 'store owner when seller_type=doctor',
    pharmacy_id     INT UNSIGNED DEFAULT NULL COMMENT 'store owner when seller_type=pharmacy',
    seller_type     ENUM('doctor','pharmacy') NOT NULL DEFAULT 'doctor',
    category_id     INT UNSIGNED DEFAULT NULL,
    type            ENUM('product','service') NOT NULL DEFAULT 'product',
    name            VARCHAR(180) NOT NULL,
    slug            VARCHAR(200) NOT NULL,
    description     TEXT,
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock           INT UNSIGNED DEFAULT NULL COMMENT 'physical products only; NULL for services',
    duration_label  VARCHAR(60) DEFAULT NULL COMMENT 'services only, e.g. "3 sessions", "45 min"',
    image           VARCHAR(255) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    meta_title      VARCHAR(200) DEFAULT NULL COMMENT 'blank = auto-generated from name',
    meta_description VARCHAR(300) DEFAULT NULL COMMENT 'blank = auto-generated from description',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doctor_product_slug (slug),
    KEY idx_doctor_product_pharmacy (pharmacy_id),
    FULLTEXT KEY ft_doctor_product_search (name, description),
    CONSTRAINT fk_doctor_product_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_product_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_product_cat FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id    INT UNSIGNED NOT NULL,
    doctor_id     INT UNSIGNED DEFAULT NULL COMMENT 'seller when seller_type=doctor',
    pharmacy_id   INT UNSIGNED DEFAULT NULL COMMENT 'seller when seller_type=pharmacy',
    seller_type   ENUM('doctor','pharmacy') NOT NULL DEFAULT 'doctor',
    order_number  VARCHAR(40) NOT NULL,
    total_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    contact_phone VARCHAR(30) DEFAULT NULL,
    shipping_address VARCHAR(255) DEFAULT NULL COMMENT 'physical products only',
    notes         TEXT,
    status        ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_number (order_number),
    KEY idx_order_pharmacy (pharmacy_id),
    CONSTRAINT fk_order_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES doctor_products(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS blog_posts;
CREATE TABLE blog_posts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id      INT UNSIGNED NOT NULL COMMENT 'users.id',
    title          VARCHAR(200) NOT NULL,
    slug           VARCHAR(220) NOT NULL,
    excerpt        VARCHAR(300) DEFAULT NULL,
    content        LONGTEXT,
    featured_image VARCHAR(255) DEFAULT NULL,
    status         ENUM('draft','published') NOT NULL DEFAULT 'draft',
    meta_title     VARCHAR(200) DEFAULT NULL COMMENT 'blank = auto-generated from title',
    meta_description VARCHAR(300) DEFAULT NULL COMMENT 'blank = auto-generated from excerpt/content',
    published_at   DATETIME DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_slug (slug),
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Informational medicine reference content (not for sale — see doctor_products
-- for the storefront). Authored by a doctor or admin, purely to give the
-- site something worth ranking for when someone searches a medicine name.
DROP TABLE IF EXISTS medicine_info;
CREATE TABLE medicine_info (
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

DROP TABLE IF EXISTS support_tickets;
CREATE TABLE support_tickets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(180) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
