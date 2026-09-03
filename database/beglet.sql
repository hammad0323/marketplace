-- =====================================================================
-- Beglet Multi-Vendor Marketplace — Full Database Schema + Demo Data
-- Import this single file into a fresh MySQL database.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- SETTINGS
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Beglet'),
('site_logo', 'assets/images/logo.png'),
('site_favicon', 'assets/images/favicon.png'),
('site_description', 'Beglet — a modern multi-vendor marketplace'),
('site_phone', '+92 300 0000000'),
('site_email', 'support@beglet.com'),
('site_address', 'Karachi, Pakistan'),
('currency_code', 'PKR'),
('currency_symbol', 'Rs.'),
('timezone', 'Asia/Karachi'),
('footer_text', 'Beglet — your one-stop multi-vendor marketplace.'),
('copyright_text', '&copy; 2026 Beglet. All rights reserved.'),
('primary_color', '#2f6fed'),
('secondary_color', '#0b1f3a'),
('accent_color', '#ff7a1a'),
('button_color', '#2f6fed'),
('header_color', '#ffffff'),
('footer_color', '#0b1f3a'),
('background_color', '#f5f7fb'),
('text_color', '#222222'),
('employee_limit', '3'),
('commission_due_days', '30'),
('default_product_status', 'pending'),
('maintenance_mode', '0'),
('google_client_id', ''),
('google_client_secret', ''),
('base_url', 'https://www.beglet.com/beta/'),
('last_sitemap_generated', '');

-- ---------------------------------------------------------------------
-- ADMIN USERS
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password for all demo accounts = Demo@1234
INSERT INTO admins (name, email, password_hash) VALUES
('Beglet Admin', 'admin@beglet.com', '$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC');

-- ---------------------------------------------------------------------
-- CUSTOMERS
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive','banned') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE google_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    google_id VARCHAR(150) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    full_name VARCHAR(150),
    phone VARCHAR(30),
    address_line VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Pakistan',
    postal_code VARCHAR(20),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO customers (first_name,last_name,email,phone,password_hash,email_verified) VALUES
('Ali','Khan','ali.khan@example.com','03001234567','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC',1),
('Sara','Ahmed','sara.ahmed@example.com','03007654321','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC',1),
('Bilal','Raza','bilal.raza@example.com','03009988776','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC',1);

INSERT INTO addresses (customer_id,label,full_name,phone,address_line,city,state,country,postal_code,is_default) VALUES
(1,'Home','Ali Khan','03001234567','Street 12, Gulshan-e-Iqbal','Karachi','Sindh','Pakistan','75300',1),
(2,'Home','Sara Ahmed','03007654321','House 45, Model Town','Lahore','Punjab','Pakistan','54700',1);

