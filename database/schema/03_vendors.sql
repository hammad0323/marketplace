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
