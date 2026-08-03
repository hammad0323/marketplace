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
