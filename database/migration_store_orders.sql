-- =====================================================================
-- Toursity — adds the Store (product) category, cart, and orders.
--
-- Run this ONCE against your existing, already-deployed database (the
-- one you ran schema.sql into before this feature existed). A fresh
-- install doesn't need this file — schema.sql already includes all of it.
--
--   mysql -u root -p your_database < database/migration_store_orders.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE categories
  ADD COLUMN listing_type ENUM('service','product') NOT NULL DEFAULT 'service' AFTER description;

ALTER TABLE services
  ADD COLUMN stock_quantity INT UNSIGNED DEFAULT NULL AFTER max_guests;

CREATE TABLE IF NOT EXISTS cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cart_item (user_id, service_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  shipping_name VARCHAR(150) DEFAULT NULL,
  shipping_phone VARCHAR(30) DEFAULT NULL,
  shipping_address VARCHAR(255) DEFAULT NULL,
  shipping_city_id INT UNSIGNED DEFAULT NULL,
  shipping_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id),
  FOREIGN KEY (shipping_city_id) REFERENCES cities(id),
  INDEX idx_orders_customer (customer_id),
  INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED DEFAULT NULL,
  provider_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL,
  commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  FOREIGN KEY (provider_id) REFERENCES providers(id),
  INDEX idx_orderitems_order (order_id),
  INDEX idx_orderitems_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, slug, icon, description, sort_order, is_active, listing_type) VALUES
  ('Store', 'store', 'bi-bag-check', 'Shops selling travel gear, souvenirs and local goods, bought online and shipped to you.', 7, 1, 'product');

INSERT INTO category_fields (category_id, field_label, field_key, field_type, field_options, is_required, sort_order)
SELECT id, 'Brand', 'brand', 'text', NULL, 0, 1 FROM categories WHERE slug = 'store';
INSERT INTO category_fields (category_id, field_label, field_key, field_type, field_options, is_required, sort_order)
SELECT id, 'SKU', 'sku', 'text', NULL, 0, 2 FROM categories WHERE slug = 'store';
INSERT INTO category_fields (category_id, field_label, field_key, field_type, field_options, is_required, sort_order)
SELECT id, 'Condition', 'condition', 'select', 'New,Used,Refurbished', 0, 3 FROM categories WHERE slug = 'store';

SET FOREIGN_KEY_CHECKS = 1;
