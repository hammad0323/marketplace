-- ==============================================================
-- Combined install file for shared hosting / phpMyAdmin.
-- Generated from database/schema/*.sql + database/seeds/*.sql,
-- concatenated in the same order database/migrate.php applies
-- them in. Those folders remain the source of truth (edit them,
-- not this file) — this is just a one-import convenience export
-- for hosts with no SSH/CLI access to run migrate.php directly.
-- Safe to re-run: every statement is CREATE TABLE IF NOT EXISTS /
-- INSERT IGNORE / INSERT ... ON DUPLICATE KEY UPDATE.
-- ==============================================================

-- ---- schema: 01_marketplace_types.sql ----
-- Lookup table for the marketplaces the platform operates.
-- New marketplace types are added here (row + config/settings.php)
-- rather than by hardcoding a marketplace name anywhere in code.
CREATE TABLE IF NOT EXISTS marketplace_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,      -- artisan | business | official
    name VARCHAR(100) NOT NULL,
    badge_label VARCHAR(50) NOT NULL,      -- e.g. "🏺 Handmade"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 02_admin_users.sql ----
CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 03_vendors.sql ----
-- A vendor is either an Artisan (handmade creator) or a Business Shop.
-- The platform's own Official Store is represented as a vendor with
-- vendor_type = 'official' that is pre-approved and has no category
-- approval workflow (it can sell in any approved category directly).
CREATE TABLE IF NOT EXISTS vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,
    store_name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,      -- used in /artisan/{slug} or /business/{slug}
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,

    -- Vendor approval workflow: a vendor cannot list products or receive
    -- orders until an admin approves them (see vendor onboarding spec).
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,   -- business verification badge
    is_featured TINYINT(1) NOT NULL DEFAULT 0,   -- featured artist / featured store badge

    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_vendors_marketplace_type FOREIGN KEY (marketplace_type_id)
        REFERENCES marketplace_types (id),
    CONSTRAINT fk_vendors_approved_by FOREIGN KEY (approved_by)
        REFERENCES admin_users (id) ON DELETE SET NULL,

    INDEX idx_vendors_marketplace_status (marketplace_type_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 04_categories.sql ----
-- Categories are scoped to a marketplace type so Artisan categories
-- (Pottery, Resin Art...) never mix with Business categories
-- (Electronics, Furniture...) in the same dropdown.
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,             -- used in /category/{slug}
    is_active TINYINT(1) NOT NULL DEFAULT 1,-- admin enable/disable switch
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_categories_marketplace_type FOREIGN KEY (marketplace_type_id)
        REFERENCES marketplace_types (id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
        REFERENCES categories (id) ON DELETE CASCADE,

    UNIQUE KEY uq_categories_marketplace_slug (marketplace_type_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 05_vendor_category_requests.sql ----
-- Business Shop vendors must request the categories they want to sell
-- in; an admin approves/rejects each request independently. Only
-- categories with an 'approved' request become selectable when the
-- vendor adds a product (enforced in ProductController/Product model).
CREATE TABLE IF NOT EXISTS vendor_category_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,

    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes VARCHAR(500) NULL,
    usage_limit INT UNSIGNED NULL,      -- optional cap on products vendor may list in this category, NULL = unlimited
    is_enabled TINYINT(1) NOT NULL DEFAULT 1, -- admin can temporarily disable an already-approved category

    decided_at TIMESTAMP NULL,
    decided_by INT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_vcr_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_vcr_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_vcr_decided_by FOREIGN KEY (decided_by)
        REFERENCES admin_users (id) ON DELETE SET NULL,

    UNIQUE KEY uq_vendor_category (vendor_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 06_artisan_profiles.sql ----
-- Extra profile data unique to Artisan Marketplace vendors: the
-- "story behind the brand" content that differentiates the premium
-- artisan experience from a standard retail shop page.
CREATE TABLE IF NOT EXISTS artisan_profiles (
    vendor_id INT UNSIGNED PRIMARY KEY,

    biography TEXT NULL,
    brand_story TEXT NULL,

    workshop_images JSON NULL,       -- array of image URLs
    gallery_images JSON NULL,        -- array of image URLs
    process_media JSON NULL,         -- array of {type: image|video, url}
    achievements JSON NULL,          -- array of strings/objects
    portfolio_items JSON NULL,       -- array of {title, image, description}
    social_links JSON NULL,          -- {instagram, facebook, pinterest, ...}

    featured_collection_title VARCHAR(150) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_artisan_profiles_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 07_business_profiles.sql ----
-- Extra profile data unique to Business Shop vendors: the standard
-- retail-shop information block (hours, policies, delivery, etc.)
CREATE TABLE IF NOT EXISTS business_profiles (
    vendor_id INT UNSIGNED PRIMARY KEY,

    banner_image VARCHAR(255) NULL,
    logo_image VARCHAR(255) NULL,

    business_info TEXT NULL,
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(30) NULL,
    contact_address VARCHAR(255) NULL,

    business_hours JSON NULL,        -- {mon: "9am-6pm", tue: "...", ...}
    shop_policies TEXT NULL,
    delivery_info TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_business_profiles_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 08_products.sql ----
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,

    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,      -- used in /product/{slug}
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    images JSON NULL,                       -- array of image URLs

    is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id),
    CONSTRAINT fk_products_marketplace_type FOREIGN KEY (marketplace_type_id)
        REFERENCES marketplace_types (id),

    INDEX idx_products_marketplace_status (marketplace_type_id, status),
    FULLTEXT KEY ft_products_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 09_customers.sql ----
-- Minimal customer accounts: only what's needed to support "Follow
-- Artist" and store/product ratings within the marketplace
-- architecture. Full customer commerce (orders, rewards, etc.) is out
-- of scope for this PR.
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 10_vendor_follows.sql ----
-- "Follow Artist" feature (also usable for following a business shop).
CREATE TABLE IF NOT EXISTS vendor_follows (
    customer_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (customer_id, vendor_id),
    CONSTRAINT fk_follows_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- schema: 11_vendor_ratings.sql ----
-- "Artist Ratings" / "Store Ratings" feature: one rating per customer
-- per vendor. Average is computed on read (COUNT is low enough at
-- this stage that a denormalized counter isn't needed yet).
CREATE TABLE IF NOT EXISTS vendor_ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,   -- 1-5
    review VARCHAR(1000) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ratings_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_ratings_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5),

    UNIQUE KEY uq_customer_vendor_rating (customer_id, vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- seed: 01_marketplace_types_seed.sql ----
INSERT INTO marketplace_types (slug, name, badge_label) VALUES
    ('artisan',  'Artisan Marketplace', '🏺 Handmade'),
    ('business', 'Business Shops',      '🏪 Business Shop'),
    ('official', 'Official Store',      '⭐ Official Store')
ON DUPLICATE KEY UPDATE name = VALUES(name), badge_label = VALUES(badge_label);

-- ---- seed: 02_categories_seed.sql ----
-- Artisan Marketplace categories
INSERT INTO categories (marketplace_type_id, name, slug, sort_order)
SELECT mt.id, c.name, c.slug, c.sort_order
FROM marketplace_types mt
JOIN (
    SELECT 'Handmade Crafts' AS name, 'handmade-crafts' AS slug, 1 AS sort_order UNION ALL
    SELECT 'Paintings', 'paintings', 2 UNION ALL
    SELECT 'Pottery', 'pottery', 3 UNION ALL
    SELECT 'Crochet', 'crochet', 4 UNION ALL
    SELECT 'Handmade Jewelry', 'handmade-jewelry', 5 UNION ALL
    SELECT 'Resin Art', 'resin-art', 6 UNION ALL
    SELECT 'Wooden Crafts', 'wooden-crafts', 7 UNION ALL
    SELECT 'Leather Products', 'leather-products', 8 UNION ALL
    SELECT 'Handmade Clothing', 'handmade-clothing', 9 UNION ALL
    SELECT 'Personalized Gifts', 'personalized-gifts', 10 UNION ALL
    SELECT 'Traditional Arts', 'traditional-arts', 11 UNION ALL
    SELECT 'Home Decor', 'home-decor-artisan', 12 UNION ALL
    SELECT 'Textile Art', 'textile-art', 13 UNION ALL
    SELECT 'Eco-Friendly Handmade Products', 'eco-friendly-handmade-products', 14
) c
WHERE mt.slug = 'artisan'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Business Shops categories
INSERT INTO categories (marketplace_type_id, name, slug, sort_order)
SELECT mt.id, c.name, c.slug, c.sort_order
FROM marketplace_types mt
JOIN (
    SELECT 'Ladies Garments' AS name, 'ladies-garments' AS slug, 1 AS sort_order UNION ALL
    SELECT 'Men''s Fashion', 'mens-fashion', 2 UNION ALL
    SELECT 'Kids Wear', 'kids-wear', 3 UNION ALL
    SELECT 'Shoes', 'shoes', 4 UNION ALL
    SELECT 'Cosmetics', 'cosmetics', 5 UNION ALL
    SELECT 'Electronics', 'electronics', 6 UNION ALL
    SELECT 'Mobile Accessories', 'mobile-accessories', 7 UNION ALL
    SELECT 'Home Appliances', 'home-appliances', 8 UNION ALL
    SELECT 'Kitchen Items', 'kitchen-items', 9 UNION ALL
    SELECT 'Grocery', 'grocery', 10 UNION ALL
    SELECT 'Furniture', 'furniture', 11 UNION ALL
    SELECT 'Stationery', 'stationery', 12 UNION ALL
    SELECT 'Sports', 'sports', 13 UNION ALL
    SELECT 'Toys', 'toys', 14 UNION ALL
    SELECT 'Pet Supplies', 'pet-supplies', 15 UNION ALL
    SELECT 'Beauty Products', 'beauty-products', 16 UNION ALL
    SELECT 'Watches', 'watches', 17 UNION ALL
    SELECT 'Perfumes', 'perfumes', 18 UNION ALL
    SELECT 'Under Garments', 'under-garments', 19 UNION ALL
    SELECT 'Books', 'books', 20 UNION ALL
    SELECT 'Office Supplies', 'office-supplies', 21
) c
WHERE mt.slug = 'business'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---- seed: 03_admin_seed.sql ----
-- Default admin login: admin@marketplace.test / admin123
-- CHANGE THIS PASSWORD before deploying to any shared environment.
INSERT INTO admin_users (name, email, password_hash) VALUES
    ('Platform Admin', 'admin@marketplace.test', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---- seed: 04_official_store_seed.sql ----
-- The platform's own Official Store: pre-approved, verified, and
-- attached to the 'official' marketplace type. Demo login:
-- store@marketplace.test / admin123 (change before going live).
INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, status, is_verified, is_featured, approved_at)
SELECT mt.id, 'Official Store', 'official-store', 'store@marketplace.test',
       '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW',
       'approved', 1, 1, NOW()
FROM marketplace_types mt
WHERE mt.slug = 'official'
ON DUPLICATE KEY UPDATE store_name = VALUES(store_name);

INSERT INTO business_profiles (vendor_id, business_info, contact_email)
SELECT v.id, 'The official platform-operated store, verified and curated directly by our team.', v.email
FROM vendors v
WHERE v.slug = 'official-store'
ON DUPLICATE KEY UPDATE business_info = VALUES(business_info);

