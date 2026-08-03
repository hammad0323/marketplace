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
