-- Lookup table for the marketplaces the platform operates.
-- New marketplace types are added here (row + app/config/config.php)
-- rather than by hardcoding a marketplace name anywhere in code.
CREATE TABLE IF NOT EXISTS marketplace_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,      -- artisan | business | official
    name VARCHAR(100) NOT NULL,
    badge_label VARCHAR(50) NOT NULL,      -- e.g. "🏺 Handmade"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
