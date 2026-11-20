-- Urban Collections migration: Design Studio (customers design their own T-shirt / hoodie).
-- For stores installed before this update. Run once in phpMyAdmin (Import), then run
-- database/migrations/2026_11_20_seed_design_studio.sql for default prices and settings.
SET NAMES utf8mb4;

ALTER TABLE products ADD COLUMN design_type VARCHAR(20) NULL AFTER allow_urgent;

-- ---------------------------------------------------------------- Design Studio (print your own T-shirt / hoodie)
CREATE TABLE IF NOT EXISTS design_assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL UNIQUE,
  owner_key CHAR(64) NOT NULL COMMENT 'sha256 of the designer cookie of the uploader',
  image VARCHAR(255) NOT NULL,
  width INT UNSIGNED NOT NULL,
  height INT UNSIGNED NOT NULL,
  bg_removed TINYINT(1) NOT NULL DEFAULT 0,
  source_id INT UNSIGNED NULL COMMENT 'Original upload this background-removed copy was made from',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_da_owner (owner_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS designs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(40) NOT NULL UNIQUE,
  owner_key CHAR(64) NOT NULL,
  customer_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  color VARCHAR(60) NOT NULL,
  spec MEDIUMTEXT NOT NULL COMMENT 'Elements JSON, validated by design_validate()',
  preview_front VARCHAR(255) NULL,
  preview_back VARCHAR(255) NULL,
  print_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Print price per piece when saved',
  status ENUM('draft','in_cart','requested','ordered') NOT NULL DEFAULT 'draft',
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_d_owner (owner_key),
  CONSTRAINT fk_d_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_d_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS design_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  access_token CHAR(40) NOT NULL,
  design_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  city VARCHAR(100) NOT NULL,
  sizes TEXT NOT NULL COMMENT 'JSON {size: qty}',
  qty INT UNSIGNED NOT NULL,
  notes TEXT NULL,
  estimate_print DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Automatic print estimate per piece when submitted',
  quoted_print_price DECIMAL(12,2) NULL COMMENT 'Print price per piece confirmed by the store (added to the garment price)',
  admin_message TEXT NULL,
  admin_notes TEXT NULL,
  status ENUM('new','quoted','ordered','cancelled') NOT NULL DEFAULT 'new',
  order_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  quoted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dr_status (status),
  CONSTRAINT fk_dr_design FOREIGN KEY (design_id) REFERENCES designs(id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