-- ---------------------------------------------------------------------
-- SHOP OWNERS / SHOPS / STAFF
-- ---------------------------------------------------------------------
CREATE TABLE shop_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    shop_name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT,
    logo VARCHAR(255) DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    business_address VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Pakistan',
    phone VARCHAR(30),
    email VARCHAR(150),
    status ENUM('pending','active','suspended','payment_overdue','banned','inactive') DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    status_reason VARCHAR(255) DEFAULT NULL,
    employee_limit_override INT DEFAULT NULL,
    primary_color VARCHAR(20) DEFAULT '#2f6fed',
    secondary_color VARCHAR(20) DEFAULT '#0b1f3a',
    rating_avg DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES shop_owners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shop_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    permission_key VARCHAR(60) NOT NULL,
    allowed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (staff_id) REFERENCES shop_staff(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_staff_perm (staff_id, permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO shop_owners (name,email,phone,password_hash) VALUES
('Ahmed Tech','tech@beglet.com','03011111111','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC'),
('Fashion Hub Owner','fashion@beglet.com','03022222222','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC'),
('Home Store Owner','home@beglet.com','03033333333','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC'),
('Beauty Store Owner','beauty@beglet.com','03044444444','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC');

INSERT INTO shops (owner_id,shop_name,slug,description,business_address,city,phone,email,status,rating_avg,rating_count) VALUES
(1,'Tech Store','tech-store','Latest gadgets, mobiles and computer accessories at best prices.','Shahrah-e-Faisal','Karachi','03011111111','tech@beglet.com','active',4.50,32),
(2,'Fashion Hub','fashion-hub','Trending men and women fashion apparel.','Liberty Market','Lahore','03022222222','fashion@beglet.com','active',4.20,21),
(3,'Home Store','home-store','Everything for your home, kitchen and living room.','Blue Area','Islamabad','03033333333','home@beglet.com','active',4.00,15),
(4,'Beauty Store','beauty-store','Skincare, cosmetics and beauty essentials.','Gulberg','Lahore','03044444444','beauty@beglet.com','payment_overdue',3.80,9);

INSERT INTO shop_staff (shop_id,name,email,password_hash) VALUES
(1,'Usman Staff','usman.staff@beglet.com','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC'),
(2,'Hina Staff','hina.staff@beglet.com','$2y$12$HMp/KFY.QPWUmR4Z3VlTBuCllAktzC2AeFm9P3DLAKs3./DGj0qSC');

INSERT INTO staff_permissions (staff_id, permission_key, allowed) VALUES
(1,'view_dashboard',1),(1,'manage_products',1),(1,'add_product',1),(1,'edit_product',1),(1,'delete_product',0),
(1,'manage_inventory',1),(1,'view_orders',1),(1,'manage_orders',1),(1,'confirm_orders',1),(1,'view_customers',1),
(1,'manage_reviews',0),(1,'manage_shop_profile',0),(1,'manage_shop_design',0),(1,'manage_shop_sections',0),
(1,'view_revenue',1),(1,'view_commission',0),(1,'manage_payments',0),(1,'view_reports',1),(1,'manage_seo',0),
(2,'view_dashboard',1),(2,'manage_products',1),(2,'add_product',1),(2,'edit_product',1),(2,'delete_product',1),
(2,'manage_inventory',1),(2,'view_orders',1),(2,'manage_orders',0),(2,'confirm_orders',0),(2,'view_customers',0),
(2,'manage_reviews',1),(2,'manage_shop_profile',1),(2,'manage_shop_design',0),(2,'manage_shop_sections',0),
(2,'view_revenue',0),(2,'view_commission',0),(2,'manage_payments',0),(2,'view_reports',0),(2,'manage_seo',0);

-- ---------------------------------------------------------------------
-- CATEGORIES / SUBCATEGORIES / BRANDS
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(100) DEFAULT 'fa-solid fa-tag',
    commission_type ENUM('percentage','fixed') DEFAULT 'percentage',
    commission_value DECIMAL(10,2) DEFAULT 10.00,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    logo VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name,slug,description,icon,commission_value,sort_order) VALUES
('Electronics','electronics','Electronics and gadgets','fa-solid fa-tv',10.00,1),
('Fashion','fashion','Men and women fashion','fa-solid fa-shirt',8.00,2),
('Home & Living','home-living','Home, kitchen and living essentials','fa-solid fa-couch',7.00,3),
('Beauty','beauty','Beauty and personal care','fa-solid fa-spa',12.00,4),
('Sports','sports','Sports and fitness gear','fa-solid fa-football',9.00,5),
('Mobile Accessories','mobile-accessories','Mobile phone accessories','fa-solid fa-mobile-screen',10.00,6),
('Computers','computers','Laptops, desktops and accessories','fa-solid fa-computer',10.00,7),
('Grocery','grocery','Daily grocery essentials','fa-solid fa-basket-shopping',6.00,8);

INSERT INTO subcategories (category_id,name,slug,sort_order) VALUES
(1,'Mobile Phones','mobile-phones',1),(1,'Televisions','televisions',2),(1,'Cameras','cameras',3),
(2,'Men Clothing','men-clothing',1),(2,'Women Clothing','women-clothing',2),(2,'Shoes','shoes',3),
(3,'Kitchen','kitchen',1),(3,'Furniture','furniture',2),(3,'Decor','decor',3),
(4,'Skincare','skincare',1),(4,'Makeup','makeup',2),(4,'Haircare','haircare',3),
(5,'Gym Equipment','gym-equipment',1),(5,'Outdoor Sports','outdoor-sports',2),
(6,'Cases & Covers','cases-covers',1),(6,'Chargers','chargers',2),
(7,'Laptops','laptops',1),(7,'Accessories','computer-accessories',2),
(8,'Snacks','snacks',1),(8,'Beverages','beverages',2);

INSERT INTO brands (name,slug) VALUES ('Samsung','samsung'),('Apple','apple'),('Generic','generic'),('Nike','nike'),('Sony','sony');

-- ---------------------------------------------------------------------
-- PRODUCTS
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    category_id INT NOT NULL,
    subcategory_id INT DEFAULT NULL,
    brand_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    sku VARCHAR(80) DEFAULT NULL,
    short_description VARCHAR(500),
    description LONGTEXT,
    product_type ENUM('simple','variable','digital','physical') DEFAULT 'simple',
    regular_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(12,2) DEFAULT NULL,
    cost_price DECIMAL(12,2) DEFAULT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 0,
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    manage_stock TINYINT(1) DEFAULT 1,
    stock_status ENUM('in_stock','out_of_stock','backorder') DEFAULT 'in_stock',
    main_image VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected','draft','active','inactive') DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    rating_avg DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    total_sold INT DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    INDEX idx_products_shop (shop_id),
    INDEX idx_products_category (category_id),
    INDEX idx_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    attribute_name VARCHAR(80) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_attribute_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attribute_id INT NOT NULL,
    value VARCHAR(80) NOT NULL,
    FOREIGN KEY (attribute_id) REFERENCES product_attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variation_label VARCHAR(150) NOT NULL,
    sku VARCHAR(80) DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_quantity INT DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seo_meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('homepage','category','subcategory','product','shop','page') NOT NULL,
    entity_id INT DEFAULT NULL,
    meta_title VARCHAR(255),
    meta_description VARCHAR(500),
    focus_keyword VARCHAR(150),
    keywords VARCHAR(255),
    canonical_url VARCHAR(255),
    robots VARCHAR(50) DEFAULT 'index,follow',
    og_title VARCHAR(255),
    og_description VARCHAR(500),
    og_image VARCHAR(255),
    twitter_title VARCHAR(255),
    twitter_description VARCHAR(500),
    twitter_image VARCHAR(255),
    schema_type VARCHAR(50) DEFAULT 'Product',
    faq_json TEXT,
    entity_description TEXT,
    key_facts TEXT,
    seo_score INT DEFAULT 0,
    UNIQUE KEY uniq_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CART / WISHLIST
-- ---------------------------------------------------------------------
CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    variation_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_wishlist (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- ORDERS
-- ---------------------------------------------------------------------
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    instructions TEXT,
    credentials TEXT,
    is_enabled TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payment_methods (method_key,title,description,instructions,is_enabled,sort_order) VALUES
('cod','Cash on Delivery','Pay when your order arrives.','Please keep exact change ready.',1,1),
('self_collection','Self Collection','Pick up your order from the shop.','Visit the shop with your order ID.',1,2),
('bank_transfer','Bank Transfer','Pay via direct bank transfer.','Bank: MCB | Account Title: Beglet | Account #: 1234567890 | IBAN: PK00MCB0001234567890',1,3),
('jazzcash','JazzCash','Pay using JazzCash mobile wallet.','Send payment to 0300-0000000.',0,4),
('easypaisa','Easypaisa','Pay using Easypaisa mobile wallet.','Send payment to 0300-0000000.',0,5),
('stripe','Stripe','Pay securely via credit/debit card.','Card payments processed securely via Stripe.',0,6);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    address_id INT DEFAULT NULL,
    payment_method VARCHAR(50) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    order_status ENUM('placed','payment_confirmed','confirmed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'placed',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (address_id) REFERENCES addresses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shop_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    shop_order_number VARCHAR(40) NOT NULL UNIQUE,
    shop_id INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    vendor_earning DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('placed','confirmed','processing','shipped','delivered','cancelled','refunded') DEFAULT 'placed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    INDEX idx_shop_orders_shop (shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    vendor_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (shop_order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_order_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- COMMISSIONS
-- ---------------------------------------------------------------------
CREATE TABLE commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    shop_order_id INT NOT NULL,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date DATE NOT NULL,
    payment_status ENUM('pending','paid','overdue') DEFAULT 'pending',
    payment_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
    INDEX idx_commissions_shop (shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE commission_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commission_id INT NOT NULL,
    shop_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transaction_id VARCHAR(100),
    payment_date DATE,
    receipt_image VARCHAR(255) DEFAULT NULL,
    notes VARCHAR(255),
    status ENUM('submitted','approved','rejected') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- REVIEWS
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_item_id INT DEFAULT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    title VARCHAR(150),
    comment TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE review_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- BANNERS / HOMEPAGE / SHOP BUILDER
-- ---------------------------------------------------------------------
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    heading VARCHAR(200),
    sub_heading VARCHAR(255),
    description TEXT,
    button_text VARCHAR(80),
    button_url VARCHAR(255),
    image VARCHAR(255),
    bg_color VARCHAR(20) DEFAULT '#2f6fed',
    text_align ENUM('left','center','right') DEFAULT 'left',
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO banners (heading,sub_heading,description,button_text,button_url,image,bg_color,sort_order) VALUES
('Big Ramzan Sale','Up to 50% off','Shop the biggest sale of the season across all categories.','Shop Now','category/electronics','','#2f6fed',1),
('New Arrivals','Fresh styles every week','Discover the latest products from top vendors.','Explore','search','','#ff7a1a',2),
('Sell on Beglet','Grow your business','Join thousands of vendors selling on Beglet.','Become a Seller','shop-register.php','','#0b1f3a',3);

CREATE TABLE homepage_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_type VARCHAR(50) NOT NULL,
    heading VARCHAR(150),
    description VARCHAR(255),
    item_count INT DEFAULT 8,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO homepage_sections (section_type,heading,description,item_count,sort_order) VALUES
('hero_slider','','',5,1),
('featured_categories','Featured Categories','Shop by category',8,2),
('featured_shops','Featured Shops','Top rated vendors on Beglet',4,3),
('featured_products','Featured Products','Hand-picked for you',8,4),
('latest_products','Latest Products','Freshly added products',8,5),
('best_selling','Best Selling Products','Customer favorites',8,6),
('promotional_banner','','',1,7);

CREATE TABLE shop_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    section_type VARCHAR(50) NOT NULL,
    heading VARCHAR(150),
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shop_section_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_section_id INT NOT NULL,
    product_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (shop_section_id) REFERENCES shop_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO shop_sections (shop_id, section_type, heading, sort_order) VALUES
(1,'featured_products','Featured Products',1),
(1,'latest_products','New Arrivals',2),
(2,'featured_products','Featured Products',1),
(2,'best_selling','Best Sellers',2);

-- ---------------------------------------------------------------------
-- NOTIFICATIONS / AUDIT / PASSWORD RESET
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('admin','shop_owner','shop_staff','customer') NOT NULL,
    recipient_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message VARCHAR(500),
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_recipient (recipient_type, recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type VARCHAR(30) NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(150),
    action VARCHAR(100) NOT NULL,
    module VARCHAR(60) NOT NULL,
    record_id INT DEFAULT NULL,
    description VARCHAR(500),
    ip_address VARCHAR(60),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','shop_owner','shop_staff','customer') NOT NULL,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- DEMO PRODUCTS (30+)
-- ---------------------------------------------------------------------
INSERT INTO products (shop_id,category_id,subcategory_id,brand_id,name,slug,sku,short_description,description,regular_price,sale_price,stock_quantity,main_image,status,rating_avg,rating_count,total_sold) VALUES
(1,1,1,1,'Samsung Galaxy A54 5G','samsung-galaxy-a54-5g','SKU-1001','6.4" AMOLED, 128GB storage, 5G ready.','<p>The Samsung Galaxy A54 5G offers a stunning AMOLED display, powerful camera and all-day battery life.</p>',89999,79999,25,'','active',4.6,18,54),
(1,1,1,2,'iPhone 13','iphone-13','SKU-1002','A15 Bionic chip, 128GB, dual camera.','<p>iPhone 13 delivers incredible speed and an advanced dual-camera system.</p>',219999,204999,12,'','active',4.8,22,40),
(1,1,2,3,'Smart LED TV 43 inch','smart-led-tv-43-inch','SKU-1003','Full HD Smart TV with built-in apps.','<p>Enjoy your favorite content in stunning Full HD with built-in streaming apps.</p>',54999,49999,10,'','active',4.3,9,20),
(1,1,3,5,'Sony Digital Camera','sony-digital-camera','SKU-1004','20MP digital camera with zoom lens.','<p>Capture every moment in high resolution with this compact Sony camera.</p>',74999,NULL,8,'','active',4.2,6,11),
(1,7,17,3,'Dell Inspiron Laptop','dell-inspiron-laptop','SKU-1005','Intel i5, 8GB RAM, 512GB SSD.','<p>A reliable everyday laptop for work and study.</p>',129999,119999,15,'','active',4.5,14,30),
(1,7,18,3,'Wireless Mouse','wireless-mouse','SKU-1006','Ergonomic wireless mouse.','<p>Smooth and precise wireless mouse for everyday use.</p>',1999,1499,60,'','active',4.1,10,80),
(1,6,15,3,'Shockproof Phone Case','shockproof-phone-case','SKU-1007','Universal shockproof case.','<p>Protect your phone in style with this shockproof case.</p>',999,699,100,'','active',4.0,25,150),
(1,6,16,3,'Fast Charger 33W','fast-charger-33w','SKU-1008','33W fast charging adapter.','<p>Charge your devices quickly and safely.</p>',2499,1999,80,'','active',4.4,17,95),
(2,2,4,4,'Men Casual Shirt','men-casual-shirt','SKU-2001','100% cotton casual shirt.','<p>A comfortable, breathable casual shirt for everyday wear.</p>',2499,1999,50,'','active',4.3,12,70),
(2,2,5,3,'Women Summer Dress','women-summer-dress','SKU-2002','Lightweight floral summer dress.','<p>Stay cool and stylish with this floral summer dress.</p>',3499,2999,40,'','active',4.5,20,65),
(2,2,6,4,'Running Shoes','running-shoes','SKU-2003','Breathable running shoes.','<p>Lightweight running shoes designed for comfort and performance.</p>',5999,4999,35,'','active',4.6,28,90),
(2,2,4,3,'Men Denim Jeans','men-denim-jeans','SKU-2004','Slim fit denim jeans.','<p>Classic slim-fit denim jeans for everyday style.</p>',3999,3499,45,'','active',4.2,15,55),
(2,2,5,3,'Women Handbag','women-handbag','SKU-2005','Elegant faux leather handbag.','<p>A stylish and spacious handbag for every occasion.</p>',4499,3999,20,'','active',4.4,11,33),
(2,2,6,4,'Formal Loafers','formal-loafers','SKU-2006','Genuine leather formal loafers.','<p>Premium leather loafers for a polished formal look.</p>',6999,5999,18,'','active',4.1,7,19),
(2,2,4,3,'Winter Jacket','winter-jacket','SKU-2007','Warm padded winter jacket.','<p>Stay warm this winter with this padded jacket.</p>',7999,6499,22,'','active',4.5,13,27),
(2,2,5,3,'Printed Scarf','printed-scarf','SKU-2008','Soft printed scarf.','<p>A soft, lightweight scarf with a beautiful print.</p>',1499,1199,60,'','active',4.0,9,44),
(3,3,7,3,'Non-stick Cookware Set','non-stick-cookware-set','SKU-3001','5-piece non-stick cookware set.','<p>A complete non-stick cookware set for your kitchen.</p>',8999,7499,15,'','active',4.4,10,26),
(3,3,8,3,'Wooden Dining Table','wooden-dining-table','SKU-3002','6-seater solid wood dining table.','<p>A sturdy, elegant dining table crafted from solid wood.</p>',49999,44999,5,'','active',4.6,5,8),
(3,3,9,3,'Wall Clock','wall-clock','SKU-3003','Modern minimalist wall clock.','<p>Add a modern touch to your walls with this minimalist clock.</p>',1999,1599,50,'','active',4.2,8,38),
(3,3,7,3,'Electric Kettle','electric-kettle','SKU-3004','1.7L stainless steel electric kettle.','<p>Boil water quickly and safely with this stainless steel kettle.</p>',2999,2499,40,'','active',4.3,12,50),
(3,3,8,3,'3-Seater Sofa','3-seater-sofa','SKU-3005','Comfortable fabric 3-seater sofa.','<p>A cozy fabric sofa perfect for your living room.</p>',64999,59999,6,'','active',4.5,6,10),
(3,3,9,3,'Decorative Vase Set','decorative-vase-set','SKU-3006','Set of 2 ceramic vases.','<p>Elegant ceramic vases to decorate your home.</p>',2499,1999,30,'','active',4.1,7,22),
(3,3,7,3,'Blender Machine','blender-machine','SKU-3007','High-speed kitchen blender.','<p>Blend smoothies, sauces and more with ease.</p>',4499,3999,25,'','active',4.4,9,31),
(3,3,8,3,'Study Table','study-table','SKU-3008','Compact wooden study table.','<p>A compact and sturdy table ideal for study or work.</p>',12999,10999,10,'','active',4.0,4,12),
(4,4,10,3,'Vitamin C Serum','vitamin-c-serum','SKU-4001','Brightening vitamin C face serum.','<p>Brighten and even your skin tone with this vitamin C serum.</p>',1999,1599,70,'','active',4.7,30,120),
(4,4,11,3,'Matte Lipstick Set','matte-lipstick-set','SKU-4002','Set of 3 long-lasting matte lipsticks.','<p>Long-lasting matte lipsticks in versatile shades.</p>',2499,1999,55,'','active',4.5,24,88),
(4,4,12,3,'Argan Hair Oil','argan-hair-oil','SKU-4003','Nourishing argan oil for hair.','<p>Nourish and repair your hair with pure argan oil.</p>',1499,1199,65,'','active',4.6,19,76),
(4,4,10,3,'Hydrating Face Mask','hydrating-face-mask','SKU-4004','Pack of 5 sheet masks.','<p>Deeply hydrate your skin with these sheet masks.</p>',999,799,90,'','active',4.3,16,60),
(4,4,11,3,'Compact Powder','compact-powder','SKU-4005','Matte finish compact powder.','<p>Achieve a flawless matte finish all day long.</p>',1799,1499,48,'','active',4.2,11,42),
(2,5,13,4,'Yoga Mat','yoga-mat','SKU-2009','Non-slip exercise yoga mat.','<p>A durable, non-slip mat for yoga and home workouts.</p>',1999,1699,40,'','active',4.4,13,37),
(2,5,14,4,'Adjustable Dumbbells','adjustable-dumbbells','SKU-2010','5-25kg adjustable dumbbell set.','<p>Compact adjustable dumbbells for a full home gym.</p>',12999,10999,12,'','active',4.5,8,15);

-- ---------------------------------------------------------------------
-- DEMO ORDERS (multi-vendor, various statuses)
-- ---------------------------------------------------------------------
INSERT INTO orders (order_number,customer_id,address_id,payment_method,subtotal,shipping_total,tax_total,grand_total,order_status,payment_status,created_at) VALUES
('BG-100001',1,1,'cod',159998,0,0,159998,'delivered','paid', DATE_SUB(NOW(), INTERVAL 40 DAY)),
('BG-100002',2,2,'bank_transfer',7498,0,0,7498,'shipped','paid', DATE_SUB(NOW(), INTERVAL 10 DAY)),
('BG-100003',1,1,'cod',3999,0,0,3999,'placed','pending', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Order 1: iPhone 13 (Tech Store) + Men Casual Shirt (Fashion Hub)
INSERT INTO shop_orders (order_id,shop_order_number,shop_id,subtotal,commission_total,vendor_earning,status,created_at) VALUES
(1,'BG-100001-A',1,204999,20499.90,184499.10,'delivered', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1,'BG-100001-B',2,1999,159.92,1839.08,'delivered', DATE_SUB(NOW(), INTERVAL 40 DAY));

INSERT INTO order_items (shop_order_id,product_id,product_name,category_id,quantity,unit_price,line_total,commission_percent,commission_amount,vendor_amount) VALUES
(1,2,'iPhone 13',1,1,204999,204999,10.00,20499.90,184499.10),
(2,9,'Men Casual Shirt',2,1,1999,1999,8.00,159.92,1839.08);

INSERT INTO order_status_history (shop_order_id,status,note) VALUES
(1,'placed','Order placed by customer'),(1,'confirmed','Confirmed by shop'),(1,'shipped','Order shipped'),(1,'delivered','Order delivered'),
(2,'placed','Order placed by customer'),(2,'confirmed','Confirmed by shop'),(2,'shipped','Order shipped'),(2,'delivered','Order delivered');

-- Order 2: Running Shoes (Fashion Hub)
INSERT INTO shop_orders (order_id,shop_order_number,shop_id,subtotal,commission_total,vendor_earning,status,created_at) VALUES
(2,'BG-100002-A',2,7498,599.84,6898.16,'shipped', DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO order_items (shop_order_id,product_id,product_name,category_id,quantity,unit_price,line_total,commission_percent,commission_amount,vendor_amount) VALUES
(3,11,'Running Shoes',2,1,4999,4999,8.00,399.92,4599.08),
(3,10,'Women Summer Dress',2,1,2999,2999,8.00,239.92,2759.08);

INSERT INTO order_status_history (shop_order_id,status,note) VALUES
(3,'placed','Order placed by customer'),(3,'confirmed','Confirmed by shop'),(3,'shipped','Order shipped');

-- Order 3: Men Denim Jeans (Fashion Hub) - just placed
INSERT INTO shop_orders (order_id,shop_order_number,shop_id,subtotal,commission_total,vendor_earning,status,created_at) VALUES
(3,'BG-100003-A',2,3999,319.92,3679.08,'placed', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO order_items (shop_order_id,product_id,product_name,category_id,quantity,unit_price,line_total,commission_percent,commission_amount,vendor_amount) VALUES
(4,12,'Men Denim Jeans',2,1,3999,3999,8.00,319.92,3679.08);

INSERT INTO order_status_history (shop_order_id,status,note) VALUES
(4,'placed','Order placed by customer');

INSERT INTO payments (order_id,method,amount,transaction_id,status,paid_at) VALUES
(1,'cod',159998,NULL,'paid', DATE_SUB(NOW(), INTERVAL 39 DAY)),
(2,'bank_transfer',7498,'TXN-88213','paid', DATE_SUB(NOW(), INTERVAL 9 DAY));

-- Demo commissions: paid / pending / overdue
INSERT INTO commissions (shop_id,shop_order_id,invoice_number,revenue,commission_amount,due_date,payment_status,payment_date,created_at) VALUES
(1,1,'INV-1001',204999,20499.90,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'paid', DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(2,2,'INV-1002',1999,159.92,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'paid', DATE_SUB(NOW(), INTERVAL 38 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(2,3,'INV-1003',7498,599.84,DATE_ADD(CURDATE(), INTERVAL 20 DAY),'pending',NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2,4,'INV-1004',3999,319.92,DATE_ADD(CURDATE(), INTERVAL 28 DAY),'pending',NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4,4,'INV-1005',15000,1800.00,DATE_SUB(CURDATE(), INTERVAL 5 DAY),'overdue',NULL, DATE_SUB(NOW(), INTERVAL 45 DAY));

INSERT INTO commission_payments (commission_id,shop_id,amount,transaction_id,payment_date,notes,status) VALUES
(1,1,20499.90,'TXN-55211','2026-07-20','Full payment via bank transfer','approved'),
(2,2,159.92,'TXN-55212','2026-07-22','Paid on time','approved');

-- Demo reviews
INSERT INTO reviews (product_id,customer_id,rating,title,comment,status,created_at) VALUES
(2,1,5,'Excellent phone','iPhone 13 works perfectly, fast delivery.','approved', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(9,1,4,'Good quality shirt','Nice fabric, true to size.','approved', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(11,2,5,'Very comfortable','Great running shoes, highly recommend.','approved', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1,3,4,'Great value phone','Good camera and battery life for the price.','approved', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Demo notifications
INSERT INTO notifications (recipient_type,recipient_id,title,message,link,is_read) VALUES
('shop_owner',1,'New Order Received','You have received a new order BG-100001-A.','shop/orders.php',1),
('shop_owner',2,'Commission Due Soon','Your commission invoice INV-1003 is due soon.','shop/commissions.php',0),
('shop_owner',4,'Commission Overdue','Your commission invoice INV-1005 is overdue. Your shop is restricted.','shop/payments.php',0),
('admin',1,'New Shop Application','A new shop has applied for approval.','admin/shops/index.php',0),
('customer',1,'Order Delivered','Your order BG-100001 has been delivered.','customer/orders.php',1);

-- Demo SEO for homepage
INSERT INTO seo_meta (entity_type,entity_id,meta_title,meta_description,focus_keyword,robots,schema_type,seo_score) VALUES
('homepage',NULL,'Beglet — Multi-Vendor Marketplace','Shop electronics, fashion, home, beauty and more from thousands of trusted vendors on Beglet.','multi-vendor marketplace','index,follow','WebSite',85);

SET FOREIGN_KEY_CHECKS = 1;
