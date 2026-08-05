-- ==============================================================
-- OPTIONAL demo/dummy data — import this AFTER database.sql, on top
-- of the schema + base seed (marketplace types, categories, the admin
-- account, the Official Store). This file exists purely so you can
-- browse the site with something in it: 8 vendors, 20 products, 3
-- customers, addresses, follows, ratings, a populated cart, and 3
-- orders in different statuses (pending / processing / delivered) so
-- every page — storefronts, product pages, cart, checkout, customer
-- "My Orders", the vendor order queue, and the admin order list — has
-- real content to show.
--
-- Every demo account uses the same password as the seed admin
-- account: admin123
--
-- Safe to re-run: vendors/customers/products are upserted by their
-- unique email/slug; the three demo orders are deleted by their fixed
-- order_number and re-inserted each time (which cascades and cleans
-- up their order_items/order_status_history/transactions too), so
-- running this file twice does not create duplicates or duplicate
-- order rows.
-- ==============================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- Vendors — 4 Artisan, 4 Business. All pre-approved so they show up
-- immediately on the storefront (no need to log in as admin first).
-- Password for every demo vendor: admin123
-- ---------------------------------------------------------------

INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, phone, status, is_verified, is_featured, approved_at)
SELECT mt.id, v.store_name, v.slug, v.email,
       '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW',
       v.phone, 'approved', v.is_verified, v.is_featured, NOW()
FROM marketplace_types mt
JOIN (
    SELECT 'artisan' AS mtype, 'Willow & Clay Studio' AS store_name, 'willow-clay-studio' AS slug, 'willow@example.com' AS email, '+92 300 1110001' AS phone, 0 AS is_verified, 1 AS is_featured
    UNION ALL SELECT 'artisan', 'Indigo Thread Co.', 'indigo-thread-co', 'indigo@example.com', '+92 300 1110002', 0, 0
    UNION ALL SELECT 'artisan', 'Cedar & Stone Woodworks', 'cedar-stone-woodworks', 'cedar@example.com', '+92 300 1110003', 0, 0
    UNION ALL SELECT 'artisan', 'Luna Resin Art', 'luna-resin-art', 'luna@example.com', '+92 300 1110004', 0, 1
    UNION ALL SELECT 'business', 'Northline Electronics', 'northline-electronics', 'northline@example.com', '+92 300 1110005', 1, 0
    UNION ALL SELECT 'business', 'Bloom Cosmetics Co.', 'bloom-cosmetics-co', 'bloom@example.com', '+92 300 1110006', 1, 1
    UNION ALL SELECT 'business', 'Urban Kicks', 'urban-kicks', 'urbankicks@example.com', '+92 300 1110007', 0, 0
    UNION ALL SELECT 'business', 'Casa Home & Kitchen', 'casa-home-kitchen', 'casa@example.com', '+92 300 1110008', 1, 0
) v ON v.mtype = mt.slug
ON DUPLICATE KEY UPDATE store_name = VALUES(store_name), status = 'approved',
    is_verified = VALUES(is_verified), is_featured = VALUES(is_featured), approved_at = NOW();

-- Profiles so the store pages have something to show beyond a name.
INSERT INTO artisan_profiles (vendor_id, biography, brand_story, social_links)
SELECT v.id, p.biography, p.brand_story, p.social_links
FROM vendors v
JOIN (
    SELECT 'willow@example.com' AS email,
        'Willow has been throwing pottery for twelve years, working out of a small studio filled with reclaimed barn wood and north-facing light.' AS biography,
        'Every piece starts as a lump of stoneware clay and a Tuesday-morning idea — nothing is designed twice.' AS brand_story,
        '{"instagram": "https://instagram.com/willowclaystudio", "facebook": "", "pinterest": ""}' AS social_links
    UNION ALL SELECT 'indigo@example.com',
        'A textile artist specializing in hand embroidery and naturally-dyed cotton, trained in traditional block printing.',
        'Indigo Thread Co. began as a way to keep a grandmother''s embroidery patterns from being forgotten.',
        '{"instagram": "https://instagram.com/indigothreadco", "facebook": "", "pinterest": ""}'
    UNION ALL SELECT 'cedar@example.com',
        'A woodworker who sources reclaimed walnut and oak from old furniture headed for the landfill.',
        'Cedar & Stone started in a garage with one table saw and a stack of rescued lumber.',
        '{"instagram": "", "facebook": "https://facebook.com/cedarstonewoodworks", "pinterest": ""}'
    UNION ALL SELECT 'luna@example.com',
        'A resin artist inspired by ocean tides and night skies, casting each piece in small batches.',
        'Luna Resin Art turns leftover pigment tests into one-of-a-kind coasters instead of throwing them away.',
        '{"instagram": "https://instagram.com/lunaresinart", "facebook": "", "pinterest": "https://pinterest.com/lunaresinart"}'
) p ON p.email = v.email
ON DUPLICATE KEY UPDATE biography = VALUES(biography), brand_story = VALUES(brand_story), social_links = VALUES(social_links);

