-- =====================================================================
--  Fahim Store - complete database (schema + starter data)
--  Import into an EMPTY database with phpMyAdmin or:
--    mysql -u USER -p DATABASE < database/fahim_store.sql
--  Admin login: admin@fahimstore.pk / Admin@123  (change after login!)
-- =====================================================================
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

-- ---------------------------------------------------------------------
--  STARTER DATA
-- ---------------------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
/*M!999999\- enable the sandbox mode */ 
INSERT INTO `admin_permissions` (`id`, `role_id`, `permission`) VALUES (2,2,'categories'),
(8,2,'content'),
(9,2,'coupons'),
(7,2,'customers'),
(1,2,'dashboard'),
(10,2,'delivery'),
(4,2,'import'),
(5,2,'inventory'),
(6,2,'orders'),
(13,2,'pos'),
(3,2,'products'),
(14,2,'reports'),
(11,2,'reviews'),
(12,2,'seo'),
(18,3,'customers'),
(15,3,'dashboard'),
(17,3,'orders'),
(16,3,'pos'),
(21,4,'categories'),
(19,4,'dashboard'),
(23,4,'import'),
(22,4,'inventory'),
(20,4,'products');
INSERT INTO `admin_roles` (`id`, `name`, `description`, `is_super`, `created_at`, `updated_at`) VALUES (1,'Super Admin','Full access to everything',1,'2026-11-10 13:52:28',NULL),
(2,'Store Manager','Everything except admin users and settings',0,'2026-11-10 13:52:28',NULL),
(3,'Cashier','POS, orders and customers',0,'2026-11-10 13:52:28',NULL),
(4,'Inventory Staff','Products, stock, purchases',0,'2026-11-10 13:52:28',NULL);
INSERT INTO `admins` (`id`, `role_id`, `name`, `email`, `phone`, `password_hash`, `is_active`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Fahim (Owner)','admin@fahimstore.pk','03001234567','$2y$10$9k.DUk58DRSPvmRueerlsOONXY5VySRhSlQ1OAkf0zWgKD5pDxOfO',1,NULL,NULL,'2026-11-10 13:52:28',NULL,NULL),
(2,3,'Counter Cashier','cashier@fahimstore.pk','03007654321','$2y$10$E63.rvyuE.tOqgmrdZiIzO5wgvfUI.osR.zHvOlhP3w1acUtdPkgS',1,NULL,NULL,'2026-11-10 13:52:28',NULL,NULL);
INSERT INTO `banners` (`id`, `position`, `heading`, `subheading`, `description`, `button_text`, `button_url`, `background_image`, `side_image`, `desktop_image`, `mobile_image`, `bg_color`, `text_color`, `starts_at`, `ends_at`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'hero','Build Your Monthly Rashan','Save time every month','Pick atta, oil, daal, tea and more in one list. Set your budget - we pack it for delivery or pickup.','Start Your List','monthly-rashan/',NULL,'assets/images/categories/groceries.svg',NULL,NULL,'#0f766e','#ffffff',NULL,NULL,1,1,'2026-11-10 13:52:28',NULL),
(2,'hero','School Reopening Sale','Class 1 to Intermediate','All text books, registers, copies and geometry boxes under one roof. Up to 15% off.','Shop Books','category/books/',NULL,'assets/images/categories/books.svg',NULL,NULL,'#1e3a8a','#ffffff',NULL,NULL,2,1,'2026-11-10 13:52:28',NULL),
(3,'hero','Stationery for Every Student','Pens · Pencils · Colours · Registers','Branded stationery from Dollar, Picasso, Faber-Castell and more at the best prices.','Shop Stationery','category/stationery/',NULL,'assets/images/categories/stationery.svg',NULL,NULL,'#7c2d12','#ffffff',NULL,NULL,3,1,'2026-11-10 13:52:28',NULL),
(4,'promo','Free Delivery above Rs. 5,000','On all grocery orders',NULL,'Order Now','category/groceries/',NULL,'assets/images/categories/groceries.svg',NULL,NULL,'#f59e0b','#1f2933',NULL,NULL,1,1,'2026-11-10 13:52:28',NULL),
(5,'promo','Registers & Copies','Buy in bulk & save',NULL,'View Range','category/stationery/registers-copies/',NULL,'assets/images/categories/registers.svg',NULL,NULL,'#ccfbf1','#134e4a',NULL,NULL,2,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `brands` (`id`, `name`, `slug`, `logo`, `description`, `is_active`, `is_featured`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Oxford University Press','oxford-university-press',NULL,NULL,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(2,'Cambridge University Press','cambridge-university-press',NULL,NULL,1,0,1,'2026-11-10 13:52:28',NULL,NULL),
(3,'Sindh Text Book Board','sindh-text-book-board',NULL,NULL,1,0,2,'2026-11-10 13:52:28',NULL,NULL),
(4,'Punjab Text Book Board','punjab-text-book-board',NULL,NULL,1,0,3,'2026-11-10 13:52:28',NULL,NULL),
(5,'Paramount Books','paramount-books',NULL,NULL,1,0,4,'2026-11-10 13:52:28',NULL,NULL),
(6,'Taj Company','taj-company',NULL,NULL,1,0,5,'2026-11-10 13:52:28',NULL,NULL),
(7,'Dollar','dollar',NULL,NULL,1,1,6,'2026-11-10 13:52:28',NULL,NULL),
(8,'Piano','piano',NULL,NULL,1,0,7,'2026-11-10 13:52:28',NULL,NULL),
(9,'Picasso','picasso',NULL,NULL,1,1,8,'2026-11-10 13:52:28',NULL,NULL),
(10,'Faber-Castell','faber-castell',NULL,NULL,1,0,9,'2026-11-10 13:52:28',NULL,NULL),
(11,'Deer','deer',NULL,NULL,1,0,10,'2026-11-10 13:52:28',NULL,NULL),
(12,'Sunridge','sunridge',NULL,NULL,1,1,11,'2026-11-10 13:52:28',NULL,NULL),
(13,'Guard','guard',NULL,NULL,1,0,12,'2026-11-10 13:52:28',NULL,NULL),
(14,'Dalda','dalda',NULL,NULL,1,1,13,'2026-11-10 13:52:28',NULL,NULL),
(15,'Habib','habib',NULL,NULL,1,0,14,'2026-11-10 13:52:28',NULL,NULL),
(16,'Tapal','tapal',NULL,NULL,1,1,15,'2026-11-10 13:52:28',NULL,NULL),
(17,'Lipton','lipton',NULL,NULL,1,0,16,'2026-11-10 13:52:28',NULL,NULL),
(18,'National Foods','national-foods',NULL,NULL,1,1,17,'2026-11-10 13:52:28',NULL,NULL),
(19,'Shan','shan',NULL,NULL,1,1,18,'2026-11-10 13:52:28',NULL,NULL),
(20,'Peek Freans','peek-freans',NULL,NULL,1,1,19,'2026-11-10 13:52:28',NULL,NULL),
(21,'LU','lu',NULL,NULL,1,0,20,'2026-11-10 13:52:28',NULL,NULL),
(22,'Olpers','olpers',NULL,NULL,1,1,21,'2026-11-10 13:52:28',NULL,NULL),
(23,'Nestle','nestle',NULL,NULL,1,0,22,'2026-11-10 13:52:28',NULL,NULL),
(24,'Surf Excel','surf-excel',NULL,NULL,1,1,23,'2026-11-10 13:52:28',NULL,NULL),
(25,'Ariel','ariel',NULL,NULL,1,0,24,'2026-11-10 13:52:28',NULL,NULL),
(26,'Lux','lux',NULL,NULL,1,1,25,'2026-11-10 13:52:28',NULL,NULL),
(27,'Lifebuoy','lifebuoy',NULL,NULL,1,0,26,'2026-11-10 13:52:28',NULL,NULL),
(28,'Max','max',NULL,NULL,1,0,27,'2026-11-10 13:52:28',NULL,NULL),
(29,'Rose Petal','rose-petal',NULL,NULL,1,0,28,'2026-11-10 13:52:28',NULL,NULL),
(30,'Mitchells','mitchells',NULL,NULL,1,0,29,'2026-11-10 13:52:28',NULL,NULL),
(31,'Energizer','energizer',NULL,NULL,1,0,30,'2026-11-10 13:52:28',NULL,NULL),
(32,'Lion','lion',NULL,NULL,1,0,31,'2026-11-10 13:52:28',NULL,NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `name_urdu`, `slug`, `description`, `image`, `image_alt`, `icon`, `sort_order`, `is_active`, `show_in_menu`, `show_on_home`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,NULL,'Books','کتابیں','books','School text books, Oxford & Cambridge books, Islamic books, general knowledge and story books for every class.','assets/images/categories/books.svg','Books','bi-book',1,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(2,1,'School Books',NULL,'school-books','Text books for Class 1 to Intermediate.','assets/images/categories/books.svg','School Books','bi-mortarboard',1,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(3,2,'Class 1',NULL,'class-1','All school books for Class 1.','assets/images/categories/books.svg','Class 1',NULL,1,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(4,2,'Class 2',NULL,'class-2','All school books for Class 2.','assets/images/categories/books.svg','Class 2',NULL,2,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(5,2,'Class 3',NULL,'class-3','All school books for Class 3.','assets/images/categories/books.svg','Class 3',NULL,3,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(6,2,'Class 4',NULL,'class-4','All school books for Class 4.','assets/images/categories/books.svg','Class 4',NULL,4,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(7,2,'Class 5',NULL,'class-5','All school books for Class 5.','assets/images/categories/books.svg','Class 5',NULL,5,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(8,2,'Class 6',NULL,'class-6','All school books for Class 6.','assets/images/categories/books.svg','Class 6',NULL,6,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(9,2,'Class 7',NULL,'class-7','All school books for Class 7.','assets/images/categories/books.svg','Class 7',NULL,7,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(10,2,'Class 8',NULL,'class-8','All school books for Class 8.','assets/images/categories/books.svg','Class 8',NULL,8,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(11,2,'Class 9',NULL,'class-9','All school books for Class 9.','assets/images/categories/books.svg','Class 9',NULL,9,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(12,2,'Class 10',NULL,'class-10','All school books for Class 10.','assets/images/categories/books.svg','Class 10',NULL,10,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(13,2,'Intermediate',NULL,'intermediate','All school books for Intermediate.','assets/images/categories/books.svg','Intermediate',NULL,11,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(14,1,'Oxford Books',NULL,'oxford-books',NULL,'assets/images/categories/books.svg','Oxford Books',NULL,2,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(15,1,'Cambridge Books',NULL,'cambridge-books',NULL,'assets/images/categories/books.svg','Cambridge Books',NULL,3,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(16,1,'Islamic Books','اسلامی کتابیں','islamic-books',NULL,'assets/images/categories/books.svg','Islamic Books',NULL,4,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(17,1,'General Knowledge',NULL,'general-knowledge',NULL,'assets/images/categories/books.svg','General Knowledge',NULL,5,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(18,1,'Story Books',NULL,'story-books',NULL,'assets/images/categories/books.svg','Story Books',NULL,6,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(19,NULL,'Stationery','اسٹیشنری','stationery','Pens, pencils, notebooks, registers, art supplies, geometry boxes and office stationery.','assets/images/categories/stationery.svg','Stationery','bi-pencil',2,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(20,19,'Pens & Pencils',NULL,'pens-and-pencils',NULL,'assets/images/categories/stationery.svg','Pens & Pencils',NULL,1,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(21,19,'Notebooks & Registers',NULL,'registers-copies',NULL,'assets/images/categories/registers.svg','Notebooks & Registers',NULL,2,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(22,19,'Art & Craft',NULL,'art-and-craft',NULL,'assets/images/categories/stationery.svg','Art & Craft',NULL,3,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(23,19,'School Accessories',NULL,'school-accessories',NULL,'assets/images/categories/general.svg','School Accessories',NULL,4,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(24,19,'Office Supplies',NULL,'office-supplies',NULL,'assets/images/categories/stationery.svg','Office Supplies',NULL,5,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(25,19,'Geometry Boxes',NULL,'geometry-boxes',NULL,'assets/images/categories/stationery.svg','Geometry Boxes',NULL,6,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(26,19,'Files & Folders',NULL,'files-and-folders',NULL,'assets/images/categories/registers.svg','Files & Folders',NULL,7,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(27,NULL,'Groceries','راشن','groceries','Atta, rice, cooking oil, ghee, pulses, sugar, spices, tea, snacks, dairy and household essentials - your complete monthly rashan.','assets/images/categories/groceries.svg','Groceries','bi-basket',3,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(28,27,'Flour & Rice','آٹا اور چاول','flour-and-rice',NULL,'assets/images/categories/groceries.svg','Flour & Rice',NULL,1,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(29,27,'Cooking Oil & Ghee','تیل اور گھی','cooking-oil-and-ghee',NULL,'assets/images/categories/groceries.svg','Cooking Oil & Ghee',NULL,2,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(30,27,'Pulses & Lentils','دالیں','pulses-and-lentils',NULL,'assets/images/categories/groceries.svg','Pulses & Lentils',NULL,3,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(31,27,'Sugar & Salt','چینی اور نمک','sugar-and-salt',NULL,'assets/images/categories/groceries.svg','Sugar & Salt',NULL,4,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(32,27,'Spices','مصالحے','spices',NULL,'assets/images/categories/groceries.svg','Spices',NULL,5,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(33,27,'Tea & Beverages','چائے','tea-and-beverages',NULL,'assets/images/categories/groceries.svg','Tea & Beverages',NULL,6,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(34,27,'Snacks & Biscuits','بسکٹ','snacks-and-biscuits',NULL,'assets/images/categories/groceries.svg','Snacks & Biscuits',NULL,7,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(35,27,'Dairy Products','دودھ','dairy-products',NULL,'assets/images/categories/groceries.svg','Dairy Products',NULL,8,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(36,27,'Cleaning Products','صفائی','cleaning-products',NULL,'assets/images/categories/cleaning.svg','Cleaning Products',NULL,9,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(37,27,'Personal Care','ذاتی نگہداشت','personal-care',NULL,'assets/images/categories/cleaning.svg','Personal Care',NULL,10,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(38,27,'Household Essentials','گھریلو اشیاء','household-essentials',NULL,'assets/images/categories/cleaning.svg','Household Essentials',NULL,11,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(39,NULL,'General Items',NULL,'general-items','School bags, lunch boxes, water bottles, batteries, toys and gift items.','assets/images/categories/general.svg','General Items','bi-shop',4,1,1,1,'2026-11-10 13:52:28',NULL,NULL),
(40,39,'Bags & Lunch Boxes',NULL,'bags-and-lunch-boxes',NULL,'assets/images/categories/general.svg','Bags & Lunch Boxes',NULL,1,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(41,39,'Batteries & Electrical',NULL,'batteries-and-electrical',NULL,'assets/images/categories/general.svg','Batteries & Electrical',NULL,2,1,1,0,'2026-11-10 13:52:28',NULL,NULL),
(42,39,'Toys & Games',NULL,'toys-and-games',NULL,'assets/images/categories/general.svg','Toys & Games',NULL,3,1,1,0,'2026-11-10 13:52:28',NULL,NULL);
INSERT INTO `coupons` (`id`, `code`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `usage_limit`, `per_customer_limit`, `used_count`, `starts_at`, `ends_at`, `is_active`, `created_at`, `updated_at`) VALUES (1,'WELCOME10','10% off your first order (max Rs. 500)','percent',10.00,1000.00,500.00,NULL,1,0,NULL,NULL,1,'2026-11-10 13:52:28',NULL),
(2,'RASHAN500','Rs. 500 off monthly rashan orders above Rs. 10,000','fixed',500.00,10000.00,NULL,NULL,NULL,0,NULL,NULL,1,'2026-11-10 13:52:28',NULL),
(3,'FREEDEL','Free delivery on orders above Rs. 2,000','free_delivery',0.00,2000.00,NULL,NULL,NULL,0,NULL,NULL,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `customer_addresses` (`id`, `customer_id`, `label`, `recipient_name`, `phone`, `address_line`, `city`, `area`, `delivery_zone_id`, `landmark`, `is_default`, `created_at`, `updated_at`) VALUES (1,1,'Home','Ayesha Khan','03211234567','House 45, Block 10, Gulshan-e-Iqbal','Karachi','Gulshan-e-Iqbal',1,NULL,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `password_hash`, `is_registered`, `is_active`, `source`, `notes`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Ayesha Khan','03211234567','ayesha@example.com','$2y$10$Dzzdj9mBoB.Mcn9DBjiV.u6mw5ir/zrvnvjrB1CW5tInh/CAP7bXu',1,1,'web',NULL,NULL,'2026-11-10 13:52:28',NULL,NULL),
(2,'Muhammad Bilal','03331112233',NULL,NULL,0,1,'pos',NULL,NULL,'2026-11-10 13:52:28',NULL,NULL),
(3,'Sana Ahmed','03009876543',NULL,NULL,0,1,'pos',NULL,NULL,'2026-11-10 13:52:28',NULL,NULL),
(4,'Imran Siddiqui','03451239876',NULL,NULL,0,1,'pos',NULL,NULL,'2026-11-10 13:52:28',NULL,NULL),
(5,'Hina Raza','03121231234',NULL,NULL,0,1,'pos',NULL,NULL,'2026-11-10 13:52:28',NULL,NULL);
INSERT INTO `delivery_zones` (`id`, `city`, `area`, `delivery_fee`, `free_delivery_over`, `min_order_amount`, `estimated_time`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'Karachi','Gulshan-e-Iqbal',100.00,3000.00,0.00,'Same day',0,1,'2026-11-10 13:52:28',NULL),
(2,'Karachi','Gulistan-e-Johar',120.00,4000.00,0.00,'Same day',1,1,'2026-11-10 13:52:28',NULL),
(3,'Karachi','North Nazimabad',150.00,5000.00,500.00,'Same day',2,1,'2026-11-10 13:52:28',NULL),
(4,'Karachi','Saddar',150.00,5000.00,500.00,'24 hours',3,1,'2026-11-10 13:52:28',NULL),
(5,'Karachi','DHA',250.00,6000.00,1000.00,'24 hours',4,1,'2026-11-10 13:52:28',NULL),
(6,'Karachi','Clifton',250.00,6000.00,1000.00,'24 hours',5,1,'2026-11-10 13:52:28',NULL),
(7,'Karachi','Korangi',200.00,6000.00,1000.00,'24-48 hours',6,1,'2026-11-10 13:52:28',NULL),
(8,'Karachi','Malir',200.00,6000.00,1000.00,'24-48 hours',7,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `grocery_list_items` (`id`, `list_id`, `product_id`, `variation_id`, `quantity`, `note`) VALUES (1,1,57,2,2,NULL),
(2,1,60,8,1,NULL),
(3,1,71,12,1,NULL);
INSERT INTO `grocery_lists` (`id`, `customer_id`, `name`, `monthly_budget`, `notes`, `created_at`, `updated_at`) VALUES (1,1,'Monthly Rashan - Family',30000.00,'Deliver on 1st of every month','2026-11-10 13:52:28',NULL);
INSERT INTO `homepage_sections` (`id`, `section_type`, `title`, `subtitle`, `settings`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'hero',NULL,NULL,'{\"autoplay\":5000}',1,1,'2026-11-10 13:52:28',NULL),
(2,'categories','Shop by Categories','Everything for school and home','{\"limit\":8,\"source\":\"home\"}',2,1,'2026-11-10 13:52:28',NULL),
(3,'products','Featured Products','Hand-picked by Fahim Store','{\"source\":\"featured\",\"limit\":10,\"link\":\"shop\\/?featured=1\"}',3,1,'2026-11-10 13:52:28',NULL),
(4,'rashan_cta','Build Your Monthly Rashan','Make your grocery list once, reorder every month','[]',4,1,'2026-11-10 13:52:28',NULL),
(5,'products','Best Sellers','Most popular with our customers','{\"source\":\"best_sellers\",\"limit\":10,\"link\":\"shop\\/?sort=popular\"}',5,1,'2026-11-10 13:52:28',NULL),
(6,'promotions','Special Promotions','Deals, bundles and seasonal offers','{\"limit\":4}',6,1,'2026-11-10 13:52:28',NULL),
(7,'products','New Arrivals','Just added to the store','{\"source\":\"new\",\"limit\":10,\"link\":\"shop\\/?sort=newest\"}',7,1,'2026-11-10 13:52:28',NULL),
(8,'promo_banners',NULL,NULL,'[]',8,1,'2026-11-10 13:52:28',NULL),
(9,'products','Weekly Deals','Discounted prices this week','{\"source\":\"on_sale\",\"limit\":10,\"link\":\"shop\\/?on_sale=1\"}',9,1,'2026-11-10 13:52:28',NULL),
(10,'brands','Shop by Brand',NULL,'{\"limit\":12}',10,1,'2026-11-10 13:52:28',NULL),
(11,'products','Recommended for You','Trending products in your area','{\"source\":\"trending\",\"limit\":10}',11,1,'2026-11-10 13:52:28',NULL),
(12,'testimonials','What Our Customers Say',NULL,'[]',12,1,'2026-11-10 13:52:28',NULL),
(13,'newsletter','Get Deals in Your Inbox','Weekly grocery deals and school offers. No spam.','[]',13,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `inventory` (`id`, `product_id`, `variation_id`, `quantity`, `updated_at`) VALUES (1,1,0,20,'2026-11-10 13:52:28'),
(2,2,0,22,'2026-11-10 13:52:28'),
(3,3,0,45,'2026-11-10 13:52:28'),
(4,4,0,36,'2026-11-10 13:52:28'),
(5,5,0,58,'2026-11-10 13:52:28'),
(6,6,0,46,'2026-11-10 13:52:28'),
(7,7,0,53,'2026-11-10 13:52:28'),
(8,8,0,56,'2026-11-10 13:52:28'),
(9,9,0,19,'2026-11-10 13:52:28'),
(10,10,0,36,'2026-11-10 13:52:28'),
(11,11,0,73,'2026-11-10 13:52:28'),
(12,12,0,37,'2026-11-10 13:52:28'),
(13,13,0,40,'2026-11-10 13:52:28'),
(14,14,0,31,'2026-11-10 13:52:28'),
(15,15,0,41,'2026-11-10 13:52:28'),
(16,16,0,52,'2026-11-10 13:52:28'),
(17,17,0,29,'2026-11-10 13:52:28'),
(18,18,0,20,'2026-11-10 13:52:28'),
(19,19,0,57,'2026-11-10 13:52:28'),
(20,20,0,37,'2026-11-10 13:52:28'),
(21,21,0,31,'2026-11-10 13:52:28'),
(22,22,0,42,'2026-11-10 13:52:28'),
(23,23,0,47,'2026-11-10 13:52:28'),
(24,24,0,49,'2026-11-10 13:52:28'),
(25,25,0,50,'2026-11-10 13:52:28'),
(26,26,0,52,'2026-11-10 13:52:28'),
(27,27,0,34,'2026-11-10 13:52:28'),
(28,28,0,42,'2026-11-10 13:52:28'),
(29,29,0,46,'2026-11-10 13:52:28'),
(30,30,0,50,'2026-11-10 13:52:28'),
(31,31,0,25,'2026-11-10 13:52:28'),
(32,32,0,8,'2026-11-10 13:52:28'),
(33,33,0,12,'2026-11-10 13:52:28'),
(34,34,0,20,'2026-11-10 13:52:28'),
(35,35,0,10,'2026-11-10 13:52:28'),
(36,36,0,14,'2026-11-10 13:52:28'),
(37,37,0,30,'2026-11-10 13:52:28'),
(38,38,0,9,'2026-11-10 13:52:28'),
(39,39,0,80,'2026-11-10 13:52:28'),
(40,40,0,60,'2026-11-10 13:52:28'),
(41,41,0,100,'2026-11-10 13:52:28'),
(42,42,0,22,'2026-11-10 13:52:28'),
(43,43,0,35,'2026-11-10 13:52:28'),
(44,44,0,18,'2026-11-10 13:52:28'),
(45,45,0,120,'2026-11-10 13:52:28'),
(46,46,0,70,'2026-11-10 13:52:28'),
(47,47,0,40,'2026-11-10 13:52:28'),
(48,48,0,45,'2026-11-10 13:52:28'),
(49,49,0,30,'2026-11-10 13:52:28'),
(50,50,0,50,'2026-11-10 13:52:28'),
(51,51,0,3,'2026-11-10 13:52:28'),
(52,52,0,15,'2026-11-10 13:52:28'),
(53,53,0,12,'2026-11-10 13:52:28'),
(54,54,0,20,'2026-11-10 13:52:28'),
(55,55,0,25,'2026-11-10 13:52:28'),
(56,56,0,10,'2026-11-10 13:52:28'),
(57,57,1,60,'2026-11-10 13:52:28'),
(58,57,2,47,'2026-11-10 13:52:28'),
(59,57,3,24,'2026-11-10 13:52:28'),
(60,58,4,80,'2026-11-10 13:52:28'),
(61,58,5,40,'2026-11-10 13:52:28'),
(62,59,0,100,'2026-11-10 13:52:28'),
(63,60,6,70,'2026-11-10 13:52:28'),
(64,60,7,29,'2026-11-10 13:52:28'),
(65,60,8,24,'2026-11-10 13:52:28'),
(66,61,0,45,'2026-11-10 13:52:28'),
(67,62,0,60,'2026-11-10 13:52:28'),
(68,63,0,50,'2026-11-10 13:52:28'),
(69,64,0,55,'2026-11-10 13:52:28'),
(70,65,0,35,'2026-11-10 13:52:28'),
(71,66,9,100,'2026-11-10 13:52:28'),
(72,66,10,40,'2026-11-10 13:52:28'),
(73,67,0,120,'2026-11-10 13:52:28'),
(74,68,0,90,'2026-11-10 13:52:28'),
(75,69,0,60,'2026-11-10 13:52:28'),
(76,70,0,55,'2026-11-10 13:52:28'),
(77,71,11,48,'2026-11-10 13:52:28'),
(78,71,12,29,'2026-11-10 13:52:28'),
(79,72,0,40,'2026-11-10 13:52:28'),
(80,73,0,25,'2026-11-10 13:52:28'),
(81,74,0,80,'2026-11-10 13:52:28'),
(82,75,0,70,'2026-11-10 13:52:28'),
(83,76,0,40,'2026-11-10 13:52:28'),
(84,77,13,59,'2026-11-10 13:52:28'),
(85,77,14,38,'2026-11-10 13:52:28'),
(86,77,15,20,'2026-11-10 13:52:28'),
(87,78,0,25,'2026-11-10 13:52:28'),
(88,79,0,45,'2026-11-10 13:52:28'),
(89,80,0,60,'2026-11-10 13:52:28'),
(90,81,0,55,'2026-11-10 13:52:28'),
(91,82,0,35,'2026-11-10 13:52:28'),
(92,83,0,30,'2026-11-10 13:52:28'),
(93,84,0,4,'2026-11-10 13:52:28');
INSERT INTO `inventory_movements` (`id`, `product_id`, `variation_id`, `movement_type`, `qty_change`, `balance_after`, `unit_cost`, `reference_type`, `reference_id`, `reason`, `admin_id`, `created_at`) VALUES (1,1,0,'opening',20,20,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(2,2,0,'opening',22,22,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(3,3,0,'opening',45,45,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(4,4,0,'opening',36,36,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(5,5,0,'opening',58,58,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(6,6,0,'opening',46,46,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(7,7,0,'opening',53,53,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(8,8,0,'opening',56,56,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(9,9,0,'opening',19,19,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(10,10,0,'opening',18,18,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(11,11,0,'opening',55,55,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(12,12,0,'opening',37,37,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(13,13,0,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(14,14,0,'opening',31,31,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(15,15,0,'opening',41,41,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(16,16,0,'opening',52,52,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(17,17,0,'opening',29,29,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(18,18,0,'opening',20,20,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(19,19,0,'opening',57,57,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(20,20,0,'opening',37,37,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(21,21,0,'opening',32,32,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(22,22,0,'opening',43,43,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(23,23,0,'opening',47,47,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(24,24,0,'opening',49,49,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(25,25,0,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(26,26,0,'opening',52,52,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(27,27,0,'opening',34,34,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(28,28,0,'opening',42,42,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(29,29,0,'opening',46,46,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(30,30,0,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(31,31,0,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(32,32,0,'opening',8,8,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(33,33,0,'opening',12,12,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(34,34,0,'opening',20,20,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(35,35,0,'opening',10,10,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(36,36,0,'opening',14,14,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(37,37,0,'opening',30,30,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(38,38,0,'opening',9,9,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(39,39,0,'opening',80,80,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(40,40,0,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(41,41,0,'opening',100,100,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(42,42,0,'opening',22,22,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(43,43,0,'opening',35,35,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(44,44,0,'opening',18,18,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(45,45,0,'opening',120,120,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(46,46,0,'opening',70,70,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(47,47,0,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(48,48,0,'opening',45,45,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(49,49,0,'opening',30,30,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(50,50,0,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(51,51,0,'opening',3,3,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(52,52,0,'opening',15,15,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(53,53,0,'opening',12,12,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(54,54,0,'opening',20,20,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(55,55,0,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(56,56,0,'opening',10,10,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(57,57,1,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(58,57,2,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(59,57,3,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(60,58,4,'opening',80,80,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(61,58,5,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(62,59,0,'opening',100,100,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(63,60,6,'opening',70,70,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(64,60,7,'opening',30,30,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(65,60,8,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(66,61,0,'opening',45,45,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(67,62,0,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(68,63,0,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(69,64,0,'opening',55,55,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(70,65,0,'opening',35,35,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(71,66,9,'opening',100,100,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(72,66,10,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(73,67,0,'opening',120,120,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(74,68,0,'opening',90,90,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(75,69,0,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(76,70,0,'opening',55,55,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(77,71,11,'opening',50,50,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(78,71,12,'opening',30,30,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(79,72,0,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(80,73,0,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(81,74,0,'opening',80,80,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(82,75,0,'opening',70,70,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(83,76,0,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(84,77,13,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(85,77,14,'opening',40,40,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(86,77,15,'opening',20,20,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(87,78,0,'opening',25,25,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(88,79,0,'opening',45,45,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(89,80,0,'opening',60,60,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(90,81,0,'opening',55,55,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(91,82,0,'opening',35,35,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(92,83,0,'opening',30,30,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(93,84,0,'opening',4,4,NULL,'manual',NULL,'Opening stock',1,'2026-11-10 13:52:28'),
(94,10,0,'purchase',20,38,240.00,'purchase',1,'Purchase #1 (UB-2231)',NULL,'2026-11-10 13:52:28'),
(95,11,0,'purchase',20,75,240.00,'purchase',1,'Purchase #1 (UB-2231)',NULL,'2026-11-10 13:52:28'),
(96,57,2,'sale',-2,48,1309.00,'order',1,'Order FS261110-51309',1,'2026-11-10 13:52:28'),
(97,60,8,'sale',-1,24,2295.00,'order',1,'Order FS261110-51309',1,'2026-11-10 13:52:28'),
(98,71,12,'sale',-1,29,1742.50,'order',1,'Order FS261110-51309',1,'2026-11-10 13:52:28'),
(99,10,0,'sale',-2,36,240.00,'order',2,'Order FS261110-67809',1,'2026-11-10 13:52:28'),
(100,11,0,'sale',-2,73,240.00,'order',2,'Order FS261110-67809',1,'2026-11-10 13:52:28'),
(101,77,14,'sale',-2,38,697.00,'order',3,'Order FS261110-59704',1,'2026-11-10 13:52:28'),
(102,57,3,'sale',-1,24,2592.50,'order',4,'Order FS261110-65176',1,'2026-11-10 13:52:28'),
(103,71,11,'sale',-2,48,833.00,'order',4,'Order FS261110-65176',1,'2026-11-10 13:52:28'),
(104,21,0,'sale',-1,31,344.00,'order',5,'Order FS261110-48407',1,'2026-11-10 13:52:28'),
(105,22,0,'sale',-1,42,336.00,'order',5,'Order FS261110-48407',1,'2026-11-10 13:52:28'),
(106,57,2,'sale',-1,47,1309.00,'order',6,'Order FS261110-24429',1,'2026-11-10 13:52:28'),
(107,60,7,'sale',-1,29,1402.50,'order',6,'Order FS261110-24429',1,'2026-11-10 13:52:28'),
(108,77,13,'sale',-1,59,357.00,'order',6,'Order FS261110-24429',1,'2026-11-10 13:52:28');
INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `link`, `reference_id`, `is_read`, `created_at`) VALUES (1,'new_order','Welcome to Fahim Store admin','Sample data has been installed. Update settings, delivery areas and products to get started.','settings.php',NULL,0,'2026-11-10 13:52:28');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variation_id`, `product_name`, `variation_name`, `sku`, `unit`, `unit_price`, `cost_price`, `quantity`, `line_total`, `is_picked`) VALUES (1,1,57,2,'Sunridge Chakki Atta (Wheat Flour)','10 kg','FS-1057-2','kg',1490.00,1309.00,2,2980.00,0),
(2,1,60,8,'Dalda Cooking Oil','5 Litre Tin','FS-1060-3','litre',2620.00,2295.00,1,2620.00,0),
(3,1,71,12,'Tapal Danedar Tea','950g','FS-1071-2','packet',1980.00,1742.50,1,1980.00,0),
(4,2,10,0,'English Text Book Class 5',NULL,'FS-1010','book',300.00,240.00,2,600.00,0),
(5,2,11,0,'Mathematics Text Book Class 5',NULL,'FS-1011','book',320.00,240.00,2,640.00,0),
(6,3,77,14,'Surf Excel Washing Powder','1 kg','FS-1077-2','kg',790.00,697.00,2,1580.00,0),
(7,4,57,3,'Sunridge Chakki Atta (Wheat Flour)','20 kg','FS-1057-3','kg',2950.00,2592.50,1,2950.00,0),
(8,4,71,11,'Tapal Danedar Tea','430g','FS-1071-1','packet',940.00,833.00,2,1880.00,0),
(9,5,21,0,'Physics Text Book Class 9',NULL,'FS-1021','book',430.00,344.00,1,430.00,0),
(10,5,22,0,'Chemistry Text Book Class 9',NULL,'FS-1022','book',420.00,336.00,1,420.00,0),
(11,6,57,2,'Sunridge Chakki Atta (Wheat Flour)','10 kg','FS-1057-2','kg',1490.00,1309.00,1,1490.00,0),
(12,6,60,7,'Dalda Cooking Oil','3 Litre Bottle','FS-1060-2','litre',1599.00,1402.50,1,1599.00,0),
(13,6,77,13,'Surf Excel Washing Powder','500g','FS-1077-1','kg',420.00,357.00,1,420.00,0);
INSERT INTO `order_status_history` (`id`, `order_id`, `from_status`, `to_status`, `note`, `is_internal`, `admin_id`, `created_at`) VALUES (1,1,NULL,'pending','Order placed online',0,1,'2026-10-29 12:52:28'),
(2,1,'pending','confirmed',NULL,0,1,'2026-11-10 13:52:28'),
(3,1,'confirmed','processing',NULL,0,1,'2026-11-10 13:52:28'),
(4,1,'processing','out_for_delivery',NULL,0,1,'2026-11-10 13:52:28'),
(5,1,'out_for_delivery','completed',NULL,0,1,'2026-11-10 13:52:28'),
(6,2,NULL,'pending','Order placed online',0,1,'2026-11-01 10:52:28'),
(7,2,'pending','confirmed',NULL,0,1,'2026-11-10 13:52:28'),
(8,2,'confirmed','processing',NULL,0,1,'2026-11-10 13:52:28'),
(9,2,'processing','ready_for_pickup',NULL,0,1,'2026-11-10 13:52:28'),
(10,2,'ready_for_pickup','completed',NULL,0,1,'2026-11-10 13:52:28'),
(11,3,NULL,'pending','Order placed online',0,1,'2026-11-04 09:52:28'),
(12,3,'pending','confirmed',NULL,0,1,'2026-11-10 13:52:28'),
(13,3,'confirmed','processing',NULL,0,1,'2026-11-10 13:52:28'),
(14,3,'processing','out_for_delivery',NULL,0,1,'2026-11-10 13:52:28'),
(15,3,'out_for_delivery','completed',NULL,0,1,'2026-11-10 13:52:28'),
(16,4,NULL,'pending','Order placed online',0,1,'2026-11-08 10:52:28'),
(17,4,'pending','confirmed',NULL,0,1,'2026-11-10 13:52:28'),
(18,4,'confirmed','processing',NULL,0,1,'2026-11-10 13:52:28'),
(19,4,'processing','ready_for_pickup',NULL,0,1,'2026-11-10 13:52:28'),
(20,5,NULL,'pending','Order placed online',0,1,'2026-11-09 12:52:28'),
(21,5,'pending','confirmed',NULL,0,1,'2026-11-10 13:52:28'),
(22,5,'confirmed','processing',NULL,0,1,'2026-11-10 13:52:28'),
(23,6,NULL,'pending','Order placed online',0,1,'2026-11-10 06:52:28');
INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `channel`, `order_type`, `fulfillment`, `status`, `customer_name`, `customer_phone`, `customer_email`, `address_line`, `city`, `area`, `delivery_zone_id`, `delivery_instructions`, `pickup_at`, `subtotal`, `discount_amount`, `coupon_code`, `delivery_fee`, `total`, `payment_method`, `payment_status`, `payment_reference`, `customer_notes`, `admin_notes`, `stock_deducted`, `grocery_list_id`, `created_by_admin`, `ip_address`, `created_at`, `updated_at`, `completed_at`) VALUES (1,'FS261110-51309',1,'web','rashan','delivery','completed','Ayesha Khan','03211234567',NULL,'House 12, Street 4','Karachi','Gulshan-e-Iqbal',1,NULL,NULL,7580.00,0.00,NULL,0.00,7580.00,'cod','paid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-10-29 12:52:28','2026-11-10 13:52:28','2026-11-10 13:52:28'),
(2,'FS261110-67809',2,'web','regular','pickup','completed','Muhammad Bilal','03331112233',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 12:00:00',1240.00,0.00,NULL,0.00,1240.00,'cash_pickup','paid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-11-01 10:52:28','2026-11-10 13:52:28','2026-11-10 13:52:28'),
(3,'FS261110-59704',3,'web','regular','delivery','completed','Sana Ahmed','03009876543',NULL,'House 12, Street 4','Karachi','Gulshan-e-Iqbal',1,NULL,NULL,1580.00,0.00,NULL,100.00,1680.00,'cod','paid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-11-04 09:52:28','2026-11-10 13:52:28','2026-11-10 13:52:28'),
(4,'FS261110-65176',4,'web','rashan','pickup','ready_for_pickup','Imran Siddiqui','03451239876',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 12:00:00',4830.00,0.00,NULL,0.00,4830.00,'cash_pickup','unpaid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-11-08 10:52:28','2026-11-10 13:52:28',NULL),
(5,'FS261110-48407',5,'web','regular','delivery','processing','Hina Raza','03121231234',NULL,'House 12, Street 4','Karachi','Gulshan-e-Iqbal',1,NULL,NULL,850.00,0.00,NULL,100.00,950.00,'cod','unpaid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-11-09 12:52:28','2026-11-10 13:52:28',NULL),
(6,'FS261110-24429',1,'web','rashan','delivery','pending','Ayesha Khan','03211234567',NULL,'House 12, Street 4','Karachi','Gulshan-e-Iqbal',1,NULL,NULL,3509.00,0.00,NULL,0.00,3509.00,'cod','unpaid',NULL,NULL,NULL,1,NULL,NULL,'0.0.0.0','2026-11-10 06:52:28','2026-11-10 13:52:28',NULL);
INSERT INTO `page_faqs` (`id`, `page_id`, `question`, `answer`, `sort_order`) VALUES (1,6,'Do you offer cash on delivery?','Yes. Cash on Delivery is available for all home delivery orders.',0),
(2,6,'How does the Monthly Rashan builder work?','Open Monthly Rashan, add the grocery items you need, set your monthly budget and save the list. Next month you can reorder it with one click.',1),
(3,6,'Can I pick up my order from the store?','Yes. Select Store Pickup at checkout and choose a convenient time. We will notify you when your order is ready.',2),
(4,6,'Which areas do you deliver to?','We currently deliver across Karachi. Select your area at checkout to see the delivery charges.',3);
INSERT INTO `pages` (`id`, `title`, `slug`, `content`, `show_in_footer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'About Us','about-us','<h2>Welcome to Fahim Store</h2><p>Fahim Store is a trusted neighbourhood store for <strong>school books, stationery, groceries and household essentials</strong>. Our online store brings the same trusted products and prices to your doorstep.</p><h3>Why shop with us?</h3><ul><li>Original books from all major publishers and text book boards</li><li>Complete monthly rashan with budget planning</li><li>Home delivery or store pickup</li><li>Cash on Delivery, bank transfer, Easypaisa and JazzCash</li></ul>',1,0,1,'2026-11-10 13:52:28',NULL),
(2,'Delivery Information','delivery-information','<h2>Delivery</h2><p>We deliver across Karachi. Delivery charges depend on your area and are shown at checkout. Orders above the free delivery limit are delivered free.</p><h2>Store Pickup</h2><p>Choose a pickup time at checkout and collect your packed order from our store.</p>',1,1,1,'2026-11-10 13:52:28',NULL),
(3,'Return & Exchange Policy','return-policy','<p>Unused stationery and books can be exchanged within 7 days with the receipt. Grocery items can be returned at the time of delivery if damaged or expired.</p>',1,2,1,'2026-11-10 13:52:28',NULL),
(4,'Privacy Policy','privacy-policy','<p>We only use your name, phone number and address to process and deliver your orders. We never sell your information.</p>',1,3,1,'2026-11-10 13:52:28',NULL),
(5,'Terms & Conditions','terms-and-conditions','<p>Prices and availability may change without notice. Orders are confirmed by phone or WhatsApp before dispatch.</p>',1,4,1,'2026-11-10 13:52:28',NULL),
(6,'Frequently Asked Questions','faq','<p>Answers to the questions our customers ask most.</p>',1,5,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `payment_methods` (`id`, `code`, `name`, `instructions`, `applies_to`, `requires_reference`, `is_active`, `show_in_pos`, `sort_order`) VALUES (1,'cod','Cash on Delivery','Pay in cash when your order arrives.','delivery',0,1,1,0),
(2,'cash_pickup','Cash on Pickup','Pay at the counter when you collect your order.','pickup',0,1,1,1),
(3,'bank_transfer','Bank Transfer','Meezan Bank - Fahim Store\nAccount #: 0101-0000000000\nIBAN: PK00MEZN0001010000000000\nPlease send the receipt on WhatsApp 0300-1234567.','both',1,1,0,2),
(4,'easypaisa','Easypaisa','Send payment to Easypaisa account 0300-1234567 (Fahim Store) and enter the transaction ID.','both',1,1,1,3),
(5,'jazzcash','JazzCash','Send payment to JazzCash account 0300-1234567 (Fahim Store) and enter the transaction ID.','both',1,1,1,4),
(6,'cash','Cash','Cash payment at the counter.','pos',0,1,1,5),
(7,'card','Debit/Credit Card','Card payment on the store POS machine.','pos',0,1,1,6);
INSERT INTO `payments` (`id`, `order_id`, `method`, `amount`, `reference`, `status`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES (1,1,'cod',7580.00,NULL,'received',1,NULL,'2026-11-10 13:52:28','2026-11-10 13:52:28'),
(2,2,'cash_pickup',1240.00,NULL,'received',1,NULL,'2026-11-10 13:52:28','2026-11-10 13:52:28'),
(3,3,'cod',1680.00,NULL,'received',1,NULL,'2026-11-10 13:52:28','2026-11-10 13:52:28'),
(4,4,'cash_pickup',4830.00,NULL,'pending',1,NULL,'2026-11-10 13:52:28',NULL),
(5,5,'cod',950.00,NULL,'pending',1,NULL,'2026-11-10 13:52:28',NULL),
(6,6,'cod',3509.00,NULL,'pending',1,NULL,'2026-11-10 13:52:28',NULL);
INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES (1,2),
(1,3),
(2,2),
(2,3),
(3,2),
(3,3),
(4,2),
(4,4),
(5,2),
(5,4),
(6,2),
(6,5),
(7,2),
(7,5),
(8,2),
(8,6),
(9,2),
(9,6),
(10,2),
(10,7),
(11,2),
(11,7),
(12,2),
(12,7),
(13,2),
(13,7),
(14,2),
(14,7),
(15,2),
(15,8),
(16,2),
(16,8),
(17,2),
(17,9),
(18,2),
(18,9),
(19,2),
(19,10),
(20,2),
(20,10),
(21,2),
(21,11),
(22,2),
(22,11),
(23,2),
(23,11),
(24,2),
(24,11),
(25,2),
(25,12),
(26,2),
(26,12),
(27,2),
(27,12),
(28,2),
(28,13),
(29,2),
(29,13),
(30,2),
(30,13),
(31,7),
(31,14),
(32,14),
(32,17),
(33,7),
(33,15),
(34,16),
(35,16),
(36,16),
(36,18),
(37,7),
(37,17),
(38,18),
(39,20),
(40,20),
(41,20),
(42,20),
(42,22),
(43,22),
(44,22),
(45,21),
(46,21),
(47,21),
(47,24),
(48,21),
(49,23),
(49,25),
(50,24),
(50,26),
(51,20),
(51,24),
(52,24),
(53,23),
(53,40),
(54,23),
(54,40),
(55,41),
(56,42),
(57,28),
(58,28),
(59,28),
(60,29),
(61,29),
(62,30),
(63,30),
(64,30),
(65,30),
(66,31),
(67,31),
(68,32),
(69,32),
(70,32),
(71,33),
(72,33),
(73,35),
(74,35),
(75,34),
(76,34),
(77,36),
(78,36),
(79,36),
(80,37),
(81,37),
(82,38),
(83,34),
(84,38);
INSERT INTO `product_images` (`id`, `product_id`, `image`, `thumb`, `alt_text`, `sort_order`, `is_primary`, `created_at`) VALUES (1,1,'products/sample/english-reader-text-book-class-1.svg','products/sample/english-reader-text-book-class-1.svg','English Reader Text Book Class 1',0,1,'2026-11-10 13:52:28'),
(2,2,'products/sample/urdu-qaida-text-book-class-1.svg','products/sample/urdu-qaida-text-book-class-1.svg','Urdu Qaida Text Book Class 1',0,1,'2026-11-10 13:52:28'),
(3,3,'products/sample/mathematics-text-book-class-1.svg','products/sample/mathematics-text-book-class-1.svg','Mathematics Text Book Class 1',0,1,'2026-11-10 13:52:28'),
(4,4,'products/sample/english-reader-text-book-class-2.svg','products/sample/english-reader-text-book-class-2.svg','English Reader Text Book Class 2',0,1,'2026-11-10 13:52:28'),
(5,5,'products/sample/mathematics-text-book-class-2.svg','products/sample/mathematics-text-book-class-2.svg','Mathematics Text Book Class 2',0,1,'2026-11-10 13:52:28'),
(6,6,'products/sample/general-science-text-book-class-3.svg','products/sample/general-science-text-book-class-3.svg','General Science Text Book Class 3',0,1,'2026-11-10 13:52:28'),
(7,7,'products/sample/mathematics-text-book-class-3.svg','products/sample/mathematics-text-book-class-3.svg','Mathematics Text Book Class 3',0,1,'2026-11-10 13:52:28'),
(8,8,'products/sample/social-studies-text-book-class-4.svg','products/sample/social-studies-text-book-class-4.svg','Social Studies Text Book Class 4',0,1,'2026-11-10 13:52:28'),
(9,9,'products/sample/english-grammar-text-book-class-4.svg','products/sample/english-grammar-text-book-class-4.svg','English Grammar Text Book Class 4',0,1,'2026-11-10 13:52:28'),
(10,10,'products/sample/english-text-book-class-5.svg','products/sample/english-text-book-class-5.svg','English Text Book Class 5',0,1,'2026-11-10 13:52:28'),
(11,11,'products/sample/mathematics-text-book-class-5.svg','products/sample/mathematics-text-book-class-5.svg','Mathematics Text Book Class 5',0,1,'2026-11-10 13:52:28'),
(12,12,'products/sample/general-science-text-book-class-5.svg','products/sample/general-science-text-book-class-5.svg','General Science Text Book Class 5',0,1,'2026-11-10 13:52:28'),
(13,13,'products/sample/urdu-text-book-class-5.svg','products/sample/urdu-text-book-class-5.svg','Urdu Text Book Class 5',0,1,'2026-11-10 13:52:28'),
(14,14,'products/sample/islamiat-text-book-class-5.svg','products/sample/islamiat-text-book-class-5.svg','Islamiat Text Book Class 5',0,1,'2026-11-10 13:52:28'),
(15,15,'products/sample/mathematics-text-book-class-6.svg','products/sample/mathematics-text-book-class-6.svg','Mathematics Text Book Class 6',0,1,'2026-11-10 13:52:28'),
(16,16,'products/sample/computer-studies-text-book-class-6.svg','products/sample/computer-studies-text-book-class-6.svg','Computer Studies Text Book Class 6',0,1,'2026-11-10 13:52:28'),
(17,17,'products/sample/science-text-book-class-7.svg','products/sample/science-text-book-class-7.svg','Science Text Book Class 7',0,1,'2026-11-10 13:52:28'),
(18,18,'products/sample/english-text-book-class-7.svg','products/sample/english-text-book-class-7.svg','English Text Book Class 7',0,1,'2026-11-10 13:52:28'),
(19,19,'products/sample/mathematics-text-book-class-8.svg','products/sample/mathematics-text-book-class-8.svg','Mathematics Text Book Class 8',0,1,'2026-11-10 13:52:28'),
(20,20,'products/sample/science-text-book-class-8.svg','products/sample/science-text-book-class-8.svg','Science Text Book Class 8',0,1,'2026-11-10 13:52:28'),
(21,21,'products/sample/physics-text-book-class-9.svg','products/sample/physics-text-book-class-9.svg','Physics Text Book Class 9',0,1,'2026-11-10 13:52:28'),
(22,22,'products/sample/chemistry-text-book-class-9.svg','products/sample/chemistry-text-book-class-9.svg','Chemistry Text Book Class 9',0,1,'2026-11-10 13:52:28'),
(23,23,'products/sample/biology-text-book-class-9.svg','products/sample/biology-text-book-class-9.svg','Biology Text Book Class 9',0,1,'2026-11-10 13:52:28'),
(24,24,'products/sample/computer-science-text-book-class-9.svg','products/sample/computer-science-text-book-class-9.svg','Computer Science Text Book Class 9',0,1,'2026-11-10 13:52:28'),
(25,25,'products/sample/mathematics-text-book-class-10.svg','products/sample/mathematics-text-book-class-10.svg','Mathematics Text Book Class 10',0,1,'2026-11-10 13:52:28'),
(26,26,'products/sample/physics-text-book-class-10.svg','products/sample/physics-text-book-class-10.svg','Physics Text Book Class 10',0,1,'2026-11-10 13:52:28'),
(27,27,'products/sample/pakistan-studies-text-book-class-10.svg','products/sample/pakistan-studies-text-book-class-10.svg','Pakistan Studies Text Book Class 10',0,1,'2026-11-10 13:52:28'),
(28,28,'products/sample/physics-part-1-text-book-intermediate.svg','products/sample/physics-part-1-text-book-intermediate.svg','Physics Part 1 Text Book Intermediate',0,1,'2026-11-10 13:52:28'),
(29,29,'products/sample/chemistry-part-1-text-book-intermediate.svg','products/sample/chemistry-part-1-text-book-intermediate.svg','Chemistry Part 1 Text Book Intermediate',0,1,'2026-11-10 13:52:28'),
(30,30,'products/sample/english-book-1-text-book-intermediate.svg','products/sample/english-book-1-text-book-intermediate.svg','English Book 1 Text Book Intermediate',0,1,'2026-11-10 13:52:28'),
(31,31,'products/sample/new-oxford-modern-english-book-5.svg','products/sample/new-oxford-modern-english-book-5.svg','New Oxford Modern English Book 5',0,1,'2026-11-10 13:52:28'),
(32,32,'products/sample/oxford-shorter-english-dictionary.svg','products/sample/oxford-shorter-english-dictionary.svg','Oxford Shorter English Dictionary',0,1,'2026-11-10 13:52:28'),
(33,33,'products/sample/cambridge-primary-mathematics-learner-book-5.svg','products/sample/cambridge-primary-mathematics-learner-book-5.svg','Cambridge Primary Mathematics Learner Book 5',0,1,'2026-11-10 13:52:28'),
(34,34,'products/sample/holy-quran-13-lines-hafzi.svg','products/sample/holy-quran-13-lines-hafzi.svg','Holy Quran 13 Lines (Hafzi)',0,1,'2026-11-10 13:52:28'),
(35,35,'products/sample/holy-quran-16-lines-with-urdu-translation.svg','products/sample/holy-quran-16-lines-with-urdu-translation.svg','Holy Quran 16 Lines with Urdu Translation',0,1,'2026-11-10 13:52:28'),
(36,36,'products/sample/seerat-un-nabi-for-kids.svg','products/sample/seerat-un-nabi-for-kids.svg','Seerat-un-Nabi for Kids',0,1,'2026-11-10 13:52:28'),
(37,37,'products/sample/general-knowledge-book-5.svg','products/sample/general-knowledge-book-5.svg','General Knowledge Book 5',0,1,'2026-11-10 13:52:28'),
(38,38,'products/sample/moral-stories-for-children-set-of-6.svg','products/sample/moral-stories-for-children-set-of-6.svg','Moral Stories for Children (Set of 6)',0,1,'2026-11-10 13:52:28'),
(39,39,'products/sample/dollar-clipper-ball-pen-blue-pack-of-10.svg','products/sample/dollar-clipper-ball-pen-blue-pack-of-10.svg','Dollar Clipper Ball Pen Blue (Pack of 10)',0,1,'2026-11-10 13:52:28'),
(40,40,'products/sample/piano-ball-pen-black-pack-of-10.svg','products/sample/piano-ball-pen-black-pack-of-10.svg','Piano Ball Pen Black (Pack of 10)',0,1,'2026-11-10 13:52:28'),
(41,41,'products/sample/deer-hb-pencils-box-of-12.svg','products/sample/deer-hb-pencils-box-of-12.svg','Deer HB Pencils (Box of 12)',0,1,'2026-11-10 13:52:28'),
(42,42,'products/sample/faber-castell-colour-pencils-24-shades.svg','products/sample/faber-castell-colour-pencils-24-shades.svg','Faber-Castell Colour Pencils (24 Shades)',0,1,'2026-11-10 13:52:28'),
(43,43,'products/sample/picasso-wax-crayons-16-colours.svg','products/sample/picasso-wax-crayons-16-colours.svg','Picasso Wax Crayons (16 Colours)',0,1,'2026-11-10 13:52:28'),
(44,44,'products/sample/picasso-water-colours-12-shades.svg','products/sample/picasso-water-colours-12-shades.svg','Picasso Water Colours (12 Shades)',0,1,'2026-11-10 13:52:28'),
(45,45,'products/sample/solo-register-200-pages-single-line.svg','products/sample/solo-register-200-pages-single-line.svg','Solo Register 200 Pages (Single Line)',0,1,'2026-11-10 13:52:28'),
(46,46,'products/sample/lion-copy-80-pages-pack-of-6.svg','products/sample/lion-copy-80-pages-pack-of-6.svg','Lion Copy 80 Pages (Pack of 6)',0,1,'2026-11-10 13:52:28'),
(47,47,'products/sample/spiral-notebook-a4-ruled.svg','products/sample/spiral-notebook-a4-ruled.svg','Spiral Notebook A4 (Ruled)',0,1,'2026-11-10 13:52:28'),
(48,48,'products/sample/maths-copy-square-lines-pack-of-4.svg','products/sample/maths-copy-square-lines-pack-of-4.svg','Maths Copy Square Lines (Pack of 4)',0,1,'2026-11-10 13:52:28'),
(49,49,'products/sample/deer-geometry-box-complete-set.svg','products/sample/deer-geometry-box-complete-set.svg','Deer Geometry Box (Complete Set)',0,1,'2026-11-10 13:52:28'),
(50,50,'products/sample/plastic-file-folder-a4-pack-of-5.svg','products/sample/plastic-file-folder-a4-pack-of-5.svg','Plastic File Folder A4 (Pack of 5)',0,1,'2026-11-10 13:52:28'),
(51,51,'products/sample/dollar-permanent-marker-pack-of-4.svg','products/sample/dollar-permanent-marker-pack-of-4.svg','Dollar Permanent Marker (Pack of 4)',0,1,'2026-11-10 13:52:28'),
(52,52,'products/sample/a4-paper-ream-80gsm-500-sheets.svg','products/sample/a4-paper-ream-80gsm-500-sheets.svg','A4 Paper Ream 80gsm (500 Sheets)',0,1,'2026-11-10 13:52:28'),
(53,53,'products/sample/school-bag-waterproof-medium.svg','products/sample/school-bag-waterproof-medium.svg','School Bag Waterproof (Medium)',0,1,'2026-11-10 13:52:28'),
(54,54,'products/sample/lunch-box-with-water-bottle.svg','products/sample/lunch-box-with-water-bottle.svg','Lunch Box with Water Bottle',0,1,'2026-11-10 13:52:28'),
(55,55,'products/sample/energizer-aa-batteries-pack-of-4.svg','products/sample/energizer-aa-batteries-pack-of-4.svg','Energizer AA Batteries (Pack of 4)',0,1,'2026-11-10 13:52:28'),
(56,56,'products/sample/ludo-and-snakes-ladders-board-game.svg','products/sample/ludo-and-snakes-ladders-board-game.svg','Ludo & Snakes Ladders Board Game',0,1,'2026-11-10 13:52:28'),
(57,57,'products/sample/sunridge-chakki-atta-wheat-flour.svg','products/sample/sunridge-chakki-atta-wheat-flour.svg','Sunridge Chakki Atta (Wheat Flour)',0,1,'2026-11-10 13:52:28'),
(58,58,'products/sample/guard-super-basmati-rice.svg','products/sample/guard-super-basmati-rice.svg','Guard Super Basmati Rice',0,1,'2026-11-10 13:52:28'),
(59,59,'products/sample/sella-rice-loose.svg','products/sample/sella-rice-loose.svg','Sella Rice (Loose)',0,1,'2026-11-10 13:52:28'),
(60,60,'products/sample/dalda-cooking-oil.svg','products/sample/dalda-cooking-oil.svg','Dalda Cooking Oil',0,1,'2026-11-10 13:52:28'),
(61,61,'products/sample/habib-banaspati-ghee-1-kg.svg','products/sample/habib-banaspati-ghee-1-kg.svg','Habib Banaspati Ghee 1 kg',0,1,'2026-11-10 13:52:28'),
(62,62,'products/sample/masoor-dal-1-kg.svg','products/sample/masoor-dal-1-kg.svg','Masoor Dal 1 kg',0,1,'2026-11-10 13:52:28'),
(63,63,'products/sample/moong-dal-1-kg.svg','products/sample/moong-dal-1-kg.svg','Moong Dal 1 kg',0,1,'2026-11-10 13:52:28'),
(64,64,'products/sample/chana-dal-1-kg.svg','products/sample/chana-dal-1-kg.svg','Chana Dal 1 kg',0,1,'2026-11-10 13:52:28'),
(65,65,'products/sample/white-chickpeas-kabuli-chana-1-kg.svg','products/sample/white-chickpeas-kabuli-chana-1-kg.svg','White Chickpeas (Kabuli Chana) 1 kg',0,1,'2026-11-10 13:52:28'),
(66,66,'products/sample/white-sugar.svg','products/sample/white-sugar.svg','White Sugar',0,1,'2026-11-10 13:52:28'),
(67,67,'products/sample/national-iodized-salt-800g.svg','products/sample/national-iodized-salt-800g.svg','National Iodized Salt 800g',0,1,'2026-11-10 13:52:28'),
(68,68,'products/sample/shan-biryani-masala-60g.svg','products/sample/shan-biryani-masala-60g.svg','Shan Biryani Masala 60g',0,1,'2026-11-10 13:52:28'),
(69,69,'products/sample/national-red-chilli-powder-200g.svg','products/sample/national-red-chilli-powder-200g.svg','National Red Chilli Powder 200g',0,1,'2026-11-10 13:52:28'),
(70,70,'products/sample/national-haldi-turmeric-powder-200g.svg','products/sample/national-haldi-turmeric-powder-200g.svg','National Haldi (Turmeric) Powder 200g',0,1,'2026-11-10 13:52:28'),
(71,71,'products/sample/tapal-danedar-tea.svg','products/sample/tapal-danedar-tea.svg','Tapal Danedar Tea',0,1,'2026-11-10 13:52:28'),
(72,72,'products/sample/lipton-yellow-label-tea-190g.svg','products/sample/lipton-yellow-label-tea-190g.svg','Lipton Yellow Label Tea 190g',0,1,'2026-11-10 13:52:28'),
(73,73,'products/sample/nestle-nesvita-milk-powder-400g.svg','products/sample/nestle-nesvita-milk-powder-400g.svg','Nestle Nesvita Milk Powder 400g',0,1,'2026-11-10 13:52:28'),
(74,74,'products/sample/olpers-full-cream-milk-1-litre.svg','products/sample/olpers-full-cream-milk-1-litre.svg','Olpers Full Cream Milk 1 Litre',0,1,'2026-11-10 13:52:28'),
(75,75,'products/sample/peek-freans-sooper-biscuits-family-pack.svg','products/sample/peek-freans-sooper-biscuits-family-pack.svg','Peek Freans Sooper Biscuits (Family Pack)',0,1,'2026-11-10 13:52:28'),
(76,76,'products/sample/lu-prince-chocolate-biscuits-pack-of-12.svg','products/sample/lu-prince-chocolate-biscuits-pack-of-12.svg','LU Prince Chocolate Biscuits (Pack of 12)',0,1,'2026-11-10 13:52:28'),
(77,77,'products/sample/surf-excel-washing-powder.svg','products/sample/surf-excel-washing-powder.svg','Surf Excel Washing Powder',0,1,'2026-11-10 13:52:28'),
(78,78,'products/sample/ariel-washing-powder-1-kg.svg','products/sample/ariel-washing-powder-1-kg.svg','Ariel Washing Powder 1 kg',0,1,'2026-11-10 13:52:28'),
(79,79,'products/sample/max-dishwash-liquid-500ml.svg','products/sample/max-dishwash-liquid-500ml.svg','Max Dishwash Liquid 500ml',0,1,'2026-11-10 13:52:28'),
(80,80,'products/sample/lux-soap-rose-pack-of-3.svg','products/sample/lux-soap-rose-pack-of-3.svg','Lux Soap Rose (Pack of 3)',0,1,'2026-11-10 13:52:28'),
(81,81,'products/sample/lifebuoy-total-soap-pack-of-3.svg','products/sample/lifebuoy-total-soap-pack-of-3.svg','Lifebuoy Total Soap (Pack of 3)',0,1,'2026-11-10 13:52:28'),
(82,82,'products/sample/rose-petal-tissue-box-pack-of-3.svg','products/sample/rose-petal-tissue-box-pack-of-3.svg','Rose Petal Tissue Box (Pack of 3)',0,1,'2026-11-10 13:52:28'),
(83,83,'products/sample/mitchells-mixed-fruit-jam-450g.svg','products/sample/mitchells-mixed-fruit-jam-450g.svg','Mitchells Mixed Fruit Jam 450g',0,1,'2026-11-10 13:52:28'),
(84,84,'products/sample/matchbox-pack-10-boxes.svg','products/sample/matchbox-pack-10-boxes.svg','Matchbox Pack (10 boxes)',0,1,'2026-11-10 13:52:28');
INSERT INTO `product_tags` (`id`, `product_id`, `tag`) VALUES (1,1,'class 1'),
(2,1,'english'),
(3,1,'school book'),
(4,2,'class 1'),
(6,2,'school book'),
(5,2,'urdu'),
(7,3,'class 1'),
(8,3,'maths'),
(9,3,'school book'),
(10,4,'class 2'),
(11,4,'english'),
(12,4,'school book'),
(13,5,'class 2'),
(14,5,'maths'),
(15,5,'school book'),
(16,6,'class 3'),
(18,6,'school book'),
(17,6,'science'),
(19,7,'class 3'),
(20,7,'maths'),
(21,7,'school book'),
(22,8,'class 4'),
(24,8,'school book'),
(23,8,'social'),
(25,9,'class 4'),
(26,9,'english'),
(27,9,'school book'),
(28,10,'class 5'),
(29,10,'english'),
(30,10,'school book'),
(31,11,'class 5'),
(32,11,'maths'),
(33,11,'school book'),
(34,12,'class 5'),
(36,12,'school book'),
(35,12,'science'),
(37,13,'class 5'),
(39,13,'school book'),
(38,13,'urdu'),
(40,14,'class 5'),
(41,14,'islamiat'),
(42,14,'school book'),
(43,15,'class 6'),
(44,15,'maths'),
(45,15,'school book'),
(46,16,'class 6'),
(47,16,'computer'),
(48,16,'school book'),
(49,17,'class 7'),
(51,17,'school book'),
(50,17,'science'),
(52,18,'class 7'),
(53,18,'english'),
(54,18,'school book'),
(55,19,'class 8'),
(56,19,'maths'),
(57,19,'school book'),
(58,20,'class 8'),
(60,20,'school book'),
(59,20,'science'),
(61,21,'class 9'),
(62,21,'physics'),
(63,21,'school book'),
(65,22,'chemistry'),
(64,22,'class 9'),
(66,22,'school book'),
(68,23,'biology'),
(67,23,'class 9'),
(69,23,'school book'),
(70,24,'class 9'),
(71,24,'computer'),
(72,24,'school book'),
(73,25,'class 10'),
(74,25,'maths'),
(75,25,'school book'),
(76,26,'class 10'),
(77,26,'physics'),
(78,26,'school book'),
(79,27,'class 10'),
(80,27,'pak studies'),
(81,27,'school book'),
(82,28,'intermediate'),
(83,28,'physics'),
(84,28,'school book'),
(86,29,'chemistry'),
(85,29,'intermediate'),
(87,29,'school book'),
(89,30,'english'),
(88,30,'intermediate'),
(90,30,'school book'),
(92,31,'english'),
(91,31,'oxford'),
(93,32,'dictionary'),
(94,33,'cambridge'),
(95,33,'maths'),
(97,34,'islamic'),
(96,34,'quran'),
(98,35,'quran'),
(99,39,'pen'),
(100,41,'pencil'),
(102,45,'copy'),
(101,45,'register'),
(103,46,'copy'),
(104,49,'geometry'),
(105,57,'atta'),
(106,57,'flour'),
(108,58,'chawal'),
(107,58,'rice'),
(109,60,'oil'),
(110,60,'tel'),
(111,61,'ghee'),
(112,62,'dal'),
(114,66,'cheeni'),
(113,66,'sugar'),
(115,68,'masala'),
(117,71,'chai'),
(116,71,'tea'),
(119,74,'doodh'),
(118,74,'milk'),
(121,77,'detergent'),
(120,77,'surf'),
(123,80,'sabun'),
(122,80,'soap');
INSERT INTO `product_variations` (`id`, `product_id`, `name`, `sku`, `barcode`, `price`, `sale_price`, `cost_price`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,57,'5 kg','FS-1057-1','8970000010570',780.00,NULL,663.00,0,1,'2026-11-10 13:52:28',NULL),
(2,57,'10 kg','FS-1057-2','8970000010571',1540.00,1490.00,1309.00,1,1,'2026-11-10 13:52:28',NULL),
(3,57,'20 kg','FS-1057-3','8970000010572',3050.00,2950.00,2592.50,2,1,'2026-11-10 13:52:28',NULL),
(4,58,'1 kg','FS-1058-1','8970000010580',520.00,NULL,442.00,0,1,'2026-11-10 13:52:28',NULL),
(5,58,'5 kg','FS-1058-2','8970000010581',2500.00,2400.00,2125.00,1,1,'2026-11-10 13:52:28',NULL),
(6,60,'1 Litre Pouch','FS-1060-1','8970000010600',560.00,NULL,476.00,0,1,'2026-11-10 13:52:28',NULL),
(7,60,'3 Litre Bottle','FS-1060-2','8970000010601',1650.00,1599.00,1402.50,1,1,'2026-11-10 13:52:28',NULL),
(8,60,'5 Litre Tin','FS-1060-3','8970000010602',2700.00,2620.00,2295.00,2,1,'2026-11-10 13:52:28',NULL),
(9,66,'1 kg','FS-1066-1','8970000010660',165.00,NULL,140.25,0,1,'2026-11-10 13:52:28',NULL),
(10,66,'5 kg','FS-1066-2','8970000010661',810.00,790.00,688.50,1,1,'2026-11-10 13:52:28',NULL),
(11,71,'430g','FS-1071-1','8970000010710',980.00,940.00,833.00,0,1,'2026-11-10 13:52:28',NULL),
(12,71,'950g','FS-1071-2','8970000010711',2050.00,1980.00,1742.50,1,1,'2026-11-10 13:52:28',NULL),
(13,77,'500g','FS-1077-1','8970000010770',420.00,NULL,357.00,0,1,'2026-11-10 13:52:28',NULL),
(14,77,'1 kg','FS-1077-2','8970000010771',820.00,790.00,697.00,1,1,'2026-11-10 13:52:28',NULL),
(15,77,'2 kg','FS-1077-3','8970000010772',1580.00,1520.00,1343.00,2,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `products` (`id`, `name`, `name_urdu`, `slug`, `sku`, `barcode`, `product_code`, `isbn`, `brand_id`, `primary_category_id`, `short_description`, `description`, `keywords`, `price`, `sale_price`, `cost_price`, `unit`, `unit_value`, `weight_grams`, `length_cm`, `width_cm`, `height_cm`, `low_stock_threshold`, `min_order_qty`, `max_order_qty`, `track_stock`, `has_variations`, `is_featured`, `is_bestseller`, `is_rashan_item`, `status`, `sales_count`, `views_count`, `rating_avg`, `rating_count`, `search_text`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'English Reader Text Book Class 1',NULL,'english-reader-text-book-class-1','FS-1001','8961000001001',NULL,'9789694521744',4,3,'Official English Reader text book for Class 1 students. Latest syllabus edition.','<p>The latest edition of <strong>English Reader</strong> text book for <strong>Class 1</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 1 english text book ',260.00,NULL,208.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' english reader text book class 1 fs 1001 8961000001001 9789694521744 punjab board books کتابیں school official for students latest syllabus edition ','2026-10-13 13:52:28','2026-11-10 13:52:28',NULL),
(2,'Urdu Qaida Text Book Class 1',NULL,'urdu-qaida-text-book-class-1','FS-1002','8961000001002',NULL,'9789695126017',4,3,'Official Urdu Qaida text book for Class 1 students. Latest syllabus edition.','<p>The latest edition of <strong>Urdu Qaida</strong> text book for <strong>Class 1</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 1 urdu text book ',240.00,NULL,192.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' urdu qaida text book class 1 fs 1002 8961000001002 9789695126017 punjab board books کتابیں school official for students latest syllabus edition ','2026-11-07 13:52:28','2026-11-10 13:52:28',NULL),
(3,'Mathematics Text Book Class 1',NULL,'mathematics-text-book-class-1','FS-1003','8961000001003',NULL,'9789691822813',4,3,'Official Mathematics text book for Class 1 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 1</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 1 maths text book math maths mathematics',260.00,NULL,208.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 1 fs 1003 8961000001003 9789691822813 punjab board maths math books کتابیں school official for students latest syllabus edition ','2026-10-09 13:52:28','2026-11-10 13:52:28',NULL),
(4,'English Reader Text Book Class 2',NULL,'english-reader-text-book-class-2','FS-1004','8961000001004',NULL,'9789699094479',4,4,'Official English Reader text book for Class 2 students. Latest syllabus edition.','<p>The latest edition of <strong>English Reader</strong> text book for <strong>Class 2</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 2 english text book ',280.00,NULL,224.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' english reader text book class 2 fs 1004 8961000001004 9789699094479 punjab board books کتابیں school official for students latest syllabus edition ','2026-09-19 13:52:28','2026-11-10 13:52:28',NULL),
(5,'Mathematics Text Book Class 2',NULL,'mathematics-text-book-class-2','FS-1005','8961000001005',NULL,'9789695481986',4,4,'Official Mathematics text book for Class 2 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 2</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 2 maths text book math maths mathematics',240.00,NULL,192.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 2 fs 1005 8961000001005 9789695481986 punjab board maths math books کتابیں school official for students latest syllabus edition ','2026-10-22 13:52:28','2026-11-10 13:52:28',NULL),
(6,'General Science Text Book Class 3',NULL,'general-science-text-book-class-3','FS-1006','8961000001006',NULL,'9789699064552',4,5,'Official General Science text book for Class 3 students. Latest syllabus edition.','<p>The latest edition of <strong>General Science</strong> text book for <strong>Class 3</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 3 science text book ',260.00,NULL,208.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' general science text book class 3 fs 1006 8961000001006 9789699064552 punjab board books کتابیں school official for students latest syllabus edition ','2026-09-26 13:52:28','2026-11-10 13:52:28',NULL),
(7,'Mathematics Text Book Class 3',NULL,'mathematics-text-book-class-3','FS-1007','8961000001007',NULL,'9789693342025',4,5,'Official Mathematics text book for Class 3 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 3</strong>, published by Punjab Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 3 maths text book math maths mathematics',290.00,NULL,232.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 3 fs 1007 8961000001007 9789693342025 punjab board maths math books کتابیں school official for students latest syllabus edition ','2026-11-06 13:52:28','2026-11-10 13:52:28',NULL),
(8,'Social Studies Text Book Class 4',NULL,'social-studies-text-book-class-4','FS-1008','8961000001008',NULL,'9789697966354',5,6,'Official Social Studies text book for Class 4 students. Latest syllabus edition.','<p>The latest edition of <strong>Social Studies</strong> text book for <strong>Class 4</strong>, published by Paramount Books. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 4 social text book ',280.00,NULL,224.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' social studies text book class 4 fs 1008 8961000001008 9789697966354 paramount books کتابیں school official for students latest syllabus edition ','2026-10-19 13:52:28','2026-11-10 13:52:28',NULL),
(9,'English Grammar Text Book Class 4',NULL,'english-grammar-text-book-class-4','FS-1009','8961000001009',NULL,'9789693115705',5,6,'Official English Grammar text book for Class 4 students. Latest syllabus edition.','<p>The latest edition of <strong>English Grammar</strong> text book for <strong>Class 4</strong>, published by Paramount Books. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 4 english text book ',310.00,NULL,248.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' english grammar text book class 4 fs 1009 8961000001009 9789693115705 paramount books کتابیں school official for students latest syllabus edition ','2026-10-13 13:52:28','2026-11-10 13:52:28',NULL),
(10,'English Text Book Class 5',NULL,'english-text-book-class-5','FS-1010','8961000001010',NULL,'9789693168879',3,7,'Official English text book for Class 5 students. Latest syllabus edition.','<p>The latest edition of <strong>English</strong> text book for <strong>Class 5</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 5 english text book ',300.00,NULL,240.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',2,0,0.00,0,' english text book class 5 fs 1010 8961000001010 9789693168879 sindh board books کتابیں school official for students latest syllabus edition ','2026-10-31 13:52:28','2026-11-10 13:52:28',NULL),
(11,'Mathematics Text Book Class 5',NULL,'mathematics-text-book-class-5','FS-1011','8961000001011',NULL,'9789693219393',3,7,'Official Mathematics text book for Class 5 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 5</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 5 maths text book math maths mathematics',320.00,NULL,240.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,1,0,'active',2,0,0.00,0,' mathematics text book class 5 fs 1011 8961000001011 9789693219393 sindh board maths math books کتابیں school official for students latest syllabus edition ','2026-09-26 13:52:28','2026-11-10 13:52:28',NULL),
(12,'General Science Text Book Class 5',NULL,'general-science-text-book-class-5','FS-1012','8961000001012',NULL,'9789691732740',3,7,'Official General Science text book for Class 5 students. Latest syllabus edition.','<p>The latest edition of <strong>General Science</strong> text book for <strong>Class 5</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 5 science text book ',300.00,NULL,240.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' general science text book class 5 fs 1012 8961000001012 9789691732740 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-12 13:52:28','2026-11-10 13:52:28',NULL),
(13,'Urdu Text Book Class 5',NULL,'urdu-text-book-class-5','FS-1013','8961000001013',NULL,'9789694028848',3,7,'Official Urdu text book for Class 5 students. Latest syllabus edition.','<p>The latest edition of <strong>Urdu</strong> text book for <strong>Class 5</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 5 urdu text book ',300.00,NULL,240.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,1,0,'active',0,0,0.00,0,' urdu text book class 5 fs 1013 8961000001013 9789694028848 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-24 13:52:28','2026-11-10 13:52:28',NULL),
(14,'Islamiat Text Book Class 5',NULL,'islamiat-text-book-class-5','FS-1014','8961000001014',NULL,'9789694792911',3,7,'Official Islamiat text book for Class 5 students. Latest syllabus edition.','<p>The latest edition of <strong>Islamiat</strong> text book for <strong>Class 5</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 5 islamiat text book ',310.00,NULL,248.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' islamiat text book class 5 fs 1014 8961000001014 9789694792911 sindh board books کتابیں school official for students latest syllabus edition ','2026-11-09 13:52:28','2026-11-10 13:52:28',NULL),
(15,'Mathematics Text Book Class 6',NULL,'mathematics-text-book-class-6','FS-1015','8961000001015',NULL,'9789695387564',5,8,'Official Mathematics text book for Class 6 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 6</strong>, published by Paramount Books. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 6 maths text book math maths mathematics',330.00,NULL,264.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 6 fs 1015 8961000001015 9789695387564 paramount books maths math کتابیں school official for students latest syllabus edition ','2026-10-30 13:52:28','2026-11-10 13:52:28',NULL),
(16,'Computer Studies Text Book Class 6',NULL,'computer-studies-text-book-class-6','FS-1016','8961000001016',NULL,'9789699311583',5,8,'Official Computer Studies text book for Class 6 students. Latest syllabus edition.','<p>The latest edition of <strong>Computer Studies</strong> text book for <strong>Class 6</strong>, published by Paramount Books. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 6 computer text book ',360.00,NULL,288.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' computer studies text book class 6 fs 1016 8961000001016 9789699311583 paramount books کتابیں school official for students latest syllabus edition ','2026-11-07 13:52:28','2026-11-10 13:52:28',NULL),
(17,'Science Text Book Class 7',NULL,'science-text-book-class-7','FS-1017','8961000001017',NULL,'9789699253401',3,9,'Official Science text book for Class 7 students. Latest syllabus edition.','<p>The latest edition of <strong>Science</strong> text book for <strong>Class 7</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 7 science text book ',350.00,NULL,280.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' science text book class 7 fs 1017 8961000001017 9789699253401 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-13 13:52:28','2026-11-10 13:52:28',NULL),
(18,'English Text Book Class 7',NULL,'english-text-book-class-7','FS-1018','8961000001018',NULL,'9789692955640',3,9,'Official English text book for Class 7 students. Latest syllabus edition.','<p>The latest edition of <strong>English</strong> text book for <strong>Class 7</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 7 english text book ',380.00,NULL,304.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' english text book class 7 fs 1018 8961000001018 9789692955640 sindh board books کتابیں school official for students latest syllabus edition ','2026-11-07 13:52:28','2026-11-10 13:52:28',NULL),
(19,'Mathematics Text Book Class 8',NULL,'mathematics-text-book-class-8','FS-1019','8961000001019',NULL,'9789695097989',3,10,'Official Mathematics text book for Class 8 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 8</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 8 maths text book math maths mathematics',380.00,NULL,304.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 8 fs 1019 8961000001019 9789695097989 sindh board maths math books کتابیں school official for students latest syllabus edition ','2026-10-06 13:52:28','2026-11-10 13:52:28',NULL),
(20,'Science Text Book Class 8',NULL,'science-text-book-class-8','FS-1020','8961000001020',NULL,'9789692529450',3,10,'Official Science text book for Class 8 students. Latest syllabus edition.','<p>The latest edition of <strong>Science</strong> text book for <strong>Class 8</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 8 science text book ',370.00,NULL,296.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' science text book class 8 fs 1020 8961000001020 9789692529450 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-12 13:52:28','2026-11-10 13:52:28',NULL),
(21,'Physics Text Book Class 9',NULL,'physics-text-book-class-9','FS-1021','8961000001021',NULL,'9789693690691',3,11,'Official Physics text book for Class 9 students. Latest syllabus edition.','<p>The latest edition of <strong>Physics</strong> text book for <strong>Class 9</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 9 physics text book ',430.00,NULL,344.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,0,'active',1,0,0.00,0,' physics text book class 9 fs 1021 8961000001021 9789693690691 sindh board books کتابیں school official for students latest syllabus edition ','2026-10-17 13:52:28','2026-11-10 13:52:28',NULL),
(22,'Chemistry Text Book Class 9',NULL,'chemistry-text-book-class-9','FS-1022','8961000001022',NULL,'9789691696404',3,11,'Official Chemistry text book for Class 9 students. Latest syllabus edition.','<p>The latest edition of <strong>Chemistry</strong> text book for <strong>Class 9</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 9 chemistry text book ',420.00,NULL,336.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',1,0,0.00,0,' chemistry text book class 9 fs 1022 8961000001022 9789691696404 sindh board books کتابیں school official for students latest syllabus edition ','2026-10-16 13:52:28','2026-11-10 13:52:28',NULL),
(23,'Biology Text Book Class 9',NULL,'biology-text-book-class-9','FS-1023','8961000001023',NULL,'9789695757591',3,11,'Official Biology text book for Class 9 students. Latest syllabus edition.','<p>The latest edition of <strong>Biology</strong> text book for <strong>Class 9</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 9 biology text book ',460.00,NULL,368.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,0,'active',0,0,0.00,0,' biology text book class 9 fs 1023 8961000001023 9789695757591 sindh board books کتابیں school official for students latest syllabus edition ','2026-11-02 13:52:28','2026-11-10 13:52:28',NULL),
(24,'Computer Science Text Book Class 9',NULL,'computer-science-text-book-class-9','FS-1024','8961000001024',NULL,'9789698874237',3,11,'Official Computer Science text book for Class 9 students. Latest syllabus edition.','<p>The latest edition of <strong>Computer Science</strong> text book for <strong>Class 9</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 9 computer text book ',430.00,NULL,344.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' computer science text book class 9 fs 1024 8961000001024 9789698874237 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-26 13:52:28','2026-11-10 13:52:28',NULL),
(25,'Mathematics Text Book Class 10',NULL,'mathematics-text-book-class-10','FS-1025','8961000001025',NULL,'9789697480601',3,12,'Official Mathematics text book for Class 10 students. Latest syllabus edition.','<p>The latest edition of <strong>Mathematics</strong> text book for <strong>Class 10</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 10 maths text book math maths mathematics',490.00,NULL,392.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' mathematics text book class 10 fs 1025 8961000001025 9789697480601 sindh board maths math books کتابیں school official for students latest syllabus edition ','2026-10-01 13:52:28','2026-11-10 13:52:28',NULL),
(26,'Physics Text Book Class 10',NULL,'physics-text-book-class-10','FS-1026','8961000001026',NULL,'9789691527109',3,12,'Official Physics text book for Class 10 students. Latest syllabus edition.','<p>The latest edition of <strong>Physics</strong> text book for <strong>Class 10</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 10 physics text book ',460.00,NULL,368.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' physics text book class 10 fs 1026 8961000001026 9789691527109 sindh board books کتابیں school official for students latest syllabus edition ','2026-10-15 13:52:28','2026-11-10 13:52:28',NULL),
(27,'Pakistan Studies Text Book Class 10',NULL,'pakistan-studies-text-book-class-10','FS-1027','8961000001027',NULL,'9789693172950',3,12,'Official Pakistan Studies text book for Class 10 students. Latest syllabus edition.','<p>The latest edition of <strong>Pakistan Studies</strong> text book for <strong>Class 10</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','class 10 pak studies text book ',450.00,NULL,360.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' pakistan studies text book class 10 fs 1027 8961000001027 9789693172950 sindh board pak books کتابیں school official for students latest syllabus edition ','2026-09-16 13:52:28','2026-11-10 13:52:28',NULL),
(28,'Physics Part 1 Text Book Intermediate',NULL,'physics-part-1-text-book-intermediate','FS-1028','8961000001028',NULL,'9789697546190',3,13,'Official Physics Part 1 text book for Intermediate students. Latest syllabus edition.','<p>The latest edition of <strong>Physics Part 1</strong> text book for <strong>Intermediate</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','intermediate physics text book ',540.00,NULL,432.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' physics part 1 text book intermediate fs 1028 8961000001028 9789697546190 sindh board books کتابیں school official for students latest syllabus edition ','2026-10-23 13:52:28','2026-11-10 13:52:28',NULL),
(29,'Chemistry Part 1 Text Book Intermediate',NULL,'chemistry-part-1-text-book-intermediate','FS-1029','8961000001029',NULL,'9789693263441',3,13,'Official Chemistry Part 1 text book for Intermediate students. Latest syllabus edition.','<p>The latest edition of <strong>Chemistry Part 1</strong> text book for <strong>Intermediate</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','intermediate chemistry text book ',530.00,NULL,424.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' chemistry part 1 text book intermediate fs 1029 8961000001029 9789693263441 sindh board books کتابیں school official for students latest syllabus edition ','2026-11-03 13:52:28','2026-11-10 13:52:28',NULL),
(30,'English Book 1 Text Book Intermediate',NULL,'english-book-1-text-book-intermediate','FS-1030','8961000001030',NULL,'9789692324290',3,13,'Official English Book 1 text book for Intermediate students. Latest syllabus edition.','<p>The latest edition of <strong>English Book 1</strong> text book for <strong>Intermediate</strong>, published by Sindh Text Book Board. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>','intermediate english text book ',560.00,NULL,448.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' english book 1 text intermediate fs 1030 8961000001030 9789692324290 sindh board books کتابیں school official for students latest syllabus edition ','2026-09-13 13:52:28','2026-11-10 13:52:28',NULL),
(31,'New Oxford Modern English Book 5',NULL,'new-oxford-modern-english-book-5','FS-1031','8961000001031',NULL,'9780199069019',1,14,NULL,'<p>New Oxford Modern English Book 5 available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','nome oxford english class 5',1350.00,1250.00,1080.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' new oxford modern english book 5 fs 1031 8961000001031 9780199069019 university press nome class books کتابیں school ','2026-10-14 13:52:28','2026-11-10 13:52:28',NULL),
(32,'Oxford Shorter English Dictionary',NULL,'oxford-shorter-english-dictionary','FS-1032','8961000001032',NULL,'9780199206872',1,14,NULL,'<p>Oxford Shorter English Dictionary available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','dictionary oxford english',2450.00,NULL,1960.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' oxford shorter english dictionary fs 1032 8961000001032 9780199206872 university press book books کتابیں general knowledge ','2026-10-03 13:52:28','2026-11-10 13:52:28',NULL),
(33,'Cambridge Primary Mathematics Learner Book 5',NULL,'cambridge-primary-mathematics-learner-book-5','FS-1033','8961000001033',NULL,'9781108746328',2,15,NULL,'<p>Cambridge Primary Mathematics Learner Book 5 available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','cambridge maths math class 5 primary',3200.00,2950.00,2560.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' cambridge primary mathematics learner book 5 fs 1033 8961000001033 9781108746328 university press maths math class books کتابیں school ','2026-09-11 13:52:28','2026-11-10 13:52:28',NULL),
(34,'Holy Quran 13 Lines (Hafzi)','قرآن مجید ۱۳ سطری','holy-quran-13-lines-hafzi','FS-1034','8961000001034',NULL,NULL,6,16,NULL,'<p>Holy Quran 13 Lines (Hafzi) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','quran majeed hafzi tilawat',1800.00,NULL,1440.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,1,0,'active',0,0,0.00,0,' holy quran 13 lines hafzi قرآن مجید ۱۳ سطری fs 1034 8961000001034 taj company majeed tilawat book books کتابیں islamic اسلامی ','2026-10-04 13:52:28','2026-11-10 13:52:28',NULL),
(35,'Holy Quran 16 Lines with Urdu Translation','قرآن مجید مع اردو ترجمہ','holy-quran-16-lines-with-urdu-translation','FS-1035','8961000001035',NULL,NULL,6,16,NULL,'<p>Holy Quran 16 Lines with Urdu Translation available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','quran tarjuma urdu translation',2600.00,NULL,2080.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' holy quran 16 lines with urdu translation قرآن مجید مع اردو ترجمہ fs 1035 8961000001035 taj company tarjuma book books کتابیں islamic اسلامی ','2026-09-30 13:52:28','2026-11-10 13:52:28',NULL),
(36,'Seerat-un-Nabi for Kids',NULL,'seerat-un-nabi-for-kids','FS-1036','8961000001036',NULL,NULL,5,16,NULL,'<p>Seerat-un-Nabi for Kids available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','seerat islamic kids',650.00,NULL,520.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' seerat un nabi for kids fs 1036 8961000001036 paramount books islamic book کتابیں اسلامی story ','2026-10-19 13:52:28','2026-11-10 13:52:28',NULL),
(37,'General Knowledge Book 5',NULL,'general-knowledge-book-5','FS-1037','8961000001037',NULL,NULL,5,17,NULL,'<p>General Knowledge Book 5 available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','gk general knowledge class 5',480.00,NULL,384.00,'book',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' general knowledge book 5 fs 1037 8961000001037 paramount books gk class کتابیں school ','2026-10-04 13:52:28','2026-11-10 13:52:28',NULL),
(38,'Moral Stories for Children (Set of 6)',NULL,'moral-stories-for-children-set-of-6','FS-1038','8961000001038',NULL,NULL,5,18,NULL,'<p>Moral Stories for Children (Set of 6) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','kids story books moral stories',1200.00,990.00,960.00,'set',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' moral stories for children set of 6 fs 1038 8961000001038 paramount books kids story کتابیں ','2026-10-22 13:52:28','2026-11-10 13:52:28',NULL),
(39,'Dollar Clipper Ball Pen Blue (Pack of 10)',NULL,'dollar-clipper-ball-pen-blue-pack-of-10','FS-1039','8961000001039',NULL,NULL,7,20,NULL,'<p>Dollar Clipper Ball Pen Blue (Pack of 10) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','pen ball point blue',250.00,220.00,200.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,1,0,'active',0,0,0.00,0,' dollar clipper ball pen blue pack of 10 fs 1039 8961000001039 point stationery اسٹیشنری pens pencils ','2026-09-28 13:52:28','2026-11-10 13:52:28',NULL),
(40,'Piano Ball Pen Black (Pack of 10)',NULL,'piano-ball-pen-black-pack-of-10','FS-1040','8961000001040',NULL,NULL,8,20,NULL,'<p>Piano Ball Pen Black (Pack of 10) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','pen black ball point',200.00,NULL,160.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' piano ball pen black pack of 10 fs 1040 8961000001040 point stationery اسٹیشنری pens pencils ','2026-10-30 13:52:28','2026-11-10 13:52:28',NULL),
(41,'Deer HB Pencils (Box of 12)',NULL,'deer-hb-pencils-box-of-12','FS-1041','8961000001041',NULL,NULL,11,20,NULL,'<p>Deer HB Pencils (Box of 12) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','pencil lead hb',180.00,NULL,144.00,'box',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,0,'active',0,0,0.00,0,' deer hb pencils box of 12 fs 1041 8961000001041 pencil lead stationery اسٹیشنری pens ','2026-09-23 13:52:28','2026-11-10 13:52:28',NULL),
(42,'Faber-Castell Colour Pencils (24 Shades)',NULL,'faber-castell-colour-pencils-24-shades','FS-1042','8961000001042',NULL,NULL,10,20,NULL,'<p>Faber-Castell Colour Pencils (24 Shades) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','color pencils colours drawing',950.00,850.00,760.00,'box',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' faber castell colour pencils 24 shades fs 1042 8961000001042 color colours drawing box stationery اسٹیشنری pens art craft ','2026-09-11 13:52:28','2026-11-10 13:52:28',NULL),
(43,'Picasso Wax Crayons (16 Colours)',NULL,'picasso-wax-crayons-16-colours','FS-1043','8961000001043',NULL,NULL,9,22,NULL,'<p>Picasso Wax Crayons (16 Colours) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','crayons colour kids',320.00,NULL,256.00,'box',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' picasso wax crayons 16 colours fs 1043 8961000001043 colour kids box stationery اسٹیشنری art craft ','2026-10-29 13:52:28','2026-11-10 13:52:28',NULL),
(44,'Picasso Water Colours (12 Shades)',NULL,'picasso-water-colours-12-shades','FS-1044','8961000001044',NULL,NULL,9,22,NULL,'<p>Picasso Water Colours (12 Shades) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','paint water colour art',450.00,NULL,360.00,'box',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' picasso water colours 12 shades fs 1044 8961000001044 paint colour art box stationery اسٹیشنری craft ','2026-10-05 13:52:28','2026-11-10 13:52:28',NULL),
(45,'Solo Register 200 Pages (Single Line)',NULL,'solo-register-200-pages-single-line','FS-1045','8961000001045',NULL,NULL,32,21,NULL,'<p>Solo Register 200 Pages (Single Line) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','register copy notebook single line',280.00,NULL,224.00,'piece',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,1,0,'active',0,0,0.00,0,' solo register 200 pages single line fs 1045 8961000001045 lion copy notebook piece stationery اسٹیشنری notebooks registers ','2026-10-16 13:52:28','2026-11-10 13:52:28',NULL),
(46,'Lion Copy 80 Pages (Pack of 6)',NULL,'lion-copy-80-pages-pack-of-6','FS-1046','8961000001046',NULL,NULL,32,21,NULL,'<p>Lion Copy 80 Pages (Pack of 6) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','copy copies notebook kapi',540.00,480.00,432.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,0,'active',0,0,0.00,0,' lion copy 80 pages pack of 6 fs 1046 8961000001046 copies notebook kapi stationery اسٹیشنری notebooks registers ','2026-10-23 13:52:28','2026-11-10 13:52:28',NULL),
(47,'Spiral Notebook A4 (Ruled)',NULL,'spiral-notebook-a4-ruled','FS-1047','8961000001047',NULL,NULL,32,21,NULL,'<p>Spiral Notebook A4 (Ruled) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','notebook spiral a4',350.00,NULL,280.00,'piece',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' spiral notebook a 4 ruled fs 1047 8961000001047 lion piece stationery اسٹیشنری notebooks registers office supplies ','2026-10-31 13:52:28','2026-11-10 13:52:28',NULL),
(48,'Maths Copy Square Lines (Pack of 4)',NULL,'maths-copy-square-lines-pack-of-4','FS-1048','8961000001048',NULL,NULL,32,21,NULL,'<p>Maths Copy Square Lines (Pack of 4) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','math copy maths square',380.00,NULL,304.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' maths copy square lines pack of 4 fs 1048 8961000001048 lion math stationery اسٹیشنری notebooks registers ','2026-10-13 13:52:28','2026-11-10 13:52:28',NULL),
(49,'Deer Geometry Box (Complete Set)',NULL,'deer-geometry-box-complete-set','FS-1049','8961000001049',NULL,NULL,11,25,NULL,'<p>Deer Geometry Box (Complete Set) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','geometry box compass protractor',420.00,380.00,336.00,'set',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' deer geometry box complete set fs 1049 8961000001049 compass protractor stationery اسٹیشنری school accessories boxes ','2026-09-23 13:52:28','2026-11-10 13:52:28',NULL),
(50,'Plastic File Folder A4 (Pack of 5)',NULL,'plastic-file-folder-a4-pack-of-5','FS-1050','8961000001050',NULL,NULL,7,26,NULL,'<p>Plastic File Folder A4 (Pack of 5) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','file folder document',300.00,NULL,240.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' plastic file folder a 4 pack of 5 fs 1050 8961000001050 dollar document stationery اسٹیشنری office supplies files folders ','2026-11-08 13:52:28','2026-11-10 13:52:28',NULL),
(51,'Dollar Permanent Marker (Pack of 4)',NULL,'dollar-permanent-marker-pack-of-4','FS-1051','8961000001051',NULL,NULL,7,24,NULL,'<p>Dollar Permanent Marker (Pack of 4) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','marker permanent',360.00,NULL,288.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' dollar permanent marker pack of 4 fs 1051 8961000001051 stationery اسٹیشنری pens pencils office supplies ','2026-10-23 13:52:28','2026-11-10 13:52:28',NULL),
(52,'A4 Paper Ream 80gsm (500 Sheets)',NULL,'a4-paper-ream-80gsm-500-sheets','FS-1052','8961000001052',NULL,NULL,7,24,NULL,'<p>A4 Paper Ream 80gsm (500 Sheets) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','paper a4 rim ream printing',1650.00,NULL,1320.00,'ream',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' a 4 paper ream 80 gsm 500 sheets fs 1052 8961000001052 dollar rim printing stationery اسٹیشنری office supplies ','2026-10-02 13:52:28','2026-11-10 13:52:28',NULL),
(53,'School Bag Waterproof (Medium)',NULL,'school-bag-waterproof-medium','FS-1053','8961000001053',NULL,NULL,NULL,40,NULL,'<p>School Bag Waterproof (Medium) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','bag school bag backpack',2500.00,2200.00,2000.00,'piece',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,0,'active',0,0,0.00,0,' school bag waterproof medium fs 1053 8961000001053 backpack piece stationery اسٹیشنری accessories general items bags lunch boxes ','2026-10-26 13:52:28','2026-11-10 13:52:28',NULL),
(54,'Lunch Box with Water Bottle',NULL,'lunch-box-with-water-bottle','FS-1054','8961000001054',NULL,NULL,NULL,40,NULL,'<p>Lunch Box with Water Bottle available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','lunch box tiffin bottle',850.00,NULL,680.00,'set',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' lunch box with water bottle fs 1054 8961000001054 tiffin set stationery اسٹیشنری school accessories general items bags boxes ','2026-10-12 13:52:28','2026-11-10 13:52:28',NULL),
(55,'Energizer AA Batteries (Pack of 4)',NULL,'energizer-aa-batteries-pack-of-4','FS-1055','8961000001055',NULL,NULL,31,41,NULL,'<p>Energizer AA Batteries (Pack of 4) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','cells battery aa',520.00,NULL,416.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' energizer aa batteries pack of 4 fs 1055 8961000001055 cells battery general items electrical ','2026-11-04 13:52:28','2026-11-10 13:52:28',NULL),
(56,'Ludo & Snakes Ladders Board Game',NULL,'ludo-and-snakes-ladders-board-game','FS-1056','8961000001056',NULL,NULL,NULL,42,NULL,'<p>Ludo &amp; Snakes Ladders Board Game available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','ludo game toys',450.00,NULL,360.00,'piece',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,0,'active',0,0,0.00,0,' ludo snakes ladders board game fs 1056 8961000001056 toys piece general items games ','2026-11-08 13:52:28','2026-11-10 13:52:28',NULL),
(57,'Sunridge Chakki Atta (Wheat Flour)','سن رج چکی آٹا','sunridge-chakki-atta-wheat-flour','FS-1057','8961000001057',NULL,NULL,12,28,NULL,'<p>Sunridge Chakki Atta (Wheat Flour) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','atta flour aata gandum wheat chakki',780.00,NULL,0.00,'kg',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,1,1,1,'active',4,0,5.00,1,' sunridge chakki atta wheat flour سن رج چکی آٹا fs 1057 8961000001057 aata gandum kg groceries راشن rice اور چاول 5 1 8970000010570 10 2 8970000010571 20 3 8970000010572 ','2026-09-15 13:52:28','2026-11-10 13:52:28',NULL),
(58,'Guard Super Basmati Rice','گارڈ باسمتی چاول','guard-super-basmati-rice','FS-1058','8961000001058',NULL,NULL,13,28,NULL,'<p>Guard Super Basmati Rice available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','rice chawal basmati',520.00,NULL,0.00,'kg',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,0,1,1,'active',0,0,0.00,0,' guard super basmati rice گارڈ باسمتی چاول fs 1058 8961000001058 chawal kg groceries راشن flour آٹا اور 1 8970000010580 5 2 8970000010581 ','2026-11-05 13:52:28','2026-11-10 13:52:28',NULL),
(59,'Sella Rice (Loose)','سیلا چاول','sella-rice-loose','FS-1059','8961000001059',NULL,NULL,NULL,28,NULL,'<p>Sella Rice (Loose) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','rice chawal sella',380.00,NULL,304.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' sella rice loose سیلا چاول fs 1059 8961000001059 chawal kg 1 groceries راشن flour آٹا اور ','2026-10-25 13:52:28','2026-11-10 13:52:28',NULL),
(60,'Dalda Cooking Oil','ڈالڈا کوکنگ آئل','dalda-cooking-oil','FS-1060','8961000001060',NULL,NULL,14,29,NULL,'<p>Dalda Cooking Oil available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','oil tel cooking oil',560.00,NULL,0.00,'litre',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,1,1,1,'active',2,0,0.00,0,' dalda cooking oil ڈالڈا کوکنگ آئل fs 1060 8961000001060 tel litre groceries راشن ghee تیل اور گھی 1 pouch 8970000010600 3 bottle 2 8970000010601 5 tin 8970000010602 ','2026-10-13 13:52:28','2026-11-10 13:52:28',NULL),
(61,'Habib Banaspati Ghee 1 kg','حبیب بناسپتی گھی','habib-banaspati-ghee-1-kg','FS-1061','8961000001061',NULL,NULL,15,29,NULL,'<p>Habib Banaspati Ghee 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','ghee banaspati',590.00,NULL,472.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' habib banaspati ghee 1 kg حبیب بناسپتی گھی fs 1061 8961000001061 groceries راشن cooking oil تیل اور ','2026-09-20 13:52:28','2026-11-10 13:52:28',NULL),
(62,'Masoor Dal 1 kg','مسور دال','masoor-dal-1-kg','FS-1062','8961000001062',NULL,NULL,NULL,30,NULL,'<p>Masoor Dal 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','daal dal lentils masoor',340.00,NULL,272.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' masoor dal 1 kg مسور دال fs 1062 8961000001062 daal lentils groceries راشن pulses دالیں ','2026-10-31 13:52:28','2026-11-10 13:52:28',NULL),
(63,'Moong Dal 1 kg','مونگ دال','moong-dal-1-kg','FS-1063','8961000001063',NULL,NULL,NULL,30,NULL,'<p>Moong Dal 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','daal dal moong mung',420.00,NULL,336.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' moong dal 1 kg مونگ دال fs 1063 8961000001063 daal mung groceries راشن pulses lentils دالیں ','2026-10-20 13:52:28','2026-11-10 13:52:28',NULL),
(64,'Chana Dal 1 kg','چنا دال','chana-dal-1-kg','FS-1064','8961000001064',NULL,NULL,NULL,30,NULL,'<p>Chana Dal 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','daal dal chana gram',360.00,NULL,288.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' chana dal 1 kg چنا دال fs 1064 8961000001064 daal gram groceries راشن pulses lentils دالیں ','2026-09-22 13:52:28','2026-11-10 13:52:28',NULL),
(65,'White Chickpeas (Kabuli Chana) 1 kg','سفید چنے','white-chickpeas-kabuli-chana-1-kg','FS-1065','8961000001065',NULL,NULL,NULL,30,NULL,'<p>White Chickpeas (Kabuli Chana) 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','chickpeas safaid chane chana',480.00,450.00,384.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' white chickpeas kabuli chana 1 kg سفید چنے fs 1065 8961000001065 safaid chane groceries راشن pulses lentils دالیں ','2026-09-13 13:52:28','2026-11-10 13:52:28',NULL),
(66,'White Sugar','چینی','white-sugar','FS-1066','8961000001066',NULL,NULL,NULL,31,NULL,'<p>White Sugar available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','sugar cheeni chini shakar',165.00,NULL,0.00,'kg',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,0,1,1,'active',0,0,0.00,0,' white sugar چینی fs 1066 8961000001066 cheeni chini shakar kg groceries راشن salt اور نمک 1 8970000010660 5 2 8970000010661 ','2026-10-25 13:52:28','2026-11-10 13:52:28',NULL),
(67,'National Iodized Salt 800g','نمک','national-iodized-salt-800g','FS-1067','8961000001067',NULL,NULL,18,31,NULL,'<p>National Iodized Salt 800g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','salt namak',60.00,NULL,48.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' national iodized salt 800 g نمک fs 1067 8961000001067 foods namak packet groceries راشن sugar چینی اور ','2026-10-19 13:52:28','2026-11-10 13:52:28',NULL),
(68,'Shan Biryani Masala 60g',NULL,'shan-biryani-masala-60g','FS-1068','8961000001068',NULL,NULL,19,32,NULL,'<p>Shan Biryani Masala 60g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','masala spices biryani shan',140.00,NULL,112.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,1,'active',0,0,0.00,0,' shan biryani masala 60 g fs 1068 8961000001068 spices packet groceries راشن مصالحے ','2026-10-09 13:52:28','2026-11-10 13:52:28',NULL),
(69,'National Red Chilli Powder 200g','لال مرچ','national-red-chilli-powder-200g','FS-1069','8961000001069',NULL,NULL,18,32,NULL,'<p>National Red Chilli Powder 200g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','lal mirch red chilli spices',330.00,NULL,264.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' national red chilli powder 200 g لال مرچ fs 1069 8961000001069 foods lal mirch spices packet groceries راشن مصالحے ','2026-10-28 13:52:28','2026-11-10 13:52:28',NULL),
(70,'National Haldi (Turmeric) Powder 200g','ہلدی','national-haldi-turmeric-powder-200g','FS-1070','8961000001070',NULL,NULL,18,32,NULL,'<p>National Haldi (Turmeric) Powder 200g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','haldi turmeric spices',260.00,NULL,208.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' national haldi turmeric powder 200 g ہلدی fs 1070 8961000001070 foods spices packet groceries راشن مصالحے ','2026-10-01 13:52:28','2026-11-10 13:52:28',NULL),
(71,'Tapal Danedar Tea','تپال دانے دار چائے','tapal-danedar-tea','FS-1071','8961000001071',NULL,NULL,16,33,NULL,'<p>Tapal Danedar Tea available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','tea chai patti tapal',980.00,NULL,0.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,1,1,1,'active',3,0,0.00,0,' tapal danedar tea تپال دانے دار چائے fs 1071 8961000001071 chai patti packet groceries راشن beverages 430 g 1 8970000010710 950 2 8970000010711 ','2026-11-01 13:52:28','2026-11-10 13:52:28',NULL),
(72,'Lipton Yellow Label Tea 190g',NULL,'lipton-yellow-label-tea-190g','FS-1072','8961000001072',NULL,NULL,17,33,NULL,'<p>Lipton Yellow Label Tea 190g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','tea chai lipton',640.00,NULL,512.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' lipton yellow label tea 190 g fs 1072 8961000001072 chai packet groceries راشن beverages چائے ','2026-10-15 13:52:28','2026-11-10 13:52:28',NULL),
(73,'Nestle Nesvita Milk Powder 400g',NULL,'nestle-nesvita-milk-powder-400g','FS-1073','8961000001073',NULL,NULL,23,35,NULL,'<p>Nestle Nesvita Milk Powder 400g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','milk doodh powder',950.00,NULL,760.00,'packet',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' nestle nesvita milk powder 400 g fs 1073 8961000001073 doodh packet groceries راشن dairy products دودھ ','2026-10-10 13:52:28','2026-11-10 13:52:28',NULL),
(74,'Olpers Full Cream Milk 1 Litre','دودھ','olpers-full-cream-milk-1-litre','FS-1074','8961000001074',NULL,NULL,22,35,NULL,'<p>Olpers Full Cream Milk 1 Litre available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','milk doodh olpers',290.00,NULL,232.00,'litre',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,1,1,'active',0,0,0.00,0,' olpers full cream milk 1 litre دودھ fs 1074 8961000001074 doodh groceries راشن dairy products ','2026-11-05 13:52:28','2026-11-10 13:52:28',NULL),
(75,'Peek Freans Sooper Biscuits (Family Pack)',NULL,'peek-freans-sooper-biscuits-family-pack','FS-1075','8961000001075',NULL,NULL,20,34,NULL,'<p>Peek Freans Sooper Biscuits (Family Pack) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','biscuits biscuit sooper',350.00,320.00,280.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,1,0,1,'active',0,0,0.00,0,' peek freans sooper biscuits family pack fs 1075 8961000001075 biscuit groceries راشن snacks بسکٹ ','2026-09-15 13:52:28','2026-11-10 13:52:28',NULL),
(76,'LU Prince Chocolate Biscuits (Pack of 12)',NULL,'lu-prince-chocolate-biscuits-pack-of-12','FS-1076','8961000001076',NULL,NULL,21,34,NULL,'<p>LU Prince Chocolate Biscuits (Pack of 12) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','biscuits chocolate prince',480.00,NULL,384.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' lu prince chocolate biscuits pack of 12 fs 1076 8961000001076 groceries راشن snacks بسکٹ ','2026-09-18 13:52:28','2026-11-10 13:52:28',NULL),
(77,'Surf Excel Washing Powder',NULL,'surf-excel-washing-powder','FS-1077','8961000001077',NULL,NULL,24,36,NULL,'<p>Surf Excel Washing Powder available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','surf detergent washing powder kapray',420.00,NULL,0.00,'kg',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,1,1,1,1,'active',3,0,0.00,0,' surf excel washing powder fs 1077 8961000001077 detergent kapray kg groceries راشن cleaning products صفائی 500 g 1 8970000010770 2 8970000010771 3 8970000010772 ','2026-09-20 13:52:28','2026-11-10 13:52:28',NULL),
(78,'Ariel Washing Powder 1 kg',NULL,'ariel-washing-powder-1-kg','FS-1078','8961000001078',NULL,NULL,25,36,NULL,'<p>Ariel Washing Powder 1 kg available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','detergent washing powder ariel surf',890.00,NULL,712.00,'kg',1.000,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' ariel washing powder 1 kg fs 1078 8961000001078 detergent surf groceries راشن cleaning products صفائی ','2026-09-24 13:52:28','2026-11-10 13:52:28',NULL),
(79,'Max Dishwash Liquid 500ml',NULL,'max-dishwash-liquid-500ml','FS-1079','8961000001079',NULL,NULL,28,36,NULL,'<p>Max Dishwash Liquid 500ml available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','dishwash bartan liquid',330.00,NULL,264.00,'bottle',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' max dishwash liquid 500 ml fs 1079 8961000001079 bartan bottle groceries راشن cleaning products صفائی ','2026-10-15 13:52:28','2026-11-10 13:52:28',NULL),
(80,'Lux Soap Rose (Pack of 3)','لکس صابن','lux-soap-rose-pack-of-3','FS-1080','8961000001080',NULL,NULL,26,37,NULL,'<p>Lux Soap Rose (Pack of 3) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','soap sabun lux',390.00,360.00,312.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' lux soap rose pack of 3 لکس صابن fs 1080 8961000001080 sabun groceries راشن personal care ذاتی نگہداشت ','2026-09-12 13:52:28','2026-11-10 13:52:28',NULL),
(81,'Lifebuoy Total Soap (Pack of 3)',NULL,'lifebuoy-total-soap-pack-of-3','FS-1081','8961000001081',NULL,NULL,27,37,NULL,'<p>Lifebuoy Total Soap (Pack of 3) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','soap sabun lifebuoy',340.00,NULL,272.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' lifebuoy total soap pack of 3 fs 1081 8961000001081 sabun groceries راشن personal care ذاتی نگہداشت ','2026-09-30 13:52:28','2026-11-10 13:52:28',NULL),
(82,'Rose Petal Tissue Box (Pack of 3)',NULL,'rose-petal-tissue-box-pack-of-3','FS-1082','8961000001082',NULL,NULL,29,38,NULL,'<p>Rose Petal Tissue Box (Pack of 3) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','tissue paper napkin',540.00,NULL,432.00,'pack',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' rose petal tissue box pack of 3 fs 1082 8961000001082 paper napkin groceries راشن household essentials گھریلو اشیاء ','2026-09-19 13:52:28','2026-11-10 13:52:28',NULL),
(83,'Mitchells Mixed Fruit Jam 450g',NULL,'mitchells-mixed-fruit-jam-450g','FS-1083','8961000001083',NULL,NULL,30,34,NULL,'<p>Mitchells Mixed Fruit Jam 450g available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','jam mitchells breakfast',420.00,NULL,336.00,'bottle',NULL,NULL,NULL,NULL,NULL,5,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' mitchells mixed fruit jam 450 g fs 1083 8961000001083 breakfast bottle groceries راشن snacks biscuits بسکٹ ','2026-09-16 13:52:28','2026-11-10 13:52:28',NULL),
(84,'Matchbox Pack (10 boxes)',NULL,'matchbox-pack-10-boxes','FS-1084','8961000001084',NULL,NULL,NULL,38,NULL,'<p>Matchbox Pack (10 boxes) available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>','matchbox machis',60.00,NULL,48.00,'pack',NULL,NULL,NULL,NULL,NULL,10,1,NULL,1,0,0,0,1,'active',0,0,0.00,0,' matchbox pack 10 boxes fs 1084 8961000001084 machis groceries راشن household essentials گھریلو اشیاء ','2026-10-14 13:52:28','2026-11-10 13:52:28',NULL);
INSERT INTO `promotion_products` (`promotion_id`, `product_id`, `sort_order`) VALUES (1,10,7),
(1,11,1),
(1,12,6),
(1,13,0),
(1,14,5),
(1,31,4),
(1,33,3),
(1,37,2),
(2,57,4),
(2,58,3),
(2,60,2),
(2,61,7),
(2,66,1),
(2,67,6),
(2,71,0),
(2,72,5),
(3,39,3),
(3,41,2),
(3,42,7),
(3,45,1),
(3,46,0),
(3,47,6),
(3,48,5),
(3,51,4),
(4,77,0),
(4,78,4),
(4,79,3),
(4,80,2),
(4,81,1);
INSERT INTO `promotions` (`id`, `title`, `slug`, `promo_type`, `subtitle`, `description`, `badge_text`, `image`, `bg_color`, `text_color`, `button_text`, `link_url`, `starts_at`, `ends_at`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (1,'School Reopening Offer','school-reopening-offer','school_offer','Up to 15% off on books, registers & geometry boxes','<p>Up to 15% off on books, registers & geometry boxes. Limited time offer at Fahim Store - order online for home delivery or pick up from the store.</p>','UP TO 15% OFF',NULL,'#1e3a8a','#ffffff','Shop Now',NULL,NULL,NULL,0,1,'2026-11-10 13:52:28',NULL),
(2,'Monthly Grocery Package','monthly-grocery-package','monthly_package','Atta, oil, sugar, tea, daal - everything for the month','<p>Atta, oil, sugar, tea, daal - everything for the month. Limited time offer at Fahim Store - order online for home delivery or pick up from the store.</p>','SAVE RS. 500',NULL,'#0f766e','#ffffff','Shop Now',NULL,NULL,NULL,1,1,'2026-11-10 13:52:28',NULL),
(3,'Stationery Bundle','stationery-bundle','stationery_bundle','Pens, pencils, colours and copies for the new term','<p>Pens, pencils, colours and copies for the new term. Limited time offer at Fahim Store - order online for home delivery or pick up from the store.</p>','BUNDLE DEAL',NULL,'#c2410c','#ffffff','Shop Now',NULL,NULL,NULL,2,1,'2026-11-10 13:52:28',NULL),
(4,'Cleaning Combo Deals','cleaning-combo-deals','combo','Washing powder, dishwash & soaps at combo prices','<p>Washing powder, dishwash & soaps at combo prices. Limited time offer at Fahim Store - order online for home delivery or pick up from the store.</p>','COMBO',NULL,'#7c3aed','#ffffff','Shop Now',NULL,NULL,NULL,3,1,'2026-11-10 13:52:28',NULL);
INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `variation_id`, `quantity`, `unit_cost`, `line_total`) VALUES (1,1,10,0,20,240.00,4800.00),
(2,1,11,0,20,240.00,4800.00);
INSERT INTO `purchases` (`id`, `supplier_id`, `reference_no`, `purchase_date`, `total_amount`, `status`, `notes`, `admin_id`, `created_at`, `updated_at`) VALUES (1,1,'UB-2231','2026-11-10',9600.00,'received','Restock Class 5 books',1,'2026-11-10 13:52:28','2026-11-10 13:52:28');
INSERT INTO `related_products` (`product_id`, `related_id`) VALUES (57,60),
(60,57),
(71,57);
INSERT INTO `reviews` (`id`, `product_id`, `customer_id`, `reviewer_name`, `rating`, `title`, `body`, `status`, `admin_reply`, `created_at`, `updated_at`) VALUES (1,57,1,'Ayesha Khan',5,'Fresh atta','Always fresh and soft rotis. Delivered on time.','approved',NULL,'2026-11-10 13:52:28',NULL);
INSERT INTO `search_synonyms` (`id`, `term`, `synonyms`) VALUES (1,'atta','flour,wheat flour'),
(2,'aata','flour,atta'),
(3,'chawal','rice'),
(4,'cheeni','sugar'),
(5,'chini','sugar'),
(6,'daal','dal,lentils'),
(7,'dal','lentils,pulses'),
(8,'tel','oil,cooking oil'),
(9,'surf','detergent,washing powder'),
(10,'sabun','soap'),
(11,'chai','tea'),
(12,'maths','mathematics'),
(13,'math','mathematics'),
(14,'copy','notebook,register,copies'),
(15,'kapi','copy,notebook'),
(16,'namak','salt'),
(17,'masala','spices'),
(18,'doodh','milk'),
(19,'ghee','banaspati'),
(20,'pencil','pencils'),
(21,'bag','school bag'),
(22,'colour','color'),
(23,'gk','general knowledge'),
(24,'quran','holy quran');
INSERT INTO `seo_metadata` (`id`, `entity_type`, `entity_id`, `entity_key`, `seo_title`, `meta_description`, `focus_keyword`, `secondary_keywords`, `canonical_url`, `meta_robots`, `og_title`, `og_description`, `og_image`, `twitter_card`, `twitter_title`, `twitter_description`, `image_alt`, `breadcrumb_title`, `schema_json`, `created_at`, `updated_at`) VALUES (1,'static',0,'home','Fahim Store - School Books, Stationery & Monthly Grocery Online in Karachi','Order school books, stationery and complete monthly rashan online from Fahim Store. Home delivery & store pickup in Karachi. Cash on delivery.','online grocery store karachi','school books, stationery, monthly rashan',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-10 13:52:28',NULL),
(2,'category',1,'','Buy School Books Online in Pakistan - Class 1 to Intermediate','Text books for all classes from Sindh & Punjab text book boards, Oxford and Cambridge. Fast delivery in Karachi.','school books online',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-10 13:52:28',NULL),
(3,'category',27,'','Online Grocery & Monthly Rashan Delivery in Karachi','Atta, rice, oil, ghee, daal, sugar, tea and household essentials delivered to your home.','grocery delivery karachi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-10 13:52:28',NULL);
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('announcement','Free home delivery on orders above Rs. 5,000 · Cash on Delivery available',NULL),
('currency_symbol','Rs.',NULL),
('default_delivery_fee','150',NULL),
('delivery_enabled','1',NULL),
('delivery_note','Orders placed before 4 PM are delivered the same day in most areas.',NULL),
('facebook_url','https://www.facebook.com/',NULL),
('free_delivery_over','5000',NULL),
('google_verification','',NULL),
('instagram_url','https://www.instagram.com/',NULL),
('invoice_footer','Thank you for your business!',NULL),
('meta_description','Fahim Store - buy school books, stationery, groceries and monthly rashan online in Pakistan. Home delivery and store pickup, cash on delivery.',NULL),
('min_order_amount','0',NULL),
('notify_email','orders@fahimstore.pk',NULL),
('notify_whatsapp','',NULL),
('opening_hours','Mon - Sun: 9:00 AM - 10:00 PM',NULL),
('opening_hours_schema','Mo-Su 09:00-22:00',NULL),
('order_prefix','FS',NULL),
('pickup_address','Fahim Store, Shop # 12, Main Market, Gulshan-e-Iqbal Block 13, Karachi',NULL),
('pickup_close','21:00',NULL),
('pickup_closed_days','',NULL),
('pickup_days_ahead','5',NULL),
('pickup_enabled','1',NULL),
('pickup_instructions','Show your order number at the counter. Your rashan will be packed and ready.',NULL),
('pickup_interval','60',NULL),
('pickup_lead_hours','2',NULL),
('pickup_open','10:00',NULL),
('popular_searches','atta,class 5 books,cooking oil,register,surf,quran,geometry box,tea',NULL),
('pos_receipt_footer','Thank you for shopping at Fahim Store! Exchange within 7 days with receipt.',NULL),
('products_per_page','24',NULL),
('rashan_default_budget','30000',NULL),
('rashan_intro','Pick your monthly grocery items, set a budget and we will pack everything for home delivery or store pickup.',NULL),
('rashan_title','Build Your Monthly Rashan',NULL),
('require_payment_reference','0',NULL),
('site_name','Fahim Store',NULL),
('site_tagline','School Books, Stationery & Monthly Grocery',NULL),
('stock_policy','placed',NULL),
('store_address','Shop # 12, Main Market, Gulshan-e-Iqbal Block 13',NULL),
('store_city','Karachi',NULL),
('store_email','info@fahimstore.pk',NULL),
('store_lat','24.9180',NULL),
('store_lng','67.0971',NULL),
('store_phone','0300-1234567',NULL),
('store_whatsapp','03001234567',NULL),
('tiktok_url','',NULL),
('twitter_handle','',NULL),
('whatsapp_customer_updates','0',NULL),
('whatsapp_enabled','0',NULL),
('youtube_url','',NULL);
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `notes`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Urdu Bazar Book Distributors','Kashif','0321-1111111',NULL,'Urdu Bazar, Karachi',NULL,1,'2026-11-10 13:52:28',NULL,NULL),
(2,'Jodia Bazar Wholesale Grocery','Haji Rafiq','0333-2222222',NULL,'Jodia Bazar, Karachi',NULL,1,'2026-11-10 13:52:28',NULL,NULL);
INSERT INTO `testimonials` (`id`, `customer_name`, `location`, `message`, `rating`, `sort_order`, `is_active`, `created_at`) VALUES (1,'Ayesha Khan','Gulshan-e-Iqbal','I order my complete monthly rashan from Fahim Store. The budget feature is amazing and delivery is always on time.',5,1,1,'2026-11-10 13:52:28'),
(2,'Muhammad Bilal','North Nazimabad','Got all Class 5 books and copies for both my kids in one order. Very convenient before school reopening!',5,2,1,'2026-11-10 13:52:28'),
(3,'Sana Ahmed','Gulistan-e-Johar','Store pickup is super quick. My order was packed and ready when I arrived.',4,3,1,'2026-11-10 13:52:28'),
(4,'Imran Siddiqui','Saddar','Original products, fair prices and cash on delivery. Highly recommended.',5,4,1,'2026-11-10 13:52:28');
SET FOREIGN_KEY_CHECKS = 1;
