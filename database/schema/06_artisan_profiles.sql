-- Extra profile data unique to Artisan Marketplace vendors: the
-- "story behind the brand" content that differentiates the premium
-- artisan experience from a standard retail shop page.
CREATE TABLE IF NOT EXISTS artisan_profiles (
    vendor_id INT UNSIGNED PRIMARY KEY,

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
        REFERENCES vendors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