INSERT INTO business_profiles (vendor_id, business_info, contact_email, contact_phone, contact_address, business_hours, shop_policies, delivery_info)
SELECT v.id, p.business_info, v.email, v.phone, p.contact_address, p.business_hours, p.shop_policies, p.delivery_info
FROM vendors v
JOIN (
    SELECT 'northline@example.com' AS email,
        'Authorized reseller of headphones, wearables, and mobile accessories, in business since 2016.' AS business_info,
        '14 Tech Plaza, Gulberg III, Lahore' AS contact_address,
        '{"mon": "10am-8pm", "tue": "10am-8pm", "wed": "10am-8pm", "thu": "10am-8pm", "fri": "2pm-8pm", "sat": "10am-8pm", "sun": "Closed"}' AS business_hours,
        '7-day returns on unopened electronics. No returns on opened earphones/headphones for hygiene reasons.' AS shop_policies,
        'Ships nationwide in 2-4 business days.' AS delivery_info
    UNION ALL SELECT 'bloom@example.com',
        'Cruelty-free skincare and cosmetics, formulated with dermatologist-reviewed ingredients.',
        '9 Beauty Row, DHA Phase 5, Karachi',
        '{"mon": "11am-7pm", "tue": "11am-7pm", "wed": "11am-7pm", "thu": "11am-7pm", "fri": "11am-7pm", "sat": "12pm-6pm", "sun": "Closed"}',
        'Unopened products may be returned within 14 days.',
        'Ships nationwide in 3-5 business days.'
    UNION ALL SELECT 'urbankicks@example.com',
        'Everyday footwear for the city — running shoes, canvas sneakers, and boots.',
        '77 Market Street, F-7, Islamabad',
        '{"mon": "10am-9pm", "tue": "10am-9pm", "wed": "10am-9pm", "thu": "10am-9pm", "fri": "2pm-9pm", "sat": "10am-9pm", "sun": "12pm-6pm"}',
        'Exchanges accepted within 10 days with original packaging.',
        'Ships nationwide in 3-6 business days.'
    UNION ALL SELECT 'casa@example.com',
        'Kitchenware and small home appliances for everyday cooking.',
        '3 Homeware Plaza, Model Town, Lahore',
        '{"mon": "10am-8pm", "tue": "10am-8pm", "wed": "10am-8pm", "thu": "10am-8pm", "fri": "2pm-8pm", "sat": "10am-8pm", "sun": "11am-6pm"}',
        '1-year manufacturer warranty on all appliances.',
        'Ships nationwide in 2-5 business days.'
) p ON p.email = v.email
ON DUPLICATE KEY UPDATE business_info = VALUES(business_info), contact_address = VALUES(contact_address),
    business_hours = VALUES(business_hours), shop_policies = VALUES(shop_policies), delivery_info = VALUES(delivery_info);

-- Business vendors need an approved (and enabled) category request
-- before they're allowed to list a product in that category.
INSERT INTO vendor_category_requests (vendor_id, category_id, status, is_enabled, decided_at)
SELECT v.id, c.id, 'approved', 1, NOW()
FROM vendors v
JOIN marketplace_types mt ON mt.slug = 'business'
JOIN categories c ON c.marketplace_type_id = mt.id
JOIN (
    SELECT 'northline@example.com' AS email, 'electronics' AS cat UNION ALL
    SELECT 'northline@example.com', 'mobile-accessories' UNION ALL
    SELECT 'bloom@example.com', 'beauty-products' UNION ALL
    SELECT 'bloom@example.com', 'cosmetics' UNION ALL
    SELECT 'urbankicks@example.com', 'shoes' UNION ALL
    SELECT 'casa@example.com', 'kitchen-items' UNION ALL
    SELECT 'casa@example.com', 'home-appliances' UNION ALL
    SELECT 'store@marketplace.test', 'stationery' UNION ALL
    SELECT 'store@marketplace.test', 'sports'
) req ON req.email = v.email AND req.cat = c.slug
ON DUPLICATE KEY UPDATE status = 'approved', is_enabled = 1, decided_at = NOW();

