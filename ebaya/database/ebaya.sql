-- Ebaya: complete install file (schema + sample data). Import this single file in phpMyAdmin.
-- =====================================================================
-- Ebaya — Premium Handcrafted Abaya Store
-- MySQL / MariaDB schema (InnoDB, utf8mb4)
-- Import ebaya.sql (schema + seed) through phpMyAdmin → Import.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Administration
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admin_roles;
CREATE TABLE admin_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_super TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_permissions;
CREATE TABLE admin_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perm_key VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  perm_group VARCHAR(60) NOT NULL DEFAULT 'General'
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
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_admin_role (role_id),
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
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS rate_limits;
CREATE TABLE rate_limits (
  rl_key VARCHAR(190) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customer_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_addresses;
CREATE TABLE customer_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  label VARCHAR(60) NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Pakistan',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
-- Catalogue
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  banner_image VARCHAR(255) NULL,
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  og_image VARCHAR(255) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  show_on_home TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cat_parent (parent_id),
  KEY idx_cat_status (status, sort_order),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attributes: size, color (used by variants) and descriptive facets
-- (sleeve_style, abaya_type, craft, occasion) used by filters.
DROP TABLE IF EXISTS attributes;
CREATE TABLE attributes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  is_variant TINYINT(1) NOT NULL DEFAULT 0,
  is_filterable TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS attribute_values;
CREATE TABLE attribute_values (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attribute_id INT UNSIGNED NOT NULL,
  value VARCHAR(80) NOT NULL,
  slug VARCHAR(90) NOT NULL,
  swatch_hex VARCHAR(7) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_attr_slug (attribute_id, slug),
  CONSTRAINT fk_av_attr FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS size_guides;
CREATE TABLE size_guides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  sku VARCHAR(80) NOT NULL UNIQUE,
  short_description VARCHAR(600) NULL,
  description MEDIUMTEXT NULL,
  regular_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NULL,
  category_id INT UNSIGNED NULL,
  subcategory_id INT UNSIGNED NULL,
  video_url VARCHAR(255) NULL,
  track_inventory TINYINT(1) NOT NULL DEFAULT 1,
  low_stock_threshold INT NOT NULL DEFAULT 3,
  weight_grams INT NULL,
  dimensions VARCHAR(80) NULL,
  fabric VARCHAR(190) NULL,
  material_details TEXT NULL,
  embroidery_type VARCHAR(190) NULL,
  crochet_details VARCHAR(255) NULL,
  sleeve_design VARCHAR(190) NULL,
  neckline_design VARCHAR(190) NULL,
  abaya_length VARCHAR(120) NULL,
  fit_silhouette VARCHAR(190) NULL,
  lining_info VARCHAR(190) NULL,
  transparency_info VARCHAR(190) NULL,
  care_instructions TEXT NULL,
  model_info VARCHAR(255) NULL,
  size_guide_id INT UNSIGNED NULL,
  dispatch_time VARCHAR(120) NULL,
  fulfillment_type ENUM('ready_to_ship','made_to_order') NOT NULL DEFAULT 'ready_to_ship',
  production_lead_days INT NOT NULL DEFAULT 0,
  allow_customization TINYINT(1) NOT NULL DEFAULT 0,
  custom_length_enabled TINYINT(1) NOT NULL DEFAULT 0,
  custom_length_min INT NULL,
  custom_length_max INT NULL,
  custom_sleeve_enabled TINYINT(1) NOT NULL DEFAULT 0,
  custom_sleeve_options VARCHAR(255) NULL,
  custom_notes_enabled TINYINT(1) NOT NULL DEFAULT 0,
  customization_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  custom_terms VARCHAR(500) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  is_limited TINYINT(1) NOT NULL DEFAULT 0,
  is_handcrafted TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published','inactive') NOT NULL DEFAULT 'draft',
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  og_title VARCHAR(190) NULL,
  og_description VARCHAR(320) NULL,
  og_image VARCHAR(255) NULL,
  canonical_url VARCHAR(255) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
  rating_count INT NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prod_status (status, published_at),
  KEY idx_prod_cat (category_id),
  KEY idx_prod_subcat (subcategory_id),
  KEY idx_prod_flags (is_new_arrival, is_best_seller, is_featured),
  FULLTEXT KEY ft_prod (name, short_description),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_subcat FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_sizeguide FOREIGN KEY (size_guide_id) REFERENCES size_guides(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional category assignments (primary category lives on products).
DROP TABLE IF EXISTS product_categories;
CREATE TABLE product_categories (
  product_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY idx_pc_cat (category_id),
  CONSTRAINT fk_pc_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_attribute_values;
CREATE TABLE product_attribute_values (
  product_id INT UNSIGNED NOT NULL,
  attribute_value_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, attribute_value_id),
  KEY idx_pav_value (attribute_value_id),
  CONSTRAINT fk_pav_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pav_val FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_images;
CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  color_value_id INT UNSIGNED NULL,
  path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(255) NULL,
  caption VARCHAR(255) NULL,
  view_type ENUM('front','back','side','detail','lifestyle','other') NOT NULL DEFAULT 'other',
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_img_prod (product_id, sort_order),
  CONSTRAINT fk_img_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_img_color FOREIGN KEY (color_value_id) REFERENCES attribute_values(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every product has at least one variant. A product without size/colour
-- options has a single default variant with no values.
DROP TABLE IF EXISTS product_variants;
CREATE TABLE product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(100) NOT NULL UNIQUE,
  price_override DECIMAL(12,2) NULL,
  sale_price_override DECIMAL(12,2) NULL,
  weight_grams INT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_var_prod (product_id),
  CONSTRAINT fk_var_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variant_values;
CREATE TABLE product_variant_values (
  variant_id INT UNSIGNED NOT NULL,
  attribute_value_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (variant_id, attribute_value_id),
  KEY idx_pvv_value (attribute_value_id),
  CONSTRAINT fk_pvv_var FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  CONSTRAINT fk_pvv_val FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_inventory;
CREATE TABLE product_inventory (
  variant_id INT UNSIGNED NOT NULL PRIMARY KEY,
  quantity INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_var FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS inventory_movements;
CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  balance_after INT NOT NULL,
  reason ENUM('initial','adjustment','order','order_cancel','return','restock','correction') NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_var (variant_id, created_at),
  CONSTRAINT fk_mov_var FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_relations;
CREATE TABLE product_relations (
  product_id INT UNSIGNED NOT NULL,
  related_id INT UNSIGNED NOT NULL,
  relation_type ENUM('related','cross_sell','upsell') NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, related_id, relation_type),
  CONSTRAINT fk_rel_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_related FOREIGN KEY (related_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collections;
CREATE TABLE collections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  banner_image VARCHAR(255) NULL,
  link_url VARCHAR(255) NULL,
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  show_on_home TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collection_products;
CREATE TABLE collection_products (
  collection_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, product_id),
  KEY idx_cp_prod (product_id),
  CONSTRAINT fk_cp_col FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(190) NULL,
  body TEXT NOT NULL,
  verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rev_prod (product_id, status),
  UNIQUE KEY uq_rev_customer (product_id, customer_id),
  CONSTRAINT fk_rev_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS testimonials;
CREATE TABLE testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(120) NULL,
  quote TEXT NOT NULL,
  rating TINYINT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  review_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_test_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_test_rev FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Carts and wishlists
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS carts;
CREATE TABLE carts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  coupon_code VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cart_customer (customer_id),
  CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  custom_length INT NULL,
  custom_sleeve VARCHAR(80) NULL,
  custom_notes VARCHAR(1000) NULL,
  custom_hash CHAR(40) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_line (cart_id, variant_id, custom_hash),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_var FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlists;
CREATE TABLE wishlists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlist_items;
CREATE TABLE wishlist_items (
  wishlist_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id, product_id),
  CONSTRAINT fk_wli_wl FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_wli_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Shipping
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS shipping_zones;
CREATE TABLE shipping_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  cod_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  cod_max_order DECIMAL(12,2) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_zone_locations;
CREATE TABLE shipping_zone_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  location_type ENUM('city','province') NOT NULL DEFAULT 'city',
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_zone_loc (location_type, name),
  CONSTRAINT fk_szl_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_rates;
CREATE TABLE shipping_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  method ENUM('standard','express') NOT NULL DEFAULT 'standard',
  label VARCHAR(120) NOT NULL,
  applies_to ENUM('all','ready_to_ship','made_to_order') NOT NULL DEFAULT 'all',
  rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  free_over DECIMAL(12,2) NULL,
  min_order DECIMAL(12,2) NULL,
  est_days_min INT NOT NULL DEFAULT 2,
  est_days_max INT NOT NULL DEFAULT 5,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  KEY idx_rate_zone (zone_id, method),
  CONSTRAINT fk_rate_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Coupons
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  type ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(12,2) NULL,
  max_discount DECIMAL(12,2) NULL,
  usage_limit INT NULL,
  per_customer_limit INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Orders and payments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  lookup_key CHAR(32) NOT NULL,
  checkout_token CHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  status ENUM('pending','confirmed','processing','in_production','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','pending','paid','failed','refunded','partially_refunded','cancelled') NOT NULL DEFAULT 'unpaid',
  payment_method VARCHAR(30) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PKR',
  subtotal DECIMAL(12,2) NOT NULL,
  customization_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  cod_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL,
  refunded_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(50) NULL,
  shipping_zone_id INT UNSIGNED NULL,
  shipping_method VARCHAR(30) NOT NULL DEFAULT 'standard',
  shipping_label VARCHAR(120) NULL,
  estimated_delivery VARCHAR(120) NULL,
  has_custom_items TINYINT(1) NOT NULL DEFAULT 0,
  custom_terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
  customer_note TEXT NULL,
  courier_name VARCHAR(120) NULL,
  tracking_number VARCHAR(120) NULL,
  tracking_url VARCHAR(255) NULL,
  cod_collected TINYINT(1) NOT NULL DEFAULT 0,
  cod_collected_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_order_customer (customer_id),
  KEY idx_order_status (status),
  KEY idx_order_pay (payment_status, payment_method),
  KEY idx_order_created (created_at),
  KEY idx_order_email (email),
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable snapshot of what was bought, at what price.
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  variant_label VARCHAR(190) NULL,
  image VARCHAR(255) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  regular_price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  customization_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  custom_length INT NULL,
  custom_sleeve VARCHAR(80) NULL,
  custom_notes VARCHAR(1000) NULL,
  fulfillment_type VARCHAR(20) NOT NULL DEFAULT 'ready_to_ship',
  lead_days INT NOT NULL DEFAULT 0,
  stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_var FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_addresses;
CREATE TABLE order_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  type ENUM('shipping','billing') NOT NULL DEFAULT 'shipping',
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Pakistan',
  UNIQUE KEY uq_order_addr (order_id, type),
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
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_notes;
CREATE TABLE order_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  is_customer_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_on_order (order_id),
  CONSTRAINT fk_on_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_on_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS coupon_usage;
CREATE TABLE coupon_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  discount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cu_order (order_id),
  KEY idx_cu_coupon_email (coupon_id, email),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gateway configuration. Secret values are AES-256-GCM encrypted with
-- APP_KEY from config/config.php and never sent to the browser.
DROP TABLE IF EXISTS payment_gateways;
CREATE TABLE payment_gateways (
  code VARCHAR(30) NOT NULL PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  tested TINYINT(1) NOT NULL DEFAULT 0,
  fee_flat DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_gateway_settings;
CREATE TABLE payment_gateway_settings (
  gateway_code VARCHAR(30) NOT NULL,
  setting_key VARCHAR(60) NOT NULL,
  setting_value TEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (gateway_code, setting_key),
  CONSTRAINT fk_pgs_gateway FOREIGN KEY (gateway_code) REFERENCES payment_gateways(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  method VARCHAR(30) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PKR',
  status ENUM('pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  gateway_reference VARCHAR(120) NULL UNIQUE,
  provider_txn_id VARCHAR(120) NULL,
  mode VARCHAR(10) NULL,
  failure_reason VARCHAR(255) NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_order (order_id),
  UNIQUE KEY uq_pay_provider (method, provider_txn_id),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_transactions;
CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  gateway VARCHAR(30) NOT NULL,
  event_type ENUM('initiate','return','callback','verify','refund','cod_collect','manual') NOT NULL,
  status VARCHAR(30) NOT NULL,
  amount DECIMAL(12,2) NULL,
  reference VARCHAR(120) NULL,
  signature_valid TINYINT(1) NULL,
  payload MEDIUMTEXT NULL,
  message VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pt_payment (payment_id),
  KEY idx_pt_order (order_id),
  KEY idx_pt_ref (gateway, reference),
  CONSTRAINT fk_pt_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_pt_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_pt_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Content, homepage builder, settings, SEO
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS site_settings;
CREATE TABLE site_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value MEDIUMTEXT NULL,
  draft_value MEDIUMTEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Layout/presentation config only (validated against a schema in
-- includes/homepage.php); never core commerce data.
DROP TABLE IF EXISTS homepage_sections;
CREATE TABLE homepage_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  settings MEDIUMTEXT NOT NULL,
  draft_settings MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS homepage_section_items;
CREATE TABLE homepage_section_items (
  section_id INT UNSIGNED NOT NULL,
  item_type ENUM('product','category','collection') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (section_id, item_type, item_id),
  CONSTRAINT fk_hsi_section FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS banner_slides;
CREATE TABLE banner_slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  desktop_image VARCHAR(255) NOT NULL,
  mobile_image VARCHAR(255) NULL,
  image_alt VARCHAR(255) NULL,
  heading VARCHAR(190) NULL,
  subheading VARCHAR(190) NULL,
  description VARCHAR(500) NULL,
  btn1_text VARCHAR(60) NULL,
  btn1_url VARCHAR(255) NULL,
  btn2_text VARCHAR(60) NULL,
  btn2_url VARCHAR(255) NULL,
  text_align ENUM('left','center','right') NOT NULL DEFAULT 'left',
  text_position ENUM('top','middle','bottom') NOT NULL DEFAULT 'middle',
  content_side ENUM('start','center','end') NOT NULL DEFAULT 'start',
  text_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
  overlay_color VARCHAR(7) NOT NULL DEFAULT '#332820',
  overlay_opacity TINYINT UNSIGNED NOT NULL DEFAULT 30,
  height_desktop SMALLINT UNSIGNED NOT NULL DEFAULT 760,
  height_mobile SMALLINT UNSIGNED NOT NULL DEFAULT 620,
  bg_position VARCHAR(30) NOT NULL DEFAULT 'center center',
  bg_size ENUM('cover','contain','auto') NOT NULL DEFAULT 'cover',
  parallax TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS navigation_items;
CREATE TABLE navigation_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu ENUM('header','footer_shop','footer_help','footer_about') NOT NULL DEFAULT 'header',
  parent_id INT UNSIGNED NULL,
  label VARCHAR(80) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  new_tab TINYINT(1) NOT NULL DEFAULT 0,
  text_color VARCHAR(7) NULL,
  KEY idx_nav_menu (menu, sort_order),
  CONSTRAINT fk_nav_parent FOREIGN KEY (parent_id) REFERENCES navigation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pages;
CREATE TABLE pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  subtitle VARCHAR(255) NULL,
  content MEDIUMTEXT NULL,
  banner_image VARCHAR(255) NULL,
  template ENUM('default','contact','faq','wide') NOT NULL DEFAULT 'default',
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  consent_text VARCHAR(255) NOT NULL,
  consent_at DATETIME NOT NULL,
  consent_ip VARCHAR(45) NULL,
  unsubscribe_token CHAR(64) NOT NULL UNIQUE,
  source VARCHAR(40) NULL,
  unsubscribed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS redirects;
CREATE TABLE redirects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_path VARCHAR(255) NOT NULL UNIQUE,
  to_path VARCHAR(255) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  is_auto TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS email_log;
CREATE TABLE email_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('sent','failed','skipped') NOT NULL,
  error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- Ebaya sample data. Safe to edit or delete from the admin panel.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO admin_roles (id, slug, name, description, is_super) VALUES
(1, 'super_admin', 'Super Admin', 'Complete control of the store', 1),
(2, 'store_manager', 'Store Manager', 'Products, categories, inventory, orders and customers', 0),
(3, 'order_manager', 'Order Manager', 'Order processing and fulfilment', 0),
(4, 'content_manager', 'Content Manager', 'Homepage, banners, collections and content pages', 0);

INSERT INTO admin_permissions (id, perm_key, label, perm_group) VALUES
(1, 'dashboard.view', 'View dashboard', 'General'),
(2, 'reports.export', 'Export data (CSV)', 'General'),
(3, 'products.manage', 'Manage products', 'Catalogue'),
(4, 'products.cost_view', 'View & edit cost prices', 'Catalogue'),
(5, 'categories.manage', 'Manage categories', 'Catalogue'),
(6, 'attributes.manage', 'Manage sizes, colours & attributes', 'Catalogue'),
(7, 'collections.manage', 'Manage collections', 'Catalogue'),
(8, 'inventory.manage', 'Manage inventory', 'Catalogue'),
(9, 'reviews.manage', 'Moderate reviews & testimonials', 'Catalogue'),
(10, 'orders.view', 'View orders', 'Sales'),
(11, 'orders.manage', 'Process orders', 'Sales'),
(12, 'orders.refund', 'Record refunds', 'Sales'),
(13, 'customers.manage', 'Manage customers', 'Sales'),
(14, 'coupons.manage', 'Manage coupons', 'Sales'),
(15, 'homepage.manage', 'Homepage, banners & navigation', 'Content'),
(16, 'content.manage', 'Content pages', 'Content'),
(17, 'newsletter.manage', 'Newsletter subscribers & messages', 'Content'),
(18, 'theme.manage', 'Theme settings', 'Settings'),
(19, 'settings.manage', 'Store settings', 'Settings'),
(20, 'seo.manage', 'SEO, redirects & analytics', 'Settings'),
(21, 'shipping.manage', 'Shipping & delivery', 'Settings'),
(22, 'payments.manage', 'Payment gateways', 'Settings'),
(23, 'admins.manage', 'Admin users & roles', 'Settings'),
(24, 'audit.view', 'View audit log', 'Settings');

INSERT INTO admin_role_permissions (role_id, permission_id) VALUES
(2, 1),
(2, 2),
(2, 3),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 13),
(2, 14),
(3, 1),
(3, 10),
(3, 11),
(3, 2),
(3, 13),
(4, 1),
(4, 15),
(4, 16),
(4, 7),
(4, 9),
(4, 17),
(4, 18);

INSERT INTO admins (id, role_id, name, email, password_hash, status, must_change_password) VALUES
(1, 1, 'Store Owner', 'admin@ebaya.test', '$2y$10$w2KzQMepN9bg7aWToh06uOfNQ4Q/0eYL.Gz./YzcVp9U3w2jfw6GC', 'active', 1);

INSERT INTO attributes (id, code, name, is_variant, is_filterable, sort_order) VALUES
(1, 'size', 'Size', 1, 1, 1),
(2, 'color', 'Colour', 1, 1, 2),
(3, 'sleeve_style', 'Sleeve style', 0, 1, 3),
(4, 'abaya_type', 'Abaya type', 0, 1, 4),
(5, 'craft', 'Craft & embellishment', 0, 1, 5),
(6, 'occasion', 'Occasion', 0, 1, 6);

INSERT INTO attribute_values (id, attribute_id, value, slug, swatch_hex, sort_order) VALUES
(1, 1, 'XS', 'xs', NULL, 1),
(2, 1, 'S', 's', NULL, 2),
(3, 1, 'M', 'm', NULL, 3),
(4, 1, 'L', 'l', NULL, 4),
(5, 1, 'XL', 'xl', NULL, 5),
(6, 1, 'XXL', 'xxl', NULL, 6),
(7, 1, '52"', '52in', NULL, 7),
(8, 1, '54"', '54in', NULL, 8),
(9, 1, '56"', '56in', NULL, 9),
(10, 1, '58"', '58in', NULL, 10),
(11, 1, '60"', '60in', NULL, 11),
(12, 2, 'Ivory', 'ivory', '#F1EBDD', 1),
(13, 2, 'Black', 'black', '#1E1C1B', 2),
(14, 2, 'Olive', 'olive', '#4B5A45', 3),
(15, 2, 'Sage', 'sage', '#A7B29A', 4),
(16, 2, 'Champagne', 'champagne', '#E2CFB4', 5),
(17, 2, 'Mocha', 'mocha', '#7A5D4A', 6),
(18, 2, 'Dusty Rose', 'dusty-rose', '#C9A3A0', 7),
(19, 2, 'Navy', 'navy', '#273049', 8),
(20, 2, 'Midnight', 'midnight', '#22252B', 9),
(21, 2, 'Stone', 'stone', '#CFC5B5', 10),
(22, 2, 'Espresso', 'espresso', '#4A3A2F', 11),
(23, 2, 'Pearl White', 'pearl-white', '#F4EFE6', 12),
(24, 3, 'Bell', 'bell', NULL, 1),
(25, 3, 'Straight', 'straight', NULL, 2),
(26, 3, 'Cuffed', 'cuffed', NULL, 3),
(27, 3, 'Bishop', 'bishop', NULL, 4),
(28, 3, 'Butterfly', 'butterfly', NULL, 5),
(29, 4, 'Closed', 'closed', NULL, 1),
(30, 4, 'Open-front', 'open-front', NULL, 2),
(31, 4, 'Kimono', 'kimono', NULL, 3),
(32, 4, 'Kaftan', 'kaftan', NULL, 4),
(33, 5, 'Crochet flowers', 'crochet-flowers', NULL, 1),
(34, 5, 'Hand embroidery', 'hand-embroidery', NULL, 2),
(35, 5, 'Pearl work', 'pearl-work', NULL, 3),
(36, 5, 'Threadwork', 'threadwork', NULL, 4),
(37, 5, 'Minimal', 'minimal', NULL, 5),
(38, 6, 'Everyday', 'everyday', NULL, 1),
(39, 6, 'Formal', 'formal', NULL, 2),
(40, 6, 'Festive & Eid', 'festive-and-eid', NULL, 3),
(41, 6, 'Wedding guest', 'wedding-guest', NULL, 4),
(42, 6, 'Travel', 'travel', NULL, 5);

INSERT INTO size_guides (id, name, content) VALUES
(1, 'Standard abaya sizing', '<p>Our abayas are designed for a graceful, relaxed fit. If you are between sizes, we suggest choosing the larger size.</p>
<table><thead><tr><th>Size</th><th>Bust (in)</th><th>Shoulder (in)</th><th>Sleeve (in)</th><th>Length (in)</th></tr></thead>
<tbody><tr><td>XS</td><td>36</td><td>14</td><td>22</td><td>52</td></tr><tr><td>S</td><td>38</td><td>14.5</td><td>22.5</td><td>54</td></tr>
<tr><td>M</td><td>40</td><td>15</td><td>23</td><td>56</td></tr><tr><td>L</td><td>42</td><td>15.5</td><td>23.5</td><td>57</td></tr>
<tr><td>XL</td><td>44</td><td>16</td><td>24</td><td>58</td></tr><tr><td>XXL</td><td>46</td><td>16.5</td><td>24</td><td>58</td></tr></tbody></table>
<h3>How to measure</h3><ul><li><strong>Bust:</strong> around the fullest part of the chest.</li><li><strong>Length:</strong> from the highest point of the shoulder to where you want the hem to fall.</li><li><strong>Sleeve:</strong> from shoulder seam to wrist with the arm relaxed.</li></ul>
<p>Need a custom length? Many styles offer made-to-order lengths — look for “Personalise this piece” on the product page.</p>');

INSERT INTO categories (id, parent_id, name, slug, description, image, banner_image, show_on_home, sort_order, seo_title, meta_description, status) VALUES
(1, NULL, 'Everyday Abayas', 'everyday-abayas', 'Effortless, comfortable pieces with quiet detail — made for the rhythm of daily life.', 'assets/img/sample/cat-everyday.svg', 'assets/img/sample/hero-1.svg', 1, 1, 'Everyday Abayas | Handcrafted Abayas', 'Effortless, comfortable pieces with quiet detail — made for the rhythm of daily life.', 'active'),
(2, NULL, 'Hand-Embroidered Abayas', 'hand-embroidered-abayas', 'Floral motifs, vines and borders embroidered by hand, stitch by stitch.', 'assets/img/sample/cat-embroidered.svg', 'assets/img/sample/hero-3.svg', 1, 2, 'Hand-Embroidered Abayas | Handcrafted Abayas', 'Floral motifs, vines and borders embroidered by hand, stitch by stitch.', 'active'),
(3, NULL, 'Crochet Flower Abayas', 'crochet-flower-abayas', 'Abayas adorned with handmade crochet flowers along necklines, sleeves and fronts.', 'assets/img/sample/cat-crochet.svg', 'assets/img/sample/hero-1.svg', 1, 3, 'Crochet Flower Abayas | Handcrafted Abayas', 'Abayas adorned with handmade crochet flowers along necklines, sleeves and fronts.', 'active'),
(4, NULL, 'Luxury Occasion Wear', 'luxury-occasion-wear', 'Elevated designs for formal evenings, Eid and celebrations.', 'assets/img/sample/cat-occasion.svg', 'assets/img/sample/hero-2.svg', 1, 4, 'Luxury Occasion Wear | Handcrafted Abayas', 'Elevated designs for formal evenings, Eid and celebrations.', 'active'),
(5, NULL, 'Open Abayas', 'open-abayas', 'Open-front and kimono silhouettes that layer beautifully.', 'assets/img/sample/cat-open.svg', 'assets/img/sample/hero-3.svg', 1, 5, 'Open Abayas | Handcrafted Abayas', 'Open-front and kimono silhouettes that layer beautifully.', 'active'),
(6, NULL, 'Limited Edition Collection', 'limited-edition-collection', 'Small-batch pieces — once they are gone, they are gone.', 'assets/img/sample/cat-limited.svg', 'assets/img/sample/hero-2.svg', 1, 6, 'Limited Edition Collection | Handcrafted Abayas', 'Small-batch pieces — once they are gone, they are gone.', 'active'),
(7, 3, 'Floral Crochet Designs', 'floral-crochet-designs', 'Garden-inspired crochet blooms.', 'assets/img/sample/detail-crochet.svg', NULL, 0, 1, 'Floral Crochet Designs | Handcrafted Abayas', 'Garden-inspired crochet blooms.', 'active'),
(8, 2, 'Pearl Embroidery', 'pearl-embroidery', 'Hand-set pearls and beadwork.', 'assets/img/sample/detail-neckline.svg', NULL, 0, 1, 'Pearl Embroidery | Handcrafted Abayas', 'Hand-set pearls and beadwork.', 'active'),
(9, 2, 'Sleeve Embroidery', 'sleeve-embroidery', 'Statement embroidered sleeves and cuffs.', 'assets/img/sample/detail-cuff.svg', NULL, 0, 2, 'Sleeve Embroidery | Handcrafted Abayas', 'Statement embroidered sleeves and cuffs.', 'active'),
(10, 5, 'Open-Front Styles', 'open-front-styles', 'Kimono and open-front abayas.', 'assets/img/sample/cat-open.svg', NULL, 0, 1, 'Open-Front Styles | Handcrafted Abayas', 'Kimono and open-front abayas.', 'active'),
(11, 4, 'Formal Abayas', 'formal-abayas', 'Refined silhouettes for formal occasions.', 'assets/img/sample/cat-occasion.svg', NULL, 0, 1, 'Formal Abayas | Handcrafted Abayas', 'Refined silhouettes for formal occasions.', 'active'),
(12, 4, 'Eid & Festive', 'eid-and-festive', 'Celebration-ready pieces.', 'assets/img/sample/detail-thread.svg', NULL, 0, 2, 'Eid & Festive | Handcrafted Abayas', 'Celebration-ready pieces.', 'active');

INSERT INTO products (id, name, slug, sku, short_description, description, regular_price, sale_price, cost_price, category_id, subcategory_id, track_inventory, low_stock_threshold, weight_grams, fabric, material_details, embroidery_type, crochet_details, sleeve_design, neckline_design, abaya_length, fit_silhouette, lining_info, transparency_info, care_instructions, model_info, size_guide_id, dispatch_time, fulfillment_type, production_lead_days, allow_customization, custom_length_enabled, custom_length_min, custom_length_max, custom_sleeve_enabled, custom_sleeve_options, custom_notes_enabled, customization_fee, is_featured, is_new_arrival, is_best_seller, is_popular, is_limited, is_handcrafted, status, seo_title, meta_description) VALUES
(1, 'Ivory Crochet Flower Abaya', 'ivory-crochet-flower-abaya', 'EB-ICF-001', 'A soft ivory abaya framed with handmade crochet flowers — delicate, luminous and quietly special.', '<p>A soft ivory abaya framed with handmade crochet flowers — delicate, luminous and quietly special.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 24500, NULL, 11025, 3, 7, 1, 3, 650, 'Soft crepe', 'Sample product data — please replace with your verified fabric and material details before launch.', NULL, 'Handmade crochet flowers along the neckline, front placket and sleeve hems', 'Bell sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 1, 1, 1, 0, 0, 1, 'published', 'Ivory Crochet Flower Abaya | Ebaya', 'A soft ivory abaya framed with handmade crochet flowers — delicate, luminous and quietly special.'),
(2, 'Sage Garden Embroidered Abaya', 'sage-garden-embroidered-abaya', 'EB-SGE-002', 'Sage green with hand-embroidered garden vines climbing the front and cuffs.', '<p>Sage green with hand-embroidered garden vines climbing the front and cuffs.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 28900, 25900, 13005, 2, 9, 1, 3, 670, 'Lightweight linen-blend', NULL, 'Hand-embroidered floral vines on front panels and cuffs', NULL, 'Cuffed sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 0, 1, 0, 1, 0, 1, 'published', 'Sage Garden Embroidered Abaya | Ebaya', 'Sage green with hand-embroidered garden vines climbing the front and cuffs.'),
(3, 'Olive Bloom Open Abaya', 'olive-bloom-open-abaya', 'EB-OBO-003', 'An open-front layer in deep olive, edged with a trail of crochet blooms.', '<p>An open-front layer in deep olive, edged with a trail of crochet blooms.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 22500, NULL, 10125, 5, 10, 1, 3, 690, 'Fluid crepe', NULL, NULL, 'Crochet flower trim along the open front', 'Bell sleeves', 'Open front with shawl collar', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 0, 1, 1, 0, 0, 1, 'published', 'Olive Bloom Open Abaya | Ebaya', 'An open-front layer in deep olive, edged with a trail of crochet blooms.'),
(4, 'Midnight Pearl Occasion Abaya', 'midnight-pearl-occasion-abaya', 'EB-MPO-004', 'A midnight occasion abaya with hand-set pearls tracing the neckline and butterfly sleeves.', '<p>A midnight occasion abaya with hand-set pearls tracing the neckline and butterfly sleeves.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 42000, NULL, 18900, 4, 8, 0, 3, 710, 'Satin-finish crepe with lining', NULL, 'Hand-set pearl work along neckline and sleeves', NULL, 'Butterfly sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Fully lined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Made to order — dispatched within 14 working days', 'made_to_order', 14, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 1, 0, 1, 0, 0, 1, 'published', 'Midnight Pearl Occasion Abaya | Ebaya', 'A midnight occasion abaya with hand-set pearls tracing the neckline and butterfly sleeves.'),
(5, 'Champagne Threadwork Abaya', 'champagne-threadwork-abaya', 'EB-CTA-005', 'Tonal champagne threadwork on the yoke and gathered bishop sleeves.', '<p>Tonal champagne threadwork on the yoke and gathered bishop sleeves.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 31500, NULL, 14175, 2, NULL, 1, 3, 730, 'Textured crepe', NULL, 'Tonal threadwork embroidery on yoke and bishop sleeves', NULL, 'Bishop sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 0, 0, 0, 1, 0, 1, 'published', 'Champagne Threadwork Abaya | Ebaya', 'Tonal champagne threadwork on the yoke and gathered bishop sleeves.'),
(6, 'Espresso Everyday Abaya', 'espresso-everyday-abaya', 'EB-EEA-006', 'An easy everyday abaya in rich espresso with a fine contrast stitch at the neckline.', '<p>An easy everyday abaya in rich espresso with a fine contrast stitch at the neckline.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 12500, 10900, 5625, 1, NULL, 1, 3, 750, 'Easy-care crepe', NULL, NULL, NULL, 'Straight sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 0, 0, 1, 0, 0, 0, 'published', 'Espresso Everyday Abaya | Ebaya', 'An easy everyday abaya in rich espresso with a fine contrast stitch at the neckline.'),
(7, 'Rose Dust Crochet Kimono', 'rose-dust-crochet-kimono', 'EB-RDC-007', 'A dusty-rose kimono abaya with crochet flowers scattered across airy sleeves.', '<p>A dusty-rose kimono abaya with crochet flowers scattered across airy sleeves.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 21500, NULL, 9675, 5, 10, 1, 3, 770, 'Airy georgette', NULL, NULL, 'Crochet flowers scattered across the kimono sleeves', 'Butterfly sleeves', 'Round neckline', '56–58 in (varies by size)', 'Flowing kimono', 'Unlined', 'Light layering piece; wear over a slip', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 0, 1, 0, 0, 0, 1, 'published', 'Rose Dust Crochet Kimono | Ebaya', 'A dusty-rose kimono abaya with crochet flowers scattered across airy sleeves.'),
(8, 'Black Floral Sleeve Abaya', 'black-floral-sleeve-abaya', 'EB-BFS-008', 'Classic black, transformed by embroidered floral sleeves and crochet-flower cuffs.', '<p>Classic black, transformed by embroidered floral sleeves and crochet-flower cuffs.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 26500, NULL, 11925, 2, 9, 1, 3, 790, 'Premium crepe', NULL, 'Floral embroidery with gold-tone thread on sleeves', 'Crochet flower accents on cuffs', 'Bell sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 0, 0, 1, 1, 0, 1, 'published', 'Black Floral Sleeve Abaya | Ebaya', 'Classic black, transformed by embroidered floral sleeves and crochet-flower cuffs.'),
(9, 'Mocha Statement Sleeve Abaya', 'mocha-statement-sleeve-abaya', 'EB-MSS-009', 'A warm mocha abaya with embroidered statement sleeves.', '<p>A warm mocha abaya with embroidered statement sleeves.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 18900, NULL, 8505, 1, NULL, 1, 3, 810, 'Soft crepe', NULL, 'Embroidered sleeve panels', NULL, 'Bishop sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 0, 1, 0, 0, 0, 0, 'published', 'Mocha Statement Sleeve Abaya | Ebaya', 'A warm mocha abaya with embroidered statement sleeves.'),
(10, 'Pearl White Eid Abaya', 'pearl-white-eid-abaya', 'EB-PWE-010', 'An open pearl-white abaya with hand-embroidered pearl edges, made to order for celebrations.', '<p>An open pearl-white abaya with hand-embroidered pearl edges, made to order for celebrations.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 36500, NULL, 16425, 4, 12, 0, 3, 830, 'Lined crepe', NULL, 'Pearl and bead embroidery along the front edges', NULL, 'Bell sleeves', 'Open front with shawl collar', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Made to order — dispatched within 10 working days', 'made_to_order', 10, 1, 1, 50, 64, 1, 'Standard,Fuller bell,Cuffed,Longer sleeve', 1, 1500, 1, 0, 0, 0, 1, 1, 'published', 'Pearl White Eid Abaya | Ebaya', 'An open pearl-white abaya with hand-embroidered pearl edges, made to order for celebrations.'),
(11, 'Navy Heirloom Limited Abaya', 'navy-heirloom-limited-abaya', 'EB-NHL-011', 'A limited heirloom piece in deep navy — all-over antique-gold embroidery with crochet blossoms.', '<p>A limited heirloom piece in deep navy — all-over antique-gold embroidery with crochet blossoms.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 45000, NULL, 20250, 6, NULL, 1, 3, 850, 'Heavyweight crepe with lining', NULL, 'All-over floral embroidery in antique gold tones', 'Crochet blossoms at neckline', 'Cuffed sleeves', 'Round neckline', '56–58 in (varies by size)', 'Relaxed A-line', 'Fully lined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', 'Model is 5''6" and wears size S.', 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 1, 0, 0, 0, 1, 1, 'published', 'Navy Heirloom Limited Abaya | Ebaya', 'A limited heirloom piece in deep navy — all-over antique-gold embroidery with crochet blossoms.'),
(12, 'Stone Minimal Open Abaya', 'stone-minimal-open-abaya', 'EB-SMO-012', 'A clean, minimal open abaya in stone with a fine sage piping detail.', '<p>A clean, minimal open abaya in stone with a fine sage piping detail.</p><p>Designed with a fluid, modest silhouette and finished with careful handwork, this piece moves easily from day to evening. Each handcrafted detail may vary very slightly — part of the character of handmade work.</p>', 14500, NULL, 6525, 5, 10, 1, 3, 870, 'Linen-look crepe', NULL, NULL, NULL, 'Straight sleeves', 'Open front with shawl collar', '56–58 in (varies by size)', 'Relaxed A-line', 'Unlined', 'Opaque in natural light', 'Dry clean recommended for embellished pieces.
If hand washing, use cold water and a gentle detergent; do not wring.
Steam or iron on low heat from the reverse, avoiding embroidery and crochet details.
Store on a padded hanger away from direct sunlight.', NULL, 1, 'Dispatched within 1–2 working days', 'ready_to_ship', 0, 0, 0, NULL, NULL, 0, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 'published', 'Stone Minimal Open Abaya | Ebaya', 'A clean, minimal open abaya in stone with a fine sage piping detail.');

UPDATE products SET published_at = DATE_SUB(NOW(), INTERVAL (20 - id) DAY);

INSERT INTO product_categories (product_id, category_id) VALUES
(1, 7),
(2, 9),
(3, 10),
(4, 8),
(7, 10),
(8, 9),
(10, 12),
(12, 10),
(3, 3),
(7, 3),
(8, 3),
(11, 3),
(5, 2),
(8, 2),
(9, 2),
(11, 2),
(10, 4),
(11, 4),
(5, 4),
(3, 1),
(12, 1),
(7, 1),
(10, 5),
(4, 6);

INSERT INTO product_attribute_values (product_id, attribute_value_id) VALUES
(1, 33),
(1, 24),
(1, 29),
(1, 39),
(1, 40),
(2, 34),
(2, 26),
(2, 29),
(2, 39),
(2, 41),
(3, 33),
(3, 24),
(3, 30),
(3, 38),
(3, 42),
(4, 35),
(4, 28),
(4, 29),
(4, 39),
(4, 41),
(5, 36),
(5, 34),
(5, 27),
(5, 29),
(5, 40),
(5, 39),
(6, 37),
(6, 25),
(6, 29),
(6, 38),
(6, 42),
(7, 33),
(7, 28),
(7, 31),
(7, 38),
(7, 40),
(8, 34),
(8, 33),
(8, 24),
(8, 29),
(8, 39),
(8, 38),
(9, 34),
(9, 27),
(9, 29),
(9, 38),
(9, 39),
(10, 35),
(10, 24),
(10, 30),
(10, 40),
(10, 41),
(11, 34),
(11, 33),
(11, 26),
(11, 29),
(11, 39),
(11, 41),
(12, 37),
(12, 25),
(12, 30),
(12, 38),
(12, 42);

INSERT INTO product_images (product_id, path, alt_text, caption, view_type, is_main, sort_order) VALUES
(1, 'assets/img/sample/ivory-crochet-flower-abaya-front.svg', 'Ivory Crochet Flower Abaya — front view', NULL, 'front', 1, 1),
(1, 'assets/img/sample/ivory-crochet-flower-abaya-back.svg', 'Ivory Crochet Flower Abaya — back view', NULL, 'back', 0, 2),
(1, 'assets/img/sample/ivory-crochet-flower-abaya-detail.svg', 'Ivory Crochet Flower Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(2, 'assets/img/sample/sage-garden-embroidered-abaya-front.svg', 'Sage Garden Embroidered Abaya — front view', NULL, 'front', 1, 1),
(2, 'assets/img/sample/sage-garden-embroidered-abaya-back.svg', 'Sage Garden Embroidered Abaya — back view', NULL, 'back', 0, 2),
(2, 'assets/img/sample/sage-garden-embroidered-abaya-detail.svg', 'Sage Garden Embroidered Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(3, 'assets/img/sample/olive-bloom-open-abaya-front.svg', 'Olive Bloom Open Abaya — front view', NULL, 'front', 1, 1),
(3, 'assets/img/sample/olive-bloom-open-abaya-back.svg', 'Olive Bloom Open Abaya — back view', NULL, 'back', 0, 2),
(3, 'assets/img/sample/olive-bloom-open-abaya-detail.svg', 'Olive Bloom Open Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(4, 'assets/img/sample/midnight-pearl-occasion-abaya-front.svg', 'Midnight Pearl Occasion Abaya — front view', NULL, 'front', 1, 1),
(4, 'assets/img/sample/midnight-pearl-occasion-abaya-back.svg', 'Midnight Pearl Occasion Abaya — back view', NULL, 'back', 0, 2),
(4, 'assets/img/sample/midnight-pearl-occasion-abaya-detail.svg', 'Midnight Pearl Occasion Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(5, 'assets/img/sample/champagne-threadwork-abaya-front.svg', 'Champagne Threadwork Abaya — front view', NULL, 'front', 1, 1),
(5, 'assets/img/sample/champagne-threadwork-abaya-back.svg', 'Champagne Threadwork Abaya — back view', NULL, 'back', 0, 2),
(5, 'assets/img/sample/champagne-threadwork-abaya-detail.svg', 'Champagne Threadwork Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(6, 'assets/img/sample/espresso-everyday-abaya-front.svg', 'Espresso Everyday Abaya — front view', NULL, 'front', 1, 1),
(6, 'assets/img/sample/espresso-everyday-abaya-back.svg', 'Espresso Everyday Abaya — back view', NULL, 'back', 0, 2),
(6, 'assets/img/sample/espresso-everyday-abaya-detail.svg', 'Espresso Everyday Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(7, 'assets/img/sample/rose-dust-crochet-kimono-front.svg', 'Rose Dust Crochet Kimono — front view', NULL, 'front', 1, 1),
(7, 'assets/img/sample/rose-dust-crochet-kimono-back.svg', 'Rose Dust Crochet Kimono — back view', NULL, 'back', 0, 2),
(7, 'assets/img/sample/rose-dust-crochet-kimono-detail.svg', 'Rose Dust Crochet Kimono — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(8, 'assets/img/sample/black-floral-sleeve-abaya-front.svg', 'Black Floral Sleeve Abaya — front view', NULL, 'front', 1, 1),
(8, 'assets/img/sample/black-floral-sleeve-abaya-back.svg', 'Black Floral Sleeve Abaya — back view', NULL, 'back', 0, 2),
(8, 'assets/img/sample/black-floral-sleeve-abaya-detail.svg', 'Black Floral Sleeve Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(9, 'assets/img/sample/mocha-statement-sleeve-abaya-front.svg', 'Mocha Statement Sleeve Abaya — front view', NULL, 'front', 1, 1),
(9, 'assets/img/sample/mocha-statement-sleeve-abaya-back.svg', 'Mocha Statement Sleeve Abaya — back view', NULL, 'back', 0, 2),
(9, 'assets/img/sample/mocha-statement-sleeve-abaya-detail.svg', 'Mocha Statement Sleeve Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(10, 'assets/img/sample/pearl-white-eid-abaya-front.svg', 'Pearl White Eid Abaya — front view', NULL, 'front', 1, 1),
(10, 'assets/img/sample/pearl-white-eid-abaya-back.svg', 'Pearl White Eid Abaya — back view', NULL, 'back', 0, 2),
(10, 'assets/img/sample/pearl-white-eid-abaya-detail.svg', 'Pearl White Eid Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(11, 'assets/img/sample/navy-heirloom-limited-abaya-front.svg', 'Navy Heirloom Limited Abaya — front view', NULL, 'front', 1, 1),
(11, 'assets/img/sample/navy-heirloom-limited-abaya-back.svg', 'Navy Heirloom Limited Abaya — back view', NULL, 'back', 0, 2),
(11, 'assets/img/sample/navy-heirloom-limited-abaya-detail.svg', 'Navy Heirloom Limited Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3),
(12, 'assets/img/sample/stone-minimal-open-abaya-front.svg', 'Stone Minimal Open Abaya — front view', NULL, 'front', 1, 1),
(12, 'assets/img/sample/stone-minimal-open-abaya-back.svg', 'Stone Minimal Open Abaya — back view', NULL, 'back', 0, 2),
(12, 'assets/img/sample/stone-minimal-open-abaya-detail.svg', 'Stone Minimal Open Abaya — close-up of the handcrafted detail', 'Close-up of the handwork', 'detail', 0, 3);

INSERT INTO product_variants (id, product_id, sku, price_override, sale_price_override, is_default, status, sort_order) VALUES
(1, 1, 'EB-ICF-001-IVO-XS', NULL, NULL, 0, 'active', 0),
(2, 1, 'EB-ICF-001-IVO-S', NULL, NULL, 1, 'active', 1),
(3, 1, 'EB-ICF-001-IVO-M', NULL, NULL, 0, 'active', 2),
(4, 1, 'EB-ICF-001-IVO-L', NULL, NULL, 0, 'active', 3),
(5, 1, 'EB-ICF-001-IVO-XL', NULL, NULL, 0, 'active', 4),
(6, 1, 'EB-ICF-001-IVO-XXL', NULL, NULL, 0, 'active', 5),
(7, 1, 'EB-ICF-001-CHA-XS', NULL, NULL, 0, 'active', 10),
(8, 1, 'EB-ICF-001-CHA-S', NULL, NULL, 0, 'active', 11),
(9, 1, 'EB-ICF-001-CHA-M', NULL, NULL, 0, 'active', 12),
(10, 1, 'EB-ICF-001-CHA-L', NULL, NULL, 0, 'active', 13),
(11, 1, 'EB-ICF-001-CHA-XL', NULL, NULL, 0, 'active', 14),
(12, 1, 'EB-ICF-001-CHA-XXL', NULL, NULL, 0, 'active', 15),
(13, 2, 'EB-SGE-002-SAG-S', NULL, NULL, 0, 'active', 0),
(14, 2, 'EB-SGE-002-SAG-M', NULL, NULL, 1, 'active', 1),
(15, 2, 'EB-SGE-002-SAG-L', NULL, NULL, 0, 'active', 2),
(16, 2, 'EB-SGE-002-SAG-XL', NULL, NULL, 0, 'active', 3),
(17, 2, 'EB-SGE-002-IVO-S', NULL, NULL, 0, 'active', 10),
(18, 2, 'EB-SGE-002-IVO-M', NULL, NULL, 0, 'active', 11),
(19, 2, 'EB-SGE-002-IVO-L', NULL, NULL, 0, 'active', 12),
(20, 2, 'EB-SGE-002-IVO-XL', NULL, NULL, 0, 'active', 13),
(21, 3, 'EB-OBO-003-OLI-S', NULL, NULL, 0, 'active', 0),
(22, 3, 'EB-OBO-003-OLI-M', NULL, NULL, 1, 'active', 1),
(23, 3, 'EB-OBO-003-OLI-L', NULL, NULL, 0, 'active', 2),
(24, 3, 'EB-OBO-003-OLI-XL', NULL, NULL, 0, 'active', 3),
(25, 3, 'EB-OBO-003-BLA-S', NULL, NULL, 0, 'active', 10),
(26, 3, 'EB-OBO-003-BLA-M', NULL, NULL, 0, 'active', 11),
(27, 3, 'EB-OBO-003-BLA-L', NULL, NULL, 0, 'active', 12),
(28, 3, 'EB-OBO-003-BLA-XL', NULL, NULL, 0, 'active', 13),
(29, 4, 'EB-MPO-004-MID-XS', NULL, NULL, 0, 'active', 0),
(30, 4, 'EB-MPO-004-MID-S', NULL, NULL, 1, 'active', 1),
(31, 4, 'EB-MPO-004-MID-M', NULL, NULL, 0, 'active', 2),
(32, 4, 'EB-MPO-004-MID-L', NULL, NULL, 0, 'active', 3),
(33, 4, 'EB-MPO-004-MID-XL', NULL, NULL, 0, 'active', 4),
(34, 4, 'EB-MPO-004-MID-XXL', NULL, NULL, 0, 'active', 5),
(35, 5, 'EB-CTA-005-CHA-S', NULL, NULL, 0, 'active', 0),
(36, 5, 'EB-CTA-005-CHA-M', NULL, NULL, 1, 'active', 1),
(37, 5, 'EB-CTA-005-CHA-L', NULL, NULL, 0, 'active', 2),
(38, 5, 'EB-CTA-005-CHA-XL', NULL, NULL, 0, 'active', 3),
(39, 6, 'EB-EEA-006-ESP-S', NULL, NULL, 0, 'active', 0),
(40, 6, 'EB-EEA-006-ESP-M', NULL, NULL, 1, 'active', 1),
(41, 6, 'EB-EEA-006-ESP-L', NULL, NULL, 0, 'active', 2),
(42, 6, 'EB-EEA-006-ESP-XL', NULL, NULL, 0, 'active', 3),
(43, 6, 'EB-EEA-006-BLA-S', NULL, NULL, 0, 'active', 10),
(44, 6, 'EB-EEA-006-BLA-M', NULL, NULL, 0, 'active', 11),
(45, 6, 'EB-EEA-006-BLA-L', NULL, NULL, 0, 'active', 12),
(46, 6, 'EB-EEA-006-BLA-XL', NULL, NULL, 0, 'active', 13),
(47, 6, 'EB-EEA-006-STO-S', NULL, NULL, 0, 'active', 20),
(48, 6, 'EB-EEA-006-STO-M', NULL, NULL, 0, 'active', 21),
(49, 6, 'EB-EEA-006-STO-L', NULL, NULL, 0, 'active', 22),
(50, 6, 'EB-EEA-006-STO-XL', NULL, NULL, 0, 'active', 23),
(51, 7, 'EB-RDC-007-DUS-XS', NULL, NULL, 0, 'active', 0),
(52, 7, 'EB-RDC-007-DUS-S', NULL, NULL, 1, 'active', 1),
(53, 7, 'EB-RDC-007-DUS-M', NULL, NULL, 0, 'active', 2),
(54, 7, 'EB-RDC-007-DUS-L', NULL, NULL, 0, 'active', 3),
(55, 7, 'EB-RDC-007-DUS-XL', NULL, NULL, 0, 'active', 4),
(56, 7, 'EB-RDC-007-DUS-XXL', NULL, NULL, 0, 'active', 5),
(57, 8, 'EB-BFS-008-BLA-S', NULL, NULL, 0, 'active', 0),
(58, 8, 'EB-BFS-008-BLA-M', NULL, NULL, 1, 'active', 1),
(59, 8, 'EB-BFS-008-BLA-L', NULL, NULL, 0, 'active', 2),
(60, 8, 'EB-BFS-008-BLA-XL', NULL, NULL, 0, 'active', 3),
(61, 9, 'EB-MSS-009-MOC-S', NULL, NULL, 0, 'active', 0),
(62, 9, 'EB-MSS-009-MOC-M', NULL, NULL, 1, 'active', 1),
(63, 9, 'EB-MSS-009-MOC-L', NULL, NULL, 0, 'active', 2),
(64, 9, 'EB-MSS-009-MOC-XL', NULL, NULL, 0, 'active', 3),
(65, 9, 'EB-MSS-009-ESP-S', NULL, NULL, 0, 'active', 10),
(66, 9, 'EB-MSS-009-ESP-M', NULL, NULL, 0, 'active', 11),
(67, 9, 'EB-MSS-009-ESP-L', NULL, NULL, 0, 'active', 12),
(68, 9, 'EB-MSS-009-ESP-XL', NULL, NULL, 0, 'active', 13),
(69, 10, 'EB-PWE-010-PEA-XS', NULL, NULL, 0, 'active', 0),
(70, 10, 'EB-PWE-010-PEA-S', NULL, NULL, 1, 'active', 1),
(71, 10, 'EB-PWE-010-PEA-M', NULL, NULL, 0, 'active', 2),
(72, 10, 'EB-PWE-010-PEA-L', NULL, NULL, 0, 'active', 3),
(73, 10, 'EB-PWE-010-PEA-XL', NULL, NULL, 0, 'active', 4),
(74, 10, 'EB-PWE-010-PEA-XXL', NULL, NULL, 0, 'active', 5),
(75, 10, 'EB-PWE-010-IVO-XS', NULL, NULL, 0, 'active', 10),
(76, 10, 'EB-PWE-010-IVO-S', NULL, NULL, 0, 'active', 11),
(77, 10, 'EB-PWE-010-IVO-M', NULL, NULL, 0, 'active', 12),
(78, 10, 'EB-PWE-010-IVO-L', NULL, NULL, 0, 'active', 13),
(79, 10, 'EB-PWE-010-IVO-XL', NULL, NULL, 0, 'active', 14),
(80, 10, 'EB-PWE-010-IVO-XXL', NULL, NULL, 0, 'active', 15),
(81, 11, 'EB-NHL-011-NAV-S', NULL, NULL, 0, 'active', 0),
(82, 11, 'EB-NHL-011-NAV-M', NULL, NULL, 1, 'active', 1),
(83, 11, 'EB-NHL-011-NAV-L', NULL, NULL, 0, 'active', 2),
(84, 11, 'EB-NHL-011-NAV-XL', NULL, NULL, 0, 'active', 3),
(85, 12, 'EB-SMO-012-STO-S', NULL, NULL, 0, 'active', 0),
(86, 12, 'EB-SMO-012-STO-M', NULL, NULL, 1, 'active', 1),
(87, 12, 'EB-SMO-012-STO-L', NULL, NULL, 0, 'active', 2),
(88, 12, 'EB-SMO-012-STO-XL', NULL, NULL, 0, 'active', 3),
(89, 12, 'EB-SMO-012-SAG-S', NULL, NULL, 0, 'active', 10),
(90, 12, 'EB-SMO-012-SAG-M', NULL, NULL, 0, 'active', 11),
(91, 12, 'EB-SMO-012-SAG-L', NULL, NULL, 0, 'active', 12),
(92, 12, 'EB-SMO-012-SAG-XL', NULL, NULL, 0, 'active', 13);

INSERT INTO product_variant_values (variant_id, attribute_value_id) VALUES
(1, 1),
(1, 12),
(2, 2),
(2, 12),
(3, 3),
(3, 12),
(4, 4),
(4, 12),
(5, 5),
(5, 12),
(6, 6),
(6, 12),
(7, 1),
(7, 16),
(8, 2),
(8, 16),
(9, 3),
(9, 16),
(10, 4),
(10, 16),
(11, 5),
(11, 16),
(12, 6),
(12, 16),
(13, 2),
(13, 15),
(14, 3),
(14, 15),
(15, 4),
(15, 15),
(16, 5),
(16, 15),
(17, 2),
(17, 12),
(18, 3),
(18, 12),
(19, 4),
(19, 12),
(20, 5),
(20, 12),
(21, 2),
(21, 14),
(22, 3),
(22, 14),
(23, 4),
(23, 14),
(24, 5),
(24, 14),
(25, 2),
(25, 13),
(26, 3),
(26, 13),
(27, 4),
(27, 13),
(28, 5),
(28, 13),
(29, 1),
(29, 20),
(30, 2),
(30, 20),
(31, 3),
(31, 20),
(32, 4),
(32, 20),
(33, 5),
(33, 20),
(34, 6),
(34, 20),
(35, 2),
(35, 16),
(36, 3),
(36, 16),
(37, 4),
(37, 16),
(38, 5),
(38, 16),
(39, 2),
(39, 22),
(40, 3),
(40, 22),
(41, 4),
(41, 22),
(42, 5),
(42, 22),
(43, 2),
(43, 13),
(44, 3),
(44, 13),
(45, 4),
(45, 13),
(46, 5),
(46, 13),
(47, 2),
(47, 21),
(48, 3),
(48, 21),
(49, 4),
(49, 21),
(50, 5),
(50, 21),
(51, 1),
(51, 18),
(52, 2),
(52, 18),
(53, 3),
(53, 18),
(54, 4),
(54, 18),
(55, 5),
(55, 18),
(56, 6),
(56, 18),
(57, 2),
(57, 13),
(58, 3),
(58, 13),
(59, 4),
(59, 13),
(60, 5),
(60, 13),
(61, 2),
(61, 17),
(62, 3),
(62, 17),
(63, 4),
(63, 17),
(64, 5),
(64, 17),
(65, 2),
(65, 22),
(66, 3),
(66, 22),
(67, 4),
(67, 22),
(68, 5),
(68, 22),
(69, 1),
(69, 23),
(70, 2),
(70, 23),
(71, 3),
(71, 23),
(72, 4),
(72, 23),
(73, 5),
(73, 23),
(74, 6),
(74, 23),
(75, 1),
(75, 12),
(76, 2),
(76, 12),
(77, 3),
(77, 12),
(78, 4),
(78, 12),
(79, 5),
(79, 12),
(80, 6),
(80, 12),
(81, 2),
(81, 19),
(82, 3),
(82, 19),
(83, 4),
(83, 19),
(84, 5),
(84, 19),
(85, 2),
(85, 21),
(86, 3),
(86, 21),
(87, 4),
(87, 21),
(88, 5),
(88, 21),
(89, 2),
(89, 15),
(90, 3),
(90, 15),
(91, 4),
(91, 15),
(92, 5),
(92, 15);

INSERT INTO product_inventory (variant_id, quantity) VALUES
(1, 3),
(2, 8),
(3, 4),
(4, 9),
(5, 5),
(6, 10),
(7, 6),
(8, 11),
(9, 7),
(10, 3),
(11, 8),
(12, 4),
(13, 10),
(14, 6),
(15, 11),
(16, 7),
(17, 4),
(18, 9),
(19, 5),
(20, 10),
(21, 8),
(22, 4),
(23, 9),
(24, 5),
(25, 11),
(26, 7),
(27, 3),
(28, 8),
(29, 0),
(30, 0),
(31, 0),
(32, 0),
(33, 0),
(34, 0),
(35, 0),
(36, 9),
(37, 5),
(38, 10),
(39, 11),
(40, 7),
(41, 3),
(42, 8),
(43, 5),
(44, 10),
(45, 6),
(46, 11),
(47, 8),
(48, 4),
(49, 9),
(50, 5),
(51, 9),
(52, 5),
(53, 10),
(54, 6),
(55, 11),
(56, 7),
(57, 7),
(58, 3),
(59, 8),
(60, 4),
(61, 5),
(62, 10),
(63, 6),
(64, 11),
(65, 8),
(66, 4),
(67, 9),
(68, 5),
(69, 0),
(70, 0),
(71, 0),
(72, 0),
(73, 0),
(74, 0),
(75, 0),
(76, 0),
(77, 0),
(78, 0),
(79, 0),
(80, 0),
(81, 10),
(82, 6),
(83, 11),
(84, 7),
(85, 8),
(86, 4),
(87, 9),
(88, 5),
(89, 11),
(90, 7),
(91, 3),
(92, 8);

INSERT INTO inventory_movements (variant_id, change_qty, balance_after, reason, note) VALUES
(1, 3, 3, 'initial', 'Opening stock'),
(2, 8, 8, 'initial', 'Opening stock'),
(3, 4, 4, 'initial', 'Opening stock'),
(4, 9, 9, 'initial', 'Opening stock'),
(5, 5, 5, 'initial', 'Opening stock'),
(6, 10, 10, 'initial', 'Opening stock'),
(7, 6, 6, 'initial', 'Opening stock'),
(8, 11, 11, 'initial', 'Opening stock'),
(9, 7, 7, 'initial', 'Opening stock'),
(10, 3, 3, 'initial', 'Opening stock'),
(11, 8, 8, 'initial', 'Opening stock'),
(12, 4, 4, 'initial', 'Opening stock'),
(13, 10, 10, 'initial', 'Opening stock'),
(14, 6, 6, 'initial', 'Opening stock'),
(15, 11, 11, 'initial', 'Opening stock'),
(16, 7, 7, 'initial', 'Opening stock'),
(17, 4, 4, 'initial', 'Opening stock'),
(18, 9, 9, 'initial', 'Opening stock'),
(19, 5, 5, 'initial', 'Opening stock'),
(20, 10, 10, 'initial', 'Opening stock'),
(21, 8, 8, 'initial', 'Opening stock'),
(22, 4, 4, 'initial', 'Opening stock'),
(23, 9, 9, 'initial', 'Opening stock'),
(24, 5, 5, 'initial', 'Opening stock'),
(25, 11, 11, 'initial', 'Opening stock'),
(26, 7, 7, 'initial', 'Opening stock'),
(27, 3, 3, 'initial', 'Opening stock'),
(28, 8, 8, 'initial', 'Opening stock'),
(29, 0, 0, 'initial', 'Opening stock'),
(30, 0, 0, 'initial', 'Opening stock'),
(31, 0, 0, 'initial', 'Opening stock'),
(32, 0, 0, 'initial', 'Opening stock'),
(33, 0, 0, 'initial', 'Opening stock'),
(34, 0, 0, 'initial', 'Opening stock'),
(35, 0, 0, 'initial', 'Opening stock'),
(36, 9, 9, 'initial', 'Opening stock'),
(37, 5, 5, 'initial', 'Opening stock'),
(38, 10, 10, 'initial', 'Opening stock'),
(39, 11, 11, 'initial', 'Opening stock'),
(40, 7, 7, 'initial', 'Opening stock'),
(41, 3, 3, 'initial', 'Opening stock'),
(42, 8, 8, 'initial', 'Opening stock'),
(43, 5, 5, 'initial', 'Opening stock'),
(44, 10, 10, 'initial', 'Opening stock'),
(45, 6, 6, 'initial', 'Opening stock'),
(46, 11, 11, 'initial', 'Opening stock'),
(47, 8, 8, 'initial', 'Opening stock'),
(48, 4, 4, 'initial', 'Opening stock'),
(49, 9, 9, 'initial', 'Opening stock'),
(50, 5, 5, 'initial', 'Opening stock'),
(51, 9, 9, 'initial', 'Opening stock'),
(52, 5, 5, 'initial', 'Opening stock'),
(53, 10, 10, 'initial', 'Opening stock'),
(54, 6, 6, 'initial', 'Opening stock'),
(55, 11, 11, 'initial', 'Opening stock'),
(56, 7, 7, 'initial', 'Opening stock'),
(57, 7, 7, 'initial', 'Opening stock'),
(58, 3, 3, 'initial', 'Opening stock'),
(59, 8, 8, 'initial', 'Opening stock'),
(60, 4, 4, 'initial', 'Opening stock'),
(61, 5, 5, 'initial', 'Opening stock'),
(62, 10, 10, 'initial', 'Opening stock'),
(63, 6, 6, 'initial', 'Opening stock'),
(64, 11, 11, 'initial', 'Opening stock'),
(65, 8, 8, 'initial', 'Opening stock'),
(66, 4, 4, 'initial', 'Opening stock'),
(67, 9, 9, 'initial', 'Opening stock'),
(68, 5, 5, 'initial', 'Opening stock'),
(69, 0, 0, 'initial', 'Opening stock'),
(70, 0, 0, 'initial', 'Opening stock'),
(71, 0, 0, 'initial', 'Opening stock'),
(72, 0, 0, 'initial', 'Opening stock'),
(73, 0, 0, 'initial', 'Opening stock'),
(74, 0, 0, 'initial', 'Opening stock'),
(75, 0, 0, 'initial', 'Opening stock'),
(76, 0, 0, 'initial', 'Opening stock'),
(77, 0, 0, 'initial', 'Opening stock'),
(78, 0, 0, 'initial', 'Opening stock'),
(79, 0, 0, 'initial', 'Opening stock'),
(80, 0, 0, 'initial', 'Opening stock'),
(81, 10, 10, 'initial', 'Opening stock'),
(82, 6, 6, 'initial', 'Opening stock'),
(83, 11, 11, 'initial', 'Opening stock'),
(84, 7, 7, 'initial', 'Opening stock'),
(85, 8, 8, 'initial', 'Opening stock'),
(86, 4, 4, 'initial', 'Opening stock'),
(87, 9, 9, 'initial', 'Opening stock'),
(88, 5, 5, 'initial', 'Opening stock'),
(89, 11, 11, 'initial', 'Opening stock'),
(90, 7, 7, 'initial', 'Opening stock'),
(91, 3, 3, 'initial', 'Opening stock'),
(92, 8, 8, 'initial', 'Opening stock');

INSERT INTO product_relations (product_id, related_id, relation_type) VALUES
(1, 7, 'related'),
(1, 2, 'related'),
(1, 4, 'upsell'),
(1, 10, 'upsell'),
(2, 8, 'related'),
(2, 5, 'related'),
(3, 7, 'related'),
(3, 12, 'related'),
(4, 10, 'related'),
(4, 11, 'upsell'),
(6, 12, 'cross_sell'),
(6, 3, 'cross_sell'),
(8, 11, 'upsell'),
(8, 2, 'related');

INSERT INTO collections (id, name, slug, description, image, banner_image, show_on_home, sort_order, seo_title, meta_description, status) VALUES
(1, 'Crochet Flower Details', 'crochet-flower-details', 'Pieces adorned with handmade crochet blooms.', 'assets/img/sample/col-crochet.svg', NULL, 1, 1, 'Crochet Flower Details | Ebaya', 'Pieces adorned with handmade crochet blooms.', 'active'),
(2, 'Floral Embroidery', 'floral-embroidery', 'Hand-embroidered vines, florals and borders.', 'assets/img/sample/col-floral.svg', NULL, 1, 2, 'Floral Embroidery | Ebaya', 'Hand-embroidered vines, florals and borders.', 'active'),
(3, 'Statement Sleeves', 'statement-sleeves', 'Bell, bishop and butterfly sleeves with handwork.', 'assets/img/sample/col-sleeves.svg', NULL, 1, 3, 'Statement Sleeves | Ebaya', 'Bell, bishop and butterfly sleeves with handwork.', 'active'),
(4, 'Delicate Neckline Work', 'delicate-neckline-work', 'Pearls, crochet and embroidery that frame the face.', 'assets/img/sample/col-neckline.svg', NULL, 1, 4, 'Delicate Neckline Work | Ebaya', 'Pearls, crochet and embroidery that frame the face.', 'active'),
(5, 'Minimalist Elegance', 'minimalist-elegance', 'Quiet, refined everyday pieces.', 'assets/img/sample/col-minimal.svg', NULL, 1, 5, 'Minimalist Elegance | Ebaya', 'Quiet, refined everyday pieces.', 'active'),
(6, 'Special Occasion Designs', 'special-occasion-designs', 'For Eid, weddings and evenings to remember.', 'assets/img/sample/col-occasion.svg', NULL, 1, 6, 'Special Occasion Designs | Ebaya', 'For Eid, weddings and evenings to remember.', 'active'),
(7, 'The Handcrafted Collection', 'handcrafted', 'Every piece in this collection carries handwork — embroidery, crochet or pearl detailing finished by hand.', 'assets/img/sample/detail-crochet.svg', 'assets/img/sample/hero-1.svg', 0, 7, 'The Handcrafted Collection | Ebaya', 'Every piece in this collection carries handwork — embroidery, crochet or pearl detailing finished by hand.', 'active');

INSERT INTO collection_products (collection_id, product_id, sort_order) VALUES
(1, 1, 0),
(1, 3, 1),
(1, 7, 2),
(1, 8, 3),
(1, 11, 4),
(2, 2, 0),
(2, 5, 1),
(2, 8, 2),
(2, 9, 3),
(2, 11, 4),
(3, 4, 0),
(3, 5, 1),
(3, 8, 2),
(3, 9, 3),
(4, 1, 0),
(4, 4, 1),
(4, 10, 2),
(4, 11, 3),
(5, 6, 0),
(5, 12, 1),
(5, 3, 2),
(6, 4, 0),
(6, 10, 1),
(6, 11, 2),
(6, 5, 3),
(7, 1, 0),
(7, 2, 1),
(7, 3, 2),
(7, 4, 3),
(7, 5, 4),
(7, 7, 5),
(7, 8, 6),
(7, 10, 7),
(7, 11, 8);

INSERT INTO homepage_sections (section_key, label, settings, sort_order, is_visible) VALUES
('hero', 'Hero banner carousel', '{}', 1, 1),
('categories', 'Shop by category', '{}', 2, 1),
('new_arrivals', 'New arrivals', '{}', 3, 1),
('handcrafted', 'Handcrafted with love', '{}', 4, 1),
('best_sellers', 'Best sellers', '{}', 5, 1),
('craft_collections', 'Shop by craft or detail', '{}', 6, 1),
('story', 'The Ebaya story', '{}', 7, 1),
('testimonials_newsletter', 'Testimonials & newsletter', '{}', 8, 1);

INSERT INTO banner_slides (desktop_image, mobile_image, image_alt, heading, subheading, description, btn1_text, btn1_url, btn2_text, btn2_url, text_align, text_position, content_side, text_color, overlay_color, overlay_opacity, sort_order) VALUES
('assets/img/sample/hero-1.svg', 'assets/img/sample/hero-1-mobile.svg', 'Women wearing handcrafted Ebaya abayas', 'Elegance, Crafted by Hand', 'The Handcrafted Collection', 'Discover timeless abayas adorned with delicate embroidery, handmade crochet flowers, and thoughtful artisanal details.', 'Shop the Collection', '/collections/handcrafted', 'Our Story', '/about-ebaya', 'left', 'middle', 'start', '#FFFFFF', '#332820', 35, 1),
('assets/img/sample/hero-2.svg', 'assets/img/sample/hero-2-mobile.svg', 'Occasion abaya with pearl work', 'For the Moments that Matter', 'Luxury Occasion Wear', 'Pearl work, fluid silhouettes and finishing made for celebrations.', 'Explore Occasion Wear', '/category/luxury-occasion-wear', NULL, NULL, 'center', 'bottom', 'center', '#FFFFFF', '#1d2420', 30, 2),
('assets/img/sample/hero-3.svg', 'assets/img/sample/hero-3-mobile.svg', 'Embroidered abayas in warm tones', 'Every Stitch, Considered', 'New Arrivals', 'Hand embroidery and crochet details on our newest designs.', 'Shop New Arrivals', '/new-arrivals', NULL, NULL, 'left', 'middle', 'start', '#FFFFFF', '#332820', 30, 3);

INSERT INTO navigation_items (id, menu, parent_id, label, url, sort_order) VALUES
(1, 'header', NULL, 'Shop All Abayas', '/shop', 1),
(2, 'header', NULL, 'New Arrivals', '/new-arrivals', 2),
(3, 'header', NULL, 'Handcrafted Collection', '/collections/handcrafted', 3),
(4, 'header', NULL, 'Occasion Wear', '/category/luxury-occasion-wear', 4),
(5, 'header', NULL, 'About Ebaya', '/about-ebaya', 5),
(6, 'header', 1, 'Everyday Abayas', '/category/everyday-abayas', 1),
(7, 'header', 1, 'Hand-Embroidered Abayas', '/category/hand-embroidered-abayas', 2),
(8, 'header', 1, 'Crochet Flower Abayas', '/category/crochet-flower-abayas', 3),
(9, 'header', 1, 'Open Abayas', '/category/open-abayas', 4),
(10, 'header', 1, 'Limited Edition', '/category/limited-edition-collection', 5),
(11, 'header', 1, 'Best Sellers', '/best-sellers', 6),
(12, 'header', 4, 'Formal Abayas', '/category/formal-abayas', 1),
(13, 'header', 4, 'Eid & Festive', '/category/eid-and-festive', 2),
(20, 'footer_shop', NULL, 'Shop All', '/shop', 1),
(21, 'footer_shop', NULL, 'New Arrivals', '/new-arrivals', 2),
(22, 'footer_shop', NULL, 'Best Sellers', '/best-sellers', 3),
(23, 'footer_shop', NULL, 'Collections', '/collections', 4),
(24, 'footer_shop', NULL, 'Handcrafted', '/collections/handcrafted', 5),
(30, 'footer_help', NULL, 'Contact Us', '/contact', 1),
(31, 'footer_help', NULL, 'Track Your Order', '/track-order', 2),
(32, 'footer_help', NULL, 'Shipping & Delivery', '/shipping-policy', 3),
(33, 'footer_help', NULL, 'Returns & Exchanges', '/returns-exchanges', 4),
(34, 'footer_help', NULL, 'Size Guide', '/size-guide', 5),
(35, 'footer_help', NULL, 'Abaya Care Guide', '/care-guide', 6),
(36, 'footer_help', NULL, 'FAQs', '/faqs', 7),
(40, 'footer_about', NULL, 'About Ebaya', '/about-ebaya', 1),
(41, 'footer_about', NULL, 'Fabric & Craftsmanship', '/craftsmanship-guide', 2),
(42, 'footer_about', NULL, 'Privacy Policy', '/privacy-policy', 3),
(43, 'footer_about', NULL, 'Terms & Conditions', '/terms-conditions', 4);

INSERT INTO pages (title, slug, subtitle, template, content, seo_title, meta_description, status) VALUES
('About Ebaya', 'about-ebaya', 'Contemporary modest fashion, finished by hand.', 'default', '<h2>Our story</h2><p>Ebaya was founded on a simple idea: that modest dressing can be both graceful and deeply personal. We design abayas with clean, contemporary lines and bring them to life with handwork — embroidery, crochet flowers and careful finishing.</p>
<h2>Our approach to craft</h2><p>We believe the most beautiful details are the ones made slowly. Embroidered motifs are worked stitch by stitch, crochet flowers are shaped one at a time, and every piece is checked before it leaves us. Because these details are made by hand, small variations are part of each garment''s character.</p>
<h2>Designed for real life</h2><p>From everyday wear to Eid, weddings and evenings out, our pieces are designed to move with you — comfortable, considered and quietly special.</p>', 'About Ebaya', 'Contemporary modest fashion, finished by hand.', 'published'),
('Contact Us', 'contact', 'We would love to hear from you.', 'contact', '<p>For questions about sizing, custom orders, delivery or an existing order, send us a message and our team will get back to you during business hours.</p>', 'Contact Us', 'We would love to hear from you.', 'published'),
('Size Guide', 'size-guide', 'Find your perfect fit.', 'default', '<p>Our abayas are designed for a graceful, relaxed fit. If you are between sizes, we suggest choosing the larger size.</p>
<table><thead><tr><th>Size</th><th>Bust (in)</th><th>Shoulder (in)</th><th>Sleeve (in)</th><th>Length (in)</th></tr></thead>
<tbody><tr><td>XS</td><td>36</td><td>14</td><td>22</td><td>52</td></tr><tr><td>S</td><td>38</td><td>14.5</td><td>22.5</td><td>54</td></tr>
<tr><td>M</td><td>40</td><td>15</td><td>23</td><td>56</td></tr><tr><td>L</td><td>42</td><td>15.5</td><td>23.5</td><td>57</td></tr>
<tr><td>XL</td><td>44</td><td>16</td><td>24</td><td>58</td></tr><tr><td>XXL</td><td>46</td><td>16.5</td><td>24</td><td>58</td></tr></tbody></table>
<h3>How to measure</h3><ul><li><strong>Bust:</strong> around the fullest part of the chest.</li><li><strong>Length:</strong> from the highest point of the shoulder to where you want the hem to fall.</li><li><strong>Sleeve:</strong> from shoulder seam to wrist with the arm relaxed.</li></ul>
<p>Need a custom length? Many styles offer made-to-order lengths — look for “Personalise this piece” on the product page.</p>', 'Size Guide', 'Find your perfect fit.', 'published'),
('Fabric & Craftsmanship Guide', 'craftsmanship-guide', 'Understanding the details.', 'default', '<h2>Hand embroidery</h2><p>Embroidered motifs are worked by hand using fine threads. Expect slight variations in stitch placement — a sign of handwork.</p>
<h2>Crochet flowers</h2><p>Each crochet flower is made individually and attached by hand. Handle gently and avoid snagging on jewellery or bags.</p>
<h2>Pearl and bead work</h2><p>Pearls and beads are hand-set. Store pieces flat or on padded hangers to protect embellishments.</p>
<h2>Fabrics</h2><p>Fabric details for each design are listed on its product page under “Details & craftsmanship”.</p><p><em>This is starter content. Please review and replace it with your own verified policies before launch.</em></p>', 'Fabric & Craftsmanship Guide', 'Understanding the details.', 'published'),
('Abaya Care Guide', 'care-guide', 'Keep your pieces beautiful for years.', 'default', '<ul><li>Dry cleaning is recommended for embroidered, crochet and pearl-embellished pieces.</li><li>For plain abayas, hand wash in cold water with a mild detergent. Do not wring or tumble dry.</li><li>Iron or steam on a low setting from the reverse, avoiding embellishments.</li><li>Store on a padded hanger away from direct sunlight and moisture.</li><li>Keep perfumes and sprays away from embroidery and pearls.</li></ul>', 'Abaya Care Guide', 'Keep your pieces beautiful for years.', 'published'),
('Shipping & Delivery Policy', 'shipping-policy', NULL, 'default', '<h2>Delivery times</h2><p>Ready-to-ship pieces are usually dispatched within 1–2 working days. Made-to-order and customised pieces are dispatched after their production time, shown on the product page and at checkout.</p>
<h2>Delivery charges</h2><p>Delivery charges depend on your city and are shown at checkout before you confirm your order. Complimentary delivery applies above the threshold shown at checkout.</p>
<h2>Cash on delivery</h2><p>Cash on delivery is available in selected areas. Any COD fee is shown at checkout.</p><p><em>This is starter content. Please review and replace it with your own verified policies before launch.</em></p>', 'Shipping & Delivery Policy', 'Shipping & Delivery Policy', 'published'),
('Returns & Exchanges', 'returns-exchanges', NULL, 'default', '<h2>Ready-to-ship pieces</h2><p>You may request an exchange within 7 days of delivery for unworn items in original condition with tags attached.</p>
<h2>Made-to-order and customised pieces</h2><p>Because these are made specifically for you, they are not eligible for change-of-mind returns. If there is a fault, please contact us within 48 hours of delivery with photos.</p>
<h2>How to request an exchange</h2><p>Contact us with your order number and the reason for your request.</p><p><em>This is starter content. Please review and replace it with your own verified policies before launch.</em></p>', 'Returns & Exchanges', 'Returns & Exchanges', 'published'),
('Privacy Policy', 'privacy-policy', NULL, 'default', '<p>We collect the information you provide when placing an order, creating an account, subscribing to our newsletter or contacting us — such as your name, email, phone number and delivery address — to process orders, provide customer service and, with your consent, send marketing emails.</p>
<p>Card payments are processed by our payment providers on their secure pages; we never store your card number or security code.</p>
<p>You can unsubscribe from marketing emails at any time using the link in each email, and you may contact us to access or delete your personal data.</p><p><em>This is starter content. Please review and replace it with your own verified policies before launch.</em></p>', 'Privacy Policy', 'Privacy Policy', 'published'),
('Terms & Conditions', 'terms-conditions', NULL, 'default', '<p>By placing an order you agree to these terms. Prices are shown in Pakistani Rupees and confirmed at checkout. An order is accepted once confirmed by us; for online payments, after the payment has been verified.</p>
<p>Product colours may vary slightly due to screen settings and the handmade nature of embellishments.</p>
<p>We may cancel an order if an item is unavailable or a pricing error occurs, in which case any payment will be refunded.</p><p><em>This is starter content. Please review and replace it with your own verified policies before launch.</em></p>', 'Terms & Conditions', 'Terms & Conditions', 'published'),
('Frequently Asked Questions', 'faqs', 'Answers to common questions.', 'faq', '<details><summary>How do I choose my size?</summary><p>Please see our Size Guide. If you are between sizes, choose the larger size for a relaxed drape.</p></details>
<details><summary>Can I request a custom length?</summary><p>Many styles offer a custom length option on the product page under “Personalise this piece”.</p></details>
<details><summary>How long does delivery take?</summary><p>Ready-to-ship items usually arrive within 2–5 working days of dispatch, depending on your city. Made-to-order items include their production time.</p></details>
<details><summary>Do you offer cash on delivery?</summary><p>Yes, in selected areas. Availability and any fee are shown at checkout.</p></details>
<details><summary>Can I exchange an item?</summary><p>Unworn ready-to-ship items can be exchanged within 7 days. Please read our Returns & Exchanges policy.</p></details>', 'Frequently Asked Questions', 'Answers to common questions.', 'published');

INSERT INTO shipping_zones (id, name, is_default, cod_enabled, cod_fee, cod_max_order, sort_order) VALUES
(1, 'Major cities', 0, 1, 0, 100000, 1),
(2, 'Rest of Pakistan', 1, 1, 150, 60000, 2);

INSERT INTO shipping_zone_locations (zone_id, location_type, name) VALUES
(1, 'city', 'Lahore'),
(1, 'city', 'Karachi'),
(1, 'city', 'Islamabad'),
(1, 'city', 'Rawalpindi'),
(1, 'city', 'Faisalabad'),
(1, 'city', 'Multan'),
(2, 'province', 'Gilgit-Baltistan');

INSERT INTO shipping_rates (zone_id, method, label, applies_to, rate, free_over, min_order, est_days_min, est_days_max) VALUES
(1, 'standard', 'Standard delivery', 'all', 250, 15000, NULL, 2, 4),
(1, 'express', 'Express delivery', 'ready_to_ship', 600, NULL, NULL, 1, 2),
(2, 'standard', 'Standard delivery', 'all', 350, 15000, NULL, 3, 6);

INSERT INTO coupons (code, description, type, value, min_order, max_discount, usage_limit, per_customer_limit, status) VALUES
('WELCOME10', '10% off your first order (sample)', 'percent', 10, 10000, 3000, NULL, 1, 'active'),
('FREESHIP', 'Free delivery (sample)', 'free_shipping', 0, 5000, NULL, 500, NULL, 'active');

INSERT INTO payment_gateways (code, name, description, enabled, mode, tested, sort_order) VALUES
('cod', 'Cash on Delivery', 'Pay in cash when your order arrives.', 1, 'live', 1, 1),
('jazzcash', 'JazzCash', 'Pay with your JazzCash mobile account or card.', 0, 'sandbox', 0, 2),
('easypaisa', 'Easypaisa', 'Pay with your Easypaisa mobile account.', 0, 'sandbox', 0, 3),
('card', 'Debit / Credit Card', 'Visa and Mastercard via secure hosted checkout.', 0, 'sandbox', 0, 4);

INSERT INTO payment_gateway_settings (gateway_code, setting_key, setting_value, is_secret) VALUES
('cod', 'instructions', 'Please keep the exact amount ready for the courier.', 0);

INSERT INTO testimonials (name, location, quote, rating, product_id, sort_order, status) VALUES
('Sample — replace me', 'Lahore', 'SAMPLE TESTIMONIAL: replace with genuine customer feedback (with permission) before activating.', 5, 1, 1, 'inactive'),
('Sample — replace me', 'Karachi', 'SAMPLE TESTIMONIAL: only publish real reviews from real customers.', 5, 2, 2, 'inactive');

SET FOREIGN_KEY_CHECKS = 1;
