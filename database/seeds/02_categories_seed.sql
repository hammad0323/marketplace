-- Artisan Marketplace categories
INSERT INTO categories (marketplace_type_id, name, slug, sort_order)
SELECT mt.id, c.name, c.slug, c.sort_order
FROM marketplace_types mt
JOIN (
    SELECT 'Handmade Crafts' AS name, 'handmade-crafts' AS slug, 1 AS sort_order UNION ALL
    SELECT 'Paintings', 'paintings', 2 UNION ALL
    SELECT 'Pottery', 'pottery', 3 UNION ALL
    SELECT 'Crochet', 'crochet', 4 UNION ALL
    SELECT 'Handmade Jewelry', 'handmade-jewelry', 5 UNION ALL
    SELECT 'Resin Art', 'resin-art', 6 UNION ALL
    SELECT 'Wooden Crafts', 'wooden-crafts', 7 UNION ALL
    SELECT 'Leather Products', 'leather-products', 8 UNION ALL
    SELECT 'Handmade Clothing', 'handmade-clothing', 9 UNION ALL
    SELECT 'Personalized Gifts', 'personalized-gifts', 10 UNION ALL
    SELECT 'Traditional Arts', 'traditional-arts', 11 UNION ALL
    SELECT 'Home Decor', 'home-decor-artisan', 12 UNION ALL
    SELECT 'Textile Art', 'textile-art', 13 UNION ALL
    SELECT 'Eco-Friendly Handmade Products', 'eco-friendly-handmade-products', 14
) c
WHERE mt.slug = 'artisan'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Business Shops categories
INSERT INTO categories (marketplace_type_id, name, slug, sort_order)
SELECT mt.id, c.name, c.slug, c.sort_order
FROM marketplace_types mt
JOIN (
    SELECT 'Ladies Garments' AS name, 'ladies-garments' AS slug, 1 AS sort_order UNION ALL
    SELECT 'Men''s Fashion', 'mens-fashion', 2 UNION ALL
    SELECT 'Kids Wear', 'kids-wear', 3 UNION ALL
    SELECT 'Shoes', 'shoes', 4 UNION ALL
    SELECT 'Cosmetics', 'cosmetics', 5 UNION ALL
    SELECT 'Electronics', 'electronics', 6 UNION ALL
    SELECT 'Mobile Accessories', 'mobile-accessories', 7 UNION ALL
    SELECT 'Home Appliances', 'home-appliances', 8 UNION ALL
    SELECT 'Kitchen Items', 'kitchen-items', 9 UNION ALL
    SELECT 'Grocery', 'grocery', 10 UNION ALL
    SELECT 'Furniture', 'furniture', 11 UNION ALL
    SELECT 'Stationery', 'stationery', 12 UNION ALL
    SELECT 'Sports', 'sports', 13 UNION ALL
    SELECT 'Toys', 'toys', 14 UNION ALL
    SELECT 'Pet Supplies', 'pet-supplies', 15 UNION ALL
    SELECT 'Beauty Products', 'beauty-products', 16 UNION ALL
    SELECT 'Watches', 'watches', 17 UNION ALL
    SELECT 'Perfumes', 'perfumes', 18 UNION ALL
    SELECT 'Under Garments', 'under-garments', 19 UNION ALL
    SELECT 'Books', 'books', 20 UNION ALL
    SELECT 'Office Supplies', 'office-supplies', 21
) c
WHERE mt.slug = 'business'
ON DUPLICATE KEY UPDATE name = VALUES(name);
