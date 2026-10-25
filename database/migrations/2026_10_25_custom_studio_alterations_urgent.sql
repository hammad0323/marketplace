-- Urban Collections migration: Custom Size Studio, Alterations, Urgent delivery, Women's range.
-- For stores installed before this update. New installs: import database/urban_collections.sql instead.
-- Run once in phpMyAdmin (Import), then run database/migrations/2026_10_25_seed_features.sql for default content.
SET NAMES utf8mb4;

ALTER TABLE products
  ADD COLUMN custom_garment_id INT UNSIGNED NULL AFTER customization_fee,
  ADD COLUMN allow_urgent TINYINT(1) NOT NULL DEFAULT 1 AFTER custom_garment_id;
ALTER TABLE cart_items
  ADD COLUMN custom_spec TEXT NULL AFTER custom_instructions,
  ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_spec;
ALTER TABLE order_items
  ADD COLUMN custom_spec TEXT NULL AFTER custom_instructions,
  ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_spec;

-- ---------------------------------------------------------------------
--  Custom Size Studio (made-to-measure at ready-made price)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS custom_garments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE COMMENT 'Matches a definition in includes/studio.php',
  name VARCHAR(120) NOT NULL,
  gender ENUM('men','women') NOT NULL DEFAULT 'men',
  intro VARCHAR(500) NULL,
  fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Extra charge for custom sizing (0 = ready-made price)',
  production_days_min INT NOT NULL DEFAULT 4,
  production_days_max INT NOT NULL DEFAULT 7,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_size_chart (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garment_id INT UNSIGNED NOT NULL,
  size_label VARCHAR(10) NOT NULL,
  measure_key VARCHAR(40) NOT NULL,
  value DECIMAL(5,1) NOT NULL,
  UNIQUE KEY uq_chart (garment_id, size_label, measure_key),
  CONSTRAINT fk_chart_garment FOREIGN KEY (garment_id) REFERENCES custom_garments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_size_fit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garment_id INT UNSIGNED NOT NULL,
  size_label VARCHAR(10) NOT NULL,
  height_min_in DECIMAL(4,1) NOT NULL COMMENT 'Body height range for the size finder',
  height_max_in DECIMAL(4,1) NOT NULL,
  weight_min_kg DECIMAL(5,1) NOT NULL,
  weight_max_kg DECIMAL(5,1) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_fit (garment_id, size_label),
  CONSTRAINT fk_fit_garment FOREIGN KEY (garment_id) REFERENCES custom_garments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_style_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garment_id INT UNSIGNED NOT NULL,
  option_group VARCHAR(30) NOT NULL COMMENT 'collar, cuff, sleeve, daman, bottom, pocket, neck',
  value_key VARCHAR(40) NOT NULL COMMENT 'Drawing key understood by the studio mannequin',
  label VARCHAR(80) NOT NULL,
  extra_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_style (garment_id, option_group, value_key),
  CONSTRAINT fk_style_garment FOREIGN KEY (garment_id) REFERENCES custom_garments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_measurements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  garment_code VARCHAR(40) NOT NULL,
  profile_name VARCHAR(60) NOT NULL DEFAULT 'My measurements',
  data TEXT NOT NULL COMMENT 'JSON of validated measurements',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cm_customer (customer_id),
  CONSTRAINT fk_cm_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Alteration service
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alteration_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(40) NOT NULL DEFAULT 'Men' COMMENT 'Men, Women, Bridal, General',
  garment VARCHAR(80) NOT NULL COMMENT 'e.g. Coat Pant, Sherwani, Bridal Lehenga',
  name VARCHAR(120) NOT NULL COMMENT 'e.g. Full fitting, Sleeve shortening',
  description VARCHAR(500) NULL,
  price_min DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  price_max DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  days_min INT NOT NULL DEFAULT 2,
  days_max INT NOT NULL DEFAULT 4,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_alt_active (is_active, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alteration_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  access_token CHAR(40) NOT NULL,
  customer_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  city VARCHAR(100) NOT NULL,
  address VARCHAR(255) NULL,
  service_method ENUM('drop_off','pickup') NOT NULL DEFAULT 'drop_off',
  preferred_date DATE NULL,
  notes TEXT NULL,
  est_min DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  est_max DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  pickup_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  est_days_min INT NOT NULL DEFAULT 0,
  est_days_max INT NOT NULL DEFAULT 0,
  quoted_price DECIMAL(12,2) NULL,
  ready_date DATE NULL,
  status ENUM('new','quoted','confirmed','received','in_progress','ready','completed','cancelled') NOT NULL DEFAULT 'new',
  payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  admin_notes TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ar_status (status),
  KEY idx_ar_phone (phone),
  CONSTRAINT fk_ar_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alteration_request_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NULL,
  garment VARCHAR(80) NOT NULL,
  service_name VARCHAR(120) NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  price_min DECIMAL(12,2) NOT NULL,
  price_max DECIMAL(12,2) NOT NULL,
  days_max INT NOT NULL DEFAULT 0,
  KEY idx_ari_request (request_id),
  CONSTRAINT fk_ari_request FOREIGN KEY (request_id) REFERENCES alteration_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ari_service FOREIGN KEY (service_id) REFERENCES alteration_services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alteration_request_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  image VARCHAR(500) NOT NULL,
  KEY idx_arp_request (request_id),
  CONSTRAINT fk_arp_request FOREIGN KEY (request_id) REFERENCES alteration_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alteration_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NULL,
  note TEXT NULL,
  is_customer_visible TINYINT(1) NOT NULL DEFAULT 1,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ash_request (request_id),
  CONSTRAINT fk_ash_request FOREIGN KEY (request_id) REFERENCES alteration_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ash_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Urgent (city-wise) delivery
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS urgent_delivery_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city VARCHAR(100) NOT NULL UNIQUE,
  fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  label VARCHAR(80) NOT NULL DEFAULT 'Urgent Delivery',
  days_min INT NOT NULL DEFAULT 0 COMMENT '0 = same day',
  days_max INT NOT NULL DEFAULT 1,
  cutoff_time TIME NULL COMMENT 'Orders after this time move to the next day',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
