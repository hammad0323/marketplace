INSERT INTO marketplace_types (slug, name, badge_label) VALUES
    ('artisan',  'Artisan Marketplace', '🏺 Handmade'),
    ('business', 'Business Shops',      '🏪 Business Shop'),
    ('official', 'Official Store',      '⭐ Official Store')
ON DUPLICATE KEY UPDATE name = VALUES(name), badge_label = VALUES(badge_label);
