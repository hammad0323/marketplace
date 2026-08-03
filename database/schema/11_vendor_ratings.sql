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
