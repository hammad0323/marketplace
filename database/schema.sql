-- ============================================================================
-- MediConnect — Doctor Association & Telemedicine Platform
-- Full database schema (MySQL 5.7+/8.0, InnoDB, utf8mb4)
--
-- Import this single file to create the database, all tables, and seed
-- data. Tables are grouped by module. Tables marked "PHASE 2+" define the
-- storage shape for features described in the product spec (chat, medicine
-- store, membership billing, blog/CMS) that are not yet wired to UI in this
-- build — they exist so future work extends the schema instead of
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
    role              ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
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
-- PHASE 2+ — storage shape reserved for modules not yet wired to UI
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

DROP TABLE IF EXISTS medicine_categories;
CREATE TABLE medicine_categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_medcat_slug (slug)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS medicines;
CREATE TABLE medicines (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED NOT NULL COMMENT 'store owner',
    category_id INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(180) NOT NULL,
    slug        VARCHAR(200) NOT NULL,
    description TEXT,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock       INT UNSIGNED NOT NULL DEFAULT 0,
    image       VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicine_slug (slug),
    CONSTRAINT fk_medicine_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_medicine_cat FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT UNSIGNED NOT NULL,
    order_number VARCHAR(40) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status       ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_number (order_number),
    CONSTRAINT fk_order_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    quantity    INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id)
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
    published_at   DATETIME DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_slug (slug),
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users(id)
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
