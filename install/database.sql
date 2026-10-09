-- =====================================================================
-- Beglet — complete install (schema + core seed + demo catalogue)
-- Import this single file in phpMyAdmin. For a store without demo
-- products, import schema.sql + seed.sql instead.
-- =====================================================================

-- =====================================================================
--  Beglet — Premium Crafted Leather
--  MySQL 5.7+ / MariaDB 10.3+ schema  (InnoDB, utf8mb4)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Administration & security
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admin_roles;
CREATE TABLE admin_roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  slug          VARCHAR(80)  NOT NULL UNIQUE,
  description   VARCHAR(255) NULL,
  is_system     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_permissions;
CREATE TABLE admin_permissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perm_key      VARCHAR(80)  NOT NULL UNIQUE,
  label         VARCHAR(150) NOT NULL,
  group_name    VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_role_permissions;
CREATE TABLE admin_role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_arp_perm FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id              INT UNSIGNED NOT NULL,
  name                 VARCHAR(120) NOT NULL,
  email                VARCHAR(190) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  status               ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at        DATETIME     NULL,
  last_login_ip        VARCHAR(45)  NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS admin_audit_logs;
CREATE TABLE admin_audit_logs (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id     INT UNSIGNED NULL,
  action       VARCHAR(80)  NOT NULL,
  entity_type  VARCHAR(60)  NULL,
  entity_id    BIGINT UNSIGNED NULL,
  details      JSON         NULL,
  ip_address   VARCHAR(45)  NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type   ENUM('customer','admin') NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL UNIQUE,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pr_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS rate_limits;
CREATE TABLE rate_limits (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action      VARCHAR(40)  NOT NULL,
  ident_hash  CHAR(64)     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rl_lookup (action, ident_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Customers
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name     VARCHAR(80)  NOT NULL,
  last_name      VARCHAR(80)  NOT NULL DEFAULT '',
  email          VARCHAR(190) NOT NULL UNIQUE,
  phone          VARCHAR(30)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  status         ENUM('active','disabled') NOT NULL DEFAULT 'active',
  admin_notes    TEXT         NULL,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customer_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS customer_addresses;
CREATE TABLE customer_addresses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NOT NULL,
  label          VARCHAR(40)  NOT NULL DEFAULT 'Home',
  full_name      VARCHAR(150) NOT NULL,
  phone          VARCHAR(30)  NOT NULL,
  address_line1  VARCHAR(255) NOT NULL,
  address_line2  VARCHAR(255) NULL,
  city           VARCHAR(100) NOT NULL,
  region         VARCHAR(100) NOT NULL,
  postal_code    VARCHAR(20)  NULL,
  country        VARCHAR(80)  NOT NULL DEFAULT 'Pakistan',
  is_default     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_addr_customer (customer_id),
  CONSTRAINT fk_addr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Catalogue
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id        INT UNSIGNED NULL,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(140) NOT NULL UNIQUE,
  short_description VARCHAR(255) NULL,
  description      TEXT         NULL,
  image            VARCHAR(255) NULL,
  banner_image     VARCHAR(255) NULL,
  image_alt        VARCHAR(190) NULL,
  seo_title        VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  sort_order       INT          NOT NULL DEFAULT 0,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  show_on_home     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cat_parent (parent_id, sort_order),
  KEY idx_cat_active (is_active),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id         INT UNSIGNED NOT NULL,
  subcategory_id      INT UNSIGNED NULL,
  name                VARCHAR(190) NOT NULL,
  slug                VARCHAR(200) NOT NULL UNIQUE,
  sku                 VARCHAR(64)  NOT NULL UNIQUE,
  short_description   VARCHAR(500) NULL,
  description         MEDIUMTEXT   NULL,
  regular_price       DECIMAL(12,2) NOT NULL,
  sale_price          DECIMAL(12,2) NULL,
  cost_price          DECIMAL(12,2) NULL,
  track_stock         TINYINT(1)   NOT NULL DEFAULT 1,
  stock_status        ENUM('in_stock','out_of_stock','preorder') NOT NULL DEFAULT 'in_stock',
  low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
  is_featured         TINYINT(1)   NOT NULL DEFAULT 0,
  is_new_arrival      TINYINT(1)   NOT NULL DEFAULT 0,
  is_best_seller      TINYINT(1)   NOT NULL DEFAULT 0,
  wallet_type         VARCHAR(60)  NULL,
  material            VARCHAR(120) NULL,
  finish              VARCHAR(120) NULL,
  care_instructions   TEXT         NULL,
  weight_grams        INT UNSIGNED NULL,
  length_mm           DECIMAL(7,1) NULL,
  width_mm            DECIMAL(7,1) NULL,
  height_mm           DECIMAL(7,1) NULL,
  gift_wrap_available TINYINT(1)   NOT NULL DEFAULT 0,
  gift_wrap_price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status              ENUM('draft','published','inactive') NOT NULL DEFAULT 'draft',
  seo_title           VARCHAR(190) NULL,
  meta_description    VARCHAR(320) NULL,
  canonical_url       VARCHAR(255) NULL,
  og_title            VARCHAR(190) NULL,
  og_description      VARCHAR(320) NULL,
  og_image            VARCHAR(255) NULL,
  view_count          INT UNSIGNED NOT NULL DEFAULT 0,
  published_at        DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prod_cat (category_id, status),
  KEY idx_prod_subcat (subcategory_id, status),
  KEY idx_prod_status (status, created_at),
  KEY idx_prod_flags (is_new_arrival, is_best_seller, is_featured),
  FULLTEXT KEY ft_prod_search (name, short_description, sku),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_prod_subcat FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_images;
CREATE TABLE product_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(190) NULL,
  caption     VARCHAR(255) NULL,
  is_main     TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_img_product (product_id, sort_order),
  CONSTRAINT fk_img_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variants;
CREATE TABLE product_variants (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id     INT UNSIGNED NOT NULL,
  sku            VARCHAR(64)  NOT NULL UNIQUE,
  label          VARCHAR(120) NOT NULL,
  color_name     VARCHAR(60)  NULL,
  color_hex      CHAR(7)      NULL,
  price_override DECIMAL(12,2) NULL,
  sale_override  DECIMAL(12,2) NULL,
  image_id       INT UNSIGNED NULL,
  sort_order     INT          NOT NULL DEFAULT 0,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_var_product (product_id, sort_order),
  KEY idx_var_color (color_name),
  CONSTRAINT fk_var_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_var_image FOREIGN KEY (image_id) REFERENCES product_images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_variant_values;
CREATE TABLE product_variant_values (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id      INT UNSIGNED NOT NULL,
  attribute_name  VARCHAR(60)  NOT NULL,
  attribute_value VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_var_attr (variant_id, attribute_name),
  KEY idx_attr_lookup (attribute_name, attribute_value),
  CONSTRAINT fk_pvv_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per sellable unit: variant_id NULL = the product itself (no variants).
DROP TABLE IF EXISTS product_inventory;
CREATE TABLE product_inventory (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id          INT UNSIGNED NOT NULL,
  variant_id          INT UNSIGNED NULL,
  variant_key         INT UNSIGNED AS (IFNULL(variant_id, 0)) STORED,
  quantity            INT          NOT NULL DEFAULT 0,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inv_unit (product_id, variant_key),
  CONSTRAINT fk_inv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS inventory_movements;
CREATE TABLE inventory_movements (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  variant_id  INT UNSIGNED NULL,
  change_qty  INT          NOT NULL,
  balance     INT          NOT NULL,
  reason      ENUM('order','order_cancel','manual','initial','refund','correction') NOT NULL,
  order_id    INT UNSIGNED NULL,
  admin_id    INT UNSIGNED NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_product (product_id, created_at),
  CONSTRAINT fk_mov_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS product_relations;
CREATE TABLE product_relations (
  product_id    INT UNSIGNED NOT NULL,
  related_id    INT UNSIGNED NOT NULL,
  relation_type ENUM('related','cross_sell','upsell') NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, related_id, relation_type),
  CONSTRAINT fk_rel_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_related FOREIGN KEY (related_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collections;
CREATE TABLE collections (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(150) NOT NULL,
  slug             VARCHAR(160) NOT NULL UNIQUE,
  description      TEXT         NULL,
  image            VARCHAR(255) NULL,
  image_alt        VARCHAR(190) NULL,
  seo_title        VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  sort_order       INT          NOT NULL DEFAULT 0,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS collection_products;
CREATE TABLE collection_products (
  collection_id INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, product_id),
  CONSTRAINT fk_cp_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id            INT UNSIGNED NOT NULL,
  customer_id           INT UNSIGNED NULL,
  author_name           VARCHAR(120) NOT NULL,
  author_email          VARCHAR(190) NOT NULL,
  rating                TINYINT UNSIGNED NOT NULL,
  title                 VARCHAR(150) NULL,
  body                  TEXT         NOT NULL,
  status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  is_verified_purchase  TINYINT(1)   NOT NULL DEFAULT 0,
  admin_reply           TEXT         NULL,
  ip_address            VARCHAR(45)  NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rev_product (product_id, status),
  CONSTRAINT chk_rev_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Cart & wishlist
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS carts;
CREATE TABLE carts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NULL UNIQUE,
  session_token  CHAR(64)     NULL UNIQUE,
  coupon_code    VARCHAR(40)  NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cart_updated (updated_at),
  CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id      INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  variant_id   INT UNSIGNED NULL,
  variant_key  INT UNSIGNED AS (IFNULL(variant_id, 0)) STORED,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  gift_wrap    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_line (cart_id, product_id, variant_key),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlists;
CREATE TABLE wishlists (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NULL UNIQUE,
  session_token  CHAR(64)     NULL UNIQUE,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS wishlist_items;
CREATE TABLE wishlist_items (
  wishlist_id  INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id, product_id),
  CONSTRAINT fk_wli_wl FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_wli_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Shipping
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS shipping_zones;
CREATE TABLE shipping_zones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120) NOT NULL,
  is_default     TINYINT(1)   NOT NULL DEFAULT 0,
  cod_available  TINYINT(1)   NOT NULL DEFAULT 1,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_zone_locations;
CREATE TABLE shipping_zone_locations (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id   INT UNSIGNED NOT NULL,
  city      VARCHAR(100) NULL,
  region    VARCHAR(100) NULL,
  KEY idx_szl_city (city),
  KEY idx_szl_region (region),
  CONSTRAINT fk_szl_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS shipping_rates;
CREATE TABLE shipping_rates (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id          INT UNSIGNED NOT NULL,
  method           ENUM('standard','express') NOT NULL DEFAULT 'standard',
  name             VARCHAR(120) NOT NULL,
  rate             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  free_over        DECIMAL(12,2) NULL,
  min_days         TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_days         TINYINT UNSIGNED NOT NULL DEFAULT 5,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_zone_method (zone_id, method),
  CONSTRAINT fk_sr_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Coupons
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code               VARCHAR(40)  NOT NULL UNIQUE,
  description        VARCHAR(255) NULL,
  discount_type      ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  discount_value     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  min_order_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  max_discount       DECIMAL(12,2) NULL,
  usage_limit        INT UNSIGNED NULL,
  per_customer_limit INT UNSIGNED NULL,
  times_used         INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at          DATETIME     NULL,
  ends_at            DATETIME     NULL,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Orders & payments
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number       VARCHAR(24)  NOT NULL UNIQUE,
  customer_id        INT UNSIGNED NULL,
  customer_name      VARCHAR(150) NOT NULL,
  email              VARCHAR(190) NOT NULL,
  phone              VARCHAR(30)  NOT NULL,
  status             ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded','on_hold') NOT NULL DEFAULT 'pending',
  payment_method     ENUM('cod','easypaisa','jazzcash','card') NOT NULL,
  payment_status     ENUM('unpaid','pending','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
  currency           CHAR(3)      NOT NULL DEFAULT 'PKR',
  subtotal           DECIMAL(12,2) NOT NULL,
  discount_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  cod_fee            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  gift_wrap_total    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total        DECIMAL(12,2) NOT NULL,
  refunded_total     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  coupon_code        VARCHAR(40)  NULL,
  shipping_zone_id   INT UNSIGNED NULL,
  shipping_method    ENUM('standard','express') NOT NULL DEFAULT 'standard',
  shipping_label     VARCHAR(120) NULL,
  delivery_estimate  VARCHAR(80)  NULL,
  customer_note      TEXT         NULL,
  courier_name       VARCHAR(100) NULL,
  tracking_number    VARCHAR(100) NULL,
  tracking_url       VARCHAR(255) NULL,
  cod_collected_at   DATETIME     NULL,
  cod_collected_by   INT UNSIGNED NULL,
  idempotency_key    CHAR(64)     NOT NULL UNIQUE,
  access_token_hash  CHAR(64)     NOT NULL,
  ip_address         VARCHAR(45)  NULL,
  user_agent         VARCHAR(255) NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ord_customer (customer_id, created_at),
  KEY idx_ord_status (status, created_at),
  KEY idx_ord_payment (payment_method, payment_status),
  KEY idx_ord_email (email),
  KEY idx_ord_created (created_at),
  CONSTRAINT fk_ord_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_ord_zone FOREIGN KEY (shipping_zone_id) REFERENCES shipping_zones(id) ON DELETE SET NULL,
  CONSTRAINT fk_ord_cod_admin FOREIGN KEY (cod_collected_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        INT UNSIGNED NOT NULL,
  product_id      INT UNSIGNED NULL,
  variant_id      INT UNSIGNED NULL,
  product_name    VARCHAR(190) NOT NULL,
  sku             VARCHAR(64)  NOT NULL,
  variant_label   VARCHAR(120) NULL,
  image_path      VARCHAR(255) NULL,
  unit_price      DECIMAL(12,2) NOT NULL,
  regular_price   DECIMAL(12,2) NOT NULL,
  quantity        INT UNSIGNED NOT NULL,
  gift_wrap       TINYINT(1)   NOT NULL DEFAULT 0,
  gift_wrap_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  line_total      DECIMAL(12,2) NOT NULL,
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_addresses;
CREATE TABLE order_addresses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id       INT UNSIGNED NOT NULL,
  address_type   ENUM('shipping','billing') NOT NULL DEFAULT 'shipping',
  full_name      VARCHAR(150) NOT NULL,
  phone          VARCHAR(30)  NOT NULL,
  address_line1  VARCHAR(255) NOT NULL,
  address_line2  VARCHAR(255) NULL,
  city           VARCHAR(100) NOT NULL,
  region         VARCHAR(100) NOT NULL,
  postal_code    VARCHAR(20)  NULL,
  country        VARCHAR(80)  NOT NULL DEFAULT 'Pakistan',
  UNIQUE KEY uq_order_addr (order_id, address_type),
  CONSTRAINT fk_oa_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_status_history;
CREATE TABLE order_status_history (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id             INT UNSIGNED NOT NULL,
  status               VARCHAR(30)  NOT NULL,
  payment_status       VARCHAR(30)  NULL,
  note                 VARCHAR(500) NULL,
  is_customer_visible  TINYINT(1)   NOT NULL DEFAULT 1,
  admin_id             INT UNSIGNED NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS order_notes;
CREATE TABLE order_notes (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id             INT UNSIGNED NOT NULL,
  admin_id             INT UNSIGNED NULL,
  note                 TEXT         NOT NULL,
  is_customer_visible  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_on_order (order_id),
  CONSTRAINT fk_on_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_on_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS coupon_usage;
CREATE TABLE coupon_usage (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id        INT UNSIGNED NOT NULL,
  order_id         INT UNSIGNED NOT NULL UNIQUE,
  customer_id      INT UNSIGNED NULL,
  email            VARCHAR(190) NOT NULL,
  discount_amount  DECIMAL(12,2) NOT NULL,
  used_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cu_coupon_email (coupon_id, email),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_gateways;
CREATE TABLE payment_gateways (
  code          VARCHAR(30)  NOT NULL PRIMARY KEY,
  display_name  VARCHAR(80)  NOT NULL,
  description   VARCHAR(255) NULL,
  is_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
  mode          ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  config        JSON         NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id            INT UNSIGNED NOT NULL,
  method              ENUM('cod','easypaisa','jazzcash','card') NOT NULL,
  provider            VARCHAR(30)  NOT NULL,
  reference           VARCHAR(40)  NOT NULL UNIQUE,
  provider_reference  VARCHAR(100) NULL,
  amount              DECIMAL(12,2) NOT NULL,
  refunded_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency            CHAR(3)      NOT NULL DEFAULT 'PKR',
  status              ENUM('pending','processing','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  paid_at             DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_order (order_id),
  KEY idx_pay_status (status, created_at),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS payment_transactions;
CREATE TABLE payment_transactions (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id          INT UNSIGNED NOT NULL,
  order_id            INT UNSIGNED NOT NULL,
  txn_type            ENUM('initiate','callback','return','inquiry','refund','cod_collection','manual') NOT NULL,
  status              VARCHAR(30)  NOT NULL,
  amount              DECIMAL(12,2) NULL,
  provider_txn_id     VARCHAR(100) NULL,
  provider_code       VARCHAR(40)  NULL,
  provider_message    VARCHAR(255) NULL,
  signature_valid     TINYINT(1)   NULL,
  payload             JSON         NULL,
  admin_id            INT UNSIGNED NULL,
  ip_address          VARCHAR(45)  NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pt_payment (payment_id, created_at),
  KEY idx_pt_provider_txn (provider_txn_id),
  CONSTRAINT fk_pt_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Content, storefront & SEO
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS homepage_sections;
CREATE TABLE homepage_sections (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_key     VARCHAR(40)  NOT NULL UNIQUE,
  section_type    VARCHAR(40)  NOT NULL,
  label           VARCHAR(80)  NOT NULL,
  is_enabled      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order      INT          NOT NULL DEFAULT 0,
  settings        JSON         NOT NULL,
  draft_settings  JSON         NULL,
  draft_enabled   TINYINT(1)   NULL,
  draft_sort      INT          NULL,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS banner_slides;
CREATE TABLE banner_slides (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title              VARCHAR(190) NOT NULL,
  subtitle           VARCHAR(190) NULL,
  description        VARCHAR(500) NULL,
  image_desktop      VARCHAR(255) NOT NULL,
  image_mobile       VARCHAR(255) NULL,
  image_alt          VARCHAR(190) NULL,
  primary_btn_text   VARCHAR(60)  NULL,
  primary_btn_url    VARCHAR(255) NULL,
  secondary_btn_text VARCHAR(60)  NULL,
  secondary_btn_url  VARCHAR(255) NULL,
  text_align         ENUM('left','center','right') NOT NULL DEFAULT 'left',
  content_position   ENUM('top','middle','bottom') NOT NULL DEFAULT 'middle',
  text_color         CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  accent_color       CHAR(7)      NOT NULL DEFAULT '#B99A5B',
  btn_bg_color       CHAR(7)      NOT NULL DEFAULT '#214E9B',
  btn_text_color     CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  overlay_color      CHAR(7)      NOT NULL DEFAULT '#0A1426',
  overlay_opacity    TINYINT UNSIGNED NOT NULL DEFAULT 40,
  height_desktop     SMALLINT UNSIGNED NOT NULL DEFAULT 680,
  height_mobile      SMALLINT UNSIGNED NOT NULL DEFAULT 560,
  bg_position        VARCHAR(30)  NOT NULL DEFAULT 'center center',
  bg_size            ENUM('cover','contain','auto') NOT NULL DEFAULT 'cover',
  sort_order         INT          NOT NULL DEFAULT 0,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  starts_at          DATETIME     NULL,
  ends_at            DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_slide_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS testimonials;
CREATE TABLE testimonials (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_name   VARCHAR(120) NOT NULL,
  author_meta   VARCHAR(120) NULL,
  quote         TEXT         NOT NULL,
  rating        TINYINT UNSIGNED NULL,
  order_id      INT UNSIGNED NULL,
  source_note   VARCHAR(255) NULL,
  is_sample     TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order    INT          NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_testimonial_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pages;
CREATE TABLE pages (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title             VARCHAR(190) NOT NULL,
  slug              VARCHAR(160) NOT NULL UNIQUE,
  content           MEDIUMTEXT   NULL,
  seo_title         VARCHAR(190) NULL,
  meta_description  VARCHAR(320) NULL,
  footer_group      ENUM('none','company','service','policy') NOT NULL DEFAULT 'none',
  sort_order        INT          NOT NULL DEFAULT 0,
  is_published      TINYINT(1)   NOT NULL DEFAULT 1,
  noindex           TINYINT(1)   NOT NULL DEFAULT 0,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS site_settings;
CREATE TABLE site_settings (
  setting_key    VARCHAR(80)  NOT NULL PRIMARY KEY,
  setting_value  MEDIUMTEXT   NULL,
  setting_group  VARCHAR(40)  NOT NULL DEFAULT 'general',
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE newsletter_subscribers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email              VARCHAR(190) NOT NULL UNIQUE,
  status             ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  consent_text       VARCHAR(255) NOT NULL,
  consent_ip         VARCHAR(45)  NULL,
  source             VARCHAR(40)  NOT NULL DEFAULT 'footer',
  unsubscribe_token  CHAR(64)     NOT NULL UNIQUE,
  subscribed_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at    DATETIME     NULL,
  KEY idx_ns_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS redirects;
CREATE TABLE redirects (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_path  VARCHAR(255) NOT NULL UNIQUE,
  target_path  VARCHAR(255) NOT NULL,
  status_code  SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  is_auto      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE contact_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(30)  NULL,
  subject     VARCHAR(190) NOT NULL,
  message     TEXT         NOT NULL,
  status      ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cm_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS email_log;
CREATE TABLE email_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient   VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  template    VARCHAR(60)  NULL,
  status      ENUM('sent','failed','skipped') NOT NULL,
  error       VARCHAR(500) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_el_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Beglet core seed data (generated by tools/build-sample-data.php)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `admin_permissions` (`id`,`perm_key`,`label`,`group_name`) VALUES
(1,'dashboard.view','View dashboard','Dashboard'),
(2,'products.view','View products','Catalogue'),
(3,'products.edit','Create & edit products','Catalogue'),
(4,'products.delete','Delete products','Catalogue'),
(5,'products.cost_price','View & edit cost prices','Catalogue'),
(6,'categories.manage','Manage categories','Catalogue'),
(7,'collections.manage','Manage collections','Catalogue'),
(8,'inventory.manage','Manage inventory','Catalogue'),
(9,'reviews.manage','Moderate reviews','Catalogue'),
(10,'orders.view','View orders','Sales'),
(11,'orders.edit','Update orders & fulfilment','Sales'),
(12,'orders.refund','Record refunds & COD collection','Sales'),
(13,'orders.export','Export orders','Sales'),
(14,'coupons.manage','Manage coupons','Sales'),
(15,'customers.view','View customers','Customers'),
(16,'customers.edit','Edit customers','Customers'),
(17,'newsletter.manage','Manage newsletter','Customers'),
(18,'messages.view','Read contact messages','Customers'),
(19,'content.homepage','Homepage builder','Content'),
(20,'content.slides','Hero slides','Content'),
(21,'content.pages','Pages','Content'),
(22,'content.testimonials','Testimonials','Content'),
(23,'shipping.manage','Shipping & delivery','Settings'),
(24,'payments.manage','Payment gateways & transactions','Settings'),
(25,'settings.general','General settings','Settings'),
(26,'settings.theme','Theme settings','Settings'),
(27,'seo.manage','SEO tools','Settings'),
(28,'admins.manage','Manage administrators & roles','System'),
(29,'audit.view','View audit log','System');

INSERT INTO `admin_roles` (`id`,`name`,`slug`,`description`,`is_system`) VALUES
(1,'Super Admin','super_admin','Full control over the website, including administrators, payments and system settings.',1),
(2,'Store Manager','store_manager','Products, categories, inventory, orders and customers.',1),
(3,'Order Manager','order_manager','Order processing and fulfilment.',1),
(4,'Content Manager','content_manager','Banners, homepage sections and informational pages.',1);

INSERT INTO `admin_role_permissions` (`role_id`,`permission_id`) VALUES
(1,1),
(1,2),
(1,3),
(1,4),
(1,5),
(1,6),
(1,7),
(1,8),
(1,9),
(1,10),
(1,11),
(1,12),
(1,13),
(1,14),
(1,15),
(1,16),
(1,17),
(1,18),
(1,19),
(1,20),
(1,21),
(1,22),
(1,23),
(1,24),
(1,25),
(1,26),
(1,27),
(1,28),
(1,29),
(2,1),
(2,2),
(2,3),
(2,4),
(2,5),
(2,6),
(2,7),
(2,8),
(2,9),
(2,10),
(2,11),
(2,12),
(2,13),
(2,14),
(2,15),
(2,16),
(2,18),
(3,1),
(3,10),
(3,11),
(3,12),
(3,13),
(3,15),
(3,18),
(3,2),
(4,1),
(4,19),
(4,20),
(4,21),
(4,22),
(4,7),
(4,9),
(4,2);

-- Default administrator. Password: ChangeMe@2026 (you will be forced to change it on first login)
INSERT INTO `admins` (`id`,`role_id`,`name`,`email`,`password_hash`,`status`,`must_change_password`) VALUES
(1,1,'Store Owner','admin@example.com','$2y$10$FP7oz6EiBPD.3Gl2GODZKO3zjF8zCm7b8L42/QrUAjmnl53EnID3u','active',1);

INSERT INTO `site_settings` (`setting_key`,`setting_value`,`setting_group`) VALUES
('site_name','Beglet','general'),
('brand_name','Beglet','general'),
('site_tagline','Crafted Leather','general'),
('browser_title','Beglet — Premium Crafted Leather Wallets & Accessories','general'),
('seo_default_description','Beglet designs refined leather wallets, card holders and everyday carry accessories — timeless essentials built for daily use.','seo'),
('contact_phone','+92 300 0000000','general'),
('whatsapp_number','923000000000','general'),
('support_email','care@example.com','general'),
('notification_email','orders@example.com','general'),
('business_address','Studio address\nCity, Pakistan','general'),
('business_hours','Mon–Sat, 10:00–19:00','general'),
('social_instagram','https://www.instagram.com/','general'),
('social_facebook','https://www.facebook.com/','general'),
('social_tiktok','','general'),
('social_youtube','','general'),
('social_pinterest','','general'),
('social_x','','general'),
('currency_code','PKR','general'),
('currency_symbol','Rs.','general'),
('currency_position','before','general'),
('currency_decimals','0','general'),
('timezone','Asia/Karachi','general'),
('date_format','d M Y','general'),
('time_format','h:i A','general'),
('default_language','en','general'),
('maintenance_mode','0','general'),
('maintenance_message','We are refining a few details. Please check back shortly.','general'),
('logo_path','','general'),
('favicon_path','assets/img/brand/favicon.svg','general'),
('footer_about','Refined leather essentials designed for everyday carry — considered details, enduring style.','general'),
('copyright_text','Beglet. All rights reserved.','general'),
('store_country','Pakistan','general'),
('order_prefix','BG','general'),
('products_per_page','12','general'),
('wishlist_enabled','1','general'),
('reviews_enabled','1','general'),
('recently_viewed_enabled','1','general'),
('email_enabled','1','general'),
('email_status_updates','1','general'),
('newsletter_welcome_email','1','general'),
('low_stock_email','1','general'),
('checkout_require_terms','1','general'),
('unpaid_order_timeout_hours','2','general'),
('show_sample_content','1','general'),
('popular_searches','bifold, card holder, travel, key fob','general'),
('mega_menu_image','assets/img/sample/misc/mega-menu.jpg','general'),
('mega_menu_link','/shop','general'),
('mega_menu_caption','Shop the collection','general'),
('main_menu','[{"label":"Shop","url":"/shop","mega":true},{"label":"New Arrivals","url":"/shop?sort=newest"},{"label":"The Noir Edit","url":"/collection/the-noir-edit"},{"label":"Our Story","url":"/about-us"},{"label":"Contact","url":"/contact"}]','general'),
('announcement_enabled','1','general'),
('announcement_messages','[{"text":"Free delivery on orders over Rs. 5,000","url":"/shop"},{"text":"Cash on delivery available nationwide","url":""},{"text":"Gift packaging available on selected pieces","url":"/shop"}]','general'),
('announcement_bg','#0A1426','general'),
('announcement_color','#E8DDCC','general'),
('announcement_interval','5000','general'),
('pdp_delivery_text','Dispatched within 1–2 working days','general'),
('pdp_returns_text','Easy returns — see our returns policy','general'),
('pdp_shipping_returns','We deliver across Pakistan. Delivery charges and estimated delivery times are shown at checkout before you place your order.\n\nIf something is not right, you can request a return in line with our Returns Policy. Items should be unused and in their original packaging.','general'),
('whatsapp_float','1','general'),
('whatsapp_message','Hello Beglet, I have a question.','general'),
('contact_intro','Questions about a product or an order? We usually reply within one working day.','general'),
('shop_heading','Shop All','general'),
('shop_intro','Wallets, card holders and everyday accessories — designed to be carried daily and kept for years.','general'),
('shop_seo_title','Shop Leather Wallets, Card Holders & Accessories','seo'),
('shop_meta_description','Browse the full Beglet collection of leather wallets, card holders, travel wallets and accessories.','seo'),
('home_seo_title','','seo'),
('home_meta_description','Beglet designs refined leather wallets, card holders and everyday carry accessories — timeless essentials built for daily use.','seo'),
('shipping_default_rate','250','shipping'),
('shipping_default_estimate','3–5 working days','shipping'),
('free_shipping_enabled','1','shipping'),
('free_shipping_threshold','5000','shipping'),
('minimum_order_amount','0','shipping'),
('express_enabled','1','shipping'),
('checkout_regions','','shipping'),
('seo_title_template','{title} | {site}','seo'),
('seo_indexing_enabled','1','seo'),
('robots_txt','','seo'),
('ads_txt','','seo'),
('google_site_verification','','seo'),
('bing_site_verification','','seo'),
('ga4_measurement_id','','seo'),
('head_scripts','','seo'),
('body_scripts','','seo'),
('og_default_image','assets/img/brand/og-default.jpg','seo'),
('twitter_handle','','seo'),
('theme_primary','#214E9B','theme'),
('theme_secondary','#101D35','theme'),
('theme_dark','#0A1426','theme'),
('theme_ivory','#F7F5F0','theme'),
('theme_beige','#E8DDCC','theme'),
('theme_accent','#B99A5B','theme'),
('theme_header_bg','#101D35','theme'),
('theme_header_text','#F7F5F0','theme'),
('theme_footer_bg','#0A1426','theme'),
('theme_footer_text','#E8DDCC','theme'),
('theme_button_bg','#214E9B','theme'),
('theme_button_text','#FFFFFF','theme'),
('theme_body_bg','#F7F5F0','theme'),
('theme_text','#1B2333','theme'),
('theme_fonts','cormorant_inter','theme'),
('theme_base_font_size','16','theme'),
('theme_container','1320','theme'),
('theme_section_spacing','96','theme'),
('theme_radius','4','theme'),
('theme_card_style','classic','theme'),
('theme_header_layout','logo_left','theme'),
('theme_footer_layout','columns','theme'),
('theme_mobile_columns','2','theme'),
('theme_mobile_sticky_cart','1','theme'),
('theme_animations','1','theme');

INSERT INTO `payment_gateways` (`code`,`display_name`,`description`,`is_enabled`,`mode`,`config`,`sort_order`) VALUES
('cod','Cash on Delivery','Pay in cash when your order arrives.',1,'live','{"fee":"0","max_order_amount":"0","instructions":"Please keep the exact amount ready for the courier."}',1),
('easypaisa','Easypaisa','Pay with your Easypaisa mobile account.',0,'sandbox','{}',2),
('jazzcash','JazzCash','Pay with your JazzCash mobile account.',0,'sandbox','{}',3),
('card','Debit / Credit Card','Visa & Mastercard via a secure hosted payment page.',0,'sandbox','{"provider":"jazzcash"}',4);

INSERT INTO `shipping_zones` (`id`,`name`,`is_default`,`cod_available`,`is_active`,`sort_order`) VALUES
(1,'Karachi',0,1,1,1),
(2,'Lahore',0,1,1,2),
(3,'Islamabad & Rawalpindi',0,1,1,3),
(4,'Rest of Pakistan',1,1,1,9);

INSERT INTO `shipping_zone_locations` (`zone_id`,`city`,`region`) VALUES
(1,'Karachi','Sindh'),
(2,'Lahore','Punjab'),
(3,'Islamabad','Islamabad Capital Territory'),
(3,'Rawalpindi','Punjab');

INSERT INTO `shipping_rates` (`zone_id`,`method`,`name`,`rate`,`free_over`,`min_days`,`max_days`,`is_active`) VALUES
(1,'standard','Standard delivery',200,NULL,1,3,1),
(1,'express','Express (next working day)',450,NULL,1,1,1),
(2,'standard','Standard delivery',200,NULL,1,3,1),
(2,'express','Express (next working day)',450,NULL,1,1,1),
(3,'standard','Standard delivery',220,NULL,2,4,1),
(3,'express','Express',500,NULL,1,2,1),
(4,'standard','Standard delivery',300,NULL,3,6,1);

INSERT INTO `pages` (`title`,`slug`,`footer_group`,`content`,`sort_order`,`is_published`,`seo_title`,`meta_description`,`noindex`) VALUES
('About Us','about-us','company','<h2>Crafted to be carried</h2><p>Beglet began with a simple idea: the things you carry every day should be made with care, look better with age and never get in your way.</p><p>We design wallets, card holders and small leather goods with clean lines, considered proportions and practical layouts. Every detail — from the spacing of a card slot to the finish of an edge — is chosen to make daily use effortless.</p><h3>What we believe</h3><ul><li><strong>Fewer, better things.</strong> Pieces designed to be used daily for years.</li><li><strong>Honest materials.</strong> We describe our materials accurately on every product page.</li><li><strong>Service that cares.</strong> Real people answering your questions.</li></ul><p><em>Store owner: please review and personalise this page, adding only claims about materials, production and origin that you can substantiate.</em></p>',0,1,NULL,NULL,0),
('Shipping Policy','shipping-policy','service','<h2>Delivery across Pakistan</h2><p>Delivery charges and estimated delivery times for your city are calculated and shown at checkout before you place your order.</p><ul><li>Orders are usually dispatched within 1–2 working days.</li><li>Standard and, where available, express options are offered at checkout.</li><li>Free standard delivery applies to orders above the threshold shown on our website.</li></ul><p>You will receive tracking details by email once your order ships. You can also check your order status anytime on our <a href="/track-order">order tracking page</a>.</p>',1,1,NULL,NULL,0),
('Returns & Exchanges','returns-policy','service','<h2>Returns</h2><p>If you are not satisfied with your purchase, please contact us within 7 days of delivery to arrange a return or exchange.</p><ul><li>Items must be unused, in original condition and packaging.</li><li>Personalised items and gift cards cannot be returned unless faulty.</li><li>Refunds are issued to the original payment method once the return is received and inspected. Cash on delivery orders are refunded by bank transfer or mobile wallet.</li></ul><p><em>Store owner: adjust the return window and conditions to match your actual policy.</em></p>',2,1,NULL,NULL,0),
('FAQ','faq','service','<h3>Do you offer cash on delivery?</h3><p>Yes — cash on delivery is available across most of Pakistan. Availability and any applicable fee are shown at checkout.</p><h3>Do I need an account to order?</h3><p>No. You can check out as a guest and track your order using your order number and email address.</p><h3>How do I care for my wallet?</h3><p>Wipe with a soft dry cloth and keep away from prolonged moisture and heat. Specific care guidance is listed on each product page.</p>',3,1,NULL,NULL,0),
('Privacy Policy','privacy-policy','policy','<h2>Your privacy</h2><p>We collect the information needed to process your orders and provide customer service: your name, contact details, delivery address and order history.</p><h3>How we use your data</h3><ul><li>To fulfil and deliver orders and to contact you about them.</li><li>To send marketing emails only if you have opted in — you can unsubscribe at any time via the link in every email.</li><li>To keep our website secure and prevent fraud.</li></ul><h3>Payments</h3><p>Online payments are processed on our payment providers\' secure pages. We never receive or store your card number, CVV or wallet PIN.</p><h3>Your rights</h3><p>You may request access to, correction of or deletion of your personal data by contacting us.</p><p><em>Store owner: have this policy reviewed to make sure it reflects your actual practices and applicable law.</em></p>',4,1,NULL,NULL,0),
('Terms & Conditions','terms-and-conditions','policy','<h2>Terms of sale</h2><p>By placing an order you agree to these terms. Prices are shown in Pakistani Rupees and include applicable taxes unless stated otherwise.</p><ul><li>Orders are subject to availability and confirmation.</li><li>We reserve the right to cancel orders in case of pricing errors or suspected fraud; any payment taken will be refunded in full.</li><li>Product colours may vary slightly from images due to screen settings.</li></ul><p><em>Store owner: review these terms with a legal adviser before launch.</em></p>',5,1,NULL,NULL,0);

INSERT INTO `homepage_sections` (`section_key`,`section_type`,`label`,`is_enabled`,`sort_order`,`settings`,`draft_settings`) VALUES
('hero','hero','Hero carousel',1,10,'{"autoplay":"1","interval":"6500","transition":"fade","transition_speed":"1400","show_arrows":"1","show_dots":"1","ken_burns":"1"}',NULL),
('categories','categories','Shop by category',1,20,'{"eyebrow":"The collection","title":"Shop by Category","subtitle":"Six families of everyday essentials, each designed around how you actually carry.","source":"flagged","limit":"6","layout":"mosaic"}',NULL),
('new_arrivals','new_arrivals','New arrivals',1,30,'{"eyebrow":"Just in","title":"New Arrivals","subtitle":"The latest additions to the Beglet collection.","source":"flagged","count":"8","cta_label":"Shop new arrivals","cta_url":"/shop?sort=newest"}',NULL),
('brand_story','brand_story','Brand story',1,40,'{"eyebrow":"Crafted to last","title":"Made with patience. Designed for years.","body":"Every Beglet piece starts with a question: how will this be used, every single day? We obsess over the small things — the depth of a card slot, the weight of a fold, the way an edge feels in your hand.\\n\\nThe result is a collection of quiet, considered essentials that become more personal the longer you carry them.","image":"assets/img/sample/story/materials.jpg","image_alt":"A fan of leather swatches in cognac, espresso, oxblood, tan, navy and black","secondary_image":"assets/img/sample/story/stitch-detail.jpg","secondary_alt":"Close-up of contrast stitching on a cognac wallet","image_position":"left","parallax":"1","button_label":"Discover our story","button_url":"/about-us","bg_color":""}',NULL),
('best_sellers','best_sellers','Best sellers',1,50,'{"eyebrow":"Most loved","title":"Best Sellers","subtitle":"The pieces our customers return to again and again.","source":"auto","count":"8","cta_label":"Shop best sellers","cta_url":"/shop?sort=popular","bg_color":"#101D35","text_theme":"light"}',NULL),
('featured_collection','featured_collection','Featured collection',1,60,'{"eyebrow":"Limited edit","title":"The Noir Edit","description":"Black pebbled leather finished with antique-gold stitching. A study in understatement.","source_type":"collection","collection_id":"1","image":"assets/img/sample/misc/collection-noir.jpg","image_alt":"Black leather wallets with gold stitching on a navy background","layout":"split_left","product_count":"4","button_label":"Explore The Noir Edit"}',NULL),
('benefits','benefits','Craftsmanship & benefits',1,70,'{"eyebrow":"The Beglet standard","title":"Considered in every detail","subtitle":"","bg_color":"","items":[{"icon":"gem","title":"Selected leathers","text":"Each material is chosen for how it feels, wears and ages."},{"icon":"scissors","title":"Careful construction","text":"Precise cutting, even stitching and finished edges."},{"icon":"grid-3x3-gap","title":"Everyday function","text":"Layouts designed around how you really carry."},{"icon":"gift","title":"Gift-ready packaging","text":"Optional presentation packaging on selected pieces."},{"icon":"headset","title":"Caring support","text":"Real people on WhatsApp, phone and email."}]}',NULL),
('testimonials','testimonials','Testimonials',1,80,'{"eyebrow":"Kind words","title":"From our customers","show_rating":"1","limit":"6"}',NULL),
('newsletter','newsletter','Newsletter',1,90,'{"title":"Join the Beglet circle","text":"New collections, care guides and private offers — never more than twice a month.","consent_text":"I agree to receive marketing emails from Beglet. I can unsubscribe at any time.","button_label":"Subscribe","image":"assets/img/sample/misc/leather-texture.jpg"}',NULL);

SET FOREIGN_KEY_CHECKS = 1;

-- Beglet demo catalogue (generated by tools/build-sample-data.php)
-- Images are procedurally rendered placeholders in assets/img/sample — replace with real photography.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `categories` (`id`,`parent_id`,`name`,`slug`,`short_description`,`description`,`image`,`banner_image`,`image_alt`,`seo_title`,`meta_description`,`sort_order`,`is_active`,`show_on_home`) VALUES
(1,NULL,'Bifold Wallets','bifold-wallets','The timeless fold — card slots, note compartments and a slim profile.','<p>The timeless fold — card slots, note compartments and a slim profile. Explore the bifold wallets collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/bifold-wallets.jpg','assets/img/sample/categories/bifold-wallets-banner.jpg','Bifold Wallets','Bifold Wallets — Leather Collection','The timeless fold — card slots, note compartments and a slim profile.',1,1,1),
(2,NULL,'Minimalist Wallets','minimalist-wallets','Slim designs for those who carry only what matters.','<p>Slim designs for those who carry only what matters. Explore the minimalist wallets collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/minimalist-wallets.jpg','assets/img/sample/categories/minimalist-wallets-banner.jpg','Minimalist Wallets','Minimalist Wallets — Leather Collection','Slim designs for those who carry only what matters.',2,1,1),
(3,NULL,'Card Holders','card-holders','Compact holders for cards and folded notes.','<p>Compact holders for cards and folded notes. Explore the card holders collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/card-holders.jpg','assets/img/sample/categories/card-holders-banner.jpg','Card Holders','Card Holders — Leather Collection','Compact holders for cards and folded notes.',3,1,1),
(4,NULL,'Long Wallets','long-wallets','Generous long wallets that keep notes flat and cards organised.','<p>Generous long wallets that keep notes flat and cards organised. Explore the long wallets collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/long-wallets.jpg','assets/img/sample/categories/long-wallets-banner.jpg','Long Wallets','Long Wallets — Leather Collection','Generous long wallets that keep notes flat and cards organised.',4,1,1),
(5,NULL,'Travel Wallets','travel-wallets','Passport covers and organisers for the journey.','<p>Passport covers and organisers for the journey. Explore the travel wallets collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/travel-wallets.jpg','assets/img/sample/categories/travel-wallets-banner.jpg','Travel Wallets','Travel Wallets — Leather Collection','Passport covers and organisers for the journey.',5,1,1),
(6,NULL,'Leather Accessories','leather-accessories','Key holders, pouches and finishing touches.','<p>Key holders, pouches and finishing touches. Explore the leather accessories collection from Beglet — designed for everyday carry with considered details.</p>','assets/img/sample/categories/leather-accessories.jpg','assets/img/sample/categories/leather-accessories-banner.jpg','Leather Accessories','Leather Accessories — Leather Collection','Key holders, pouches and finishing touches.',6,1,1),
(7,1,'Classic Bifold','classic-bifold','Traditional bifolds with multiple card slots.','<p>Traditional bifolds with multiple card slots. Explore the classic bifold collection from Beglet — designed for everyday carry with considered details.</p>',NULL,NULL,'Classic Bifold','Classic Bifold — Leather Wallets','Traditional bifolds with multiple card slots.',1,1,0),
(8,1,'Coin Pocket Bifold','coin-pocket-bifold','Bifolds with a dedicated coin pocket.','<p>Bifolds with a dedicated coin pocket. Explore the coin pocket bifold collection from Beglet — designed for everyday carry with considered details.</p>',NULL,NULL,'Coin Pocket Bifold','Coin Pocket Bifold — Leather Wallets','Bifolds with a dedicated coin pocket.',2,1,0),
(9,2,'Slim Wallets','slim-wallets','Ultra-thin wallets for the front pocket.','<p>Ultra-thin wallets for the front pocket. Explore the slim wallets collection from Beglet — designed for everyday carry with considered details.</p>',NULL,NULL,'Slim Wallets','Slim Wallets — Leather Wallets','Ultra-thin wallets for the front pocket.',1,1,0),
(10,6,'Key Holders','key-holders','Leather key fobs and organisers.','<p>Leather key fobs and organisers. Explore the key holders collection from Beglet — designed for everyday carry with considered details.</p>',NULL,NULL,'Key Holders','Key Holders — Leather Wallets','Leather key fobs and organisers.',1,1,0),
(11,6,'Pouches','pouches','Coin and earphone pouches.','<p>Coin and earphone pouches. Explore the pouches collection from Beglet — designed for everyday carry with considered details.</p>',NULL,NULL,'Pouches','Pouches — Leather Wallets','Coin and earphone pouches.',2,1,0);

INSERT INTO `products` (`id`,`category_id`,`subcategory_id`,`name`,`slug`,`sku`,`short_description`,`description`,`regular_price`,`sale_price`,`cost_price`,`track_stock`,`stock_status`,`low_stock_threshold`,`is_featured`,`is_new_arrival`,`is_best_seller`,`wallet_type`,`material`,`finish`,`care_instructions`,`weight_grams`,`length_mm`,`width_mm`,`height_mm`,`gift_wrap_available`,`gift_wrap_price`,`status`,`seo_title`,`meta_description`,`published_at`,`created_at`) VALUES
(1,1,7,'Heritage Bifold Wallet','heritage-bifold-wallet','BG-BF-001','A classic bifold with eight card slots, two note compartments and a slim profile that settles comfortably into a jacket or trouser pocket.','<p>A classic bifold with eight card slots, two note compartments and a slim profile that settles comfortably into a jacket or trouser pocket.</p><p>Designed by Beglet for daily use, the Heritage Bifold Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Burnished edges</li><li>Dimensions: approx. 112 × 85 mm</li></ul>',6950,5950,2919,1,'in_stock',5,1,1,1,'Bifold','Pebbled leather','Burnished edges','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',78,112,85,14,1,350,'published',NULL,NULL,'2026-09-01 00:00:00','2026-09-01 00:00:00'),
(2,1,8,'Signature Coin Bifold','signature-coin-bifold','BG-BF-002','Our bifold with a press-stud coin pocket — six card slots, a full-length note section and space for loose change.','<p>Our bifold with a press-stud coin pocket — six card slots, a full-length note section and space for loose change.</p><p>Designed by Beglet for daily use, the Signature Coin Bifold pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Matte</li><li>Dimensions: approx. 115 × 90 mm</li></ul>',7450,NULL,3129,1,'in_stock',5,0,0,1,'Bifold','Smooth leather','Matte','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',92,115,90,18,1,350,'published',NULL,NULL,'2026-09-02 00:00:00','2026-09-02 00:00:00'),
(3,1,7,'Executive Bifold Wallet','executive-bifold-wallet','BG-BF-003','Twelve card slots, two hidden pockets and a structured spine designed to hold its shape for years of daily use.','<p>Twelve card slots, two hidden pockets and a structured spine designed to hold its shape for years of daily use.</p><p>Designed by Beglet for daily use, the Executive Bifold Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Polished</li><li>Dimensions: approx. 120 × 95 mm</li></ul>',8250,NULL,3465,1,'in_stock',5,1,0,0,'Bifold','Smooth leather','Polished','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',88,120,95,15,1,350,'published',NULL,NULL,'2026-09-03 00:00:00','2026-09-03 00:00:00'),
(4,2,9,'Slimline Minimalist Wallet','slimline-minimalist-wallet','BG-MN-001','Just six millimetres thick: four card slots and a central pocket for folded notes. Everything you need, nothing you don\'t.','<p>Just six millimetres thick: four card slots and a central pocket for folded notes. Everything you need, nothing you don\'t.</p><p>Designed by Beglet for daily use, the Slimline Minimalist Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Natural</li><li>Dimensions: approx. 102 × 70 mm</li></ul>',4450,NULL,1869,1,'in_stock',5,0,1,1,'Minimalist','Smooth leather','Natural','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',34,102,70,6,1,250,'published',NULL,NULL,'2026-09-04 00:00:00','2026-09-04 00:00:00'),
(5,2,9,'Front Pocket Minimal Wallet','front-pocket-minimal-wallet','BG-MN-002','Designed for the front pocket — a quick-access thumb slot, three card pockets and a clean, rounded silhouette.','<p>Designed for the front pocket — a quick-access thumb slot, three card pockets and a clean, rounded silhouette.</p><p>Designed by Beglet for daily use, the Front Pocket Minimal Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Natural</li><li>Dimensions: approx. 100 × 68 mm</li></ul>',3950,3450,1659,1,'in_stock',5,0,1,0,'Minimalist','Pebbled leather','Natural','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',30,100,68,7,1,250,'published',NULL,NULL,'2026-09-05 00:00:00','2026-09-05 00:00:00'),
(6,3,NULL,'Atelier Card Holder','atelier-card-holder','BG-CH-001','Four exterior slots and a central pocket in a scratch-resistant cross-grain finish. A refined everyday essential.','<p>Four exterior slots and a central pocket in a scratch-resistant cross-grain finish. A refined everyday essential.</p><p>Designed by Beglet for daily use, the Atelier Card Holder pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Textured leather</li><li>Finish: Cross-grain</li><li>Dimensions: approx. 102 × 72 mm</li></ul>',2950,NULL,1239,1,'in_stock',5,1,0,1,'Card holder','Textured leather','Cross-grain','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',22,102,72,5,1,250,'published',NULL,NULL,'2026-09-06 00:00:00','2026-09-06 00:00:00'),
(7,3,NULL,'Quick-Draw Card Sleeve','quick-draw-card-sleeve','BG-CH-002','A pull-tab sleeve that presents your most-used cards in a single motion, with two quick-access side slots.','<p>A pull-tab sleeve that presents your most-used cards in a single motion, with two quick-access side slots.</p><p>Designed by Beglet for daily use, the Quick-Draw Card Sleeve pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Satin</li><li>Dimensions: approx. 100 × 70 mm</li></ul>',2650,NULL,1113,1,'in_stock',5,0,1,0,'Card holder','Smooth leather','Satin','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',18,100,70,5,0,0,'published',NULL,NULL,'2026-09-07 00:00:00','2026-09-07 00:00:00'),
(8,3,NULL,'Noir Card Case','noir-card-case','BG-CH-003','Black pebbled leather finished with contrast antique-gold stitching — part of The Noir Edit.','<p>Black pebbled leather finished with contrast antique-gold stitching — part of The Noir Edit.</p><p>Designed by Beglet for daily use, the Noir Card Case pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Gold-tone stitching</li><li>Dimensions: approx. 102 × 72 mm</li></ul>',3250,NULL,1365,1,'in_stock',5,0,0,0,'Card holder','Pebbled leather','Gold-tone stitching','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',24,102,72,6,1,250,'published',NULL,NULL,'2026-09-08 00:00:00','2026-09-08 00:00:00'),
(9,4,NULL,'Continental Zip Wallet','continental-zip-wallet','BG-LW-001','A full zip-around long wallet with sixteen card slots, a zipped coin section and room for a phone.','<p>A full zip-around long wallet with sixteen card slots, a zipped coin section and room for a phone.</p><p>Designed by Beglet for daily use, the Continental Zip Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Polished, metal zip</li><li>Dimensions: approx. 195 × 100 mm</li></ul>',9950,NULL,4179,1,'in_stock',5,1,1,0,'Long wallet','Smooth leather','Polished, metal zip','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',165,195,100,25,1,450,'published',NULL,NULL,'2026-09-09 00:00:00','2026-09-09 00:00:00'),
(10,4,NULL,'Classic Long Wallet','classic-long-wallet','BG-LW-002','An elegant long wallet that keeps notes flat and cards organised, ideal for a jacket breast pocket or bag.','<p>An elegant long wallet that keeps notes flat and cards organised, ideal for a jacket breast pocket or bag.</p><p>Designed by Beglet for daily use, the Classic Long Wallet pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Burnished edges</li><li>Dimensions: approx. 185 × 95 mm</li></ul>',8950,7950,3759,1,'in_stock',5,0,0,1,'Long wallet','Pebbled leather','Burnished edges','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',140,185,95,18,1,450,'published',NULL,NULL,'2026-09-10 00:00:00','2026-09-10 00:00:00'),
(11,5,NULL,'Voyager Passport Cover','voyager-passport-cover','BG-TW-001','Protects your passport and keeps boarding passes, cards and a pen within reach — secured with an elastic band.','<p>Protects your passport and keeps boarding passes, cards and a pen within reach — secured with an elastic band.</p><p>Designed by Beglet for daily use, the Voyager Passport Cover pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Natural</li><li>Dimensions: approx. 140 × 100 mm</li></ul>',5450,NULL,2289,1,'in_stock',5,1,1,0,'Travel wallet','Pebbled leather','Natural','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',82,140,100,10,1,350,'published',NULL,NULL,'2026-09-11 00:00:00','2026-09-11 00:00:00'),
(12,5,NULL,'Nomad Travel Organizer','nomad-travel-organizer','BG-TW-002','A document organiser for longer trips: two passport sleeves, SIM card slots, a zip pocket and ticket compartment.','<p>A document organiser for longer trips: two passport sleeves, SIM card slots, a zip pocket and ticket compartment.</p><p>Designed by Beglet for daily use, the Nomad Travel Organizer pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Matte</li><li>Dimensions: approx. 220 × 115 mm</li></ul>',7950,NULL,3339,1,'in_stock',5,0,0,0,'Travel wallet','Smooth leather','Matte','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',190,220,115,20,1,450,'published',NULL,NULL,'2026-09-12 00:00:00','2026-09-12 00:00:00'),
(13,6,10,'Loop Key Fob','loop-key-fob','BG-AC-001','A double-stitched leather loop with a solid split ring — a small luxury for everyday keys.','<p>A double-stitched leather loop with a solid split ring — a small luxury for everyday keys.</p><p>Designed by Beglet for daily use, the Loop Key Fob pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Brass-tone ring</li><li>Dimensions: approx. 110 × 30 mm</li></ul>',1850,NULL,777,1,'in_stock',5,0,0,1,'Key holder','Smooth leather','Brass-tone ring','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',26,110,30,6,1,200,'published',NULL,NULL,'2026-09-13 00:00:00','2026-09-13 00:00:00'),
(14,6,11,'Cove Coin Pouch','cove-coin-pouch','BG-AC-002','A rounded coin and earphone pouch that opens wide, with a discreet press-stud closure.','<p>A rounded coin and earphone pouch that opens wide, with a discreet press-stud closure.</p><p>Designed by Beglet for daily use, the Cove Coin Pouch pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Smooth leather</li><li>Finish: Press-stud closure</li><li>Dimensions: approx. 95 × 85 mm</li></ul>',2450,2150,1029,1,'in_stock',5,0,1,0,'Pouch','Smooth leather','Press-stud closure','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',32,95,85,20,1,200,'published',NULL,NULL,'2026-09-14 00:00:00','2026-09-14 00:00:00'),
(15,6,10,'Ridge Key Organizer','ridge-key-organizer','BG-AC-003','Holds up to six keys silently folded inside a pebbled leather case — no more scratched phones.','<p>Holds up to six keys silently folded inside a pebbled leather case — no more scratched phones.</p><p>Designed by Beglet for daily use, the Ridge Key Organizer pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Gunmetal hardware</li><li>Dimensions: approx. 115 × 40 mm</li></ul>',2250,NULL,945,1,'in_stock',5,0,0,0,'Key holder','Pebbled leather','Gunmetal hardware','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',40,115,40,15,1,200,'published',NULL,NULL,'2026-09-15 00:00:00','2026-09-15 00:00:00'),
(16,1,7,'Midnight Noir Bifold','midnight-noir-bifold','BG-BF-004','Black pebbled leather with antique-gold contrast stitching. The signature piece of The Noir Edit.','<p>Black pebbled leather with antique-gold contrast stitching. The signature piece of The Noir Edit.</p><p>Designed by Beglet for daily use, the Midnight Noir Bifold pairs a clean silhouette with a practical interior. Edges are finished for a refined feel and the stitching is kept tight and even for durability.</p><ul><li>Material: Pebbled leather</li><li>Finish: Gold-tone stitching</li><li>Dimensions: approx. 112 × 88 mm</li></ul>',7250,NULL,3045,1,'in_stock',5,1,0,0,'Bifold','Pebbled leather','Gold-tone stitching','<p>Wipe clean with a soft, dry cloth. Keep away from prolonged moisture, direct sunlight and heat. Condition occasionally with a leather care product suited to the finish, testing on a hidden area first.</p>',80,112,88,14,1,350,'published',NULL,NULL,'2026-09-16 00:00:00','2026-09-16 00:00:00');

INSERT INTO `product_images` (`id`,`product_id`,`file_path`,`alt_text`,`caption`,`is_main`,`sort_order`) VALUES
(1,1,'assets/img/sample/products/heritage-bifold-wallet-1.jpg','Heritage Bifold Wallet in cognac — front view',NULL,1,1),
(2,1,'assets/img/sample/products/heritage-bifold-wallet-2.jpg','Heritage Bifold Wallet in cognac — angled view',NULL,0,2),
(3,1,'assets/img/sample/products/heritage-bifold-wallet-3.jpg','Heritage Bifold Wallet in cognac — stitching detail',NULL,0,3),
(4,1,'assets/img/sample/products/heritage-bifold-wallet-espresso-1.jpg','Heritage Bifold Wallet in espresso',NULL,0,4),
(5,2,'assets/img/sample/products/signature-coin-bifold-1.jpg','Signature Coin Bifold in tan — front view',NULL,1,1),
(6,2,'assets/img/sample/products/signature-coin-bifold-2.jpg','Signature Coin Bifold in tan — angled view',NULL,0,2),
(7,2,'assets/img/sample/products/signature-coin-bifold-3.jpg','Signature Coin Bifold in tan — stitching detail',NULL,0,3),
(8,2,'assets/img/sample/products/signature-coin-bifold-navy-1.jpg','Signature Coin Bifold in navy',NULL,0,4),
(9,3,'assets/img/sample/products/executive-bifold-wallet-1.jpg','Executive Bifold Wallet in espresso — front view',NULL,1,1),
(10,3,'assets/img/sample/products/executive-bifold-wallet-2.jpg','Executive Bifold Wallet in espresso — angled view',NULL,0,2),
(11,3,'assets/img/sample/products/executive-bifold-wallet-3.jpg','Executive Bifold Wallet in espresso — stitching detail',NULL,0,3),
(12,3,'assets/img/sample/products/executive-bifold-wallet-black-1.jpg','Executive Bifold Wallet in black',NULL,0,4),
(13,4,'assets/img/sample/products/slimline-minimalist-wallet-1.jpg','Slimline Minimalist Wallet in navy — front view',NULL,1,1),
(14,4,'assets/img/sample/products/slimline-minimalist-wallet-2.jpg','Slimline Minimalist Wallet in navy — angled view',NULL,0,2),
(15,4,'assets/img/sample/products/slimline-minimalist-wallet-3.jpg','Slimline Minimalist Wallet in navy — stitching detail',NULL,0,3),
(16,4,'assets/img/sample/products/slimline-minimalist-wallet-stone-1.jpg','Slimline Minimalist Wallet in stone',NULL,0,4),
(17,5,'assets/img/sample/products/front-pocket-minimal-wallet-1.jpg','Front Pocket Minimal Wallet in black — front view',NULL,1,1),
(18,5,'assets/img/sample/products/front-pocket-minimal-wallet-2.jpg','Front Pocket Minimal Wallet in black — angled view',NULL,0,2),
(19,5,'assets/img/sample/products/front-pocket-minimal-wallet-3.jpg','Front Pocket Minimal Wallet in black — stitching detail',NULL,0,3),
(20,5,'assets/img/sample/products/front-pocket-minimal-wallet-cognac-1.jpg','Front Pocket Minimal Wallet in cognac',NULL,0,4),
(21,6,'assets/img/sample/products/atelier-card-holder-1.jpg','Atelier Card Holder in oxblood — front view',NULL,1,1),
(22,6,'assets/img/sample/products/atelier-card-holder-2.jpg','Atelier Card Holder in oxblood — angled view',NULL,0,2),
(23,6,'assets/img/sample/products/atelier-card-holder-3.jpg','Atelier Card Holder in oxblood — stitching detail',NULL,0,3),
(24,6,'assets/img/sample/products/atelier-card-holder-black-1.jpg','Atelier Card Holder in black',NULL,0,4),
(25,7,'assets/img/sample/products/quick-draw-card-sleeve-1.jpg','Quick-Draw Card Sleeve in tan — front view',NULL,1,1),
(26,7,'assets/img/sample/products/quick-draw-card-sleeve-2.jpg','Quick-Draw Card Sleeve in tan — angled view',NULL,0,2),
(27,7,'assets/img/sample/products/quick-draw-card-sleeve-3.jpg','Quick-Draw Card Sleeve in tan — stitching detail',NULL,0,3),
(28,7,'assets/img/sample/products/quick-draw-card-sleeve-forest-1.jpg','Quick-Draw Card Sleeve in forest',NULL,0,4),
(29,8,'assets/img/sample/products/noir-card-case-1.jpg','Noir Card Case in black — front view',NULL,1,1),
(30,8,'assets/img/sample/products/noir-card-case-2.jpg','Noir Card Case in black — angled view',NULL,0,2),
(31,8,'assets/img/sample/products/noir-card-case-3.jpg','Noir Card Case in black — stitching detail',NULL,0,3),
(32,9,'assets/img/sample/products/continental-zip-wallet-1.jpg','Continental Zip Wallet in espresso — front view',NULL,1,1),
(33,9,'assets/img/sample/products/continental-zip-wallet-2.jpg','Continental Zip Wallet in espresso — angled view',NULL,0,2),
(34,9,'assets/img/sample/products/continental-zip-wallet-3.jpg','Continental Zip Wallet in espresso — stitching detail',NULL,0,3),
(35,9,'assets/img/sample/products/continental-zip-wallet-black-1.jpg','Continental Zip Wallet in black',NULL,0,4),
(36,10,'assets/img/sample/products/classic-long-wallet-1.jpg','Classic Long Wallet in oxblood — front view',NULL,1,1),
(37,10,'assets/img/sample/products/classic-long-wallet-2.jpg','Classic Long Wallet in oxblood — angled view',NULL,0,2),
(38,10,'assets/img/sample/products/classic-long-wallet-3.jpg','Classic Long Wallet in oxblood — stitching detail',NULL,0,3),
(39,10,'assets/img/sample/products/classic-long-wallet-navy-1.jpg','Classic Long Wallet in navy',NULL,0,4),
(40,11,'assets/img/sample/products/voyager-passport-cover-1.jpg','Voyager Passport Cover in cognac — front view',NULL,1,1),
(41,11,'assets/img/sample/products/voyager-passport-cover-2.jpg','Voyager Passport Cover in cognac — angled view',NULL,0,2),
(42,11,'assets/img/sample/products/voyager-passport-cover-3.jpg','Voyager Passport Cover in cognac — stitching detail',NULL,0,3),
(43,11,'assets/img/sample/products/voyager-passport-cover-navy-1.jpg','Voyager Passport Cover in navy',NULL,0,4),
(44,12,'assets/img/sample/products/nomad-travel-organizer-1.jpg','Nomad Travel Organizer in espresso — front view',NULL,1,1),
(45,12,'assets/img/sample/products/nomad-travel-organizer-2.jpg','Nomad Travel Organizer in espresso — angled view',NULL,0,2),
(46,12,'assets/img/sample/products/nomad-travel-organizer-3.jpg','Nomad Travel Organizer in espresso — stitching detail',NULL,0,3),
(47,12,'assets/img/sample/products/nomad-travel-organizer-black-1.jpg','Nomad Travel Organizer in black',NULL,0,4),
(48,13,'assets/img/sample/products/loop-key-fob-1.jpg','Loop Key Fob in cognac — front view',NULL,1,1),
(49,13,'assets/img/sample/products/loop-key-fob-2.jpg','Loop Key Fob in cognac — angled view',NULL,0,2),
(50,13,'assets/img/sample/products/loop-key-fob-3.jpg','Loop Key Fob in cognac — stitching detail',NULL,0,3),
(51,13,'assets/img/sample/products/loop-key-fob-black-1.jpg','Loop Key Fob in black',NULL,0,4),
(52,14,'assets/img/sample/products/cove-coin-pouch-1.jpg','Cove Coin Pouch in tan — front view',NULL,1,1),
(53,14,'assets/img/sample/products/cove-coin-pouch-2.jpg','Cove Coin Pouch in tan — angled view',NULL,0,2),
(54,14,'assets/img/sample/products/cove-coin-pouch-3.jpg','Cove Coin Pouch in tan — stitching detail',NULL,0,3),
(55,14,'assets/img/sample/products/cove-coin-pouch-oxblood-1.jpg','Cove Coin Pouch in oxblood',NULL,0,4),
(56,15,'assets/img/sample/products/ridge-key-organizer-1.jpg','Ridge Key Organizer in espresso — front view',NULL,1,1),
(57,15,'assets/img/sample/products/ridge-key-organizer-2.jpg','Ridge Key Organizer in espresso — angled view',NULL,0,2),
(58,15,'assets/img/sample/products/ridge-key-organizer-3.jpg','Ridge Key Organizer in espresso — stitching detail',NULL,0,3),
(59,16,'assets/img/sample/products/midnight-noir-bifold-1.jpg','Midnight Noir Bifold in black — front view',NULL,1,1),
(60,16,'assets/img/sample/products/midnight-noir-bifold-2.jpg','Midnight Noir Bifold in black — angled view',NULL,0,2),
(61,16,'assets/img/sample/products/midnight-noir-bifold-3.jpg','Midnight Noir Bifold in black — stitching detail',NULL,0,3);

INSERT INTO `product_variants` (`id`,`product_id`,`sku`,`label`,`color_name`,`color_hex`,`price_override`,`sale_override`,`image_id`,`sort_order`,`is_active`) VALUES
(1,1,'BG-BF-001-COG','Cognac','Cognac','#8B4A22',NULL,NULL,1,0,1),
(2,1,'BG-BF-001-ESP','Espresso','Espresso','#3B2418',NULL,NULL,4,1,1),
(3,1,'BG-BF-001-BLA','Black','Black','#1C1B1D',NULL,NULL,NULL,2,1),
(4,2,'BG-BF-002-TAN','Tan','Tan','#A86A3D',NULL,NULL,5,0,1),
(5,2,'BG-BF-002-NAV','Navy','Navy','#1D2C4D',NULL,NULL,8,1,1),
(6,3,'BG-BF-003-ESP','Espresso','Espresso','#3B2418',NULL,NULL,9,0,1),
(7,3,'BG-BF-003-BLA','Black','Black','#1C1B1D',NULL,NULL,12,1,1),
(8,4,'BG-MN-001-NAV','Navy','Navy','#1D2C4D',NULL,NULL,13,0,1),
(9,4,'BG-MN-001-STO','Stone','Stone','#8A7967',NULL,NULL,16,1,1),
(10,4,'BG-MN-001-BLA','Black','Black','#1C1B1D',NULL,NULL,NULL,2,1),
(11,5,'BG-MN-002-BLA','Black','Black','#1C1B1D',NULL,NULL,17,0,1),
(12,5,'BG-MN-002-COG','Cognac','Cognac','#8B4A22',NULL,NULL,20,1,1),
(13,6,'BG-CH-001-OXB','Oxblood','Oxblood','#5A1F22',NULL,NULL,21,0,1),
(14,6,'BG-CH-001-BLA','Black','Black','#1C1B1D',NULL,NULL,24,1,1),
(15,6,'BG-CH-001-NAV','Navy','Navy','#1D2C4D',NULL,NULL,NULL,2,1),
(16,7,'BG-CH-002-TAN','Tan','Tan','#A86A3D',NULL,NULL,25,0,1),
(17,7,'BG-CH-002-FOR','Forest','Forest','#2F3E30',NULL,NULL,28,1,1),
(18,8,'BG-CH-003-BLA','Black','Black','#1C1B1D',NULL,NULL,29,0,1),
(19,9,'BG-LW-001-ESP','Espresso','Espresso','#3B2418',NULL,NULL,32,0,1),
(20,9,'BG-LW-001-BLA','Black','Black','#1C1B1D',NULL,NULL,35,1,1),
(21,10,'BG-LW-002-OXB','Oxblood','Oxblood','#5A1F22',NULL,NULL,36,0,1),
(22,10,'BG-LW-002-NAV','Navy','Navy','#1D2C4D',NULL,NULL,39,1,1),
(23,11,'BG-TW-001-COG','Cognac','Cognac','#8B4A22',NULL,NULL,40,0,1),
(24,11,'BG-TW-001-NAV','Navy','Navy','#1D2C4D',NULL,NULL,43,1,1),
(25,12,'BG-TW-002-ESP','Espresso','Espresso','#3B2418',NULL,NULL,44,0,1),
(26,12,'BG-TW-002-BLA','Black','Black','#1C1B1D',NULL,NULL,47,1,1),
(27,13,'BG-AC-001-COG','Cognac','Cognac','#8B4A22',NULL,NULL,48,0,1),
(28,13,'BG-AC-001-BLA','Black','Black','#1C1B1D',NULL,NULL,51,1,1),
(29,13,'BG-AC-001-NAV','Navy','Navy','#1D2C4D',NULL,NULL,NULL,2,1),
(30,14,'BG-AC-002-TAN','Tan','Tan','#A86A3D',NULL,NULL,52,0,1),
(31,14,'BG-AC-002-OXB','Oxblood','Oxblood','#5A1F22',NULL,NULL,55,1,1),
(32,15,'BG-AC-003-ESP','Espresso','Espresso','#3B2418',NULL,NULL,56,0,1),
(33,16,'BG-BF-004-BLA','Black','Black','#1C1B1D',NULL,NULL,59,0,1);

INSERT INTO `product_variant_values` (`variant_id`,`attribute_name`,`attribute_value`) VALUES
(1,'Colour','Cognac'),
(2,'Colour','Espresso'),
(3,'Colour','Black'),
(4,'Colour','Tan'),
(5,'Colour','Navy'),
(6,'Colour','Espresso'),
(7,'Colour','Black'),
(8,'Colour','Navy'),
(9,'Colour','Stone'),
(10,'Colour','Black'),
(11,'Colour','Black'),
(12,'Colour','Cognac'),
(13,'Colour','Oxblood'),
(14,'Colour','Black'),
(15,'Colour','Navy'),
(16,'Colour','Tan'),
(17,'Colour','Forest'),
(18,'Colour','Black'),
(19,'Colour','Espresso'),
(20,'Colour','Black'),
(21,'Colour','Oxblood'),
(22,'Colour','Navy'),
(23,'Colour','Cognac'),
(24,'Colour','Navy'),
(25,'Colour','Espresso'),
(26,'Colour','Black'),
(27,'Colour','Cognac'),
(28,'Colour','Black'),
(29,'Colour','Navy'),
(30,'Colour','Tan'),
(31,'Colour','Oxblood'),
(32,'Colour','Espresso'),
(33,'Colour','Black');

INSERT INTO `product_inventory` (`product_id`,`variant_id`,`quantity`) VALUES
(1,1,24),
(1,2,9),
(1,3,3),
(2,4,16),
(2,5,11),
(3,6,12),
(3,7,14),
(4,8,30),
(4,9,18),
(4,10,22),
(5,11,20),
(5,12,2),
(6,13,26),
(6,14,31),
(6,15,0),
(7,16,15),
(7,17,13),
(8,18,9),
(9,19,8),
(9,20,6),
(10,21,10),
(10,22,7),
(11,23,14),
(11,24,12),
(12,25,6),
(12,26,4),
(13,27,40),
(13,28,35),
(13,29,28),
(14,30,18),
(14,31,15),
(15,32,12),
(16,33,0);

INSERT INTO `inventory_movements` (`product_id`,`variant_id`,`change_qty`,`balance`,`reason`,`note`) VALUES
(1,1,24,24,'initial','Sample data'),
(1,2,9,9,'initial','Sample data'),
(1,3,3,3,'initial','Sample data'),
(2,4,16,16,'initial','Sample data'),
(2,5,11,11,'initial','Sample data'),
(3,6,12,12,'initial','Sample data'),
(3,7,14,14,'initial','Sample data'),
(4,8,30,30,'initial','Sample data'),
(4,9,18,18,'initial','Sample data'),
(4,10,22,22,'initial','Sample data'),
(5,11,20,20,'initial','Sample data'),
(5,12,2,2,'initial','Sample data'),
(6,13,26,26,'initial','Sample data'),
(6,14,31,31,'initial','Sample data'),
(6,15,0,0,'initial','Sample data'),
(7,16,15,15,'initial','Sample data'),
(7,17,13,13,'initial','Sample data'),
(8,18,9,9,'initial','Sample data'),
(9,19,8,8,'initial','Sample data'),
(9,20,6,6,'initial','Sample data'),
(10,21,10,10,'initial','Sample data'),
(10,22,7,7,'initial','Sample data'),
(11,23,14,14,'initial','Sample data'),
(11,24,12,12,'initial','Sample data'),
(12,25,6,6,'initial','Sample data'),
(12,26,4,4,'initial','Sample data'),
(13,27,40,40,'initial','Sample data'),
(13,28,35,35,'initial','Sample data'),
(13,29,28,28,'initial','Sample data'),
(14,30,18,18,'initial','Sample data'),
(14,31,15,15,'initial','Sample data'),
(15,32,12,12,'initial','Sample data'),
(16,33,0,0,'initial','Sample data');

INSERT INTO `product_relations` (`product_id`,`related_id`,`relation_type`,`sort_order`) VALUES
(1,2,'related',0),
(1,3,'related',1),
(1,16,'related',2),
(1,13,'cross_sell',0),
(1,6,'cross_sell',1),
(1,9,'upsell',0),
(4,5,'related',0),
(4,6,'related',1),
(4,13,'cross_sell',0),
(4,1,'upsell',0),
(6,7,'related',0),
(6,8,'related',1),
(6,15,'cross_sell',0),
(6,14,'cross_sell',1),
(6,4,'upsell',0),
(11,12,'related',0),
(11,6,'cross_sell',0),
(11,13,'cross_sell',1),
(11,12,'upsell',0),
(9,10,'related',0),
(9,14,'cross_sell',0),
(16,8,'related',0),
(16,1,'related',1),
(16,8,'cross_sell',0);

INSERT INTO `collections` (`id`,`name`,`slug`,`description`,`image`,`image_alt`,`seo_title`,`meta_description`,`sort_order`,`is_active`) VALUES
(1,'The Noir Edit','the-noir-edit','Black pebbled leather finished with antique-gold stitching. A study in understatement.','assets/img/sample/misc/collection-noir.jpg','The Noir Edit','The Noir Edit — Black Leather Wallets','Black leather wallets and card cases with antique-gold stitching.',1,1),
(2,'The Gift Edit','the-gift-edit','Thoughtful pieces with optional gift packaging — for birthdays, Eid and every occasion.','assets/img/sample/categories/leather-accessories.jpg','Gift edit','Leather Gifts','Leather wallet and accessory gift ideas with optional gift packaging.',2,1);

INSERT INTO `collection_products` (`collection_id`,`product_id`,`sort_order`) VALUES
(1,16,0),
(1,8,1),
(1,1,2),
(1,3,3),
(1,9,4),
(2,13,0),
(2,6,1),
(2,14,2),
(2,1,3),
(2,11,4),
(2,4,5);

INSERT INTO `banner_slides` (`title`,`subtitle`,`description`,`image_desktop`,`image_mobile`,`image_alt`,`primary_btn_text`,`primary_btn_url`,`secondary_btn_text`,`secondary_btn_url`,`text_align`,`content_position`,`text_color`,`accent_color`,`btn_bg_color`,`btn_text_color`,`overlay_color`,`overlay_opacity`,`height_desktop`,`height_mobile`,`bg_position`,`bg_size`,`sort_order`,`is_active`) VALUES
('Crafted to be carried','The Autumn Collection','Refined leather wallets and everyday essentials, designed for the way you move.','assets/img/sample/hero/hero-1-desktop.jpg','assets/img/sample/hero/hero-1-mobile.jpg','Cognac bifold, black card holder and tan key fob on a midnight blue surface','Shop the collection','/shop','New arrivals','/shop?sort=newest','left','middle','#F7F5F0','#B99A5B','#214E9B','#FFFFFF','#0A1426',30,760,640,'center center','cover',1,1),
('Quiet luxury, every day','Long wallets & bifolds','Clean lines, tight stitching and layouts that keep everything in its place.','assets/img/sample/hero/hero-2-desktop.jpg','assets/img/sample/hero/hero-2-mobile.jpg','Espresso long wallet, navy bifold and stone minimalist wallet on warm beige','Explore wallets','/category/bifold-wallets','Long wallets','/category/long-wallets','left','middle','#101D35','#8A6A2E','#101D35','#FFFFFF','#F7F5F0',10,760,640,'center center','cover',2,1),
('Made for the journey','Travel wallets','Passport covers and organisers that keep documents safe and close at hand.','assets/img/sample/hero/hero-3-desktop.jpg','assets/img/sample/hero/hero-3-mobile.jpg','Oxblood passport cover and tan coin pouch on navy','Shop travel','/category/travel-wallets',NULL,NULL,'left','middle','#F7F5F0','#B99A5B','#B99A5B','#0A1426','#0A1426',25,760,640,'center center','cover',3,1);

-- SAMPLE testimonials: flagged is_sample = 1 and only shown while 'Show sample content' is on.
-- Replace with genuine customer feedback before launch.
INSERT INTO `testimonials` (`author_name`,`author_meta`,`quote`,`rating`,`is_sample`,`is_active`,`sort_order`,`source_note`) VALUES
('Sample Customer','Sample testimonial — replace','Replace this with genuine feedback from a real customer. Sample text shown for layout purposes only.',5,1,1,1,'Demo content'),
('Sample Customer','Sample testimonial — replace','Testimonials should be real quotes you have permission to publish, ideally linked to a verified order.',5,1,1,2,'Demo content');

INSERT INTO `coupons` (`code`,`description`,`discount_type`,`discount_value`,`min_order_amount`,`max_discount`,`usage_limit`,`per_customer_limit`,`is_active`) VALUES
('WELCOME10','Sample: 10% off first order (max Rs. 1,000)','percent',10,3000,1000,NULL,1,1),
('FREESHIP','Sample: free delivery','free_shipping',0,0,NULL,100,NULL,1);

SET FOREIGN_KEY_CHECKS = 1;