-- ---------------------------------------------------------------
-- Products — 8 Artisan, 10 Business, 2 Official Store (20 total).
-- No image URLs (kept empty so every card falls back to the built-in
-- local placeholder — no external image host dependency).
-- ---------------------------------------------------------------

INSERT INTO products (vendor_id, category_id, marketplace_type_id, title, slug, description, price, stock_quantity, is_best_seller, is_featured, status)
SELECT v.id, c.id, v.marketplace_type_id, p.title, p.slug, p.description, p.price, p.stock_quantity, p.is_best_seller, p.is_featured, 'published'
FROM (
    SELECT 'willow@example.com' AS email, 'pottery' AS cat, 'artisan' AS mtype,
        'Handthrown Ceramic Vase' AS title, 'handthrown-ceramic-vase' AS slug,
        'A one-of-a-kind stoneware vase, wheel-thrown and finished with a matte glaze.' AS description,
        48.00 AS price, 12 AS stock_quantity, 1 AS is_best_seller, 1 AS is_featured
    UNION ALL SELECT 'willow@example.com', 'pottery', 'artisan',
        'Stoneware Dinner Bowl Set', 'stoneware-dinner-bowl-set',
        'Set of 4 hand-thrown dinner bowls, dishwasher and microwave safe.',
        65.00, 8, 0, 0
    UNION ALL SELECT 'indigo@example.com', 'textile-art', 'artisan',
        'Hand-Embroidered Table Runner', 'hand-embroidered-table-runner',
        'Naturally-dyed cotton table runner with hand embroidery along both edges.',
        34.00, 15, 1, 0
    UNION ALL SELECT 'indigo@example.com', 'handmade-clothing', 'artisan',
        'Woven Cotton Throw Blanket', 'woven-cotton-throw-blanket',
        'Soft handwoven cotton throw, finished with a hand-knotted fringe.',
        52.00, 10, 0, 0
    UNION ALL SELECT 'cedar@example.com', 'wooden-crafts', 'artisan',
        'Live-Edge Walnut Charcuterie Board', 'live-edge-walnut-charcuterie-board',
        'Reclaimed walnut serving board with a natural live edge, food-safe oil finish.',
        58.00, 6, 0, 1
    UNION ALL SELECT 'cedar@example.com', 'wooden-crafts', 'artisan',
        'Hand-Carved Oak Jewelry Box', 'hand-carved-oak-jewelry-box',
        'Solid oak jewelry box with a felt-lined interior and brass hinges.',
        72.00, 4, 0, 0
    UNION ALL SELECT 'luna@example.com', 'resin-art', 'artisan',
        'Ocean Wave Resin Coasters (Set of 4)', 'ocean-wave-resin-coasters-set-of-4',
        'Hand-poured resin coasters with a layered ocean-wave design, cork backing.',
        28.00, 20, 1, 0
    UNION ALL SELECT 'luna@example.com', 'resin-art', 'artisan',
        'Galaxy Resin Wall Clock', 'galaxy-resin-wall-clock',
        'Wall clock cast in deep-blue and gold resin with a galaxy-inspired pattern.',
        45.00, 9, 0, 1
    UNION ALL SELECT 'northline@example.com', 'electronics', 'business',
        'Wireless Noise-Cancelling Headphones', 'wireless-noise-cancelling-headphones',
        'Over-ear wireless headphones with active noise cancellation and 30-hour battery life.',
        89.00, 25, 1, 0
    UNION ALL SELECT 'northline@example.com', 'electronics', 'business',
        'Smart Fitness Watch', 'smart-fitness-watch',
        'Fitness tracker with heart-rate monitoring, sleep tracking, and a 7-day battery.',
        59.00, 30, 0, 0
    UNION ALL SELECT 'northline@example.com', 'mobile-accessories', 'business',
        'Fast-Charge Power Bank 20000mAh', 'fast-charge-power-bank-20000mah',
        'High-capacity power bank with dual USB-C fast charging ports.',
        24.00, 40, 0, 0
    UNION ALL SELECT 'bloom@example.com', 'beauty-products', 'business',
        'Botanical Glow Face Serum', 'botanical-glow-face-serum',
        'Lightweight vitamin C serum with botanical extracts for a natural glow.',
        22.00, 50, 1, 1
    UNION ALL SELECT 'bloom@example.com', 'cosmetics', 'business',
        'Matte Finish Lipstick Set', 'matte-finish-lipstick-set',
        'Set of 3 long-wear matte lipsticks in everyday shades.',
        18.00, 60, 0, 0
    UNION ALL SELECT 'bloom@example.com', 'beauty-products', 'business',
        'Rose Clay Face Mask', 'rose-clay-face-mask',
        'Purifying rose clay mask for a gentle deep-clean, suitable for all skin types.',
        15.00, 45, 0, 0
    UNION ALL SELECT 'urbankicks@example.com', 'shoes', 'business',
        'Classic Leather Running Shoes', 'classic-leather-running-shoes',
        'Everyday running shoes with a genuine leather upper and cushioned sole.',
        65.00, 18, 0, 0
    UNION ALL SELECT 'urbankicks@example.com', 'shoes', 'business',
        'Canvas Slip-On Sneakers', 'canvas-slip-on-sneakers',
        'Lightweight canvas slip-ons, perfect for everyday wear.',
        38.00, 22, 1, 0
    UNION ALL SELECT 'casa@example.com', 'kitchen-items', 'business',
        'Non-Stick Cookware Set (5-Piece)', 'non-stick-cookware-set-5-piece',
        'Five-piece non-stick cookware set with heat-resistant handles.',
        74.00, 14, 0, 1
    UNION ALL SELECT 'casa@example.com', 'home-appliances', 'business',
        'Electric Kettle 1.7L', 'electric-kettle-1-7l',
        'Rapid-boil electric kettle with auto shut-off and a 1.7 liter capacity.',
        26.00, 20, 0, 0
    UNION ALL SELECT 'store@marketplace.test', 'stationery', 'official',
        'Marketplace Signature Tote Bag', 'marketplace-signature-tote-bag',
        'Heavyweight canvas tote bag with the Marketplace logo — an Official Store exclusive.',
        15.00, 100, 0, 1
    UNION ALL SELECT 'store@marketplace.test', 'sports', 'official',
        'Marketplace Branded Water Bottle', 'marketplace-branded-water-bottle',
        'Insulated stainless steel water bottle, keeps drinks cold for 24 hours.',
        12.00, 150, 0, 0
) p
JOIN vendors v ON v.email = p.email
JOIN marketplace_types mt ON mt.slug = p.mtype
JOIN categories c ON c.slug = p.cat AND c.marketplace_type_id = (
    SELECT id FROM marketplace_types WHERE slug = IF(p.mtype = 'official', 'business', p.mtype)
)
ON DUPLICATE KEY UPDATE price = VALUES(price), stock_quantity = VALUES(stock_quantity),
    is_best_seller = VALUES(is_best_seller), is_featured = VALUES(is_featured), status = 'published';

