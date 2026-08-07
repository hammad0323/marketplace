-- ==============================================================
-- Full database install: every CREATE TABLE + all seed data in one
-- file. Import this once (phpMyAdmin: Databases -> your db ->
-- Import -> choose this file -> Go; or `mysql -u user -p db < database.sql`)
-- and the site is ready to use.
--
-- SET NAMES forces the import connection to use utf8mb4 regardless of
-- the client's own default charset — without this, emoji in the seed
-- data (e.g. the marketplace badges) can get corrupted on import by
-- clients that default to a different charset.
--
-- Safe to re-run: every statement is CREATE TABLE IF NOT EXISTS /
-- INSERT IGNORE / INSERT ... ON DUPLICATE KEY UPDATE.
-- ==============================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- Schema
-- ---------------------------------------------------------------

-- ---------------------------------------------------------------
-- Multi-tenancy: this is a shared-database, shared-code SaaS design
-- (not database-per-tenant, which shared/cPanel hosting typically
-- can't support anyway). Every tenant-owned table below carries a
-- tenant_id column scoping its rows to one signed-up business's
-- isolated marketplace instance. A request is resolved to a tenant
-- by subdomain (config/tenant.php + includes/middlewares/tenant.php);
-- every query function reads the current tenant via mp_tenant_id()
-- (see includes/functions/*.php) the same way the rest of this
-- project already reads "the current admin" via mp_current_admin() —
-- an ambient, session/request-derived accessor, not a parameter
-- threaded through every function call.
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subdomain VARCHAR(63) NOT NULL UNIQUE,   -- {subdomain}.APP_BASE_DOMAIN (see config/constants.php)
    business_name VARCHAR(150) NOT NULL,
    plan ENUM('trial', 'basic', 'pro') NOT NULL DEFAULT 'trial',
    status ENUM('trial', 'active', 'suspended') NOT NULL DEFAULT 'trial',
    owner_email VARCHAR(150) NOT NULL,
    trial_ends_at TIMESTAMP NULL,
    suspended_at TIMESTAMP NULL,
    suspended_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenant #1: today's single existing marketplace (all its vendors,
-- customers, products, orders, settings) becomes the first tenant so
-- upgrading an existing pre-multi-tenant install loses no data — every
-- `ADD COLUMN IF NOT EXISTS tenant_id ... DEFAULT 1` migration below
-- silently backfills every already-seeded row onto this exact tenant.
INSERT INTO tenants (id, subdomain, business_name, plan, status, owner_email) VALUES
    (1, 'demo', 'Marketplace', 'pro', 'active', 'admin@marketplace.test')
ON DUPLICATE KEY UPDATE business_name = VALUES(business_name);

-- Platform operator staff — deliberately a separate table from
-- admin_users (which is per-tenant). A platform_admins account manages
-- tenants themselves (approve/suspend signups, cross-tenant reporting
-- in platform/) and never belongs to or logs into any one tenant's own
-- admin panel. See includes/middlewares/platform-auth.php.
CREATE TABLE IF NOT EXISTS platform_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default platform operator login: platform@marketplace.test / admin123
-- CHANGE THIS PASSWORD before deploying to any shared environment.
INSERT INTO platform_admins (name, email, password_hash) VALUES
    ('Platform Operator', 'platform@marketplace.test', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Lookup table for the marketplaces a tenant operates. Now tenant-owned
-- (each signed-up business gets its own artisan/business/official rows,
-- seeded at signup — see mp_seed_default_marketplace_types() — and
-- editable from that tenant's own admin panel) rather than one fixed
-- global lookup shared by every tenant.
CREATE TABLE IF NOT EXISTS marketplace_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    slug VARCHAR(30) NOT NULL,             -- artisan | business | official
    name VARCHAR(100) NOT NULL,
    badge_label VARCHAR(50) NOT NULL,      -- e.g. "🏺 Handmade"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_marketplace_types_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    UNIQUE KEY uq_marketplace_types_tenant_slug (tenant_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrading an existing pre-multi-tenant install: adds tenant_id
-- (backfilling every row onto tenant #1) and widens slug uniqueness
-- from global to per-tenant. The DROP/ADD INDEX pair uses MariaDB's
-- IF [NOT] EXISTS index syntax (this project's tested target — see
-- README) so it's safe to re-run; on a fresh install this whole block
-- is a no-op since the CREATE TABLE above already has the final shape.
ALTER TABLE marketplace_types ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE marketplace_types DROP INDEX IF EXISTS slug;
ALTER TABLE marketplace_types ADD FOREIGN KEY IF NOT EXISTS fk_marketplace_types_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE marketplace_types ADD UNIQUE KEY IF NOT EXISTS uq_marketplace_types_tenant_slug (tenant_id, slug);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_admin_users_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    UNIQUE KEY uq_admin_users_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin' AFTER password_hash;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL AFTER is_active;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE admin_users DROP INDEX IF EXISTS email;
ALTER TABLE admin_users ADD FOREIGN KEY IF NOT EXISTS fk_admin_users_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE admin_users ADD UNIQUE KEY IF NOT EXISTS uq_admin_users_tenant_email (tenant_id, email);

-- A vendor is either an Artisan (handmade creator) or a Business Shop.
-- The platform's own Official Store is represented as a vendor with
-- marketplace_type 'official' that is pre-approved and has no category
-- approval workflow (it can sell in any approved category directly).
CREATE TABLE IF NOT EXISTS vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,
    store_name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL,             -- used in artisan-store.php?slug=... or business-store.php?slug=...
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,

    -- Registration detail collected during the multi-step signup wizard
    -- (vendor/register.php) — optional at signup, editable later from
    -- vendor/profile.php. Payout fields are collected now so the future
    -- wallet/payouts module has something to pay out to without another
    -- registration-flow change.
    tax_id VARCHAR(50) NULL,                -- business registration / tax / CNIC number
    bank_name VARCHAR(100) NULL,
    bank_account_title VARCHAR(150) NULL,
    bank_account_number VARCHAR(50) NULL,
    terms_accepted_at TIMESTAMP NULL,

    -- Email verification (see includes/functions/verification.php) —
    -- a vendor can browse their dashboard unverified, but mp_require_vendor()
    -- surfaces a reminder banner until this is set.
    email_verified_at TIMESTAMP NULL,
    verification_token VARCHAR(64) NULL,
    verification_token_expires_at TIMESTAMP NULL,

    -- Vendor approval workflow: a vendor cannot list products or receive
    -- orders until an admin approves them.
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
    CONSTRAINT fk_vendors_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_vendors_marketplace_status (marketplace_type_id, status),
    UNIQUE KEY uq_vendors_tenant_slug (tenant_id, slug),
    UNIQUE KEY uq_vendors_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-running against a database created before these columns existed
-- adds them without disturbing existing rows.
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS tax_id VARCHAR(50) NULL AFTER phone;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) NULL AFTER tax_id;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS bank_account_title VARCHAR(150) NULL AFTER bank_name;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(50) NULL AFTER bank_account_title;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS terms_accepted_at TIMESTAMP NULL AFTER bank_account_number;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL AFTER terms_accepted_at;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL AFTER email_verified_at;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS verification_token_expires_at TIMESTAMP NULL AFTER verification_token;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE vendors DROP INDEX IF EXISTS slug;
ALTER TABLE vendors DROP INDEX IF EXISTS email;
ALTER TABLE vendors ADD FOREIGN KEY IF NOT EXISTS fk_vendors_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE vendors ADD UNIQUE KEY IF NOT EXISTS uq_vendors_tenant_slug (tenant_id, slug);
ALTER TABLE vendors ADD UNIQUE KEY IF NOT EXISTS uq_vendors_tenant_email (tenant_id, email);

-- Categories are scoped to a marketplace type so Artisan categories
-- (Pottery, Resin Art...) never mix with Business categories
-- (Electronics, Furniture...) in the same dropdown.
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,             -- used in category.php?slug=...
    is_active TINYINT(1) NOT NULL DEFAULT 1,-- admin enable/disable switch
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_categories_marketplace_type FOREIGN KEY (marketplace_type_id)
        REFERENCES marketplace_types (id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
        REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_categories_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_categories_marketplace_type (marketplace_type_id),
    UNIQUE KEY uq_categories_tenant_marketplace_slug (tenant_id, marketplace_type_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrading an existing pre-multi-tenant install: the OLD composite
-- unique key (uq_categories_marketplace_slug, leftmost column
-- marketplace_type_id) is likely the sole supporting index for
-- fk_categories_marketplace_type on a pre-existing table, so a plain
-- index on that column is added first to guarantee the FK keeps a
-- supporting index once the old unique key is dropped. The new unique
-- key is deliberately given a NEW name (not reused from the old one)
-- so this whole block is a true no-op on a fresh install, where the
-- old name never existed in the first place.
ALTER TABLE categories ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_categories_marketplace_type (marketplace_type_id);
ALTER TABLE categories ADD FOREIGN KEY IF NOT EXISTS fk_categories_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE categories DROP INDEX IF EXISTS uq_categories_marketplace_slug;
ALTER TABLE categories ADD UNIQUE KEY IF NOT EXISTS uq_categories_tenant_marketplace_slug (tenant_id, marketplace_type_id, slug);

-- Business Shop vendors must request the categories they want to sell
-- in; an admin approves/rejects each request independently. Only
-- categories with an 'approved' request become selectable when the
-- vendor adds a product.
CREATE TABLE IF NOT EXISTS vendor_category_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
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
    CONSTRAINT fk_vcr_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    UNIQUE KEY uq_vendor_category (vendor_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE vendor_category_requests ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE vendor_category_requests ADD FOREIGN KEY IF NOT EXISTS fk_vcr_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Extra profile data unique to Artisan Marketplace vendors: the
-- "story behind the brand" content that differentiates the premium
-- artisan experience from a standard retail shop page.
CREATE TABLE IF NOT EXISTS artisan_profiles (
    vendor_id INT UNSIGNED PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,

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
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_artisan_profiles_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE artisan_profiles ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER vendor_id;
ALTER TABLE artisan_profiles ADD FOREIGN KEY IF NOT EXISTS fk_artisan_profiles_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Extra profile data unique to Business Shop vendors: the standard
-- retail-shop information block (hours, policies, delivery, etc.)
CREATE TABLE IF NOT EXISTS business_profiles (
    vendor_id INT UNSIGNED PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,

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
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_business_profiles_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE business_profiles ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER vendor_id;
ALTER TABLE business_profiles ADD FOREIGN KEY IF NOT EXISTS fk_business_profiles_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    vendor_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    marketplace_type_id TINYINT UNSIGNED NOT NULL,

    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,             -- used in product.php?slug=...
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    images JSON NULL,                       -- array of image URLs

    sku VARCHAR(64) NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,

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
    CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_products_marketplace_status (marketplace_type_id, status),
    FULLTEXT KEY ft_products_search (title, description),
    UNIQUE KEY uq_products_tenant_slug (tenant_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-running this file against a database created before stock/SKU
-- tracking existed adds the columns without disturbing existing rows
-- (MySQL 8.0.29+ / MariaDB 10.0.2+ both support ADD COLUMN IF NOT EXISTS).
ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(64) NULL AFTER images;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER sku;
ALTER TABLE products ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE products DROP INDEX IF EXISTS slug;
ALTER TABLE products ADD FOREIGN KEY IF NOT EXISTS fk_products_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE products ADD UNIQUE KEY IF NOT EXISTS uq_products_tenant_slug (tenant_id, slug);

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,

    email_verified_at TIMESTAMP NULL,
    verification_token VARCHAR(64) NULL,
    verification_token_expires_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_customers_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    UNIQUE KEY uq_customers_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE customers ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER password_hash;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL AFTER phone;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL AFTER email_verified_at;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS verification_token_expires_at TIMESTAMP NULL AFTER verification_token;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE customers DROP INDEX IF EXISTS email;
ALTER TABLE customers ADD FOREIGN KEY IF NOT EXISTS fk_customers_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE customers ADD UNIQUE KEY IF NOT EXISTS uq_customers_tenant_email (tenant_id, email);

-- "Follow Artist" feature (also usable for following a business shop).
CREATE TABLE IF NOT EXISTS vendor_follows (
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (customer_id, vendor_id),
    CONSTRAINT fk_follows_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE vendor_follows ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 FIRST;
ALTER TABLE vendor_follows ADD FOREIGN KEY IF NOT EXISTS fk_follows_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- "Artist Ratings" / "Store Ratings" feature: one rating per customer
-- per vendor. Average is computed on read.
CREATE TABLE IF NOT EXISTS vendor_ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,   -- 1-5
    review VARCHAR(1000) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ratings_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_ratings_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id) ON DELETE CASCADE,
    CONSTRAINT fk_ratings_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5),

    UNIQUE KEY uq_customer_vendor_rating (customer_id, vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE vendor_ratings ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE vendor_ratings ADD FOREIGN KEY IF NOT EXISTS fk_ratings_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------
-- Commerce: addresses, cart, orders, order items, status history,
-- transactions. An order can contain products from several vendors
-- (one checkout, one payment) — order_items carries its own vendor_id
-- and fulfillment status so each vendor manages only their own items,
-- while orders/transactions track the checkout and payment as a whole.
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NOT NULL,

    label VARCHAR(50) NULL,             -- "Home", "Office", ...
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    line1 VARCHAR(255) NOT NULL,
    line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Pakistan',
    is_default TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_addresses_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_addresses_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE addresses ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE addresses ADD FOREIGN KEY IF NOT EXISTS fk_addresses_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Persisted cart so it survives across sessions/devices — one row per
-- (customer, product), quantity incremented on repeat "Add to Cart".
CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cart_items_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    UNIQUE KEY uq_cart_customer_product (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE cart_items ADD FOREIGN KEY IF NOT EXISTS fk_cart_items_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- One order per checkout. Shipping details are snapshotted onto the
-- order itself (not just linked via address_id) so editing or deleting
-- a saved address later never rewrites order history.
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    order_number VARCHAR(30) NOT NULL,          -- e.g. ORD-20260805-00001 (globally unique by construction — see note below)
    customer_id INT UNSIGNED NOT NULL,
    address_id INT UNSIGNED NULL,

    shipping_name VARCHAR(150) NOT NULL,
    shipping_phone VARCHAR(30) NOT NULL,
    shipping_line1 VARCHAR(255) NOT NULL,
    shipping_line2 VARCHAR(255) NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_state VARCHAR(100) NULL,
    shipping_postal_code VARCHAR(20) NULL,
    shipping_country VARCHAR(100) NOT NULL,

    subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',

    -- Order-level aggregate status; each order_item also carries its
    -- own per-vendor fulfillment status (see order_items below).
    status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('cod', 'manual', 'stripe', 'paypal') NOT NULL DEFAULT 'cod',

    placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id),
    CONSTRAINT fk_orders_address FOREIGN KEY (address_id)
        REFERENCES addresses (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_orders_customer (customer_id, placed_at),
    INDEX idx_orders_status (status),
    UNIQUE KEY uq_orders_tenant_number (tenant_id, order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- order_number is built from `id` (see mp_generate_order_number() in
-- includes/functions/orders.php), and `id` stays one single global
-- auto-increment column in this shared-database design — so the
-- generated string is already globally unique by construction, not
-- just per-tenant. The composite UNIQUE below is added purely for
-- defense-in-depth/convention consistency with every other per-tenant
-- unique key in this file, not to fix an active collision risk.
ALTER TABLE orders ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE orders DROP INDEX IF EXISTS order_number;
ALTER TABLE orders ADD FOREIGN KEY IF NOT EXISTS fk_orders_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE orders ADD UNIQUE KEY IF NOT EXISTS uq_orders_tenant_number (tenant_id, order_number);

-- Line items. product_title/unit_price are snapshotted at purchase
-- time so a later product edit or deletion never rewrites what a
-- customer was actually charged.
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    order_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,

    product_title VARCHAR(200) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,

    -- Per-vendor fulfillment status: each vendor updates only the
    -- items that belong to them within a shared multi-vendor order.
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_vendor FOREIGN KEY (vendor_id)
        REFERENCES vendors (id),
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_items_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_vendor_status (vendor_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE order_items ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE order_items ADD FOREIGN KEY IF NOT EXISTS fk_order_items_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Audit trail of order-level status changes — who changed it and when,
-- independent of the current row's mutable status column.
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by_type ENUM('customer', 'vendor', 'admin', 'system') NOT NULL DEFAULT 'system',
    changed_by_id INT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_order_status_history_order (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE order_status_history ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE order_status_history ADD FOREIGN KEY IF NOT EXISTS fk_order_status_history_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Payment attempts against an order. gateway is deliberately an ENUM
-- that already includes 'stripe'/'paypal' so wiring up real payment
-- processing later (per the project's Stripe/PayPal "future ready"
-- requirement) is a new row shape, not a schema change. 'cod'/'manual'
-- are what this build actually uses today.
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    order_id INT UNSIGNED NOT NULL,
    gateway ENUM('cod', 'manual', 'stripe', 'paypal') NOT NULL DEFAULT 'cod',
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    gateway_reference VARCHAR(150) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_transactions_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_transactions_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE transactions ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE transactions ADD FOREIGN KEY IF NOT EXISTS fk_transactions_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------
-- Platform administration: dynamic settings, audit trail, homepage
-- CMS banners. This is what makes the admin panel actually "control
-- the site" rather than just moderate it — see includes/functions/
-- settings.php, activity.php, cms.php.
-- ---------------------------------------------------------------

-- Site-wide configuration as key/value rows instead of hardcoded PHP
-- constants, so admin/settings.php can edit them without touching
-- code or redeploying. setting_type tells the settings helper how to
-- cast setting_value back to a PHP value on read.
-- tenant_id is part of the primary key (not a separate unique index)
-- so the same setting_key can exist independently per tenant — every
-- tenant gets its own site_name/currency/commission-rate/etc rows.
CREATE TABLE IF NOT EXISTS settings (
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (tenant_id, setting_key),
    CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrading an existing pre-multi-tenant install: widens the primary
-- key from (setting_key) alone to (tenant_id, setting_key). This pair
-- of statements is naturally idempotent — DROP PRIMARY KEY always
-- targets "the" primary key regardless of its current shape, so
-- re-running this is safe even after it has already been applied.
ALTER TABLE settings ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 FIRST;
ALTER TABLE settings DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, setting_key);
ALTER TABLE settings ADD FOREIGN KEY IF NOT EXISTS fk_settings_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Generic audit trail — every admin/vendor/platform action that
-- changes state writes one row here (see mp_log_activity()),
-- independent of the narrower order_status_history above. tenant_id
-- is nullable because platform-level events (e.g. a platform admin
-- suspending a tenant) have no single owning tenant.
CREATE TABLE IF NOT EXISTS activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL DEFAULT 1,
    actor_type ENUM('admin', 'vendor', 'customer', 'platform_admin', 'system') NOT NULL,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,          -- e.g. "vendor.approved", "product.deleted"
    entity_type VARCHAR(50) NULL,          -- e.g. "vendor", "product", "order"
    entity_id INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_log_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_activity_log_actor (actor_type, actor_id),
    INDEX idx_activity_log_entity (entity_type, entity_id),
    INDEX idx_activity_log_created (created_at),
    INDEX idx_activity_log_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NULL DEFAULT 1 AFTER id;
ALTER TABLE activity_log MODIFY COLUMN actor_type ENUM('admin', 'vendor', 'customer', 'platform_admin', 'system') NOT NULL;
ALTER TABLE activity_log ADD FOREIGN KEY IF NOT EXISTS fk_activity_log_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;
ALTER TABLE activity_log ADD INDEX IF NOT EXISTS idx_activity_log_tenant (tenant_id);

-- Homepage/marketplace hero content, editable from admin/banners.php.
-- marketplace_type_id NULL = shown on the main homepage; set it to
-- show a banner on one marketplace's own landing page instead.
CREATE TABLE IF NOT EXISTS cms_banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    marketplace_type_id TINYINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(500) NULL,
    cta_label VARCHAR(60) NULL,
    cta_url VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cms_banners_marketplace_type FOREIGN KEY (marketplace_type_id)
        REFERENCES marketplace_types (id) ON DELETE CASCADE,
    CONSTRAINT fk_cms_banners_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants (id) ON DELETE CASCADE,

    INDEX idx_cms_banners_marketplace (marketplace_type_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cms_banners ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE cms_banners ADD FOREIGN KEY IF NOT EXISTS fk_cms_banners_tenant (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------

INSERT INTO marketplace_types (slug, name, badge_label) VALUES
    ('artisan',  'Artisan Marketplace', '🏺 Handmade'),
    ('business', 'Business Shops',      '🏪 Business Shop'),
    ('official', 'Official Store',      '⭐ Official Store')
ON DUPLICATE KEY UPDATE name = VALUES(name), badge_label = VALUES(badge_label);

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

-- Default admin login: admin@marketplace.test / admin123
-- CHANGE THIS PASSWORD before deploying to any shared environment.
INSERT INTO admin_users (name, email, password_hash, role) VALUES
    ('Platform Admin', 'admin@marketplace.test', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW', 'super_admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role);

-- The platform's own Official Store: pre-approved, verified, and
-- attached to the 'official' marketplace type. Demo login:
-- store@marketplace.test / admin123 (change before going live).
INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, status, is_verified, is_featured, approved_at, email_verified_at, terms_accepted_at)
SELECT mt.id, 'Official Store', 'official-store', 'store@marketplace.test',
       '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW',
       'approved', 1, 1, NOW(), NOW(), NOW()
FROM marketplace_types mt
WHERE mt.slug = 'official'
ON DUPLICATE KEY UPDATE store_name = VALUES(store_name);

INSERT INTO business_profiles (vendor_id, business_info, contact_email)
SELECT v.id, 'The official platform-operated store, verified and curated directly by our team.', v.email
FROM vendors v
WHERE v.slug = 'official-store'
ON DUPLICATE KEY UPDATE business_info = VALUES(business_info);

-- Default site settings — every value here is editable from
-- admin/settings.php; these rows are just sane starting values.
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
    ('site_name', 'Marketplace', 'string'),
    ('site_tagline', 'Handmade Treasures & Trusted Retail, All in One Place', 'string'),
    ('contact_email', 'support@marketplace.test', 'string'),
    ('contact_phone', '+92 300 0000000', 'string'),
    ('currency_code', 'USD', 'string'),
    ('currency_symbol', '$', 'string'),
    ('commission_rate_artisan', '10', 'number'),
    ('commission_rate_business', '12', 'number'),
    ('social_facebook', '', 'string'),
    ('social_instagram', '', 'string'),
    ('social_twitter', '', 'string'),
    ('maintenance_mode', '0', 'boolean'),
    ('vendor_registration_enabled', '1', 'boolean'),
    ('footer_about_text', 'A multi-vendor marketplace connecting independent artisans and trusted retail businesses with customers in one place.', 'string')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Default homepage banner (shown when no admin-authored banner exists
-- for a given marketplace) so cms_banners has at least one real row
-- to edit instead of an empty admin/banners.php on first install.
-- Guarded by WHERE NOT EXISTS (cms_banners.id has no natural unique
-- key to upsert against) so re-running this file doesn't duplicate it.
INSERT INTO cms_banners (tenant_id, marketplace_type_id, title, subtitle, cta_label, cta_url, sort_order, is_active)
SELECT 1, NULL, 'Handmade Treasures & Trusted Retail, All in One Place',
       'Discover one-of-a-kind creations from independent artisans, or shop everyday essentials from verified business owners — start exploring below.',
       'Explore Artisan Marketplace', '/artisan/index.php', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM cms_banners WHERE marketplace_type_id IS NULL AND tenant_id = 1);
