-- =====================================================================
-- PrintHub — multi-tenant digital printing platform for Pakistan
-- MySQL 5.7+ / MariaDB 10.3+   ·   utf8mb4   ·   all money in PKR
-- Import this file first, then database/seed.sql (master data only).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- platform
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS platform_settings;
CREATE TABLE platform_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- shops
DROP TABLE IF EXISTS shops;
CREATE TABLE shops (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Public ID used in the QR link /shop/{code}; never changes',
  name VARCHAR(150) NOT NULL,
  owner_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  whatsapp VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  address VARCHAR(255) NOT NULL,
  area VARCHAR(120) NULL,
  city VARCHAR(100) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  logo_path VARCHAR(255) NULL,
  status ENUM('active','suspended','deactivated') NOT NULL DEFAULT 'active',
  accepting_orders TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Owner switch: open / temporarily closed',
  subscription_price DECIMAL(10,2) NULL COMMENT 'Custom monthly price; NULL = platform default',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_shops_status (status),
  KEY idx_shops_city (city),
  CONSTRAINT fk_shops_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_users;
CREATE TABLE shop_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','staff') NOT NULL DEFAULT 'owner',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  reset_token_hash CHAR(64) NULL,
  reset_expires_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_su_shop (shop_id),
  KEY idx_su_email (email),
  CONSTRAINT fk_su_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS auth_tokens;
CREATE TABLE auth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE COMMENT 'sha256 of the bearer token; the token itself is never stored',
  user_type ENUM('owner','admin') NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  shop_id INT UNSIGNED NULL,
  device_name VARCHAR(120) NULL,
  ip_address VARCHAR(45) NULL,
  expires_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_at_user (user_type, user_id),
  KEY idx_at_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS subscriptions;
CREATE TABLE subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL COMMENT 'Monthly price in PKR for this period',
  months INT UNSIGNED NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  grace_days INT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active' COMMENT 'Expiry is computed from end_date + grace_days',
  reminder_sent_at DATETIME NULL,
  expired_notified_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sub_shop (shop_id, end_date),
  KEY idx_sub_end (end_date),
  CONSTRAINT fk_sub_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS subscription_payments;
CREATE TABLE subscription_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  subscription_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','bank_transfer','jazzcash','easypaisa','other') NOT NULL,
  reference VARCHAR(120) NULL,
  paid_on DATE NOT NULL,
  months_covered INT UNSIGNED NOT NULL DEFAULT 1,
  proof_path VARCHAR(255) NULL,
  status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'confirmed',
  notes VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sp_shop (shop_id),
  KEY idx_sp_paid (paid_on),
  KEY idx_sp_status (status),
  CONSTRAINT fk_sp_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_sp_admin FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_settings;
CREATE TABLE shop_settings (
  shop_id INT UNSIGNED PRIMARY KEY,
  pricing_mode ENUM('auto','quote') NOT NULL DEFAULT 'auto',
  payment_mode ENUM('pay_at_shop','advance_required','advance_optional') NOT NULL DEFAULT 'pay_at_shop',
  payment_instructions TEXT NULL COMMENT 'e.g. JazzCash / Easypaisa / bank account for advance payment',
  min_order_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  estimated_minutes INT UNSIGNED NOT NULL DEFAULT 30,
  opening_hours VARCHAR(255) NULL,
  landing_heading VARCHAR(120) NULL,
  landing_message VARCHAR(500) NULL,
  theme_color CHAR(7) NOT NULL DEFAULT '#0F766E',
  retention_days INT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'Customer files are deleted this many days after the order is closed',
  max_upload_mb INT UNSIGNED NOT NULL DEFAULT 25,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ss_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_wifi_settings;
CREATE TABLE shop_wifi_settings (
  shop_id INT UNSIGNED PRIMARY KEY,
  ssid VARCHAR(64) NULL,
  password_enc TEXT NULL COMMENT 'AES-256-GCM with APP_KEY',
  security ENUM('WPA','WEP','nopass') NOT NULL DEFAULT 'WPA',
  is_hidden_network TINYINT(1) NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Shown to customers only when the owner turns this on',
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sw_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_qr_settings;
CREATE TABLE shop_qr_settings (
  shop_id INT UNSIGNED PRIMARY KEY,
  fg_color CHAR(7) NOT NULL DEFAULT '#0F172A',
  bg_color CHAR(7) NOT NULL DEFAULT '#FFFFFF',
  accent_color CHAR(7) NOT NULL DEFAULT '#0F766E',
  show_logo TINYINT(1) NOT NULL DEFAULT 1,
  heading VARCHAR(80) NOT NULL DEFAULT 'Scan to print',
  subheading VARCHAR(160) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Bumped on regenerate; the link (shop code) never changes',
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sq_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- catalogue (global, managed by Super Admin)
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  name_ur VARCHAR(100) NULL,
  description VARCHAR(255) NULL,
  icon VARCHAR(60) NOT NULL DEFAULT 'printer' COMMENT 'Icon name (Bootstrap Icons / Material mapping)',
  icon_path VARCHAR(255) NULL COMMENT 'Optional uploaded icon image',
  pricing_basis ENUM('pages','items') NOT NULL DEFAULT 'pages' COMMENT 'pages = documents; items = cards, photos, sets',
  accepts_upload TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS subcategories;
CREATE TABLE subcategories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_ur VARCHAR(100) NULL,
  description VARCHAR(255) NULL,
  default_paper_size_id INT UNSIGNED NULL,
  pricing_basis ENUM('pages','items') NULL COMMENT 'NULL = same as the category',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_sub_code (category_id, code),
  CONSTRAINT fk_subc_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS printing_options;
CREATE TABLE printing_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  option_group ENUM('paper_size','color','sides','paper_type','gsm','finishing') NOT NULL,
  code VARCHAR(40) NOT NULL,
  label VARCHAR(80) NOT NULL,
  label_ur VARCHAR(80) NULL,
  width_mm DECIMAL(7,2) NULL COMMENT 'Paper sizes only',
  height_mm DECIMAL(7,2) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_po (option_group, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS category_options;
CREATE TABLE category_options (
  category_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (category_id, option_id),
  CONSTRAINT fk_co_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_co_opt FOREIGN KEY (option_id) REFERENCES printing_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- shop services & pricing (per shop)
DROP TABLE IF EXISTS shop_services;
CREATE TABLE shop_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  subcategory_id INT UNSIGNED NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(160) NULL COMMENT 'Shown to customers, e.g. "Same-day"',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ss (shop_id, category_id, subcategory_id),
  CONSTRAINT fk_svc_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_svc_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_svc_sub FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_option_settings;
CREATE TABLE shop_option_settings (
  shop_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Paper sizes, papers, GSM and finishing the shop actually offers',
  PRIMARY KEY (shop_id, option_id),
  CONSTRAINT fk_sos_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_sos_opt FOREIGN KEY (option_id) REFERENCES printing_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shop_pricing;
CREATE TABLE shop_pricing (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  rule_type ENUM('print','paper','finishing','item','discount') NOT NULL
    COMMENT 'print = per printed page/sheet; paper = surcharge per sheet for paper type/GSM; finishing = binding etc; item = per piece (cards, photos); discount = % off when total pages >= min_qty',
  category_id INT UNSIGNED NULL COMMENT 'NULL = every category',
  paper_size_id INT UNSIGNED NULL,
  color ENUM('bw','color') NULL,
  sides ENUM('single','double') NULL,
  paper_type_id INT UNSIGNED NULL,
  gsm_id INT UNSIGNED NULL,
  finishing_id INT UNSIGNED NULL,
  unit ENUM('per_page','per_sheet','per_copy','per_order','per_item','percent') NOT NULL DEFAULT 'per_page',
  price DECIMAL(10,2) NOT NULL COMMENT 'PKR (or % for discount rules)',
  min_qty INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Tier: applies when the quantity reaches this number',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sp_lookup (shop_id, rule_type, is_active),
  CONSTRAINT fk_pr_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_size FOREIGN KEY (paper_size_id) REFERENCES printing_options(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_type FOREIGN KEY (paper_type_id) REFERENCES printing_options(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_gsm FOREIGN KEY (gsm_id) REFERENCES printing_options(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_fin FOREIGN KEY (finishing_id) REFERENCES printing_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- customers
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cust_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_sessions;
CREATE TABLE customer_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE COMMENT 'Anonymous device session; customers never need an account',
  customer_id INT UNSIGNED NULL,
  last_shop_id INT UNSIGNED NULL,
  platform ENUM('web','app') NOT NULL DEFAULT 'web',
  user_agent VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  KEY idx_cs_customer (customer_id),
  CONSTRAINT fk_cs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_cs_shop FOREIGN KEY (last_shop_id) REFERENCES shops(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- uploads (before an order exists)
DROP TABLE IF EXISTS file_uploads;
CREATE TABLE file_uploads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL UNIQUE,
  session_id INT UNSIGNED NOT NULL,
  shop_id INT UNSIGNED NOT NULL,
  storage_path VARCHAR(255) NOT NULL COMMENT 'Relative to the private storage directory',
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  file_kind ENUM('pdf','image','docx') NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  page_count INT UNSIGNED NULL,
  width_px INT UNSIGNED NULL,
  height_px INT UNSIGNED NULL,
  converted_path VARCHAR(255) NULL COMMENT 'PDF converted from DOCX, when the server can convert',
  status ENUM('temp','attached','deleted') NOT NULL DEFAULT 'temp',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  KEY idx_fu_session (session_id),
  KEY idx_fu_expiry (status, expires_at),
  CONSTRAINT fk_fu_session FOREIGN KEY (session_id) REFERENCES customer_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- CV builder
DROP TABLE IF EXISTS cv_templates;
CREATE TABLE cv_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(40) NOT NULL COMMENT 'Layout file in core/cv_templates/{key}.php',
  name VARCHAR(80) NOT NULL,
  name_ur VARCHAR(80) NULL,
  category ENUM('professional','modern','simple','student','fresher','experienced','creative','ats','executive','corporate','minimal') NOT NULL DEFAULT 'professional',
  description VARCHAR(255) NULL,
  preview_path VARCHAR(255) NULL,
  default_color CHAR(7) NOT NULL DEFAULT '#0F766E',
  supports_photo TINYINT(1) NOT NULL DEFAULT 1,
  ats_friendly TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cvt_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_cvs;
CREATE TABLE customer_cvs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_token CHAR(32) NOT NULL UNIQUE,
  session_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  template_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL DEFAULT 'My CV',
  accent_color CHAR(7) NOT NULL DEFAULT '#0F766E',
  language VARCHAR(5) NOT NULL DEFAULT 'en',
  direction ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
  data MEDIUMTEXT NOT NULL COMMENT 'Validated JSON: personal details, sections, entries',
  section_order VARCHAR(500) NOT NULL DEFAULT '[]',
  photo_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_cv_session (session_id),
  CONSTRAINT fk_cv_session FOREIGN KEY (session_id) REFERENCES customer_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_cv_tpl FOREIGN KEY (template_id) REFERENCES cv_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- orders
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(24) NOT NULL UNIQUE,
  tracking_token CHAR(40) NOT NULL COMMENT 'Private tracking link token',
  shop_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  session_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  status ENUM('submitted','awaiting_approval','accepted','quoted','awaiting_payment','payment_confirmed','printing','ready','completed','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  pricing_mode ENUM('auto','quote') NOT NULL,
  payment_mode ENUM('pay_at_shop','advance_required','advance_optional') NOT NULL DEFAULT 'pay_at_shop',
  payment_status ENUM('unpaid','pending_verification','paid','refunded') NOT NULL DEFAULT 'unpaid',
  estimated_amount DECIMAL(10,2) NULL COMMENT 'Automatic estimate; NULL when no matching price exists',
  quoted_amount DECIMAL(10,2) NULL COMMENT 'Set by the owner (quote or manual override)',
  final_amount DECIMAL(10,2) NULL,
  special_instructions TEXT NULL,
  rejection_reason VARCHAR(255) NULL,
  source ENUM('web','app') NOT NULL DEFAULT 'web',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  closed_at DATETIME NULL COMMENT 'Completed / rejected / cancelled',
  files_deleted_at DATETIME NULL,
  KEY idx_o_shop_status (shop_id, status),
  KEY idx_o_shop_created (shop_id, created_at),
  KEY idx_o_session (session_id),
  KEY idx_o_phone (customer_phone),
  CONSTRAINT fk_o_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_o_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_o_session FOREIGN KEY (session_id) REFERENCES customer_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  item_type ENUM('document','cv','custom') NOT NULL DEFAULT 'document',
  category_id INT UNSIGNED NULL,
  subcategory_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  paper_size_id INT UNSIGNED NULL,
  custom_width_mm DECIMAL(7,2) NULL,
  custom_height_mm DECIMAL(7,2) NULL,
  color ENUM('bw','color') NULL,
  sides ENUM('single','double') NULL,
  paper_type_id INT UNSIGNED NULL,
  gsm_id INT UNSIGNED NULL,
  orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'portrait',
  pages INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Pages to print per copy (after page selection)',
  copies INT UNSIGNED NOT NULL DEFAULT 1,
  quantity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Pieces for item-based categories',
  layout TEXT NULL COMMENT 'JSON: fit/fill, margins, scale, crop, rotation',
  page_selection TEXT NULL COMMENT 'JSON: [{page, rotate}] in print order',
  estimated_price DECIMAL(10,2) NULL,
  price_breakdown TEXT NULL COMMENT 'JSON lines from the pricing engine',
  cv_id INT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_oi_order (order_id),
  KEY idx_oi_cat (category_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_sub FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_size FOREIGN KEY (paper_size_id) REFERENCES printing_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_type FOREIGN KEY (paper_type_id) REFERENCES printing_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_gsm FOREIGN KEY (gsm_id) REFERENCES printing_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_cv FOREIGN KEY (cv_id) REFERENCES customer_cvs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_item_finishings;
CREATE TABLE order_item_finishings (
  order_item_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL,
  price DECIMAL(10,2) NULL,
  PRIMARY KEY (order_item_id, option_id),
  CONSTRAINT fk_oif_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_oif_opt FOREIGN KEY (option_id) REFERENCES printing_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_files;
CREATE TABLE order_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NULL,
  kind ENUM('original','print_ready','converted','payment_proof') NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  page_count INT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  note VARCHAR(255) NULL COMMENT 'e.g. why a print-ready file could not be produced',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_of_order (order_id),
  CONSTRAINT fk_of_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_of_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_status_history;
CREATE TABLE order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  actor_type ENUM('customer','owner','admin','system') NOT NULL,
  actor_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  shop_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','bank_transfer','jazzcash','easypaisa','other') NOT NULL,
  reference VARCHAR(120) NULL,
  proof_file_id INT UNSIGNED NULL,
  status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  submitted_by ENUM('customer','owner') NOT NULL,
  confirmed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at DATETIME NULL,
  KEY idx_pay_order (order_id),
  KEY idx_pay_shop (shop_id, status),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_file FOREIGN KEY (proof_file_id) REFERENCES order_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (confirmed_by) REFERENCES shop_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- notifications, logs, limits
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('admin','shop','customer') NOT NULL COMMENT 'shop = every user of the shop; customer = a customer session',
  recipient_id INT UNSIGNED NULL COMMENT 'admin id / shop id / customer session id (NULL = all admins)',
  shop_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY idx_n_recipient (recipient_type, recipient_id, is_read),
  CONSTRAINT fk_n_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_n_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('admin','owner','customer','system') NOT NULL,
  actor_id INT UNSIGNED NULL,
  shop_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_al_shop (shop_id, created_at),
  KEY idx_al_actor (actor_type, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS rate_limits;
CREATE TABLE rate_limits (
  rl_key VARCHAR(190) PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
