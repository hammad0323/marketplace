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
