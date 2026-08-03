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
