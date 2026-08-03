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
