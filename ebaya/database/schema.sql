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