-- ---------------------------------------------------------------
-- Customers — password for every demo customer: admin123
-- ---------------------------------------------------------------

INSERT INTO customers (name, email, password_hash)
VALUES
    ('Ayesha Khan', 'ayesha@example.com', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW'),
    ('Bilal Ahmed', 'bilal@example.com', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW'),
    ('Sara Malik', 'sara@example.com', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- One saved address per customer.
DELETE a FROM addresses a JOIN customers c ON c.id = a.customer_id
WHERE c.email IN ('ayesha@example.com', 'bilal@example.com', 'sara@example.com') AND a.label = 'Home';

INSERT INTO addresses (customer_id, label, full_name, phone, line1, city, state, postal_code, country, is_default)
SELECT c.id, 'Home', a.full_name, a.phone, a.line1, a.city, a.state, a.postal_code, 'Pakistan', 1
FROM customers c
JOIN (
    SELECT 'ayesha@example.com' AS email, 'Ayesha Khan' AS full_name, '+92 300 1234567' AS phone, 'House 12, Street 5' AS line1, 'Lahore' AS city, 'Punjab' AS state, '54000' AS postal_code
    UNION ALL SELECT 'bilal@example.com', 'Bilal Ahmed', '+92 301 2345678', 'Flat 4B, Clifton Block 2', 'Karachi', 'Sindh', '75600'
    UNION ALL SELECT 'sara@example.com', 'Sara Malik', '+92 302 3456789', '22 Mall Road', 'Rawalpindi', 'Punjab', '46000'
) a ON a.email = c.email;

-- Follows and ratings so store pages show followers/reviews.
INSERT IGNORE INTO vendor_follows (customer_id, vendor_id)
SELECT c.id, v.id FROM customers c JOIN vendors v ON 1=1
JOIN (
    SELECT 'ayesha@example.com' AS cemail, 'willow@example.com' AS vemail UNION ALL
    SELECT 'ayesha@example.com', 'luna@example.com' UNION ALL
    SELECT 'bilal@example.com', 'northline@example.com' UNION ALL
    SELECT 'sara@example.com', 'bloom@example.com'
) f ON f.cemail = c.email AND f.vemail = v.email;

INSERT INTO vendor_ratings (customer_id, vendor_id, rating, review)
SELECT c.id, v.id, r.rating, r.review
FROM customers c JOIN vendors v ON 1=1
JOIN (
    SELECT 'ayesha@example.com' AS cemail, 'willow@example.com' AS vemail, 5 AS rating, 'Beautiful craftsmanship, exactly as pictured!' AS review UNION ALL
    SELECT 'bilal@example.com', 'northline@example.com', 4, 'Fast shipping, headphones work great.' UNION ALL
    SELECT 'sara@example.com', 'bloom@example.com', 5, 'My skin has never felt better. Repeat customer!'
) r ON r.cemail = c.email AND r.vemail = v.email
ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review);

-- A populated cart for Ayesha (not checked out) so /cart/view.php has
-- something to show without placing an order first.
INSERT INTO cart_items (customer_id, product_id, quantity)
SELECT c.id, p.id, ci.quantity
FROM customers c JOIN products p ON 1=1
JOIN (
    SELECT 'ayesha@example.com' AS cemail, 'galaxy-resin-wall-clock' AS pslug, 1 AS quantity UNION ALL
    SELECT 'ayesha@example.com', 'rose-clay-face-mask', 2
) ci ON ci.cemail = c.email AND ci.pslug = p.slug
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

-- ---------------------------------------------------------------
-- Orders — one per status you'd want to see: delivered/paid,
-- processing/unpaid (multi-vendor), and a freshly-placed pending one.
-- Deleting by order_number first (cascades to order_items /
-- order_status_history / transactions) makes this block safe to
-- re-run.
-- ---------------------------------------------------------------

DELETE FROM orders WHERE order_number IN ('ORD-20260728-00001', 'ORD-20260803-00002', 'ORD-20260805-00003');

-- Order 1 — Bilal, delivered & paid.
INSERT INTO orders (order_number, customer_id, shipping_name, shipping_phone, shipping_line1, shipping_city, shipping_state, shipping_postal_code, shipping_country, subtotal_amount, shipping_amount, total_amount, status, payment_status, payment_method, placed_at)
SELECT 'ORD-20260728-00001', c.id, 'Bilal Ahmed', '+92 301 2345678', 'Flat 4B, Clifton Block 2', 'Karachi', 'Sindh', '75600', 'Pakistan',
       89.00, 0, 89.00, 'completed', 'paid', 'cod', DATE_SUB(NOW(), INTERVAL 8 DAY)
FROM customers c WHERE c.email = 'bilal@example.com';

INSERT INTO order_items (order_id, vendor_id, product_id, product_title, unit_price, quantity, line_total, status)
SELECT o.id, v.id, p.id, p.title, p.price, 1, p.price, 'delivered'
FROM orders o, vendors v, products p
WHERE o.order_number = 'ORD-20260728-00001' AND v.email = 'northline@example.com' AND p.slug = 'wireless-noise-cancelling-headphones';

INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by_type, created_at)
SELECT o.id, h.old_status, h.new_status, h.note, h.changed_by_type, h.created_at
FROM orders o JOIN (
    SELECT NULL AS old_status, 'pending' AS new_status, 'Order placed' AS note, 'customer' AS changed_by_type, DATE_SUB(NOW(), INTERVAL 8 DAY) AS created_at
    UNION ALL SELECT 'pending', 'processing', NULL, 'vendor', DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL SELECT 'processing', 'completed', NULL, 'vendor', DATE_SUB(NOW(), INTERVAL 5 DAY)
) h ON 1=1
WHERE o.order_number = 'ORD-20260728-00001';

INSERT INTO transactions (order_id, gateway, amount, status)
SELECT o.id, 'cod', 89.00, 'completed' FROM orders o WHERE o.order_number = 'ORD-20260728-00001';

-- Order 2 — Sara, processing & unpaid, items from two different vendors.
INSERT INTO orders (order_number, customer_id, shipping_name, shipping_phone, shipping_line1, shipping_city, shipping_state, shipping_postal_code, shipping_country, subtotal_amount, shipping_amount, total_amount, status, payment_status, payment_method, placed_at)
SELECT 'ORD-20260803-00002', c.id, 'Sara Malik', '+92 302 3456789', '22 Mall Road', 'Rawalpindi', 'Punjab', '46000', 'Pakistan',
       109.00, 0, 109.00, 'processing', 'unpaid', 'cod', DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM customers c WHERE c.email = 'sara@example.com';

INSERT INTO order_items (order_id, vendor_id, product_id, product_title, unit_price, quantity, line_total, status)
SELECT o.id, v.id, p.id, p.title, p.price, 2, p.price * 2, 'processing'
FROM orders o, vendors v, products p
WHERE o.order_number = 'ORD-20260803-00002' AND v.email = 'bloom@example.com' AND p.slug = 'botanical-glow-face-serum';

INSERT INTO order_items (order_id, vendor_id, product_id, product_title, unit_price, quantity, line_total, status)
SELECT o.id, v.id, p.id, p.title, p.price, 1, p.price, 'pending'
FROM orders o, vendors v, products p
WHERE o.order_number = 'ORD-20260803-00002' AND v.email = 'urbankicks@example.com' AND p.slug = 'classic-leather-running-shoes';

INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by_type, created_at)
SELECT o.id, h.old_status, h.new_status, h.note, h.changed_by_type, h.created_at
FROM orders o JOIN (
    SELECT NULL AS old_status, 'pending' AS new_status, 'Order placed' AS note, 'customer' AS changed_by_type, DATE_SUB(NOW(), INTERVAL 3 DAY) AS created_at
    UNION ALL SELECT 'pending', 'processing', NULL, 'vendor', DATE_SUB(NOW(), INTERVAL 2 DAY)
) h ON 1=1
WHERE o.order_number = 'ORD-20260803-00002';

