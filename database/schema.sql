-- =====================================================================
--  Fahim Store - Database schema (MySQL 5.7+/8.0, MariaDB 10.3+)
--  Engine: InnoDB, charset utf8mb4 (supports Urdu product names)
--  All money columns are DECIMAL(12,2). Never FLOAT for prices.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  ADMIN USERS, ROLES & PERMISSIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admin_roles;
CREATE TABLE admin_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_super TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_permissions;
CREATE TABLE admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id INT UNSIGNED NOT NULL,
  permission VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_perm (role_id, permission),
  CONSTRAINT fk_perm_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email),
  KEY idx_admin_role (role_id),
  CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope VARCHAR(20) NOT NULL,           -- admin | customer
  identifier VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempt_lookup (scope, ip_address, attempted_at),
  KEY idx_attempt_ident (scope, identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CUSTOMERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NULL,      -- NULL = guest / POS customer without login
  is_registered TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  source VARCHAR(20) NOT NULL DEFAULT 'web',   -- web | pos | guest
  notes TEXT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_phone (phone),
  UNIQUE KEY uq_customer_email (email),
  KEY idx_customer_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_addresses;
CREATE TABLE customer_addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Home',
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  city VARCHAR(80) NOT NULL,
  area VARCHAR(120) NULL,
  delivery_zone_id INT UNSIGNED NULL,
  landmark VARCHAR(160) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addr_customer (customer_id),
  CONSTRAINT fk_addr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CATALOG: CATEGORIES, BRANDS, PRODUCTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  name_urdu VARCHAR(150) NULL,
  slug VARCHAR(170) NOT NULL,           -- globally unique, path built from the tree
  description TEXT NULL,
  image VARCHAR(255) NULL,
  image_alt VARCHAR(190) NULL,
  icon VARCHAR(60) NULL,                -- bootstrap-icons class e.g. bi-book
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  show_in_menu TINYINT(1) NOT NULL DEFAULT 1,
  show_on_home TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_slug (slug),
  KEY idx_category_parent (parent_id, sort_order),
  KEY idx_category_active (is_active),
  CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS brands;
CREATE TABLE brands (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  logo VARCHAR(255) NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brand_slug (slug),
  UNIQUE KEY uq_brand_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(220) NOT NULL,
  name_urdu VARCHAR(220) NULL,
  slug VARCHAR(240) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  barcode VARCHAR(64) NULL,
  product_code VARCHAR(64) NULL,
  isbn VARCHAR(20) NULL,
  brand_id INT UNSIGNED NULL,
  primary_category_id INT UNSIGNED NULL,
  short_description VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  keywords VARCHAR(500) NULL,           -- extra search keywords (english / roman urdu)
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,        -- regular price
  sale_price DECIMAL(12,2) NULL,                    -- discounted price, NULL = none
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- purchase cost
  unit VARCHAR(20) NOT NULL DEFAULT 'piece',        -- kg, gram, litre, ml, packet, piece, dozen...
  unit_value DECIMAL(10,3) NULL,                    -- e.g. 5 (for 5 kg)
  weight_grams INT UNSIGNED NULL,
  length_cm DECIMAL(8,2) NULL,
  width_cm DECIMAL(8,2) NULL,
  height_cm DECIMAL(8,2) NULL,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  min_order_qty INT UNSIGNED NOT NULL DEFAULT 1,
  max_order_qty INT UNSIGNED NULL,
  track_stock TINYINT(1) NOT NULL DEFAULT 1,
  has_variations TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_rashan_item TINYINT(1) NOT NULL DEFAULT 0,     -- appears in Monthly Rashan builder
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  sales_count INT NOT NULL DEFAULT 0,
  views_count INT UNSIGNED NOT NULL DEFAULT 0,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  search_text TEXT NULL,                -- normalised index: name, urdu, sku, isbn, brand, categories, tags
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_slug (slug),
  UNIQUE KEY uq_product_sku (sku),
  KEY idx_product_barcode (barcode),
  KEY idx_product_isbn (isbn),
  KEY idx_product_code (product_code),
  KEY idx_product_brand (brand_id),
  KEY idx_product_category (primary_category_id),
  KEY idx_product_listing (status, deleted_at, is_featured),
  KEY idx_product_sales (status, sales_count),
  KEY idx_product_created (status, created_at),
  CONSTRAINT fk_product_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
  CONSTRAINT fk_product_pcat FOREIGN KEY (primary_category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_categories;
CREATE TABLE product_categories (
  product_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  KEY idx_pc_category (category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_images;
CREATE TABLE product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  image VARCHAR(255) NOT NULL,          -- relative path under /uploads
  thumb VARCHAR(255) NULL,
  alt_text VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pimg_product (product_id, sort_order),
  CONSTRAINT fk_pimg_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variations;
CREATE TABLE product_variations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,           -- e.g. "5 kg", "Blue", "Pack of 12"
  sku VARCHAR(64) NOT NULL,
  barcode VARCHAR(64) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variation_sku (sku),
  KEY idx_var_barcode (barcode),
  KEY idx_var_product (product_id),
  CONSTRAINT fk_var_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_tags;
CREATE TABLE product_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  tag VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_tag (product_id, tag),
  KEY idx_tag (tag),
  CONSTRAINT fk_tag_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS related_products;
CREATE TABLE related_products (
  product_id INT UNSIGNED NOT NULL,
  related_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, related_id),
  CONSTRAINT fk_rel_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_related FOREIGN KEY (related_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  INVENTORY
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS inventory;
CREATE TABLE inventory (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = the base product (no variation)
  quantity INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_item (product_id, variation_id),
  KEY idx_inventory_qty (quantity),
  CONSTRAINT fk_inv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS inventory_movements;
CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,
  movement_type VARCHAR(30) NOT NULL,   -- opening, purchase, sale, pos_sale, adjustment, cancel_restock, import, return
  qty_change INT NOT NULL,              -- + stock in, - stock out
  balance_after INT NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(30) NULL,      -- order | purchase | manual | import
  reference_id INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mov_product (product_id, created_at),
  KEY idx_mov_type (movement_type, created_at),
  KEY idx_mov_ref (reference_type, reference_id),
  CONSTRAINT fk_mov_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS suppliers;
CREATE TABLE suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  address VARCHAR(255) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS purchases;
CREATE TABLE purchases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id INT UNSIGNED NULL,
  reference_no VARCHAR(60) NULL,        -- supplier invoice number
  purchase_date DATE NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('draft','received') NOT NULL DEFAULT 'received',
  notes TEXT NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_purchase_date (purchase_date),
  CONSTRAINT fk_purchase_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS purchase_items;
CREATE TABLE purchase_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_pi_purchase (purchase_id),
  CONSTRAINT fk_pi_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CART & WISHLIST
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS carts;
CREATE TABLE carts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  session_token CHAR(40) NOT NULL,
  coupon_code VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_token (session_token),
  KEY idx_cart_customer (customer_id),
  CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_line (cart_id, product_id, variation_id),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlists;
CREATE TABLE wishlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlist_customer (customer_id),
  CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlist_items;
CREATE TABLE wishlist_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wishlist_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wl_item (wishlist_id, product_id),
  CONSTRAINT fk_wli_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_wli_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  DELIVERY & PAYMENT CONFIGURATION
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS delivery_zones;
CREATE TABLE delivery_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  city VARCHAR(80) NOT NULL,
  area VARCHAR(120) NOT NULL,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  free_delivery_over DECIMAL(12,2) NULL,
  min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estimated_time VARCHAR(80) NULL,      -- "Same day", "24-48 hours"
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_zone (city, area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_methods;
CREATE TABLE payment_methods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,            -- cod, cash_pickup, bank_transfer, easypaisa, jazzcash, cash, card
  name VARCHAR(80) NOT NULL,
  instructions TEXT NULL,               -- account numbers etc shown at checkout
  applies_to ENUM('both','delivery','pickup','pos') NOT NULL DEFAULT 'both',
  requires_reference TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  show_in_pos TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ORDERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NULL,
  channel ENUM('web','pos') NOT NULL DEFAULT 'web',
  order_type ENUM('regular','rashan') NOT NULL DEFAULT 'regular',
  fulfillment ENUM('delivery','pickup','walkin') NOT NULL DEFAULT 'delivery',
  status ENUM('draft','pending','confirmed','processing','ready_for_pickup','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending',
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  customer_email VARCHAR(190) NULL,
  address_line VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  area VARCHAR(120) NULL,
  delivery_zone_id INT UNSIGNED NULL,
  delivery_instructions VARCHAR(500) NULL,
  pickup_at DATETIME NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  coupon_code VARCHAR(40) NULL,
  delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(30) NOT NULL,
  payment_status ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',
  payment_reference VARCHAR(120) NULL,
  customer_notes TEXT NULL,
  admin_notes TEXT NULL,
  stock_deducted TINYINT(1) NOT NULL DEFAULT 0,  -- guard against double deduction
  grocery_list_id INT UNSIGNED NULL,
  created_by_admin INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_number (order_number),
  KEY idx_order_customer (customer_id),
  KEY idx_order_status (status, created_at),
  KEY idx_order_type (order_type, status),
  KEY idx_order_created (created_at),
  KEY idx_order_phone (customer_phone),
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,
  product_name VARCHAR(220) NOT NULL,   -- snapshot at time of order
  variation_name VARCHAR(120) NULL,
  sku VARCHAR(64) NULL,
  unit VARCHAR(20) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  is_picked TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_status_history;
CREATE TABLE order_status_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,  -- internal admin note vs customer visible
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  method VARCHAR(30) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reference VARCHAR(120) NULL,
  status ENUM('pending','received','failed','refunded') NOT NULL DEFAULT 'pending',
  received_by INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_order (order_id),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  COUPONS & PROMOTIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL,
  discount_type ENUM('percent','fixed','free_delivery') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  max_discount DECIMAL(12,2) NULL,
  usage_limit INT UNSIGNED NULL,
  per_customer_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS coupon_usage;
CREATE TABLE coupon_usage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  customer_phone VARCHAR(30) NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_order (coupon_id, order_id),
  KEY idx_cu_phone (coupon_id, customer_phone),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS promotions;
CREATE TABLE promotions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  promo_type VARCHAR(40) NOT NULL DEFAULT 'seasonal', -- grocery_deal, school_offer, stationery_bundle, monthly_package, seasonal, combo
  subtitle VARCHAR(255) NULL,
  description TEXT NULL,
  badge_text VARCHAR(40) NULL,
  image VARCHAR(255) NULL,
  bg_color VARCHAR(20) NULL,
  text_color VARCHAR(20) NULL,
  button_text VARCHAR(60) NULL,
  link_url VARCHAR(255) NULL,           -- optional override; default = /offers/{slug}/
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_promo_slug (slug),
  KEY idx_promo_active (is_active, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS promotion_products;
CREATE TABLE promotion_products (
  promotion_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (promotion_id, product_id),
  CONSTRAINT fk_pp_promo FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  HOMEPAGE CONTENT
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS banners;
CREATE TABLE banners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  position ENUM('hero','promo') NOT NULL DEFAULT 'hero',
  heading VARCHAR(160) NULL,
  subheading VARCHAR(200) NULL,
  description VARCHAR(500) NULL,
  button_text VARCHAR(60) NULL,
  button_url VARCHAR(255) NULL,
  background_image VARCHAR(255) NULL,
  side_image VARCHAR(255) NULL,
  desktop_image VARCHAR(255) NULL,      -- full artwork banner (optional)
  mobile_image VARCHAR(255) NULL,
  bg_color VARCHAR(20) NOT NULL DEFAULT '#0f766e',
  text_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banner_active (position, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS homepage_sections;
CREATE TABLE homepage_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_type VARCHAR(40) NOT NULL,    -- hero, categories, products, promotions, brands, promo_banners, newsletter, testimonials, rashan_cta
  title VARCHAR(160) NULL,
  subtitle VARCHAR(255) NULL,
  settings TEXT NULL,                   -- JSON: {"source":"featured","limit":8,...}
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hs_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS homepage_section_items;
CREATE TABLE homepage_section_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id INT UNSIGNED NOT NULL,
  item_type ENUM('product','category','brand','promotion') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  custom_image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hsi (section_id, item_type, item_id),
  CONSTRAINT fk_hsi_section FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS testimonials;
CREATE TABLE testimonials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name VARCHAR(120) NOT NULL,
  location VARCHAR(120) NULL,
  message VARCHAR(600) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  MONTHLY RASHAN (GROCERY) LISTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS grocery_lists;
CREATE TABLE grocery_lists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  monthly_budget DECIMAL(12,2) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gl_customer (customer_id),
  CONSTRAINT fk_gl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS grocery_list_items;
CREATE TABLE grocery_list_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  note VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gli (list_id, product_id, variation_id),
  CONSTRAINT fk_gli_list FOREIGN KEY (list_id) REFERENCES grocery_lists(id) ON DELETE CASCADE,
  CONSTRAINT fk_gli_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  REVIEWS, PAGES, SEO
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  reviewer_name VARCHAR(120) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(160) NULL,
  body TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_reply TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_review_product (product_id, status),
  CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pages;
CREATE TABLE pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  content MEDIUMTEXT NULL,
  show_in_footer TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_page_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS page_faqs;
CREATE TABLE page_faqs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_id INT UNSIGNED NOT NULL,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_faq_page (page_id),
  CONSTRAINT fk_faq_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS seo_metadata;
CREATE TABLE seo_metadata (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(30) NOT NULL,     -- product, category, brand, page, promotion, static
  entity_id INT UNSIGNED NOT NULL DEFAULT 0,
  entity_key VARCHAR(60) NOT NULL DEFAULT '',  -- for static pages: home, shop, monthly-rashan, contact
  seo_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  focus_keyword VARCHAR(120) NULL,
  secondary_keywords VARCHAR(255) NULL,
  canonical_url VARCHAR(255) NULL,
  meta_robots VARCHAR(60) NULL,
  og_title VARCHAR(190) NULL,
  og_description VARCHAR(320) NULL,
  og_image VARCHAR(255) NULL,
  twitter_card VARCHAR(30) NULL,
  twitter_title VARCHAR(190) NULL,
  twitter_description VARCHAR(320) NULL,
  image_alt VARCHAR(190) NULL,
  breadcrumb_title VARCHAR(120) NULL,
  schema_json TEXT NULL,                -- extra custom JSON-LD
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_entity (entity_type, entity_id, entity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS redirects;
CREATE TABLE redirects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  old_path VARCHAR(255) NOT NULL,
  new_path VARCHAR(255) NOT NULL,
  status_code SMALLINT NOT NULL DEFAULT 301,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirect_old (old_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS search_synonyms;
CREATE TABLE search_synonyms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  term VARCHAR(80) NOT NULL,            -- what customers type, e.g. "atta"
  synonyms VARCHAR(255) NOT NULL,       -- comma separated, e.g. "flour,wheat flour"
  PRIMARY KEY (id),
  UNIQUE KEY uq_synonym_term (term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS search_logs;
CREATE TABLE search_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  term VARCHAR(160) NOT NULL,
  results_count INT UNSIGNED NOT NULL DEFAULT 0,
  customer_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_search_term (term),
  KEY idx_search_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SETTINGS, NOTIFICATIONS, POS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(40) NOT NULL,            -- new_order, rashan_order, low_stock, review, contact
  title VARCHAR(190) NOT NULL,
  message TEXT NULL,
  link VARCHAR(255) NULL,
  reference_id INT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pos_sessions;
CREATE TABLE pos_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  closing_cash DECIMAL(12,2) NULL,
  expected_cash DECIMAL(12,2) NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  notes VARCHAR(255) NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pos_admin (admin_id, status),
  CONSTRAINT fk_pos_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pos_sales;
CREATE TABLE pos_sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pos_session_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  amount_tendered DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  change_given DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_order (order_id),
  KEY idx_ps_session (pos_session_id),
  CONSTRAINT fk_ps_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_session FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
