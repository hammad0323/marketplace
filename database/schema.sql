-- =====================================================================
--  Urban Collections — MySQL schema
--  Engine: InnoDB, charset utf8mb4. Compatible with MySQL 5.7+ / MariaDB 10.3+
--  Import this file first, then database/seed.sql (sample data).
--  database/urban_collections.sql contains both in one file.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Administration
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admin_roles;
CREATE TABLE admin_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_permissions;
CREATE TABLE admin_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perm_key VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  group_name VARCHAR(60) NOT NULL DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_role_permissions;
CREATE TABLE admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_arp_perm FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_audit_logs;
CREATE TABLE admin_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Customers
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  admin_notes TEXT NULL,
  newsletter TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customer_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_addresses;
CREATE TABLE customer_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  region VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Pakistan',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_addr_customer (customer_id),
  CONSTRAINT fk_addr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('customer','admin') NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reset_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Catalogue
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(500) NULL,
  banner_image VARCHAR(500) NULL,
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  og_image VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  show_on_home TINYINT(1) NOT NULL DEFAULT 0,
  show_in_menu TINYINT(1) NOT NULL DEFAULT 1,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cat_parent (parent_id),
  KEY idx_cat_active (is_active, sort_order),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS sizes;
CREATE TABLE sizes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  size_group VARCHAR(40) NOT NULL DEFAULT 'Apparel',
  name VARCHAR(40) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_size (size_group, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS colors;
CREATE TABLE colors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  hex_code CHAR(7) NOT NULL DEFAULT '#000000',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  sku VARCHAR(80) NOT NULL UNIQUE,
  short_description VARCHAR(600) NULL,
  description MEDIUMTEXT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NULL,
  has_variants TINYINT(1) NOT NULL DEFAULT 0,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  stock_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  allow_backorder TINYINT(1) NOT NULL DEFAULT 0,
  weight_kg DECIMAL(8,3) NULL,
  dimensions VARCHAR(80) NULL,
  brand VARCHAR(80) NULL,
  fabric VARCHAR(120) NULL,
  fit VARCHAR(60) NULL,
  pattern VARCHAR(60) NULL,
  sleeve_type VARCHAR(60) NULL,
  collar_type VARCHAR(60) NULL,
  waist_type VARCHAR(60) NULL,
  length_type VARCHAR(60) NULL,
  season VARCHAR(60) NULL,
  occasion VARCHAR(80) NULL,
  care_instructions TEXT NULL,
  size_guide TEXT NULL,
  video_url VARCHAR(500) NULL,
  ready_to_ship TINYINT(1) NOT NULL DEFAULT 1,
  made_to_order TINYINT(1) NOT NULL DEFAULT 0,
  lead_time_days INT NOT NULL DEFAULT 0,
  is_customizable TINYINT(1) NOT NULL DEFAULT 0,
  custom_fields SET('measurements','color','personalization','instructions') NULL,
  customization_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  custom_garment_id INT UNSIGNED NULL COMMENT 'Custom Size Studio garment (made-to-measure at ready-made price)',
  allow_urgent TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Urgent delivery allowed for this product',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  badge VARCHAR(40) NULL,
  status ENUM('draft','published','inactive') NOT NULL DEFAULT 'draft',
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  og_title VARCHAR(190) NULL,
  og_description VARCHAR(320) NULL,
  og_image VARCHAR(500) NULL,
  canonical_url VARCHAR(500) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count INT NOT NULL DEFAULT 0,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prod_cat (category_id),
  KEY idx_prod_status (status, published_at),
  KEY idx_prod_flags (is_featured, is_new, is_bestseller),
  KEY idx_prod_price (price),
  FULLTEXT KEY ft_prod_search (name, short_description, sku),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_images;
CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image VARCHAR(500) NOT NULL,
  alt_text VARCHAR(190) NULL,
  caption VARCHAR(190) NULL,
  view_type ENUM('front','back','side','detail','lifestyle','other') NOT NULL DEFAULT 'front',
  color_name VARCHAR(60) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_img_product (product_id, sort_order),
  CONSTRAINT fk_img_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variants;
CREATE TABLE product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(100) NOT NULL UNIQUE,
  price DECIMAL(12,2) NULL COMMENT 'NULL = use product price',
  sale_price DECIMAL(12,2) NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_var_product (product_id),
  CONSTRAINT fk_var_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variant_values;
CREATE TABLE product_variant_values (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  option_name VARCHAR(40) NOT NULL COMMENT 'Size, Color, Waist, Length, Shoe Size...',
  option_value VARCHAR(60) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_variant_option (variant_id, option_name),
  KEY idx_pvv_option (option_name, option_value),
  CONSTRAINT fk_pvv_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-form specification rows shown on the product page ("Neckline: Crew")
DROP TABLE IF EXISTS product_attributes;
CREATE TABLE product_attributes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  value VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_attr_product (product_id),
  CONSTRAINT fk_attr_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock ledger: every change to product / variant stock is recorded here.
DROP TABLE IF EXISTS product_inventory;
CREATE TABLE product_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  change_qty INT NOT NULL,
  balance_after INT NOT NULL,
  reason ENUM('initial','manual','order','cancel','refund','return','correction') NOT NULL DEFAULT 'manual',
  reference VARCHAR(80) NULL,
  note VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_product (product_id, variant_id),
  KEY idx_inv_created (created_at),
  CONSTRAINT fk_inv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_relations;
CREATE TABLE product_relations (
  product_id INT UNSIGNED NOT NULL,
  related_id INT UNSIGNED NOT NULL,
  relation_type ENUM('related','cross_sell','upsell') NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, related_id, relation_type),
  CONSTRAINT fk_rel_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_related FOREIGN KEY (related_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collections;
CREATE TABLE collections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(500) NULL,
  banner_image VARCHAR(500) NULL,
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  show_on_home TINYINT(1) NOT NULL DEFAULT 0,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collection_products;
CREATE TABLE collection_products (
  collection_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, product_id),
  KEY idx_cp_product (product_id),
  CONSTRAINT fk_cp_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  author_name VARCHAR(120) NOT NULL,
  author_city VARCHAR(80) NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(160) NULL,
  body TEXT NOT NULL,
  is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Show in homepage testimonials',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_reply TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_review_product (product_id, status),
  UNIQUE KEY uq_review_customer_product (customer_id, product_id),
  CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Cart & wishlist
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS carts;
CREATE TABLE carts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(48) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cart_customer (customer_id),
  CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  custom_measurements TEXT NULL,
  custom_color VARCHAR(80) NULL,
  personalization VARCHAR(120) NULL,
  custom_instructions TEXT NULL,
  custom_spec TEXT NULL COMMENT 'Custom Size Studio JSON, validated by studio_validate()',
  is_custom TINYINT(1) NOT NULL DEFAULT 0,
  custom_hash CHAR(32) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ci_cart (cart_id),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlists;
CREATE TABLE wishlists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(48) NULL UNIQUE,
  customer_id INT UNSIGNED NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlist_items;
CREATE TABLE wishlist_items (
  wishlist_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id, product_id),
  CONSTRAINT fk_wi_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_wi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Shipping, coupons, payment methods
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS shipping_zones;
CREATE TABLE shipping_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Used when no city/region matches',
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_zone_locations;
CREATE TABLE shipping_zone_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  location_type ENUM('city','region') NOT NULL,
  name VARCHAR(100) NOT NULL,
  KEY idx_szl_lookup (location_type, name),
  CONSTRAINT fk_szl_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_rates;
CREATE TABLE shipping_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  method ENUM('standard','express') NOT NULL DEFAULT 'standard',
  label VARCHAR(80) NOT NULL DEFAULT 'Standard Delivery',
  fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  free_above DECIMAL(12,2) NULL COMMENT 'Free delivery when subtotal >= this',
  min_order DECIMAL(12,2) NULL,
  days_min INT NOT NULL DEFAULT 2,
  days_max INT NOT NULL DEFAULT 5,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_zone_method (zone_id, method),
  CONSTRAINT fk_rate_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  discount_type ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  min_order DECIMAL(12,2) NULL,
  max_discount DECIMAL(12,2) NULL,
  usage_limit INT NULL,
  usage_limit_per_customer INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_methods;
CREATE TABLE payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  is_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin confirmed sandbox/live testing completed',
  mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  min_order DECIMAL(12,2) NULL,
  max_order DECIMAL(12,2) NULL,
  credentials_encrypted TEXT NULL COMMENT 'AES-256-GCM encrypted JSON, key in config/config.php',
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Orders & payments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  access_token CHAR(40) NOT NULL,
  idempotency_key CHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded','on_hold') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','pending','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
  payment_method VARCHAR(30) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PKR',
  subtotal DECIMAL(12,2) NOT NULL,
  customization_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_refunded DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  coupon_id INT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  shipping_zone VARCHAR(120) NULL,
  shipping_method VARCHAR(20) NOT NULL DEFAULT 'standard',
  estimated_delivery VARCHAR(80) NULL,
  customer_notes TEXT NULL,
  courier_name VARCHAR(80) NULL,
  tracking_number VARCHAR(80) NULL,
  tracking_url VARCHAR(500) NULL,
  cod_collected TINYINT(1) NOT NULL DEFAULT 0,
  stock_restored TINYINT(1) NOT NULL DEFAULT 0,
  has_custom_items TINYINT(1) NOT NULL DEFAULT 0,
  placed_ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  KEY idx_order_customer (customer_id),
  KEY idx_order_status (status),
  KEY idx_order_pay (payment_status, payment_method),
  KEY idx_order_created (created_at),
  KEY idx_order_email (email),
  KEY idx_order_phone (phone),
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable snapshot of what was bought, at the price it was bought for.
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  variant_label VARCHAR(190) NULL,
  image VARCHAR(500) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  regular_price DECIMAL(12,2) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  customization_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL,
  custom_measurements TEXT NULL,
  custom_color VARCHAR(80) NULL,
  personalization VARCHAR(120) NULL,
  custom_instructions TEXT NULL,
  custom_spec TEXT NULL COMMENT 'Snapshot of the Custom Size Studio specification',
  is_custom TINYINT(1) NOT NULL DEFAULT 0,
  made_to_order TINYINT(1) NOT NULL DEFAULT 0,
  lead_time_days INT NOT NULL DEFAULT 0,
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_addresses;
CREATE TABLE order_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  address_type ENUM('shipping','billing') NOT NULL DEFAULT 'shipping',
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  region VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Pakistan',
  KEY idx_oa_order (order_id),
  KEY idx_oa_city (city),
  CONSTRAINT fk_oa_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_status_history;
CREATE TABLE order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NULL,
  payment_status VARCHAR(30) NULL,
  note TEXT NULL,
  is_customer_visible TINYINT(1) NOT NULL DEFAULT 0,
  entry_type ENUM('status','payment','note','system') NOT NULL DEFAULT 'status',
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  method VARCHAR(30) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  gateway_reference VARCHAR(120) NULL COMMENT 'Reference we sent to the gateway',
  provider_txn_id VARCHAR(120) NULL COMMENT 'Gateway transaction id',
  refunded_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gateway_ref (gateway_reference),
  KEY idx_pay_order (order_id),
  KEY idx_pay_status (status),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw log of every gateway interaction, kept for reconciliation.
DROP TABLE IF EXISTS payment_transactions;
CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  gateway VARCHAR(30) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  status VARCHAR(30) NULL,
  amount DECIMAL(12,2) NULL,
  provider_txn_id VARCHAR(120) NULL,
  signature_valid TINYINT(1) NULL,
  payload TEXT NULL,
  message VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ptx_payment (payment_id),
  KEY idx_ptx_order (order_id),
  KEY idx_ptx_provider (gateway, provider_txn_id),
  CONSTRAINT fk_ptx_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_ptx_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS coupon_usage;
CREATE TABLE coupon_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cu_coupon (coupon_id, email),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Storefront content
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS homepage_sections;
CREATE TABLE homepage_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_type VARCHAR(40) NOT NULL,
  admin_label VARCHAR(120) NOT NULL,
  settings TEXT NULL COMMENT 'JSON, validated against the section schema in includes/sections.php',
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('published','draft','hidden') NOT NULL DEFAULT 'published',
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_hs_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS banner_slides;
CREATE TABLE banner_slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_desktop VARCHAR(500) NULL,
  image_mobile VARCHAR(500) NULL,
  eyebrow VARCHAR(120) NULL,
  heading VARCHAR(190) NULL,
  subheading VARCHAR(255) NULL,
  description TEXT NULL,
  btn1_text VARCHAR(60) NULL,
  btn1_url VARCHAR(500) NULL,
  btn2_text VARCHAR(60) NULL,
  btn2_url VARCHAR(500) NULL,
  text_align ENUM('left','center','right') NOT NULL DEFAULT 'left',
  text_position ENUM('top','middle','bottom') NOT NULL DEFAULT 'middle',
  text_color CHAR(7) NOT NULL DEFAULT '#FFFFFF',
  overlay_color CHAR(7) NOT NULL DEFAULT '#000000',
  overlay_opacity TINYINT UNSIGNED NOT NULL DEFAULT 35,
  height_desktop SMALLINT UNSIGNED NOT NULL DEFAULT 88 COMMENT 'vh',
  height_mobile SMALLINT UNSIGNED NOT NULL DEFAULT 78 COMMENT 'vh',
  image_position VARCHAR(30) NOT NULL DEFAULT 'center center',
  background_size ENUM('cover','contain') NOT NULL DEFAULT 'cover',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS navigation_items;
CREATE TABLE navigation_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu VARCHAR(30) NOT NULL DEFAULT 'header' COMMENT 'header, footer_shop, footer_help, footer_about',
  parent_id INT UNSIGNED NULL,
  label VARCHAR(80) NOT NULL,
  url VARCHAR(500) NULL,
  category_id INT UNSIGNED NULL COMMENT 'Mega menu: show this category tree',
  is_mega TINYINT(1) NOT NULL DEFAULT 0,
  mega_image VARCHAR(500) NULL,
  open_new_tab TINYINT(1) NOT NULL DEFAULT 0,
  highlight TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_nav_menu (menu, parent_id, sort_order),
  CONSTRAINT fk_nav_parent FOREIGN KEY (parent_id) REFERENCES navigation_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_nav_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS site_settings;
CREATE TABLE site_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value MEDIUMTEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pages;
CREATE TABLE pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  template ENUM('default','contact','faq','wide') NOT NULL DEFAULT 'default',
  excerpt VARCHAR(320) NULL,
  content MEDIUMTEXT NULL,
  banner_image VARCHAR(500) NULL,
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  consent_text VARCHAR(255) NULL,
  consent_at DATETIME NULL,
  consent_ip VARCHAR(45) NULL,
  unsubscribe_token CHAR(40) NOT NULL UNIQUE,
  source VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS redirects;
CREATE TABLE redirects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_path VARCHAR(255) NOT NULL UNIQUE,
  to_path VARCHAR(500) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS rate_limits;
CREATE TABLE rate_limits (
  rate_key VARCHAR(120) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Custom Size Studio (made-to-measure at ready-made price)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS custom_garments;
CREATE TABLE custom_garments (
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

DROP TABLE IF EXISTS custom_size_chart;
CREATE TABLE custom_size_chart (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garment_id INT UNSIGNED NOT NULL,
  size_label VARCHAR(10) NOT NULL,
  measure_key VARCHAR(40) NOT NULL,
  value DECIMAL(5,1) NOT NULL,
  UNIQUE KEY uq_chart (garment_id, size_label, measure_key),
  CONSTRAINT fk_chart_garment FOREIGN KEY (garment_id) REFERENCES custom_garments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS custom_size_fit;
CREATE TABLE custom_size_fit (
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

DROP TABLE IF EXISTS custom_style_options;
CREATE TABLE custom_style_options (
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

DROP TABLE IF EXISTS customer_measurements;
CREATE TABLE customer_measurements (
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
DROP TABLE IF EXISTS alteration_services;
CREATE TABLE alteration_services (
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

DROP TABLE IF EXISTS alteration_requests;
CREATE TABLE alteration_requests (
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

DROP TABLE IF EXISTS alteration_request_items;
CREATE TABLE alteration_request_items (
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

DROP TABLE IF EXISTS alteration_request_photos;
CREATE TABLE alteration_request_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  image VARCHAR(500) NOT NULL,
  KEY idx_arp_request (request_id),
  CONSTRAINT fk_arp_request FOREIGN KEY (request_id) REFERENCES alteration_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS alteration_status_history;
CREATE TABLE alteration_status_history (
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
DROP TABLE IF EXISTS urgent_delivery_rates;
CREATE TABLE urgent_delivery_rates (
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

SET FOREIGN_KEY_CHECKS = 1;
