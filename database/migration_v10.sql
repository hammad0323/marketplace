-- MediConnect / DoctorApna — incremental migration (v10)
-- Adds: (1) a ticket/token queue booking mode as an alternative to the
-- existing date/time slot system, with a doctor-manager staff role, and
-- (2) latitude/longitude on doctors and pharmacies for "near me" search.

ALTER TABLE users MODIFY COLUMN role ENUM('patient','doctor','admin','pharmacy','manager') NOT NULL DEFAULT 'patient';

ALTER TABLE doctors
    ADD COLUMN booking_mode ENUM('slots','tickets') NOT NULL DEFAULT 'slots' COMMENT 'admin-controlled: date/time slots, or a first-come-first-served ticket queue' AFTER meta_description,
    ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL COMMENT 'clinic location, for "near me" search' AFTER booking_mode,
    ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;

ALTER TABLE pharmacies
    ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL COMMENT 'store location, for "near me" search' AFTER profile_views,
    ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;

CREATE TABLE IF NOT EXISTS doctor_ticket_schedule (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday .. 6=Saturday',
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_ticket_sched_doctor_day (doctor_id, day_of_week),
    CONSTRAINT fk_ticket_sched_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctor_ticket_counters (
    doctor_id       INT UNSIGNED NOT NULL,
    ticket_date     DATE NOT NULL,
    last_number     INT UNSIGNED NOT NULL DEFAULT 0,
    current_serving INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doctor_id, ticket_date),
    CONSTRAINT fk_ticket_counter_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctor_tickets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id     INT UNSIGNED NOT NULL,
    patient_id    INT UNSIGNED DEFAULT NULL,
    guest_name    VARCHAR(150) DEFAULT NULL,
    guest_phone   VARCHAR(30) DEFAULT NULL,
    ticket_date   DATE NOT NULL,
    ticket_number INT UNSIGNED NOT NULL,
    status        ENUM('waiting','serving','completed','no_show','cancelled') NOT NULL DEFAULT 'waiting',
    added_by      ENUM('patient','doctor','manager') NOT NULL DEFAULT 'patient',
    notes         VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticket_doctor_date_number (doctor_id, ticket_date, ticket_number),
    KEY idx_ticket_patient (patient_id),
    CONSTRAINT fk_ticket_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctor_managers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_manager_user (user_id),
    KEY idx_manager_doctor (doctor_id),
    CONSTRAINT fk_manager_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    CONSTRAINT fk_manager_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
