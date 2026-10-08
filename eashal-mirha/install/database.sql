-- Eashal Mirha — complete database (structure + starter content)
-- Import this ONE file in phpMyAdmin if you are not using the /install web installer.
-- =========================================================
--  Eashal Mirha — database structure
--  MySQL 5.7+ / MariaDB 10.3+   (utf8mb4)
-- =========================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS settings, admins, customers, categories, products, product_images,
  slides, home_sections, banners, shipping_zones, coupons, orders, order_items,
  order_history, pages, reviews, messages, subscribers, wishlist;

CREATE TABLE settings (
  skey   VARCHAR(80) NOT NULL PRIMARY KEY,
  svalue MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super','manager','staff') NOT NULL DEFAULT 'manager',
  status TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password VARCHAR(255) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  banner VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1,
  show_home TINYINT(1) NOT NULL DEFAULT 1,
  show_menu TINYINT(1) NOT NULL DEFAULT 1,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  meta_keywords VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  subcategory_id INT UNSIGNED NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  sku VARCHAR(80) NULL,
  short_description TEXT NULL,
  description MEDIUMTEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  sizes VARCHAR(255) NULL,
  colors VARCHAR(255) NULL,
  fabric VARCHAR(120) NULL,
  pieces VARCHAR(60) NULL,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  sales_count INT UNSIGNED NOT NULL DEFAULT 0,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  meta_keywords VARCHAR(500) NULL,
  focus_keyword VARCHAR(120) NULL,
  canonical_url VARCHAR(255) NULL,
  og_image VARCHAR(255) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_cat (category_id), KEY idx_sub (subcategory_id), KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  image VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  small_text VARCHAR(160) NULL,
  heading VARCHAR(255) NULL,
  subheading VARCHAR(500) NULL,
  btn_text VARCHAR(60) NULL,
  btn_link VARCHAR(255) NULL,
  btn2_text VARCHAR(60) NULL,
  btn2_link VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  mobile_image VARCHAR(255) NULL,
  text_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  overlay DECIMAL(3,2) NOT NULL DEFAULT 0.35,
  align ENUM('left','center','right') NOT NULL DEFAULT 'left',
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE home_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skey VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  title VARCHAR(255) NULL,
  subtitle VARCHAR(500) NULL,
  item_limit INT NOT NULL DEFAULT 8,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  small_text VARCHAR(160) NULL,
  heading VARCHAR(255) NULL,
  text VARCHAR(600) NULL,
  btn_text VARCHAR(60) NULL,
  btn_link VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  height INT NOT NULL DEFAULT 520,
  text_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  overlay DECIMAL(3,2) NOT NULL DEFAULT 0.40,
  status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city VARCHAR(80) NOT NULL,
  charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  free_above DECIMAL(10,2) NULL,
  delivery_days VARCHAR(40) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT NOT NULL DEFAULT 0,
  used INT NOT NULL DEFAULT 0,
  expires_at DATE NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  access_key VARCHAR(40) NOT NULL,
  customer_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(80) NOT NULL,
  postal_code VARCHAR(20) NULL,
  notes TEXT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(40) NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
  payment_status ENUM('unpaid','pending_verification','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  txn_ref VARCHAR(60) NULL,
  txn_id VARCHAR(120) NULL,
  payment_proof VARCHAR(255) NULL,
  gateway_response TEXT NULL,
  courier VARCHAR(80) NULL,
  tracking_no VARCHAR(80) NULL,
  admin_note TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_customer (customer_id), KEY idx_status (status), KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  name VARCHAR(200) NOT NULL,
  sku VARCHAR(80) NULL,
  size VARCHAR(40) NULL,
  color VARCHAR(40) NULL,
  price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  image VARCHAR(255) NULL,
  KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  content MEDIUMTEXT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  show_footer TINYINT(1) NOT NULL DEFAULT 1,
  status TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  comment TEXT NULL,
  status TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  subject VARCHAR(200) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wish (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- =========================================================
--  Eashal Mirha — starter content (settings, categories,
--  demo products, homepage, pages). Safe to edit from admin.
-- =========================================================
SET NAMES utf8mb4;

-- Default admin:  admin@eashalmirha.com  /  Admin@123   (change it after first login)
INSERT INTO admins (name, email, password, role) VALUES
('Store Owner', 'admin@eashalmirha.com', '$2y$10$lLEv5hWyNdv1gJAOfQij0Om3.fSlNGh46gsTfzH/au2uBm.EHBmoO', 'super');

INSERT INTO settings (skey, svalue) VALUES
('site_name', 'Eashal Mirha'),
('tagline', 'Luxury Pret & Bridal Couture'),
('logo', ''),
('logo_height', '46'),
('favicon', ''),
('phone', '+92 300 0000000'),
('whatsapp', '923000000000'),
('email', 'info@eashalmirha.com'),
('address', 'Main Boulevard, Gulberg III, Lahore, Pakistan'),
('business_hours', 'Mon - Sat: 11:00 AM - 9:00 PM'),
('facebook', 'https://facebook.com/'),
('instagram', 'https://instagram.com/'),
('tiktok', ''),
('youtube', ''),
('pinterest', ''),
('announcement_enabled', '1'),
('announcement', 'Free delivery nationwide on orders above Rs. 10,000  ✦  Cash on Delivery available  ✦  New Luxury Pret collection is live'),
('footer_about', 'Eashal Mirha is a Pakistani luxury womenswear label crafting pret, luxury pret and bridal couture with hand-finished embroideries and timeless silhouettes.'),
('copyright', '© {year} Eashal Mirha. All rights reserved.'),
('currency', 'Rs.'),
('color_gold', '#c9a24a'),
('color_black', '#0b0b0b'),
('color_cream', '#f7f1e6'),
('preloader', '1'),
('order_prefix', 'EM'),
('low_stock', '3'),
('shipping_flat', '250'),
('free_shipping_min', '10000'),
('shipping_note', 'Delivery in 3-5 working days across Pakistan.'),
('guest_checkout', '1'),
('hero_height', '92'),
('hero_height_unit', 'vh'),
('hero_height_mobile', '78'),
('hero_width', 'full'),
('hero_max_width', '1400'),
('hero_autoplay', '6000'),
('hero_effect', 'fade'),
('hero_heading_size', '64'),
('hero_heading_size_mobile', '36'),
('hero_kenburns', '1'),
('pay_cod_enabled', '1'),
('pay_cod_title', 'Cash on Delivery'),
('pay_cod_note', 'Pay in cash when your parcel arrives.'),
('pay_cod_fee', '0'),
('pay_cod_max', '0'),
('pay_easypaisa_enabled', '1'),
('pay_easypaisa_title', 'EasyPaisa'),
('pay_easypaisa_mode', 'manual'),
('pay_easypaisa_sandbox', '1'),
('pay_easypaisa_account_title', 'Eashal Mirha'),
('pay_easypaisa_account_no', '0300-0000000'),
('pay_easypaisa_store_id', ''),
('pay_easypaisa_hash_key', ''),
('pay_easypaisa_note', 'Send the total amount to our EasyPaisa account and enter the Transaction ID (TID).'),
('pay_jazzcash_enabled', '1'),
('pay_jazzcash_title', 'JazzCash'),
('pay_jazzcash_mode', 'manual'),
('pay_jazzcash_sandbox', '1'),
('pay_jazzcash_account_title', 'Eashal Mirha'),
('pay_jazzcash_account_no', '0300-0000000'),
('pay_jazzcash_merchant_id', ''),
('pay_jazzcash_password', ''),
('pay_jazzcash_salt', ''),
('pay_jazzcash_note', 'Send the total amount to our JazzCash account and enter the Transaction ID (TID).'),
('pay_card_enabled', '1'),
('pay_card_title', 'Debit / Credit Card'),
('pay_card_mode', 'bank'),
('pay_card_bank_name', 'Meezan Bank'),
('pay_card_account_title', 'Eashal Mirha'),
('pay_card_account_no', '0000-0000000000'),
('pay_card_iban', 'PK00MEZN0000000000000000'),
('pay_card_note', 'Pay securely with your Visa / Mastercard debit or credit card.'),
('meta_title', 'Eashal Mirha | Luxury Pret, Bridal & Formal Wear Pakistan'),
('meta_description', 'Shop Eashal Mirha luxury pret, bridal couture, formals and unstitched collections online in Pakistan. Cash on Delivery, EasyPaisa, JazzCash and card payments.'),
('meta_keywords', 'luxury pret, bridal dresses pakistan, ladies suits, formal wear, eashal mirha'),
('og_image', ''),
('google_verification', ''),
('ga_id', ''),
('fb_pixel', ''),
('head_code', ''),
('body_code', ''),
('robots_txt', ''),
('ads_txt', ''),
('smtp_note', '');

INSERT INTO categories (id, parent_id, name, slug, description, image, banner, sort_order) VALUES
(1, NULL, 'Pret', 'pret', 'Ready-to-wear everyday luxury, cut for effortless elegance.', 'assets/images/demo/cat-1.svg', 'assets/images/demo/banner-2.svg', 1),
(2, NULL, 'Luxury Pret', 'luxury-pret', 'Hand-embellished statement pieces for evenings and soirées.', 'assets/images/demo/cat-2.svg', 'assets/images/demo/banner-1.svg', 2),
(3, NULL, 'Bridal', 'bridal', 'Heirloom bridal couture, crafted to be remembered.', 'assets/images/demo/cat-3.svg', 'assets/images/demo/banner-3.svg', 3),
(4, NULL, 'Formal Wear', 'formal-wear', 'Occasion-ready formals with intricate zardozi and dabka work.', 'assets/images/demo/cat-4.svg', 'assets/images/demo/banner-2.svg', 4),
(5, NULL, 'Unstitched', 'unstitched', 'Premium fabrics to tailor your own story.', 'assets/images/demo/cat-5.svg', 'assets/images/demo/banner-1.svg', 5),
(6, NULL, 'Party Wear', 'party-wear', 'Glamour for mehndis, dholkis and every celebration.', 'assets/images/demo/cat-6.svg', 'assets/images/demo/banner-3.svg', 6),
(7, 1, '2 Piece', 'pret-2-piece', NULL, 'assets/images/demo/cat-1.svg', NULL, 1),
(8, 1, '3 Piece', 'pret-3-piece', NULL, 'assets/images/demo/cat-2.svg', NULL, 2),
(9, 1, 'Kurtis', 'kurtis', NULL, 'assets/images/demo/cat-6.svg', NULL, 3),
(10, 2, 'Embroidered', 'embroidered-luxury', NULL, 'assets/images/demo/cat-2.svg', NULL, 1),
(11, 2, 'Chiffon', 'chiffon-luxury', NULL, 'assets/images/demo/cat-4.svg', NULL, 2),
(12, 2, 'Silk', 'silk-luxury', NULL, 'assets/images/demo/cat-5.svg', NULL, 3),
(13, 3, 'Lehenga', 'bridal-lehenga', NULL, 'assets/images/demo/cat-3.svg', NULL, 1),
(14, 3, 'Sharara & Gharara', 'bridal-sharara', NULL, 'assets/images/demo/cat-1.svg', NULL, 2),
(15, 3, 'Bridal Maxi', 'bridal-maxi', NULL, 'assets/images/demo/cat-4.svg', NULL, 3),
(16, 4, 'Gowns', 'formal-gowns', NULL, 'assets/images/demo/cat-4.svg', NULL, 1),
(17, 4, 'Peshwas', 'peshwas', NULL, 'assets/images/demo/cat-3.svg', NULL, 2),
(18, 5, 'Lawn', 'unstitched-lawn', NULL, 'assets/images/demo/cat-5.svg', NULL, 1),
(19, 5, 'Velvet', 'unstitched-velvet', NULL, 'assets/images/demo/cat-2.svg', NULL, 2),
(20, 6, 'Anarkali', 'anarkali', NULL, 'assets/images/demo/cat-6.svg', NULL, 1),
(21, 6, 'Sarees', 'sarees', NULL, 'assets/images/demo/cat-1.svg', NULL, 2);

INSERT INTO products (id, category_id, subcategory_id, name, slug, sku, short_description, description, price, sale_price, stock, sizes, colors, fabric, pieces, is_new, is_bestseller, is_featured, sales_count, meta_title, meta_description) VALUES
(1, 1, 8, 'Noor-e-Shab', 'noor-e-shab', 'EM-PR-001', 'Midnight black lawn three piece with gold tilla embroidery.', '<p>A midnight black shirt finished with gold tilla embroidery on the neckline and sleeves, paired with straight trousers and a printed chiffon dupatta.</p><ul><li>Shirt: Embroidered lawn</li><li>Dupatta: Printed chiffon</li><li>Trouser: Dyed cambric</li></ul>', 8950, 7450, 25, 'XS,S,M,L,XL', 'Black,Gold', 'Lawn', '3 Piece', 1, 1, 1, 42, NULL, NULL),
(2, 1, 7, 'Gul-e-Rana', 'gul-e-rana', 'EM-PR-002', 'Blush pink two piece with delicate thread work.', '<p>Soft blush shirt with resham thread work and lace finishing paired with cigarette pants.</p>', 6450, NULL, 18, 'XS,S,M,L', 'Blush Pink', 'Cambric', '2 Piece', 1, 0, 1, 15, NULL, NULL),
(3, 1, 9, 'Zard Mehtab Kurti', 'zard-mehtab-kurti', 'EM-PR-003', 'Mustard kurti with mirror and gota detailing.', '<p>Vibrant mustard kurti accented with mirror work and gota borders. Perfect for daytime festivities.</p>', 4250, 3650, 30, 'S,M,L,XL', 'Mustard', 'Khaddar', '1 Piece', 0, 1, 0, 61, NULL, NULL),
(4, 2, 10, 'Shehzadi', 'shehzadi', 'EM-LP-004', 'Emerald luxury pret with zardozi neckline.', '<p>Deep emerald raw silk shirt with a hand-worked zardozi neckline, organza dupatta with scalloped borders and jamawar trousers.</p>', 24500, NULL, 8, 'S,M,L', 'Emerald', 'Raw Silk', '3 Piece', 1, 1, 1, 33, NULL, NULL),
(5, 2, 11, 'Mah-e-Noor', 'mah-e-noor', 'EM-LP-005', 'Ivory chiffon with pearl and sequin embellishment.', '<p>Flowing ivory chiffon embellished with pearls, sequins and cut-dana, finished with a net dupatta.</p>', 28900, 25900, 6, 'S,M,L', 'Ivory,Gold', 'Chiffon', '3 Piece', 1, 0, 1, 12, NULL, NULL),
(6, 2, 12, 'Firoza', 'firoza', 'EM-LP-006', 'Teal silk ensemble with antique gold motifs.', '<p>Teal pure silk shirt with antique gold motifs and a contrasting magenta dupatta.</p>', 21500, NULL, 10, 'XS,S,M,L', 'Teal', 'Silk', '3 Piece', 0, 1, 0, 27, NULL, NULL),
(7, 3, 13, 'Rang-e-Hina Bridal Lehenga', 'rang-e-hina-bridal-lehenga', 'EM-BR-007', 'Classic red bridal lehenga with heavy zardozi.', '<p>A traditional red bridal lehenga worked with zardozi, dabka, naqshi and kora, paired with a fully embellished choli and double dupatta.</p><p>Made to order — delivery in 6 to 8 weeks.</p>', 285000, NULL, 3, 'Custom', 'Red,Gold', 'Raw Silk & Net', '3 Piece', 1, 1, 1, 9, 'Red Bridal Lehenga | Rang-e-Hina | Eashal Mirha', 'Hand-embellished red bridal lehenga with zardozi and dabka work. Made to order in Pakistan.'),
(8, 3, 14, 'Afreen Sharara', 'afreen-sharara', 'EM-BR-008', 'Gold sharara with pishwas-style kurti.', '<p>Antique gold sharara set with hand embellished kurti, perfect for nikkah and mehndi.</p>', 165000, 149000, 4, 'Custom', 'Gold', 'Tissue', '3 Piece', 0, 1, 1, 7, NULL, NULL),
(9, 3, 15, 'Shahana Bridal Maxi', 'shahana-bridal-maxi', 'EM-BR-009', 'Pastel bridal maxi with a cathedral-length trail.', '<p>Pastel peach maxi with a sweeping trail, sequined net dupatta and crystal-studded borders.</p>', 245000, NULL, 2, 'Custom', 'Peach', 'Net', '2 Piece', 1, 0, 1, 4, NULL, NULL),
(10, 4, 16, 'Raat Ki Rani Gown', 'raat-ki-rani-gown', 'EM-FW-010', 'Black velvet gown with gold kora work.', '<p>Luxurious black velvet gown featuring gold kora and dabka detailing across the bodice.</p>', 38500, 34500, 7, 'S,M,L,XL', 'Black', 'Velvet', '2 Piece', 1, 1, 0, 22, NULL, NULL),
(11, 4, 17, 'Mehrunissa Peshwas', 'mehrunissa-peshwas', 'EM-FW-011', 'Maroon peshwas with Mughal-inspired borders.', '<p>Floor-length maroon peshwas inspired by Mughal court attire, with borders in antique zari.</p>', 42000, NULL, 5, 'S,M,L', 'Maroon', 'Organza', '3 Piece', 0, 1, 1, 18, NULL, NULL),
(12, 4, 16, 'Sitara Gown', 'sitara-gown', 'EM-FW-012', 'Champagne gown dusted with hand-placed sequins.', '<p>Champagne net gown dusted with thousands of hand placed sequins.</p>', 36000, NULL, 9, 'S,M,L', 'Champagne', 'Net', '2 Piece', 1, 0, 0, 3, NULL, NULL),
(13, 5, 18, 'Bahar Lawn Unstitched', 'bahar-lawn-unstitched', 'EM-UN-013', 'Printed lawn with embroidered neckline patch.', '<p>3.0m printed lawn shirt, embroidered neckline patch, 2.5m chiffon dupatta, 2.5m dyed trouser.</p>', 5950, 4950, 40, 'Unstitched', 'Sage Green', 'Lawn', '3 Piece', 1, 1, 0, 88, NULL, NULL),
(14, 5, 19, 'Shahi Velvet Unstitched', 'shahi-velvet-unstitched', 'EM-UN-014', 'Plush velvet with embroidered sleeves and borders.', '<p>Premium micro velvet shirt with embroidered sleeves, borders and a shawl.</p>', 14500, NULL, 15, 'Unstitched', 'Plum', 'Velvet', '3 Piece', 0, 1, 0, 30, NULL, NULL),
(15, 6, 20, 'Jhoomar Anarkali', 'jhoomar-anarkali', 'EM-PW-015', 'Flared anarkali with gota and mirror work.', '<p>Multi-panelled flared anarkali with gota and mirror work — made for dancing at the mehndi.</p>', 18500, 15900, 12, 'S,M,L,XL', 'Fuchsia,Gold', 'Chiffon', '3 Piece', 1, 1, 1, 40, NULL, NULL),
(16, 6, 21, 'Saba Saree', 'saba-saree', 'EM-PW-016', 'Ready-to-wear saree with embellished blouse.', '<p>Pre-draped ready-to-wear saree in organza with a fully embellished blouse.</p>', 26500, NULL, 6, 'S,M,L', 'Lilac', 'Organza', '2 Piece', 1, 0, 0, 5, NULL, NULL),
(17, 1, 8, 'Sunehri Dhoop', 'sunehri-dhoop', 'EM-PR-017', 'Ochre printed three piece with lace detailing.', '<p>Ochre digital printed lawn with lace inserts, organza dupatta and printed trousers.</p>', 7250, NULL, 22, 'XS,S,M,L,XL', 'Ochre', 'Lawn', '3 Piece', 1, 0, 0, 10, NULL, NULL),
(18, 2, 10, 'Zarnish', 'zarnish', 'EM-LP-018', 'Powder blue luxury pret with silver kora.', '<p>Powder blue organza shirt, silver kora work and a pearl-trimmed dupatta.</p>', 23500, 21000, 9, 'S,M,L', 'Powder Blue', 'Organza', '3 Piece', 1, 1, 0, 19, NULL, NULL);

UPDATE products SET created_at = DATE_SUB(NOW(), INTERVAL (20 - id) DAY);

INSERT INTO product_images (product_id, image, sort_order) VALUES
(1,'assets/images/demo/p1.svg',0),(1,'assets/images/demo/p2.svg',1),
(2,'assets/images/demo/p3.svg',0),(2,'assets/images/demo/p4.svg',1),
(3,'assets/images/demo/p5.svg',0),(3,'assets/images/demo/p6.svg',1),
(4,'assets/images/demo/p7.svg',0),(4,'assets/images/demo/p8.svg',1),
(5,'assets/images/demo/p9.svg',0),(5,'assets/images/demo/p10.svg',1),
(6,'assets/images/demo/p11.svg',0),(6,'assets/images/demo/p12.svg',1),
(7,'assets/images/demo/p13.svg',0),(7,'assets/images/demo/p14.svg',1),
(8,'assets/images/demo/p15.svg',0),(8,'assets/images/demo/p16.svg',1),
(9,'assets/images/demo/p17.svg',0),(9,'assets/images/demo/p18.svg',1),
(10,'assets/images/demo/p2.svg',0),(10,'assets/images/demo/p1.svg',1),
(11,'assets/images/demo/p14.svg',0),(11,'assets/images/demo/p13.svg',1),
(12,'assets/images/demo/p10.svg',0),(12,'assets/images/demo/p9.svg',1),
(13,'assets/images/demo/p12.svg',0),(13,'assets/images/demo/p11.svg',1),
(14,'assets/images/demo/p8.svg',0),(14,'assets/images/demo/p7.svg',1),
(15,'assets/images/demo/p16.svg',0),(15,'assets/images/demo/p15.svg',1),
(16,'assets/images/demo/p18.svg',0),(16,'assets/images/demo/p17.svg',1),
(17,'assets/images/demo/p6.svg',0),(17,'assets/images/demo/p5.svg',1),
(18,'assets/images/demo/p4.svg',0),(18,'assets/images/demo/p3.svg',1);

INSERT INTO slides (small_text, heading, subheading, btn_text, btn_link, btn2_text, btn2_link, image, text_color, overlay, align, sort_order) VALUES
('New Season · 2026', 'The Luxury Pret Edit', 'Hand-finished embroideries, fluid silhouettes and a palette of midnight and gold.', 'Shop Luxury Pret', 'category/luxury-pret', 'New Arrivals', 'shop?filter=new', 'assets/images/demo/hero-1.svg', '#ffffff', 0.30, 'left', 1),
('Bridal Couture', 'Heirlooms in the Making', 'Bespoke bridal lehengas and shararas, crafted by master artisans.', 'Explore Bridal', 'category/bridal', 'Book Appointment', 'contact', 'assets/images/demo/hero-2.svg', '#ffffff', 0.30, 'center', 2),
('Everyday Elegance', 'Pret, Perfected', 'Effortless ready-to-wear for every day of the week.', 'Shop Pret', 'category/pret', NULL, NULL, 'assets/images/demo/hero-3.svg', '#ffffff', 0.30, 'right', 3);

INSERT INTO home_sections (skey, label, title, subtitle, item_limit, enabled, sort_order) VALUES
('hero', 'Hero Banner Carousel', NULL, NULL, 0, 1, 1),
('categories', 'Shop by Category', 'Shop by Category', 'Curated collections for every occasion', 6, 1, 2),
('new_arrivals', 'New Arrivals Carousel', 'New Arrivals', 'Fresh off the atelier — the latest from Eashal Mirha', 10, 1, 3),
('parallax', 'Parallax Banner', NULL, NULL, 0, 1, 4),
('best_sellers', 'Best Sellers Carousel', 'Best Sellers', 'The pieces everyone is talking about', 10, 1, 5),
('promo_duo', 'Two Promo Banners', NULL, NULL, 0, 1, 6),
('category_tabs', 'Shop by Collection (Tabs)', 'Shop the Collection', 'Browse our signature edits by category', 8, 1, 7),
('features', 'Brand Promises / Features', NULL, NULL, 0, 1, 8),
('testimonials', 'Testimonials & Reviews', 'Loved by Our Clients', 'Words from the women who wear Eashal Mirha', 6, 1, 9),
('newsletter', 'Newsletter Signup', 'Join the Inner Circle', 'Be the first to know about new collections, private sales and styling notes.', 0, 1, 10);

INSERT INTO banners (position, label, small_text, heading, text, btn_text, btn_link, image, height, text_color, overlay) VALUES
('parallax', 'Parallax Banner', 'Limited Edition', 'Crafted in Gold, Worn in Grace', 'Discover our festive luxury pret — each piece hand-embellished in our Lahore atelier.', 'Discover the Edit', 'category/luxury-pret', 'assets/images/demo/banner-1.svg', 560, '#ffffff', 0.45),
('promo_left', 'Promo Banner (Left)', 'Bridal 2026', 'The Bridal Atelier', 'Made-to-measure couture for your big day.', 'View Bridal', 'category/bridal', 'assets/images/demo/banner-3.svg', 520, '#ffffff', 0.35),
('promo_right', 'Promo Banner (Right)', 'Up to 30% Off', 'Pret Essentials', 'Everyday classics, now at special prices.', 'Shop Sale', 'shop?filter=sale', 'assets/images/demo/banner-2.svg', 520, '#ffffff', 0.35),
('feature_1', 'Feature 1', NULL, 'Nationwide Delivery', 'Free shipping on orders above Rs. 10,000', NULL, NULL, 'truck', 0, '#ffffff', 0),
('feature_2', 'Feature 2', NULL, 'Cash on Delivery', 'Pay when your parcel arrives', NULL, NULL, 'cash', 0, '#ffffff', 0),
('feature_3', 'Feature 3', NULL, 'Hand Crafted', 'Embellished by master artisans', NULL, NULL, 'needle', 0, '#ffffff', 0),
('feature_4', 'Feature 4', NULL, 'Easy Exchange', '7-day hassle free exchange', NULL, NULL, 'refresh', 0, '#ffffff', 0);

INSERT INTO shipping_zones (city, charge, free_above, delivery_days) VALUES
('Lahore', 200, 8000, '1-2 days'),
('Karachi', 300, 10000, '2-4 days'),
('Islamabad', 250, 10000, '2-3 days'),
('Rawalpindi', 250, 10000, '2-3 days');

INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at) VALUES
('WELCOME10', 'percent', 10, 3000, 0, NULL);

INSERT INTO pages (title, slug, content, meta_title, meta_description, show_footer, sort_order) VALUES
('About Us', 'about-us', '<h2>Our Story</h2><p>Eashal Mirha was born from a love of Pakistani craftsmanship — the patience of hand embroidery, the richness of our textiles and the grace of the women who wear them.</p><p>Every collection is designed in our Lahore atelier and finished by artisans whose skills have been passed down through generations.</p>', 'About Eashal Mirha', 'The story behind Eashal Mirha, a Pakistani luxury womenswear label.', 1, 1),
('Shipping Policy', 'shipping-policy', '<p>We deliver nationwide across Pakistan through trusted courier partners. Orders are dispatched within 2-3 working days. Bridal and made-to-order pieces have their own timelines mentioned on the product page.</p>', NULL, NULL, 1, 2),
('Return & Exchange', 'return-exchange', '<p>Items can be exchanged within 7 days of delivery in original condition with tags attached. Sale, bridal and customised articles are not eligible for exchange.</p>', NULL, NULL, 1, 3),
('Privacy Policy', 'privacy-policy', '<p>We respect your privacy. Personal information is used only to process your orders and improve your experience and is never sold to third parties.</p>', NULL, NULL, 1, 4),
('Terms & Conditions', 'terms-conditions', '<p>By using this website you agree to our terms. Prices and availability are subject to change without notice. Colours may vary slightly due to photography and screen settings.</p>', NULL, NULL, 1, 5),
('FAQs', 'faqs', '<h3>Do you offer Cash on Delivery?</h3><p>Yes, COD is available across Pakistan.</p><h3>How can I track my order?</h3><p>Use the Track Order page with your order number and phone number.</p>', NULL, NULL, 1, 6);

INSERT INTO reviews (product_id, name, rating, comment, status) VALUES
(1, 'Ayesha K.', 5, 'The embroidery is even more beautiful in person. Fabric quality is premium and delivery was quick!', 1),
(4, 'Hira S.', 5, 'Wore Shehzadi to my sister''s nikkah and got endless compliments. Stitching was perfect.', 1),
(7, 'Mahnoor A.', 5, 'My bridal lehenga was a dream. The team guided me through every fitting.', 1),
(15, 'Sana R.', 4, 'Gorgeous anarkali, the flare is amazing. Sizing runs slightly large.', 1),
(13, 'Zainab M.', 5, 'Best lawn I have bought this season. Colours did not fade at all.', 1),
(10, 'Fatima N.', 5, 'Luxurious velvet and the gold work is so elegant. Worth every rupee.', 1);
