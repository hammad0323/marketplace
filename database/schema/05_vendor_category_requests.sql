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
