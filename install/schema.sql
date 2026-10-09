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
