-- =====================================================================
-- Premium Pakistani Fashion E-Commerce Platform
-- Database schema (MySQL / MySQLi, InnoDB, utf8mb4)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Admin users, roles
-- ---------------------------------------------------------------------
CREATE TABLE admin_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    permissions TEXT NULL COMMENT 'JSON array of permission keys, null = super admin (all)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NULL COMMENT 'null when account created via Google only',
    google_id VARCHAR(100) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL DEFAULT 'Home',
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Pakistan',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Catalog: categories (self-referencing for subcategories), brands
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    image VARCHAR(255) NULL,
    description TEXT NULL,
    seo_title VARCHAR(180) NULL,
    seo_description VARCHAR(300) NULL,
    seo_keywords VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    logo VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    sku VARCHAR(80) NOT NULL UNIQUE,
    short_description VARCHAR(500) NULL,
    description TEXT NULL,
    regular_price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    stock_status ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
    status ENUM('active','inactive','draft') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
    is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
    is_trending TINYINT(1) NOT NULL DEFAULT 0,
    tags VARCHAR(255) NULL COMMENT 'comma separated',
    weight VARCHAR(30) NULL,
    dimensions VARCHAR(60) NULL,
    video_url VARCHAR(255) NULL,
    seo_title VARCHAR(180) NULL,
    seo_description VARCHAR(300) NULL,
    seo_keywords VARCHAR(255) NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_search (name, short_description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Attributes / variations (Size, Color, ...)
-- ---------------------------------------------------------------------
CREATE TABLE attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attribute_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute_id INT UNSIGNED NOT NULL,
    value VARCHAR(80) NOT NULL,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_variations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NULL COMMENT 'overrides product price when set',
    stock_qty INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_variation_values (
    variation_id INT UNSIGNED NOT NULL,
    attribute_value_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (variation_id, attribute_value_id),
    FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Cart (guest via session_id, or customer_id once logged in)
-- ---------------------------------------------------------------------
CREATE TABLE cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(191) NULL,
    customer_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    variation_id INT UNSIGNED NULL,
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (session_id),
    KEY idx_customer (customer_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Wishlist
-- ---------------------------------------------------------------------
CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL UNIQUE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_wish_product (wishlist_id, product_id),
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Coupons
-- ---------------------------------------------------------------------
CREATE TABLE coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('fixed','percentage') NOT NULL DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_discount DECIMAL(10,2) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    usage_limit INT UNSIGNED NULL,
    per_customer_limit INT UNSIGNED NULL DEFAULT 1,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    category_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coupon_usage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT UNSIGNED NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    order_id INT UNSIGNED NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Shipping
-- ---------------------------------------------------------------------
CREATE TABLE shipping_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NULL,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Payments
-- ---------------------------------------------------------------------
CREATE TABLE payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    settings TEXT NULL COMMENT 'JSON: gateway credentials / COD min-max etc',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Orders
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    guest_name VARCHAR(120) NULL,
    guest_email VARCHAR(150) NULL,
    guest_phone VARCHAR(30) NULL,
    shipping_address VARCHAR(255) NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) NULL,
    shipping_postal_code VARCHAR(20) NULL,
    shipping_country VARCHAR(100) NOT NULL DEFAULT 'Pakistan',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    coupon_code VARCHAR(50) NULL,
    payment_method VARCHAR(40) NOT NULL,
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    order_status ENUM('pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','refunded') NOT NULL DEFAULT 'pending',
    customer_note TEXT NULL,
    admin_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variation_id INT UNSIGNED NULL,
    product_name VARCHAR(200) NOT NULL,
    variation_label VARCHAR(120) NULL,
    sku VARCHAR(100) NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Reviews
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    comment TEXT NULL,
    image VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Homepage builder: 4 selectable homepages, banners, dynamic sections
-- ---------------------------------------------------------------------
CREATE TABLE homepage_settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    active_home TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1-10'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_configs (
    homepage TINYINT UNSIGNED PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    hero_style ENUM('slider','split','centered','collage') NOT NULL DEFAULT 'slider'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homepage TINYINT UNSIGNED NOT NULL COMMENT '1-10',
    title VARCHAR(180) NULL,
    subtitle VARCHAR(255) NULL,
    button_text VARCHAR(60) NULL,
    button_url VARCHAR(255) NULL,
    image_desktop VARCHAR(255) NOT NULL,
    image_mobile VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    homepage TINYINT UNSIGNED NOT NULL COMMENT '1-10',
    section_type ENUM('categories','products','promo_banner','image_text','brand_story','newsletter','testimonials','instagram','custom_html','text_banner','two_column','brands','features','counters') NOT NULL,
    title VARCHAR(180) NULL,
    subtitle VARCHAR(255) NULL,
    product_filter ENUM('featured','new_arrival','best_seller','trending','sale','category','manual') NULL,
    category_id INT UNSIGNED NULL,
    layout VARCHAR(40) NULL COMMENT 'e.g. grid-4, slider, split-left, split-right',
    button_text VARCHAR(60) NULL,
    button_url VARCHAR(255) NULL,
    custom_html TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_section_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NULL,
    subtitle VARCHAR(255) NULL,
    image VARCHAR(255) NULL,
    icon_class VARCHAR(60) NULL COMMENT 'Bootstrap Icon class, used by features/counters sections',
    link VARCHAR(255) NULL,
    product_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CMS pages, blog, newsletter, settings, social links
-- ---------------------------------------------------------------------
CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    content LONGTEXT NULL,
    seo_title VARCHAR(180) NULL,
    seo_description VARCHAR(300) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_category_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NULL,
    content LONGTEXT NULL,
    featured_image VARCHAR(255) NULL,
    author VARCHAR(120) NULL,
    tags VARCHAR(255) NULL,
    seo_title VARCHAR(180) NULL,
    seo_description VARCHAR(300) NULL,
    status ENUM('published','draft') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE social_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(60) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon_class VARCHAR(60) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Seed data
-- =====================================================================

INSERT INTO admin_roles (id, name, permissions) VALUES
(1, 'Super Admin', NULL),
(2, 'Manager', '["orders","products","customers"]'),
(3, 'Content Manager', '["homepage","banners","categories","pages","blog"]'),
(4, 'Order Manager', '["orders","customers"]');

-- password: Admin@123
INSERT INTO admins (id, role_id, name, email, password_hash) VALUES
(1, 1, 'Store Admin', 'admin@fashionstore.pk', '$2y$12$qBQclj22OsKCIsck3ZiXx.Lei7Wz2.pRDefpDBbnFxX9R8H8VAtx.');

INSERT INTO homepage_settings (id, active_home) VALUES (1, 1);

INSERT INTO categories (id, parent_id, name, slug, sort_order) VALUES
(1, NULL, 'Women', 'women', 1),
(2, NULL, 'Men', 'men', 2),
(3, NULL, 'Kids', 'kids', 3),
(4, 1, 'Unstitched', 'women-unstitched', 1),
(5, 1, 'Ready To Wear', 'women-ready-to-wear', 2),
(6, 1, 'Lawn', 'women-lawn', 3),
(7, 1, 'Embroidered', 'women-embroidered', 4),
(8, 1, 'Festive', 'women-festive', 5),
(9, 2, 'Shalwar Kameez', 'men-shalwar-kameez', 1),
(10, 2, 'Kurta', 'men-kurta', 2),
(11, 2, 'Waistcoat', 'men-waistcoat', 3);

INSERT INTO brands (id, name, slug, logo, status) VALUES
(1, 'Noor Studio', 'noor-studio', NULL, 'active'),
(2, 'Aïna Label', 'aina-label', NULL, 'active'),
(3, 'Zar Textiles', 'zar-textiles', NULL, 'active'),
(4, 'Rivaayat', 'rivaayat', NULL, 'active');

INSERT INTO attributes (id, name) VALUES (1, 'Size'), (2, 'Color');
INSERT INTO attribute_values (attribute_id, value) VALUES
(1,'XS'),(1,'S'),(1,'M'),(1,'L'),(1,'XL'),(1,'XXL'),
(2,'Black'),(2,'White'),(2,'Blue'),(2,'Green'),(2,'Maroon');

INSERT INTO products (id, category_id, name, slug, sku, short_description, description, regular_price, sale_price, stock_qty, is_featured, is_new_arrival, is_best_seller, is_trending) VALUES
(1, 6, 'Embroidered Lawn Suit', 'embroidered-lawn-suit', 'WL-1001', 'Premium 3-piece embroidered lawn suit.', '<p>Premium 3-piece embroidered lawn suit crafted from finest fabric with detailed embroidery.</p>', 6500.00, 5200.00, 40, 1, 1, 0, 1),
(2, 7, 'Chikankari Embroidered Shirt', 'chikankari-embroidered-shirt', 'WE-1002', 'Hand embroidered chikankari shirt.', '<p>Elegant hand embroidered chikankari shirt, perfect for festive occasions.</p>', 4200.00, NULL, 25, 1, 0, 1, 0),
(3, 8, 'Festive Formal 3-Piece', 'festive-formal-3-piece', 'WF-1003', 'Heavily embellished festive formal wear.', '<p>Heavily embellished festive formal 3-piece suitable for weddings and celebrations.</p>', 15500.00, 13900.00, 15, 1, 1, 1, 1),
(4, 5, 'Ready To Wear Kurti', 'ready-to-wear-kurti', 'WR-1004', 'Comfortable printed kurti.', '<p>Comfortable everyday printed kurti made from soft lawn fabric.</p>', 2800.00, NULL, 60, 0, 1, 0, 0),
(5, 9, 'Men Formal Shalwar Kameez', 'men-formal-shalwar-kameez', 'MS-1005', 'Classic formal shalwar kameez.', '<p>Classic formal shalwar kameez tailored from premium cotton blend.</p>', 4800.00, 4300.00, 30, 1, 0, 1, 0),
(6, 10, 'Embroidered Kurta', 'embroidered-kurta', 'MK-1006', 'Casual embroidered kurta.', '<p>Casual embroidered kurta for everyday elegant wear.</p>', 3200.00, NULL, 45, 0, 1, 0, 1);

INSERT INTO product_images (product_id, image_path, sort_order, is_primary) VALUES
(1, 'assets/img/placeholder.svg', 0, 1),
(2, 'assets/img/placeholder.svg', 0, 1),
(3, 'assets/img/placeholder.svg', 0, 1),
(4, 'assets/img/placeholder.svg', 0, 1),
(5, 'assets/img/placeholder.svg', 0, 1),
(6, 'assets/img/placeholder.svg', 0, 1);

INSERT INTO payment_methods (code, name, description, is_enabled, settings, sort_order) VALUES
('cod', 'Cash on Delivery', 'Pay with cash upon delivery.', 1, '{"min_order":0,"max_order":0,"fee":0}', 1),
('easypaisa', 'EasyPaisa', 'Pay via EasyPaisa mobile account.', 0, '{"merchant_id":"","api_key":""}', 2),
('jazzcash', 'JazzCash', 'Pay via JazzCash mobile account.', 0, '{"merchant_id":"","api_key":""}', 3),
('stripe', 'Credit / Debit Card (Stripe)', 'Pay securely with your card via Stripe.', 0, '{"publishable_key":"","secret_key":""}', 4),
('square', 'Square', 'Pay securely with Square.', 0, '{"app_id":"","access_token":""}', 5),
('moneris', 'Moneris', 'Pay securely with Moneris.', 0, '{"store_id":"","api_token":""}', 6);

INSERT INTO shipping_methods (city, province, fee, sort_order) VALUES
('Karachi', 'Sindh', 200.00, 1),
('Lahore', 'Punjab', 250.00, 2),
('Islamabad', 'Islamabad', 250.00, 3),
('Other Cities', NULL, 300.00, 99);

INSERT INTO settings (setting_key, setting_value) VALUES
('store_name', 'Noor Couture'),
('store_tagline', 'Premium Pakistani Fashion'),
('store_email', 'hello@fashionstore.pk'),
('store_phone', '+92 300 0000000'),
('store_address', 'Shahra-e-Faisal, Karachi, Pakistan'),
('currency_symbol', 'Rs.'),
('guest_checkout_enabled', '1'),
('google_login_enabled', '0'),
('google_client_id', ''),
('google_client_secret', ''),
('free_shipping_threshold', '5000'),
('tax_percent', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', 'Noor Couture'),
('smtp_encryption', 'tls'),
('order_emails_enabled', '1'),
('reviews_enabled', '1'),
('seo_default_title', 'Noor Couture | Premium Pakistani Fashion'),
('seo_default_description', 'Shop premium Pakistani unstitched, ready-to-wear and festive fashion online.'),
('google_site_verification', ''),
('bing_site_verification', ''),
('social_twitter_handle', ''),
('site_logo', ''),
('site_favicon', ''),
('site_og_image', ''),
('theme_accent_color', '#a5763f'),
('theme_dark_color', '#211d17'),
('ads_txt_content', ''),
('robots_extra_rules', '');

INSERT INTO social_links (platform, url, icon_class, sort_order) VALUES
('Facebook', 'https://facebook.com', 'bi-facebook', 1),
('Instagram', 'https://instagram.com', 'bi-instagram', 2),
('TikTok', 'https://tiktok.com', 'bi-tiktok', 3),
('YouTube', 'https://youtube.com', 'bi-youtube', 4);

INSERT INTO pages (title, slug, content, status) VALUES
('About Us', 'about-us', '<p>Noor Couture is a premium Pakistani fashion brand crafting elegant, contemporary clothing for modern women and men.</p>', 'active'),
('Privacy Policy', 'privacy-policy', '<p>We respect your privacy and are committed to protecting your personal information.</p>', 'active'),
('Terms & Conditions', 'terms-conditions', '<p>By using this website, you agree to the following terms and conditions.</p>', 'active'),
('Shipping Policy', 'shipping-policy', '<p>Orders are processed within 1-2 business days and delivered nationwide.</p>', 'active'),
('Return Policy', 'return-policy', '<p>Items can be returned within 7 days of delivery in original condition.</p>', 'active'),
('FAQ', 'faq', '<p><strong>How do I track my order?</strong> You can track your order from your account dashboard.</p>', 'active'),
('Size Guide', 'size-guide', '<p>Refer to our size chart to find your perfect fit.</p>', 'active');

-- Homepage 1 sections (Al Imran Fabrics inspired: hero, category grid, new arrivals, promo split, best sellers, newsletter)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(1, 'categories', 'Shop By Category', 'Explore our curated collections', NULL, 'grid-4', 1),
(1, 'products', 'New Arrivals', 'Fresh off the runway', 'new_arrival', 'grid-4', 2),
(1, 'image_text', 'Crafted With Tradition', 'Every stitch tells a story of heritage and craftsmanship.', NULL, 'split-left', 3),
(1, 'products', 'Best Sellers', 'Loved by our customers', 'best_seller', 'grid-4', 4),
(1, 'newsletter', 'Stay In Style', 'Subscribe for early access to new collections and offers.', NULL, NULL, 5);

-- Homepage 2 sections (Gul Ahmed inspired: hero slider, promo banners row, trending, brand story, testimonials)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(2, 'products', 'Trending Now', 'What everyone is wearing this season', 'trending', 'slider', 1),
(2, 'promo_banner', NULL, NULL, NULL, 'two-column', 2),
(2, 'products', 'On Sale', 'Limited time offers', 'sale', 'grid-4', 3),
(2, 'brand_story', 'Our Story', 'A legacy of premium Pakistani textile craftsmanship since generations.', NULL, 'split-right', 4),
(2, 'testimonials', 'What Our Customers Say', NULL, NULL, NULL, 5);

-- Homepage 3 sections (Alkaram Studio inspired: minimal full-width hero, featured, editorial split, instagram)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(3, 'products', 'Featured Collection', 'Handpicked for you', 'featured', 'grid-3', 1),
(3, 'image_text', 'Studio Edit', 'Minimal silhouettes, maximal elegance.', NULL, 'split-right', 2),
(3, 'products', 'New In', 'Just landed', 'new_arrival', 'grid-4', 3),
(3, 'instagram', 'Follow Our Journey', '@fashionstore', NULL, NULL, 4);

-- Homepage 4 sections (Zeen inspired: hero, category tiles, seasonal collection, best sellers, newsletter)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(4, 'categories', 'Explore Collections', NULL, NULL, 'grid-3', 1),
(4, 'products', 'Festive Edit', 'Celebrate in style', 'featured', 'grid-4', 2),
(4, 'promo_banner', NULL, NULL, NULL, 'full-width', 3),
(4, 'products', 'Best Sellers', 'Customer favourites', 'best_seller', 'grid-4', 4),
(4, 'newsletter', 'Join The Noor Circle', 'Be the first to know about new drops.', NULL, NULL, 5);

-- ---------------------------------------------------------------------
-- Ten selectable homepage designs: names + hero style per homepage
-- ---------------------------------------------------------------------
INSERT INTO homepage_configs (homepage, name, description, hero_style) VALUES
(1, 'Al Imran Edit', 'Category grid, new arrivals, editorial split, best sellers.', 'slider'),
(2, 'Gul Ahmed Edit', 'Trending slider, promo banners, brand story, testimonials.', 'slider'),
(3, 'Alkaram Studio Edit', 'Minimal full-width hero, featured edit, editorial split, Instagram feed.', 'centered'),
(4, 'Zeen Edit', 'Category tiles, festive edit, full-width promo, best sellers.', 'split'),
(5, 'Editorial Luxe', 'Statement text banner, featured edit, two-column philosophy, counters.', 'split'),
(6, 'Boutique Minimal', 'Clean category grid, featured pieces, brand strip, testimonials.', 'centered'),
(7, 'Festive Grand', 'Bold text banner, sale edit, full-width promo, feature icons.', 'collage'),
(8, 'Modern Grid', 'Category grid, new arrivals, two-column story, brand strip, counters.', 'collage'),
(9, 'Heritage Weave', 'Brand story, featured slider, text banner, testimonials, Instagram feed.', 'split'),
(10, 'Studio Mono', 'Text banner, trending edit, feature icons, two-column, best sellers.', 'centered');

-- Homepage 5 sections (Editorial Luxe)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(5, 'text_banner', 'The Autumn/Winter Edit', 'Considered pieces for a considered wardrobe.', NULL, NULL, 1),
(5, 'products', 'Featured', 'Handpicked essentials', 'featured', 'grid-3', 2),
(5, 'two_column', 'Our Philosophy', NULL, NULL, NULL, 3),
(5, 'products', 'New Arrivals', 'Fresh drops weekly', 'new_arrival', 'slider', 4),
(5, 'counters', NULL, NULL, NULL, NULL, 5),
(5, 'newsletter', 'Join Our List', 'First access to new editions and private sales.', NULL, NULL, 6);

-- Homepage 6 sections (Boutique Minimal)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(6, 'categories', 'Shop The Edit', NULL, NULL, 'grid-3', 1),
(6, 'products', 'Featured Pieces', 'Considered essentials', 'featured', 'grid-3', 2),
(6, 'brands', 'Our Brands', NULL, NULL, NULL, 3),
(6, 'testimonials', 'Loved By Our Customers', NULL, NULL, NULL, 4),
(6, 'newsletter', 'Stay Updated', 'Subscribe for new arrivals.', NULL, NULL, 5);

-- Homepage 7 sections (Festive Grand)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(7, 'text_banner', 'Festive Season Sale', 'Up to 40% off on select styles', NULL, NULL, 1),
(7, 'products', 'On Sale', 'Limited time only', 'sale', 'grid-4', 2),
(7, 'promo_banner', NULL, NULL, NULL, 'full-width', 3),
(7, 'features', 'Why Shop With Us', NULL, NULL, NULL, 4),
(7, 'products', 'Best Sellers', 'Customer favourites', 'best_seller', 'grid-4', 5),
(7, 'newsletter', 'Never Miss a Sale', 'Get notified about upcoming offers.', NULL, NULL, 6);

-- Homepage 8 sections (Modern Grid)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(8, 'categories', 'Browse Categories', NULL, NULL, 'grid-4', 1),
(8, 'products', 'New Arrivals', 'Just landed', 'new_arrival', 'grid-4', 2),
(8, 'two_column', 'Design Story', NULL, NULL, NULL, 3),
(8, 'brands', 'Featured Brands', NULL, NULL, NULL, 4),
(8, 'counters', NULL, NULL, NULL, NULL, 5),
(8, 'newsletter', 'Stay In The Loop', 'Sign up for updates.', NULL, NULL, 6);

-- Homepage 9 sections (Heritage Weave)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(9, 'brand_story', 'A Legacy of Craft', 'Handwoven traditions reimagined for the modern wardrobe.', NULL, 'split-left', 1),
(9, 'products', 'Featured', 'Curated for you', 'featured', 'slider', 2),
(9, 'text_banner', 'Every Thread Has a Story', 'Discover our heritage collections', NULL, NULL, 3),
(9, 'testimonials', 'Customer Love', NULL, NULL, NULL, 4),
(9, 'instagram', 'Follow Our Journey', '@fashionstore', NULL, NULL, 5);

-- Homepage 10 sections (Studio Mono)
INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, layout, sort_order) VALUES
(10, 'text_banner', 'Studio Mono', 'Monochrome essentials, timeless design.', NULL, NULL, 1),
(10, 'products', 'Trending', 'What everyone is wearing', 'trending', 'grid-3', 2),
(10, 'features', 'The Studio Promise', NULL, NULL, NULL, 3),
(10, 'two_column', 'Behind The Design', NULL, NULL, NULL, 4),
(10, 'products', 'Best Sellers', NULL, 'best_seller', 'grid-3', 5),
(10, 'newsletter', 'Join Studio Mono', 'Subscribe for exclusive drops.', NULL, NULL, 6);

-- Text content for the two-column sections seeded above
INSERT INTO homepage_section_items (section_id, title, subtitle, sort_order) VALUES
((SELECT id FROM homepage_sections WHERE homepage=5 AND section_type='two_column' LIMIT 1), 'Timeless Design', 'We believe in pieces that outlast trends, crafted with intention and worn for years to come.', 1),
((SELECT id FROM homepage_sections WHERE homepage=5 AND section_type='two_column' LIMIT 1), 'Sustainable Craft', 'Every fabric is sourced responsibly, and every stitch is made by skilled artisans across Pakistan.', 2),
((SELECT id FROM homepage_sections WHERE homepage=8 AND section_type='two_column' LIMIT 1), 'Our Design Story', 'Modern silhouettes inspired by traditional Pakistani craftsmanship, reimagined for today.', 1),
((SELECT id FROM homepage_sections WHERE homepage=8 AND section_type='two_column' LIMIT 1), 'Ethically Made', 'Fair wages, safe workshops, and a supply chain we are proud to stand behind.', 2),
((SELECT id FROM homepage_sections WHERE homepage=10 AND section_type='two_column' LIMIT 1), 'The Studio Process', 'From sketch to sample to final piece, every step is refined for quality and fit.', 1),
((SELECT id FROM homepage_sections WHERE homepage=10 AND section_type='two_column' LIMIT 1), 'Considered Details', 'Hand-finished seams, premium trims, and fabric chosen for how it feels, not just how it looks.', 2);