INSERT INTO transactions (order_id, gateway, amount, status)
SELECT o.id, 'cod', 109.00, 'pending' FROM orders o WHERE o.order_number = 'ORD-20260803-00002';

-- Order 3 — Ayesha, just placed, still pending.
INSERT INTO orders (order_number, customer_id, shipping_name, shipping_phone, shipping_line1, shipping_city, shipping_state, shipping_postal_code, shipping_country, subtotal_amount, shipping_amount, total_amount, status, payment_status, payment_method, placed_at)
SELECT 'ORD-20260805-00003', c.id, 'Ayesha Khan', '+92 300 1234567', 'House 12, Street 5', 'Lahore', 'Punjab', '54000', 'Pakistan',
       48.00, 0, 48.00, 'pending', 'unpaid', 'cod', NOW()
FROM customers c WHERE c.email = 'ayesha@example.com';

INSERT INTO order_items (order_id, vendor_id, product_id, product_title, unit_price, quantity, line_total, status)
SELECT o.id, v.id, p.id, p.title, p.price, 1, p.price, 'pending'
FROM orders o, vendors v, products p
WHERE o.order_number = 'ORD-20260805-00003' AND v.email = 'willow@example.com' AND p.slug = 'handthrown-ceramic-vase';

INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by_type, created_at)
SELECT o.id, NULL, 'pending', 'Order placed', 'customer', NOW()
FROM orders o WHERE o.order_number = 'ORD-20260805-00003';

INSERT INTO transactions (order_id, gateway, amount, status)
SELECT o.id, 'cod', 48.00, 'pending' FROM orders o WHERE o.order_number = 'ORD-20260805-00003';
