<?php
/**
 * Seeds the starter data (settings, categories, brands, products, banners,
 * homepage sections, promotions, coupons, delivery zones, admin users, sample
 * orders). Developers run it on an EMPTY schema via tools/build-database.sh,
 * which then exports database/fahim_store.sql. Shop owners never need it -
 * they just import database/fahim_store.sql.
 */
if (PHP_SAPI !== 'cli') exit('CLI only');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
require dirname(__DIR__) . '/includes/bootstrap.php';
require __DIR__ . '/sample-images.php';

$root = FS_ROOT;
@mkdir("$root/uploads/products/sample", 0775, true);
@mkdir("$root/assets/images/categories", 0775, true);

function out($m) { echo $m . PHP_EOL; }

/* ---------------------------------------------------------------
 * SETTINGS
 * --------------------------------------------------------------- */
$settings = [
    'site_name' => 'Fahim Store',
    'site_tagline' => 'School Books, Stationery & Monthly Grocery',
    'meta_description' => 'Fahim Store - buy school books, stationery, groceries and monthly rashan online in Pakistan. Home delivery and store pickup, cash on delivery.',
    'currency_symbol' => 'Rs.',
    'store_phone' => '0300-1234567',
    'store_whatsapp' => '03001234567',
    'store_email' => 'info@fahimstore.pk',
    'store_address' => 'Shop # 12, Main Market, Gulshan-e-Iqbal Block 13',
    'store_city' => 'Karachi',
    'store_lat' => '24.9180',
    'store_lng' => '67.0971',
    'opening_hours' => 'Mon - Sun: 9:00 AM - 10:00 PM',
    'opening_hours_schema' => 'Mo-Su 09:00-22:00',
    'announcement' => 'Free home delivery on orders above Rs. 5,000 · Cash on Delivery available',
    'facebook_url' => 'https://www.facebook.com/',
    'instagram_url' => 'https://www.instagram.com/',
    'youtube_url' => '',
    'tiktok_url' => '',
    'twitter_handle' => '',
    'stock_policy' => 'placed',
    'order_prefix' => 'FS',
    'min_order_amount' => '0',
    'delivery_enabled' => '1',
    'default_delivery_fee' => '150',
    'free_delivery_over' => '5000',
    'delivery_note' => 'Orders placed before 4 PM are delivered the same day in most areas.',
    'pickup_enabled' => '1',
    'pickup_address' => 'Fahim Store, Shop # 12, Main Market, Gulshan-e-Iqbal Block 13, Karachi',
    'pickup_open' => '10:00',
    'pickup_close' => '21:00',
    'pickup_interval' => '60',
    'pickup_lead_hours' => '2',
    'pickup_days_ahead' => '5',
    'pickup_closed_days' => '',
    'pickup_instructions' => 'Show your order number at the counter. Your rashan will be packed and ready.',
    'notify_email' => 'orders@fahimstore.pk',
    'notify_whatsapp' => '',
    'whatsapp_enabled' => '0',
    'whatsapp_customer_updates' => '0',
    'require_payment_reference' => '0',
    'popular_searches' => 'atta,class 5 books,cooking oil,register,surf,quran,geometry box,tea',
    'products_per_page' => '24',
    'rashan_title' => 'Build Your Monthly Rashan',
    'rashan_intro' => 'Pick your monthly grocery items, set a budget and we will pack everything for home delivery or store pickup.',
    'rashan_default_budget' => '30000',
    'pos_receipt_footer' => 'Thank you for shopping at Fahim Store! Exchange within 7 days with receipt.',
    'invoice_footer' => 'Thank you for your business!',
    'google_verification' => '',
];
foreach ($settings as $k => $v) setting_set($k, $v);
out('settings ok');

/* ---------------------------------------------------------------
 * ADMIN ROLES / USERS
 * --------------------------------------------------------------- */
$superRole = db_insert("INSERT INTO admin_roles (name, description, is_super) VALUES ('Super Admin', 'Full access to everything', 1)");
$mgrRole = db_insert("INSERT INTO admin_roles (name, description) VALUES ('Store Manager', 'Everything except admin users and settings')");
$cashRole = db_insert("INSERT INTO admin_roles (name, description) VALUES ('Cashier', 'POS, orders and customers')");
$invRole = db_insert("INSERT INTO admin_roles (name, description) VALUES ('Inventory Staff', 'Products, stock, purchases')");
foreach (array_keys(admin_permission_list()) as $p) {
    if (!in_array($p, ['admins', 'settings'], true)) db_exec('INSERT INTO admin_permissions (role_id, permission) VALUES (?, ?)', [$mgrRole, $p]);
}
foreach (['dashboard', 'pos', 'orders', 'customers'] as $p) db_exec('INSERT INTO admin_permissions (role_id, permission) VALUES (?, ?)', [$cashRole, $p]);
foreach (['dashboard', 'products', 'categories', 'inventory', 'import'] as $p) db_exec('INSERT INTO admin_permissions (role_id, permission) VALUES (?, ?)', [$invRole, $p]);
$adminId = db_insert('INSERT INTO admins (role_id, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
    [$superRole, 'Fahim (Owner)', 'admin@fahimstore.pk', '03001234567', password_hash('Admin@123', PASSWORD_DEFAULT)]);
db_insert('INSERT INTO admins (role_id, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
    [$cashRole, 'Counter Cashier', 'cashier@fahimstore.pk', '03007654321', password_hash('Cashier@123', PASSWORD_DEFAULT)]);
$_SESSION = [];
out('admins ok');

/* ---------------------------------------------------------------
 * CATEGORY IMAGES
 * --------------------------------------------------------------- */
$catImgs = [
    'books' => svg_cat_books(), 'stationery' => svg_cat_stationery(), 'groceries' => svg_cat_grocery(), 'general' => svg_cat_general(),
    'cleaning' => svg_cat_clean(), 'registers' => svg_cat_register(),
];
foreach ($catImgs as $k => $svg) file_put_contents("$root/assets/images/categories/$k.svg", $svg);

/* ---------------------------------------------------------------
 * CATEGORIES (Main -> Sub -> Child)
 * --------------------------------------------------------------- */
$catIds = [];
function add_cat(string $name, ?int $parent, int $sort, array $extra = []): int
{
    global $catIds;
    $slug = unique_slug('categories', $extra['slug'] ?? $name);
    $id = db_insert_row('categories', [
        'parent_id' => $parent, 'name' => $name, 'name_urdu' => $extra['urdu'] ?? null, 'slug' => $slug,
        'description' => $extra['desc'] ?? null, 'image' => $extra['image'] ?? null, 'icon' => $extra['icon'] ?? null,
        'sort_order' => $sort, 'show_on_home' => $extra['home'] ?? 0, 'image_alt' => $name,
    ]);
    categories_all(true);
    $catIds[$extra['key'] ?? $slug] = $id;
    return $id;
}

$books = add_cat('Books', null, 1, ['icon' => 'bi-book', 'image' => 'assets/images/categories/books.svg', 'home' => 1, 'urdu' => 'کتابیں',
    'desc' => 'School text books, Oxford & Cambridge books, Islamic books, general knowledge and story books for every class.']);
$school = add_cat('School Books', $books, 1, ['icon' => 'bi-mortarboard', 'image' => 'assets/images/categories/books.svg', 'desc' => 'Text books for Class 1 to Intermediate.']);
$classIds = [];
foreach (['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Intermediate'] as $i => $cl) {
    $classIds[$cl] = add_cat($cl, $school, $i + 1, ['image' => 'assets/images/categories/books.svg', 'desc' => "All school books for $cl."]);
}
$oxford = add_cat('Oxford Books', $books, 2, ['image' => 'assets/images/categories/books.svg']);
$cambridge = add_cat('Cambridge Books', $books, 3, ['image' => 'assets/images/categories/books.svg']);
$islamic = add_cat('Islamic Books', $books, 4, ['image' => 'assets/images/categories/books.svg', 'urdu' => 'اسلامی کتابیں']);
$gk = add_cat('General Knowledge', $books, 5, ['image' => 'assets/images/categories/books.svg']);
$story = add_cat('Story Books', $books, 6, ['image' => 'assets/images/categories/books.svg']);

$stat = add_cat('Stationery', null, 2, ['icon' => 'bi-pencil', 'image' => 'assets/images/categories/stationery.svg', 'home' => 1, 'urdu' => 'اسٹیشنری',
    'desc' => 'Pens, pencils, notebooks, registers, art supplies, geometry boxes and office stationery.']);
$pens = add_cat('Pens & Pencils', $stat, 1, ['image' => 'assets/images/categories/stationery.svg']);
$registers = add_cat('Notebooks & Registers', $stat, 2, ['image' => 'assets/images/categories/registers.svg', 'home' => 1, 'slug' => 'registers-copies']);
$art = add_cat('Art & Craft', $stat, 3, ['image' => 'assets/images/categories/stationery.svg']);
$schoolAcc = add_cat('School Accessories', $stat, 4, ['image' => 'assets/images/categories/general.svg']);
$office = add_cat('Office Supplies', $stat, 5, ['image' => 'assets/images/categories/stationery.svg']);
$geometry = add_cat('Geometry Boxes', $stat, 6, ['image' => 'assets/images/categories/stationery.svg']);
$files = add_cat('Files & Folders', $stat, 7, ['image' => 'assets/images/categories/registers.svg']);

$groc = add_cat('Groceries', null, 3, ['icon' => 'bi-basket', 'image' => 'assets/images/categories/groceries.svg', 'home' => 1, 'urdu' => 'راشن',
    'desc' => 'Atta, rice, cooking oil, ghee, pulses, sugar, spices, tea, snacks, dairy and household essentials - your complete monthly rashan.']);
$g = [];
$gList = [
    ['Flour & Rice', 'آٹا اور چاول'], ['Cooking Oil & Ghee', 'تیل اور گھی'], ['Pulses & Lentils', 'دالیں'], ['Sugar & Salt', 'چینی اور نمک'],
    ['Spices', 'مصالحے'], ['Tea & Beverages', 'چائے'], ['Snacks & Biscuits', 'بسکٹ'], ['Dairy Products', 'دودھ'],
    ['Cleaning Products', 'صفائی'], ['Personal Care', 'ذاتی نگہداشت'], ['Household Essentials', 'گھریلو اشیاء'],
];
foreach ($gList as $i => [$n, $u]) {
    $img = in_array($n, ['Cleaning Products', 'Personal Care', 'Household Essentials'], true) ? 'assets/images/categories/cleaning.svg' : 'assets/images/categories/groceries.svg';
    $g[$n] = add_cat($n, $groc, $i + 1, ['image' => $img, 'urdu' => $u, 'home' => $n === 'Cleaning Products' ? 1 : 0]);
}

$general = add_cat('General Items', null, 4, ['icon' => 'bi-shop', 'image' => 'assets/images/categories/general.svg', 'home' => 1,
    'desc' => 'School bags, lunch boxes, water bottles, batteries, toys and gift items.']);
$bags = add_cat('Bags & Lunch Boxes', $general, 1, ['image' => 'assets/images/categories/general.svg']);
$elec = add_cat('Batteries & Electrical', $general, 2, ['image' => 'assets/images/categories/general.svg']);
$toys = add_cat('Toys & Games', $general, 3, ['image' => 'assets/images/categories/general.svg']);
out('categories ok: ' . count(categories_all()));

/* ---------------------------------------------------------------
 * BRANDS
 * --------------------------------------------------------------- */
$brandIds = [];
$brandList = ['Oxford University Press', 'Cambridge University Press', 'Sindh Text Book Board', 'Punjab Text Book Board', 'Paramount Books', 'Taj Company',
    'Dollar', 'Piano', 'Picasso', 'Faber-Castell', 'Deer', 'Sunridge', 'Guard', 'Dalda', 'Habib', 'Tapal', 'Lipton', 'National Foods', 'Shan',
    'Peek Freans', 'LU', 'Olpers', 'Nestle', 'Surf Excel', 'Ariel', 'Lux', 'Lifebuoy', 'Max', 'Rose Petal', 'Mitchells', 'Energizer', 'Lion'];
foreach ($brandList as $i => $b) {
    $brandIds[$b] = db_insert('INSERT INTO brands (name, slug, sort_order, is_featured) VALUES (?, ?, ?, ?)', [$b, slugify($b), $i, $i < 32 && in_array($b, ['Oxford University Press', 'Dollar', 'Picasso', 'Sunridge', 'Dalda', 'Tapal', 'National Foods', 'Shan', 'Peek Freans', 'Olpers', 'Surf Excel', 'Lux'], true) ? 1 : 0]);
}
out('brands ok');

/* ---------------------------------------------------------------
 * PRODUCTS
 * --------------------------------------------------------------- */
$skuN = 1000;
$productIds = [];
/**
 * $p: name, cats[], brand, price, sale, cost, stock, unit, unit_value, svg, urdu, isbn, keywords, tags[], featured, best, rashan, desc, variations[[name, price, sale, stock]]
 */
function add_product(array $p): int
{
    global $skuN, $brandIds, $root, $productIds;
    $skuN++;
    $slug = unique_slug('products', $p['name']);
    $sku = $p['sku'] ?? ('FS-' . $skuN);
    $hasVar = !empty($p['variations']);
    $id = db_insert_row('products', [
        'name' => $p['name'], 'name_urdu' => $p['urdu'] ?? null, 'slug' => $slug, 'sku' => $sku,
        'barcode' => $p['barcode'] ?? ('896' . str_pad((string) (1000000000 + $skuN), 10, '0', STR_PAD_LEFT)),
        'isbn' => $p['isbn'] ?? null, 'brand_id' => isset($p['brand']) ? $brandIds[$p['brand']] : null,
        'primary_category_id' => $p['cats'][0], 'short_description' => $p['short'] ?? null,
        'description' => $p['desc'] ?? ('<p>' . e($p['name']) . ' available at Fahim Store with fast home delivery and store pickup. 100% original product.</p>'),
        'keywords' => $p['keywords'] ?? null,
        'price' => $p['price'], 'sale_price' => $p['sale'] ?? null, 'cost_price' => $p['cost'] ?? round($p['price'] * 0.8, 2),
        'unit' => $p['unit'] ?? 'piece', 'unit_value' => $p['unit_value'] ?? null, 'weight_grams' => $p['weight'] ?? null,
        'low_stock_threshold' => $p['low'] ?? 5, 'min_order_qty' => 1, 'max_order_qty' => $p['max'] ?? null,
        'has_variations' => $hasVar ? 1 : 0, 'is_featured' => $p['featured'] ?? 0, 'is_bestseller' => $p['best'] ?? 0, 'is_rashan_item' => $p['rashan'] ?? 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 60) . ' days')),
    ]);
    foreach (array_unique($p['cats']) as $c) db_exec('INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)', [$id, $c]);
    foreach ($p['tags'] ?? [] as $t) db_exec('INSERT IGNORE INTO product_tags (product_id, tag) VALUES (?, ?)', [$id, $t]);
    // image
    $file = 'products/sample/' . $slug . '.svg';
    file_put_contents($root . '/uploads/' . $file, $p['svg']);
    db_exec('INSERT INTO product_images (product_id, image, thumb, alt_text, is_primary) VALUES (?, ?, ?, ?, 1)', [$id, $file, $file, $p['name']]);
    if ($hasVar) {
        foreach ($p['variations'] as $i => $v) {
            $vid = db_insert('INSERT INTO product_variations (product_id, name, sku, barcode, price, sale_price, cost_price, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $v[0], $sku . '-' . ($i + 1), '897' . str_pad((string) ($skuN * 10 + $i), 10, '0', STR_PAD_LEFT), $v[1], $v[2], round($v[1] * 0.85, 2), $i]);
            stock_change($id, $vid, $v[3], 'opening', ['reason' => 'Opening stock', 'ref_type' => 'manual', 'admin_id' => 1]);
        }
        $min = min(array_column($p['variations'], 1));
        db_exec('UPDATE products SET price = ? WHERE id = ?', [$min, $id]);
    } else {
        stock_change($id, 0, $p['stock'] ?? 50, 'opening', ['reason' => 'Opening stock', 'ref_type' => 'manual', 'admin_id' => 1]);
    }
    product_rebuild_search($id);
    $productIds[$p['key'] ?? $slug] = $id;
    return $id;
}

// ----- School books
$colors = [['#0ea5e9', '#0369a1'], ['#f43f5e', '#9f1239'], ['#22c55e', '#15803d'], ['#a855f7', '#6b21a8'], ['#f59e0b', '#b45309'], ['#14b8a6', '#0f766e']];
$bookSet = [
    ['Class 1', [['English Reader', 'English'], ['Urdu Qaida', 'Urdu'], ['Mathematics', 'Maths']], 'Punjab Text Book Board', 220],
    ['Class 2', [['English Reader', 'English'], ['Mathematics', 'Maths']], 'Punjab Text Book Board', 240],
    ['Class 3', [['General Science', 'Science'], ['Mathematics', 'Maths']], 'Punjab Text Book Board', 260],
    ['Class 4', [['Social Studies', 'Social'], ['English Grammar', 'English']], 'Paramount Books', 280],
    ['Class 5', [['English', 'English'], ['Mathematics', 'Maths'], ['General Science', 'Science'], ['Urdu', 'Urdu'], ['Islamiat', 'Islamiat']], 'Sindh Text Book Board', 300],
    ['Class 6', [['Mathematics', 'Maths'], ['Computer Studies', 'Computer']], 'Paramount Books', 320],
    ['Class 7', [['Science', 'Science'], ['English', 'English']], 'Sindh Text Book Board', 340],
    ['Class 8', [['Mathematics', 'Maths'], ['Science', 'Science']], 'Sindh Text Book Board', 360],
    ['Class 9', [['Physics', 'Physics'], ['Chemistry', 'Chemistry'], ['Biology', 'Biology'], ['Computer Science', 'Computer']], 'Sindh Text Book Board', 420],
    ['Class 10', [['Mathematics', 'Maths'], ['Physics', 'Physics'], ['Pakistan Studies', 'Pak Studies']], 'Sindh Text Book Board', 450],
    ['Intermediate', [['Physics Part 1', 'Physics'], ['Chemistry Part 1', 'Chemistry'], ['English Book 1', 'English']], 'Sindh Text Book Board', 520],
];
$ci = 0;
foreach ($bookSet as [$class, $subjects, $brand, $price]) {
    foreach ($subjects as [$subject, $short]) {
        [$c1, $c2] = $colors[$ci++ % count($colors)];
        $kw = strtolower("$class $short text book " . ($short === 'Maths' ? 'math maths mathematics' : ''));
        add_product([
            'key' => "$class $subject", 'name' => "$subject Text Book $class", 'cats' => [$classIds[$class], $school], 'brand' => $brand,
            'price' => $price + random_int(0, 4) * 10, 'stock' => random_int(15, 60), 'unit' => 'book', 'isbn' => '978969' . random_int(1000000, 9999999),
            'keywords' => $kw, 'tags' => [strtolower($class), strtolower($short), 'school book'],
            'svg' => svg_book($subject, $class, $c1, $c2, $brand), 'featured' => $class === 'Class 5' ? 1 : 0, 'best' => in_array($class, ['Class 5', 'Class 9'], true) && $ci % 2 ? 1 : 0,
            'short' => "Official $subject text book for $class students. Latest syllabus edition.",
            'desc' => "<p>The latest edition of <strong>$subject</strong> text book for <strong>$class</strong>, published by $brand. Covers the complete syllabus approved by the board.</p><ul><li>Latest syllabus</li><li>Clear printing on quality paper</li><li>Original publisher copy</li></ul>",
        ]);
    }
}
add_product(['name' => 'New Oxford Modern English Book 5', 'cats' => [$oxford, $classIds['Class 5']], 'brand' => 'Oxford University Press', 'price' => 1350, 'sale' => 1250, 'stock' => 25, 'unit' => 'book', 'isbn' => '9780199069019',
    'svg' => svg_book('New Oxford Modern English', 'Book 5', '#1d4ed8', '#1e3a8a', 'OXFORD'), 'featured' => 1, 'keywords' => 'nome oxford english class 5', 'tags' => ['oxford', 'english']]);
add_product(['name' => 'Oxford Shorter English Dictionary', 'cats' => [$oxford, $gk], 'brand' => 'Oxford University Press', 'price' => 2450, 'stock' => 8, 'unit' => 'book', 'isbn' => '9780199206872',
    'svg' => svg_book('Shorter Oxford English Dictionary', '6th Edition', '#1e40af', '#172554', 'OXFORD'), 'featured' => 1, 'keywords' => 'dictionary oxford english', 'tags' => ['dictionary']]);
add_product(['name' => 'Cambridge Primary Mathematics Learner Book 5', 'cats' => [$cambridge, $classIds['Class 5']], 'brand' => 'Cambridge University Press', 'price' => 3200, 'sale' => 2950, 'stock' => 12, 'unit' => 'book', 'isbn' => '9781108746328',
    'svg' => svg_book('Primary Mathematics', 'Learner Book 5', '#ea580c', '#9a3412', 'CAMBRIDGE'), 'keywords' => 'cambridge maths math class 5 primary', 'tags' => ['cambridge', 'maths']]);
add_product(['name' => 'Holy Quran 13 Lines (Hafzi)', 'urdu' => 'قرآن مجید ۱۳ سطری', 'cats' => [$islamic], 'brand' => 'Taj Company', 'price' => 1800, 'stock' => 20, 'unit' => 'book',
    'svg' => svg_book('Holy Quran', '13 Lines', '#1e3a8a', '#172554', 'TAJ COMPANY'), 'featured' => 1, 'best' => 1, 'keywords' => 'quran majeed hafzi tilawat', 'tags' => ['quran', 'islamic']]);
add_product(['name' => 'Holy Quran 16 Lines with Urdu Translation', 'urdu' => 'قرآن مجید مع اردو ترجمہ', 'cats' => [$islamic], 'brand' => 'Taj Company', 'price' => 2600, 'stock' => 10, 'unit' => 'book',
    'svg' => svg_book('Holy Quran', 'Urdu Translation', '#065f46', '#064e3b', 'TAJ COMPANY'), 'keywords' => 'quran tarjuma urdu translation', 'tags' => ['quran']]);
add_product(['name' => 'Seerat-un-Nabi for Kids', 'cats' => [$islamic, $story], 'brand' => 'Paramount Books', 'price' => 650, 'stock' => 14, 'unit' => 'book',
    'svg' => svg_book('Seerat un Nabi for Kids', 'Illustrated', '#0d9488', '#115e59'), 'keywords' => 'seerat islamic kids']);
add_product(['name' => 'General Knowledge Book 5', 'cats' => [$gk, $classIds['Class 5']], 'brand' => 'Paramount Books', 'price' => 480, 'stock' => 30, 'unit' => 'book',
    'svg' => svg_book('General Knowledge', 'Book 5', '#7c3aed', '#4c1d95'), 'keywords' => 'gk general knowledge class 5']);
add_product(['name' => 'Moral Stories for Children (Set of 6)', 'cats' => [$story], 'brand' => 'Paramount Books', 'price' => 1200, 'sale' => 990, 'stock' => 9, 'unit' => 'set',
    'svg' => svg_book('Moral Stories', 'Set of 6', '#db2777', '#831843'), 'keywords' => 'kids story books moral stories', 'featured' => 1]);

// ----- Stationery
add_product(['name' => 'Dollar Clipper Ball Pen Blue (Pack of 10)', 'cats' => [$pens], 'brand' => 'Dollar', 'price' => 250, 'sale' => 220, 'stock' => 80, 'unit' => 'pack',
    'svg' => svg_pens('Dollar Ball Pens', ['#1d4ed8', '#1d4ed8', '#1d4ed8', '#1d4ed8', '#1d4ed8']), 'best' => 1, 'featured' => 1, 'keywords' => 'pen ball point blue', 'tags' => ['pen']]);
add_product(['name' => 'Piano Ball Pen Black (Pack of 10)', 'cats' => [$pens], 'brand' => 'Piano', 'price' => 200, 'stock' => 60, 'unit' => 'pack',
    'svg' => svg_pens('Piano Ball Pens', ['#111827', '#111827', '#111827', '#111827', '#111827']), 'keywords' => 'pen black ball point']);
add_product(['name' => 'Deer HB Pencils (Box of 12)', 'cats' => [$pens], 'brand' => 'Deer', 'price' => 180, 'stock' => 100, 'unit' => 'box',
    'svg' => svg_pens('HB Pencils x12', ['#facc15', '#facc15', '#facc15', '#facc15', '#facc15']), 'best' => 1, 'keywords' => 'pencil lead hb', 'tags' => ['pencil']]);
add_product(['name' => 'Faber-Castell Colour Pencils (24 Shades)', 'cats' => [$pens, $art], 'brand' => 'Faber-Castell', 'price' => 950, 'sale' => 850, 'stock' => 22, 'unit' => 'box',
    'svg' => svg_pens('Colour Pencils 24', ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6']), 'featured' => 1, 'keywords' => 'color pencils colours drawing']);
add_product(['name' => 'Picasso Wax Crayons (16 Colours)', 'cats' => [$art], 'brand' => 'Picasso', 'price' => 320, 'stock' => 35, 'unit' => 'box',
    'svg' => svg_pens('Wax Crayons 16', ['#ec4899', '#8b5cf6', '#14b8a6', '#f59e0b', '#ef4444']), 'keywords' => 'crayons colour kids']);
add_product(['name' => 'Picasso Water Colours (12 Shades)', 'cats' => [$art], 'brand' => 'Picasso', 'price' => 450, 'stock' => 18, 'unit' => 'box',
    'svg' => svg_box('Picasso', 'Water Colours', '12 Shades', '#7c3aed', '#5b21b6'), 'keywords' => 'paint water colour art']);
add_product(['name' => 'Solo Register 200 Pages (Single Line)', 'cats' => [$registers], 'brand' => 'Lion', 'price' => 280, 'stock' => 120, 'unit' => 'piece',
    'svg' => svg_notebook('Solo Register', '200 Pages', '#2563eb'), 'featured' => 1, 'best' => 1, 'keywords' => 'register copy notebook single line', 'tags' => ['register', 'copy']]);
add_product(['name' => 'Lion Copy 80 Pages (Pack of 6)', 'cats' => [$registers], 'brand' => 'Lion', 'price' => 540, 'sale' => 480, 'stock' => 70, 'unit' => 'pack',
    'svg' => svg_notebook('Lion Copies', '6 x 80 Pages', '#dc2626'), 'best' => 1, 'keywords' => 'copy copies notebook kapi', 'tags' => ['copy']]);
add_product(['name' => 'Spiral Notebook A4 (Ruled)', 'cats' => [$registers, $office], 'brand' => 'Lion', 'price' => 350, 'stock' => 40, 'unit' => 'piece',
    'svg' => svg_notebook('Spiral Notebook', 'A4 Ruled', '#0d9488'), 'keywords' => 'notebook spiral a4']);
add_product(['name' => 'Maths Copy Square Lines (Pack of 4)', 'cats' => [$registers], 'brand' => 'Lion', 'price' => 380, 'stock' => 45, 'unit' => 'pack',
    'svg' => svg_notebook('Maths Copy', 'Square Lines', '#7c3aed'), 'keywords' => 'math copy maths square']);
add_product(['name' => 'Deer Geometry Box (Complete Set)', 'cats' => [$geometry, $schoolAcc], 'brand' => 'Deer', 'price' => 420, 'sale' => 380, 'stock' => 30, 'unit' => 'set',
    'svg' => svg_item('Geometry Box', 'Compass · Protractor · Scale', '#0ea5e9', 'box'), 'featured' => 1, 'keywords' => 'geometry box compass protractor', 'tags' => ['geometry']]);
add_product(['name' => 'Plastic File Folder A4 (Pack of 5)', 'cats' => [$files, $office], 'brand' => 'Dollar', 'price' => 300, 'stock' => 50, 'unit' => 'pack',
    'svg' => svg_item('File Folders', 'A4 · Pack of 5', '#f59e0b', 'box'), 'keywords' => 'file folder document']);
add_product(['name' => 'Dollar Permanent Marker (Pack of 4)', 'cats' => [$office, $pens], 'brand' => 'Dollar', 'price' => 360, 'stock' => 3, 'unit' => 'pack', 'low' => 5,
    'svg' => svg_pens('Permanent Markers', ['#111827', '#dc2626', '#2563eb', '#16a34a']), 'keywords' => 'marker permanent']);
add_product(['name' => 'A4 Paper Ream 80gsm (500 Sheets)', 'cats' => [$office], 'brand' => 'Dollar', 'price' => 1650, 'stock' => 15, 'unit' => 'ream',
    'svg' => svg_box('Copy Paper', 'A4 80gsm', '500 Sheets', '#0ea5e9', '#0369a1'), 'keywords' => 'paper a4 rim ream printing']);
add_product(['name' => 'School Bag Waterproof (Medium)', 'cats' => [$bags, $schoolAcc], 'price' => 2500, 'sale' => 2200, 'stock' => 12, 'unit' => 'piece',
    'svg' => svg_item('School Bag', 'Waterproof · Medium', '#2563eb'), 'featured' => 1, 'keywords' => 'bag school bag backpack']);
add_product(['name' => 'Lunch Box with Water Bottle', 'cats' => [$bags, $schoolAcc], 'price' => 850, 'stock' => 20, 'unit' => 'set',
    'svg' => svg_item('Lunch Box Set', 'with Water Bottle', '#ec4899', 'box'), 'keywords' => 'lunch box tiffin bottle']);
add_product(['name' => 'Energizer AA Batteries (Pack of 4)', 'cats' => [$elec], 'brand' => 'Energizer', 'price' => 520, 'stock' => 25, 'unit' => 'pack',
    'svg' => svg_box('Energizer', 'AA Batteries', 'Pack of 4', '#16a34a', '#14532d'), 'keywords' => 'cells battery aa']);
add_product(['name' => 'Ludo & Snakes Ladders Board Game', 'cats' => [$toys], 'price' => 450, 'stock' => 10, 'unit' => 'piece',
    'svg' => svg_item('Ludo Board Game', 'Family Game', '#ef4444', 'box'), 'keywords' => 'ludo game toys']);

// ----- Groceries (rashan items)
add_product(['key' => 'atta', 'name' => 'Sunridge Chakki Atta (Wheat Flour)', 'urdu' => 'سن رج چکی آٹا', 'cats' => [$g['Flour & Rice']], 'brand' => 'Sunridge', 'price' => 0, 'unit' => 'kg', 'rashan' => 1, 'featured' => 1, 'best' => 1,
    'variations' => [['5 kg', 780, null, 60], ['10 kg', 1540, 1490, 50], ['20 kg', 3050, 2950, 25]],
    'svg' => svg_sack('Sunridge', 'Chakki Atta', 'Wheat Flour', '#fde68a', '#b45309'), 'keywords' => 'atta flour aata gandum wheat chakki', 'tags' => ['atta', 'flour']]);
add_product(['name' => 'Guard Super Basmati Rice', 'urdu' => 'گارڈ باسمتی چاول', 'cats' => [$g['Flour & Rice']], 'brand' => 'Guard', 'price' => 0, 'unit' => 'kg', 'rashan' => 1, 'best' => 1,
    'variations' => [['1 kg', 520, null, 80], ['5 kg', 2500, 2400, 40]],
    'svg' => svg_sack('Guard', 'Super Basmati Rice', 'Basmati', '#e0f2fe', '#0369a1'), 'keywords' => 'rice chawal basmati', 'tags' => ['rice', 'chawal']]);
add_product(['name' => 'Sella Rice (Loose)', 'urdu' => 'سیلا چاول', 'cats' => [$g['Flour & Rice']], 'price' => 380, 'stock' => 100, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_sack('Fahim', 'Sella Rice', '1 kg', '#f1f5f9', '#475569'), 'keywords' => 'rice chawal sella']);
add_product(['key' => 'oil', 'name' => 'Dalda Cooking Oil', 'urdu' => 'ڈالڈا کوکنگ آئل', 'cats' => [$g['Cooking Oil & Ghee']], 'brand' => 'Dalda', 'price' => 0, 'unit' => 'litre', 'rashan' => 1, 'featured' => 1, 'best' => 1,
    'variations' => [['1 Litre Pouch', 560, null, 70], ['3 Litre Bottle', 1650, 1599, 30], ['5 Litre Tin', 2700, 2620, 25]],
    'svg' => svg_bottle('Dalda', 'Cooking Oil', '5 Litre', '#15803d'), 'keywords' => 'oil tel cooking oil', 'tags' => ['oil', 'tel']]);
add_product(['name' => 'Habib Banaspati Ghee 1 kg', 'urdu' => 'حبیب بناسپتی گھی', 'cats' => [$g['Cooking Oil & Ghee']], 'brand' => 'Habib', 'price' => 590, 'stock' => 45, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_box('Habib', 'Banaspati Ghee', '1 kg', '#ca8a04', '#854d0e'), 'keywords' => 'ghee banaspati', 'tags' => ['ghee']]);
add_product(['name' => 'Masoor Dal 1 kg', 'urdu' => 'مسور دال', 'cats' => [$g['Pulses & Lentils']], 'price' => 340, 'stock' => 60, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_packet('Fahim', 'Masoor Dal', '1 kg', '#ea580c', '#9a3412'), 'keywords' => 'daal dal lentils masoor', 'tags' => ['dal']]);
add_product(['name' => 'Moong Dal 1 kg', 'urdu' => 'مونگ دال', 'cats' => [$g['Pulses & Lentils']], 'price' => 420, 'stock' => 50, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_packet('Fahim', 'Moong Dal', '1 kg', '#65a30d', '#3f6212'), 'keywords' => 'daal dal moong mung']);
add_product(['name' => 'Chana Dal 1 kg', 'urdu' => 'چنا دال', 'cats' => [$g['Pulses & Lentils']], 'price' => 360, 'stock' => 55, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_packet('Fahim', 'Chana Dal', '1 kg', '#d97706', '#92400e'), 'keywords' => 'daal dal chana gram']);
add_product(['name' => 'White Chickpeas (Kabuli Chana) 1 kg', 'urdu' => 'سفید چنے', 'cats' => [$g['Pulses & Lentils']], 'price' => 480, 'sale' => 450, 'stock' => 35, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_packet('Fahim', 'Kabuli Chana', '1 kg', '#a16207', '#713f12'), 'keywords' => 'chickpeas safaid chane chana']);
add_product(['name' => 'White Sugar', 'urdu' => 'چینی', 'cats' => [$g['Sugar & Salt']], 'price' => 0, 'unit' => 'kg', 'rashan' => 1, 'best' => 1,
    'variations' => [['1 kg', 165, null, 100], ['5 kg', 810, 790, 40]],
    'svg' => svg_sack('Fahim', 'White Sugar', 'Cheeni', '#ffffff', '#0f766e'), 'keywords' => 'sugar cheeni chini shakar', 'tags' => ['sugar', 'cheeni']]);
add_product(['name' => 'National Iodized Salt 800g', 'urdu' => 'نمک', 'cats' => [$g['Sugar & Salt']], 'brand' => 'National Foods', 'price' => 60, 'stock' => 120, 'unit' => 'packet', 'rashan' => 1,
    'svg' => svg_packet('National', 'Iodized Salt', '800g', '#2563eb', '#1e3a8a'), 'keywords' => 'salt namak']);
add_product(['name' => 'Shan Biryani Masala 60g', 'cats' => [$g['Spices']], 'brand' => 'Shan', 'price' => 140, 'stock' => 90, 'unit' => 'packet', 'rashan' => 1, 'best' => 1,
    'svg' => svg_packet('Shan', 'Biryani Masala', '60g', '#dc2626', '#7f1d1d'), 'keywords' => 'masala spices biryani shan', 'tags' => ['masala']]);
add_product(['name' => 'National Red Chilli Powder 200g', 'urdu' => 'لال مرچ', 'cats' => [$g['Spices']], 'brand' => 'National Foods', 'price' => 330, 'stock' => 60, 'unit' => 'packet', 'rashan' => 1,
    'svg' => svg_packet('National', 'Red Chilli Powder', '200g', '#b91c1c', '#7f1d1d'), 'keywords' => 'lal mirch red chilli spices']);
add_product(['name' => 'National Haldi (Turmeric) Powder 200g', 'urdu' => 'ہلدی', 'cats' => [$g['Spices']], 'brand' => 'National Foods', 'price' => 260, 'stock' => 55, 'unit' => 'packet', 'rashan' => 1,
    'svg' => svg_packet('National', 'Haldi Powder', '200g', '#eab308', '#854d0e'), 'keywords' => 'haldi turmeric spices']);
add_product(['key' => 'tea', 'name' => 'Tapal Danedar Tea', 'urdu' => 'تپال دانے دار چائے', 'cats' => [$g['Tea & Beverages']], 'brand' => 'Tapal', 'price' => 0, 'unit' => 'packet', 'rashan' => 1, 'featured' => 1, 'best' => 1,
    'variations' => [['430g', 980, 940, 50], ['950g', 2050, 1980, 30]],
    'svg' => svg_box('Tapal', 'Danedar Tea', '950g', '#b91c1c', '#7f1d1d'), 'keywords' => 'tea chai patti tapal', 'tags' => ['tea', 'chai']]);
add_product(['name' => 'Lipton Yellow Label Tea 190g', 'cats' => [$g['Tea & Beverages']], 'brand' => 'Lipton', 'price' => 640, 'stock' => 40, 'unit' => 'packet', 'rashan' => 1,
    'svg' => svg_box('Lipton', 'Yellow Label', '190g', '#eab308', '#a16207'), 'keywords' => 'tea chai lipton']);
add_product(['name' => 'Nestle Nesvita Milk Powder 400g', 'cats' => [$g['Dairy Products']], 'brand' => 'Nestle', 'price' => 950, 'stock' => 25, 'unit' => 'packet', 'rashan' => 1,
    'svg' => svg_box('Nestle', 'Milk Powder', '400g', '#2563eb', '#1e3a8a'), 'keywords' => 'milk doodh powder']);
add_product(['name' => 'Olpers Full Cream Milk 1 Litre', 'urdu' => 'دودھ', 'cats' => [$g['Dairy Products']], 'brand' => 'Olpers', 'price' => 290, 'stock' => 80, 'unit' => 'litre', 'unit_value' => 1, 'rashan' => 1, 'best' => 1,
    'svg' => svg_box('Olpers', 'Full Cream Milk', '1 Litre', '#1d4ed8', '#1e3a8a'), 'keywords' => 'milk doodh olpers', 'tags' => ['milk', 'doodh']]);
add_product(['name' => 'Peek Freans Sooper Biscuits (Family Pack)', 'cats' => [$g['Snacks & Biscuits']], 'brand' => 'Peek Freans', 'price' => 350, 'sale' => 320, 'stock' => 70, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_packet('Sooper', 'Egg & Milk Biscuits', 'Family', '#f59e0b', '#92400e'), 'keywords' => 'biscuits biscuit sooper', 'featured' => 1]);
add_product(['name' => 'LU Prince Chocolate Biscuits (Pack of 12)', 'cats' => [$g['Snacks & Biscuits']], 'brand' => 'LU', 'price' => 480, 'stock' => 40, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_packet('Prince', 'Chocolate Biscuits', '12 Pack', '#7c2d12', '#431407'), 'keywords' => 'biscuits chocolate prince']);
add_product(['key' => 'surf', 'name' => 'Surf Excel Washing Powder', 'cats' => [$g['Cleaning Products']], 'brand' => 'Surf Excel', 'price' => 0, 'unit' => 'kg', 'rashan' => 1, 'best' => 1, 'featured' => 1,
    'variations' => [['500g', 420, null, 60], ['1 kg', 820, 790, 40], ['2 kg', 1580, 1520, 20]],
    'svg' => svg_box('Surf Excel', 'Washing Powder', '1 kg', '#2563eb', '#1e40af'), 'keywords' => 'surf detergent washing powder kapray', 'tags' => ['surf', 'detergent']]);
add_product(['name' => 'Ariel Washing Powder 1 kg', 'cats' => [$g['Cleaning Products']], 'brand' => 'Ariel', 'price' => 890, 'stock' => 25, 'unit' => 'kg', 'unit_value' => 1, 'rashan' => 1,
    'svg' => svg_box('Ariel', 'Washing Powder', '1 kg', '#16a34a', '#14532d'), 'keywords' => 'detergent washing powder ariel surf']);
add_product(['name' => 'Max Dishwash Liquid 500ml', 'cats' => [$g['Cleaning Products']], 'brand' => 'Max', 'price' => 330, 'stock' => 45, 'unit' => 'bottle', 'rashan' => 1,
    'svg' => svg_bottle('Max', 'Dishwash Liquid', '500 ml', '#16a34a', '#86efac'), 'keywords' => 'dishwash bartan liquid']);
add_product(['name' => 'Lux Soap Rose (Pack of 3)', 'urdu' => 'لکس صابن', 'cats' => [$g['Personal Care']], 'brand' => 'Lux', 'price' => 390, 'sale' => 360, 'stock' => 60, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_soap('Lux', 'Soft Rose · Pack of 3', '#db2777'), 'keywords' => 'soap sabun lux', 'tags' => ['soap', 'sabun']]);
add_product(['name' => 'Lifebuoy Total Soap (Pack of 3)', 'cats' => [$g['Personal Care']], 'brand' => 'Lifebuoy', 'price' => 340, 'stock' => 55, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_soap('Lifebuoy', 'Total · Pack of 3', '#dc2626'), 'keywords' => 'soap sabun lifebuoy']);
add_product(['name' => 'Rose Petal Tissue Box (Pack of 3)', 'cats' => [$g['Household Essentials']], 'brand' => 'Rose Petal', 'price' => 540, 'stock' => 35, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_box('Rose Petal', 'Facial Tissue', '3 Boxes', '#ec4899', '#9d174d'), 'keywords' => 'tissue paper napkin']);
add_product(['name' => 'Mitchells Mixed Fruit Jam 450g', 'cats' => [$g['Snacks & Biscuits']], 'brand' => 'Mitchells', 'price' => 420, 'stock' => 30, 'unit' => 'bottle', 'rashan' => 1,
    'svg' => svg_bottle('Mitchells', 'Mixed Fruit Jam', '450g', '#b91c1c', '#f87171'), 'keywords' => 'jam mitchells breakfast']);
add_product(['name' => 'Matchbox Pack (10 boxes)', 'cats' => [$g['Household Essentials']], 'price' => 60, 'stock' => 4, 'low' => 10, 'unit' => 'pack', 'rashan' => 1,
    'svg' => svg_box('Safety', 'Matches', '10 Boxes', '#f97316', '#9a3412'), 'keywords' => 'matchbox machis']);
out('products ok: ' . count($productIds));

// Related products examples
$pi = $productIds;
db_exec('INSERT IGNORE INTO related_products (product_id, related_id) VALUES (?, ?), (?, ?), (?, ?)', [$pi['atta'], $pi['oil'], $pi['oil'], $pi['atta'], $pi['tea'], $pi['atta']]);

/* ---------------------------------------------------------------
 * SEARCH SYNONYMS
 * --------------------------------------------------------------- */
$syn = ['atta' => 'flour,wheat flour', 'aata' => 'flour,atta', 'chawal' => 'rice', 'cheeni' => 'sugar', 'chini' => 'sugar', 'daal' => 'dal,lentils',
    'dal' => 'lentils,pulses', 'tel' => 'oil,cooking oil', 'surf' => 'detergent,washing powder', 'sabun' => 'soap', 'chai' => 'tea', 'maths' => 'mathematics',
    'math' => 'mathematics', 'copy' => 'notebook,register,copies', 'kapi' => 'copy,notebook', 'namak' => 'salt', 'masala' => 'spices', 'doodh' => 'milk',
    'ghee' => 'banaspati', 'pencil' => 'pencils', 'bag' => 'school bag', 'colour' => 'color', 'gk' => 'general knowledge', 'quran' => 'holy quran'];
foreach ($syn as $t => $s) db_exec('INSERT INTO search_synonyms (term, synonyms) VALUES (?, ?)', [$t, $s]);

/* ---------------------------------------------------------------
 * DELIVERY ZONES & PAYMENT METHODS
 * --------------------------------------------------------------- */
$zones = [['Karachi', 'Gulshan-e-Iqbal', 100, 3000, 0, 'Same day'], ['Karachi', 'Gulistan-e-Johar', 120, 4000, 0, 'Same day'], ['Karachi', 'North Nazimabad', 150, 5000, 500, 'Same day'],
    ['Karachi', 'Saddar', 150, 5000, 500, '24 hours'], ['Karachi', 'DHA', 250, 6000, 1000, '24 hours'], ['Karachi', 'Clifton', 250, 6000, 1000, '24 hours'],
    ['Karachi', 'Korangi', 200, 6000, 1000, '24-48 hours'], ['Karachi', 'Malir', 200, 6000, 1000, '24-48 hours']];
foreach ($zones as $i => $z) {
    db_exec('INSERT INTO delivery_zones (city, area, delivery_fee, free_delivery_over, min_order_amount, estimated_time, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)', [$z[0], $z[1], $z[2], $z[3], $z[4], $z[5], $i]);
}
$pms = [
    ['cod', 'Cash on Delivery', 'Pay in cash when your order arrives.', 'delivery', 0, 1],
    ['cash_pickup', 'Cash on Pickup', 'Pay at the counter when you collect your order.', 'pickup', 0, 1],
    ['bank_transfer', 'Bank Transfer', "Meezan Bank - Fahim Store\nAccount #: 0101-0000000000\nIBAN: PK00MEZN0001010000000000\nPlease send the receipt on WhatsApp 0300-1234567.", 'both', 1, 0],
    ['easypaisa', 'Easypaisa', "Send payment to Easypaisa account 0300-1234567 (Fahim Store) and enter the transaction ID.", 'both', 1, 1],
    ['jazzcash', 'JazzCash', "Send payment to JazzCash account 0300-1234567 (Fahim Store) and enter the transaction ID.", 'both', 1, 1],
    ['cash', 'Cash', 'Cash payment at the counter.', 'pos', 0, 1],
    ['card', 'Debit/Credit Card', 'Card payment on the store POS machine.', 'pos', 0, 1],
];
foreach ($pms as $i => $p) {
    db_exec('INSERT INTO payment_methods (code, name, instructions, applies_to, requires_reference, show_in_pos, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)', [$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $i]);
}

/* ---------------------------------------------------------------
 * COUPONS
 * --------------------------------------------------------------- */
db_exec("INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_discount, per_customer_limit) VALUES
 ('WELCOME10', '10% off your first order (max Rs. 500)', 'percent', 10, 1000, 500, 1),
 ('RASHAN500', 'Rs. 500 off monthly rashan orders above Rs. 10,000', 'fixed', 500, 10000, NULL, NULL),
 ('FREEDEL', 'Free delivery on orders above Rs. 2,000', 'free_delivery', 0, 2000, NULL, NULL)");

/* ---------------------------------------------------------------
 * BANNERS
 * --------------------------------------------------------------- */
db_exec("INSERT INTO banners (position, heading, subheading, description, button_text, button_url, side_image, bg_color, text_color, sort_order) VALUES
 ('hero', 'Build Your Monthly Rashan', 'Save time every month', 'Pick atta, oil, daal, tea and more in one list. Set your budget - we pack it for delivery or pickup.', 'Start Your List', 'monthly-rashan/', 'assets/images/categories/groceries.svg', '#0f766e', '#ffffff', 1),
 ('hero', 'School Reopening Sale', 'Class 1 to Intermediate', 'All text books, registers, copies and geometry boxes under one roof. Up to 15% off.', 'Shop Books', 'category/books/', 'assets/images/categories/books.svg', '#1e3a8a', '#ffffff', 2),
 ('hero', 'Stationery for Every Student', 'Pens · Pencils · Colours · Registers', 'Branded stationery from Dollar, Picasso, Faber-Castell and more at the best prices.', 'Shop Stationery', 'category/stationery/', 'assets/images/categories/stationery.svg', '#7c2d12', '#ffffff', 3),
 ('promo', 'Free Delivery above Rs. 5,000', 'On all grocery orders', NULL, 'Order Now', 'category/groceries/', 'assets/images/categories/groceries.svg', '#f59e0b', '#1f2933', 1),
 ('promo', 'Registers & Copies', 'Buy in bulk & save', NULL, 'View Range', 'category/stationery/registers-copies/', 'assets/images/categories/registers.svg', '#ccfbf1', '#134e4a', 2)");

/* ---------------------------------------------------------------
 * PROMOTIONS
 * --------------------------------------------------------------- */
$promoData = [
    ['School Reopening Offer', 'school_offer', 'Up to 15% off on books, registers & geometry boxes', 'UP TO 15% OFF', '#1e3a8a', '#ffffff', [$classIds['Class 5']]],
    ['Monthly Grocery Package', 'monthly_package', 'Atta, oil, sugar, tea, daal - everything for the month', 'SAVE RS. 500', '#0f766e', '#ffffff', [$g['Flour & Rice'], $g['Cooking Oil & Ghee'], $g['Tea & Beverages'], $g['Sugar & Salt']]],
    ['Stationery Bundle', 'stationery_bundle', 'Pens, pencils, colours and copies for the new term', 'BUNDLE DEAL', '#c2410c', '#ffffff', [$pens, $registers]],
    ['Cleaning Combo Deals', 'combo', 'Washing powder, dishwash & soaps at combo prices', 'COMBO', '#7c3aed', '#ffffff', [$g['Cleaning Products'], $g['Personal Care']]],
];
foreach ($promoData as $i => [$t, $type, $sub, $badge, $bg, $fg, $cats]) {
    $pid = db_insert('INSERT INTO promotions (title, slug, promo_type, subtitle, description, badge_text, bg_color, text_color, button_text, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$t, slugify($t), $type, $sub, "<p>$sub. Limited time offer at Fahim Store - order online for home delivery or pick up from the store.</p>", $badge, $bg, $fg, 'Shop Now', $i]);
    $r = products_query(['category_ids' => $cats, 'limit' => 8, 'sort' => 'popular']);
    foreach ($r['items'] as $j => $p) db_exec('INSERT IGNORE INTO promotion_products (promotion_id, product_id, sort_order) VALUES (?, ?, ?)', [$pid, $p['id'], $j]);
}

/* ---------------------------------------------------------------
 * HOMEPAGE SECTIONS
 * --------------------------------------------------------------- */
$sections = [
    ['hero', null, null, ['autoplay' => 5000]],
    ['categories', 'Shop by Categories', 'Everything for school and home', ['limit' => 8, 'source' => 'home']],
    ['products', 'Featured Products', 'Hand-picked by Fahim Store', ['source' => 'featured', 'limit' => 10, 'link' => 'shop/?featured=1']],
    ['rashan_cta', 'Build Your Monthly Rashan', 'Make your grocery list once, reorder every month', []],
    ['products', 'Best Sellers', 'Most popular with our customers', ['source' => 'best_sellers', 'limit' => 10, 'link' => 'shop/?sort=popular']],
    ['promotions', 'Special Promotions', 'Deals, bundles and seasonal offers', ['limit' => 4]],
    ['products', 'New Arrivals', 'Just added to the store', ['source' => 'new', 'limit' => 10, 'link' => 'shop/?sort=newest']],
    ['promo_banners', null, null, []],
    ['products', 'Weekly Deals', 'Discounted prices this week', ['source' => 'on_sale', 'limit' => 10, 'link' => 'shop/?on_sale=1']],
    ['brands', 'Shop by Brand', null, ['limit' => 12]],
    ['products', 'Recommended for You', 'Trending products in your area', ['source' => 'trending', 'limit' => 10]],
    ['testimonials', 'What Our Customers Say', null, []],
    ['newsletter', 'Get Deals in Your Inbox', 'Weekly grocery deals and school offers. No spam.', []],
];
foreach ($sections as $i => [$type, $title, $sub, $set]) {
    db_exec('INSERT INTO homepage_sections (section_type, title, subtitle, settings, sort_order) VALUES (?, ?, ?, ?, ?)', [$type, $title, $sub, json_encode($set), $i + 1]);
}

/* ---------------------------------------------------------------
 * TESTIMONIALS, PAGES
 * --------------------------------------------------------------- */
db_exec("INSERT INTO testimonials (customer_name, location, message, rating, sort_order) VALUES
 ('Ayesha Khan', 'Gulshan-e-Iqbal', 'I order my complete monthly rashan from Fahim Store. The budget feature is amazing and delivery is always on time.', 5, 1),
 ('Muhammad Bilal', 'North Nazimabad', 'Got all Class 5 books and copies for both my kids in one order. Very convenient before school reopening!', 5, 2),
 ('Sana Ahmed', 'Gulistan-e-Johar', 'Store pickup is super quick. My order was packed and ready when I arrived.', 4, 3),
 ('Imran Siddiqui', 'Saddar', 'Original products, fair prices and cash on delivery. Highly recommended.', 5, 4)");

$pages = [
    ['About Us', 'about-us', '<h2>Welcome to Fahim Store</h2><p>Fahim Store is a trusted neighbourhood store for <strong>school books, stationery, groceries and household essentials</strong>. Our online store brings the same trusted products and prices to your doorstep.</p><h3>Why shop with us?</h3><ul><li>Original books from all major publishers and text book boards</li><li>Complete monthly rashan with budget planning</li><li>Home delivery or store pickup</li><li>Cash on Delivery, bank transfer, Easypaisa and JazzCash</li></ul>'],
    ['Delivery Information', 'delivery-information', '<h2>Delivery</h2><p>We deliver across Karachi. Delivery charges depend on your area and are shown at checkout. Orders above the free delivery limit are delivered free.</p><h2>Store Pickup</h2><p>Choose a pickup time at checkout and collect your packed order from our store.</p>'],
    ['Return & Exchange Policy', 'return-policy', '<p>Unused stationery and books can be exchanged within 7 days with the receipt. Grocery items can be returned at the time of delivery if damaged or expired.</p>'],
    ['Privacy Policy', 'privacy-policy', '<p>We only use your name, phone number and address to process and deliver your orders. We never sell your information.</p>'],
    ['Terms & Conditions', 'terms-and-conditions', '<p>Prices and availability may change without notice. Orders are confirmed by phone or WhatsApp before dispatch.</p>'],
    ['Frequently Asked Questions', 'faq', '<p>Answers to the questions our customers ask most.</p>'],
];
foreach ($pages as $i => [$t, $s, $c]) {
    $pid = db_insert('INSERT INTO pages (title, slug, content, sort_order) VALUES (?, ?, ?, ?)', [$t, $s, $c, $i]);
    if ($s === 'faq') {
        $faqs = [
            ['Do you offer cash on delivery?', 'Yes. Cash on Delivery is available for all home delivery orders.'],
            ['How does the Monthly Rashan builder work?', 'Open Monthly Rashan, add the grocery items you need, set your monthly budget and save the list. Next month you can reorder it with one click.'],
            ['Can I pick up my order from the store?', 'Yes. Select Store Pickup at checkout and choose a convenient time. We will notify you when your order is ready.'],
            ['Which areas do you deliver to?', 'We currently deliver across Karachi. Select your area at checkout to see the delivery charges.'],
        ];
        foreach ($faqs as $j => [$q, $a]) db_exec('INSERT INTO page_faqs (page_id, question, answer, sort_order) VALUES (?, ?, ?, ?)', [$pid, $q, $a, $j]);
    }
}

/* ---------------------------------------------------------------
 * SEO samples
 * --------------------------------------------------------------- */
seo_save('static', 0, ['seo_title' => 'Fahim Store - School Books, Stationery & Monthly Grocery Online in Karachi',
    'meta_description' => 'Order school books, stationery and complete monthly rashan online from Fahim Store. Home delivery & store pickup in Karachi. Cash on delivery.',
    'focus_keyword' => 'online grocery store karachi', 'secondary_keywords' => 'school books, stationery, monthly rashan'], 'home');
seo_save('category', $books, ['seo_title' => 'Buy School Books Online in Pakistan - Class 1 to Intermediate', 'meta_description' => 'Text books for all classes from Sindh & Punjab text book boards, Oxford and Cambridge. Fast delivery in Karachi.', 'focus_keyword' => 'school books online']);
seo_save('category', $groc, ['seo_title' => 'Online Grocery & Monthly Rashan Delivery in Karachi', 'meta_description' => 'Atta, rice, oil, ghee, daal, sugar, tea and household essentials delivered to your home.', 'focus_keyword' => 'grocery delivery karachi']);

/* ---------------------------------------------------------------
 * SUPPLIERS & A PURCHASE
 * --------------------------------------------------------------- */
$sup1 = db_insert("INSERT INTO suppliers (name, contact_person, phone, address) VALUES ('Urdu Bazar Book Distributors', 'Kashif', '0321-1111111', 'Urdu Bazar, Karachi')");
db_insert("INSERT INTO suppliers (name, contact_person, phone, address) VALUES ('Jodia Bazar Wholesale Grocery', 'Haji Rafiq', '0333-2222222', 'Jodia Bazar, Karachi')");
$purId = db_insert("INSERT INTO purchases (supplier_id, reference_no, purchase_date, total_amount, status, notes, admin_id) VALUES (?, 'UB-2231', CURDATE(), 0, 'draft', 'Restock Class 5 books', 1)", [$sup1]);
$tot = 0;
foreach (['Class 5 English', 'Class 5 Mathematics'] as $k) {
    $cost = 240;
    db_exec('INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, line_total) VALUES (?, ?, 20, ?, ?)', [$purId, $productIds[$k], $cost, $cost * 20]);
    $tot += $cost * 20;
}
db_exec('UPDATE purchases SET total_amount = ? WHERE id = ?', [$tot, $purId]);
purchase_receive($purId);

/* ---------------------------------------------------------------
 * CUSTOMERS & SAMPLE ORDERS
 * --------------------------------------------------------------- */
$cust = customer_register('Ayesha Khan', '03211234567', 'ayesha@example.com', 'Customer@123');
db_exec("INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_line, city, area, delivery_zone_id, is_default) VALUES (?, 'Home', 'Ayesha Khan', '03211234567', 'House 45, Block 10, Gulshan-e-Iqbal', 'Karachi', 'Gulshan-e-Iqbal', 1, 1)", [$cust['id']]);

$samples = [
    ['Ayesha Khan', '03211234567', 'rashan', 'delivery', [[$pi['atta'], 2, 2], [$pi['oil'], 3, 1], [$pi['tea'], 2, 1]], 'completed', 12],
    ['Muhammad Bilal', '03331112233', 'regular', 'pickup', [[$pi['Class 5 English'], 0, 2], [$pi['Class 5 Mathematics'], 0, 2]], 'completed', 9],
    ['Sana Ahmed', '03009876543', 'regular', 'delivery', [[$pi['surf'], 2, 2]], 'completed', 6],
    ['Imran Siddiqui', '03451239876', 'rashan', 'pickup', [[$pi['atta'], 3, 1], [$pi['tea'], 1, 2]], 'ready_for_pickup', 2],
    ['Hina Raza', '03121231234', 'regular', 'delivery', [[$pi['Class 9 Physics'], 0, 1], [$pi['Class 9 Chemistry'], 0, 1]], 'processing', 1],
    ['Ayesha Khan', '03211234567', 'rashan', 'delivery', [[$pi['atta'], 2, 1], [$pi['oil'], 2, 1], [$pi['surf'], 1, 1]], 'pending', 0],
];
foreach ($samples as [$n, $ph, $type, $ful, $items, $status, $daysAgo]) {
    $lines = [];
    foreach ($items as [$prod, $varIdx, $q]) {
        $vid = $varIdx ? (int) db_val('SELECT id FROM product_variations WHERE product_id = ? ORDER BY sort_order LIMIT 1 OFFSET ' . ($varIdx - 1), [$prod]) : 0;
        $lines[] = ['product_id' => $prod, 'variation_id' => $vid, 'quantity' => $q];
    }
    $r = order_create([
        'customer_id' => $ph === '03211234567' ? $cust['id'] : 0, 'name' => $n, 'phone' => $ph, 'fulfillment' => $ful, 'zone_id' => $ful === 'delivery' ? 1 : null,
        'address_line' => 'House 12, Street 4', 'pickup_at' => date('Y-m-d H:i', strtotime('+1 day 12:00')), 'payment_method' => $ful === 'delivery' ? 'cod' : 'cash_pickup',
        'order_type' => $type, 'channel' => 'pos', 'status' => 'draft', 'admin_id' => $adminId,
    ], $lines);
    if (!$r['ok']) { out('order failed: ' . $r['error']); continue; }
    db_exec("UPDATE orders SET channel = 'web', created_by_admin = NULL WHERE id = ?", [$r['order_id']]);
    db_exec("UPDATE order_status_history SET to_status = 'pending', note = 'Order placed online' WHERE order_id = ?", [$r['order_id']]);
    db_exec("UPDATE orders SET status = 'pending' WHERE id = ?", [$r['order_id']]);
    $_SESSION['admin_id'] = $adminId;
    order_deduct_stock($r['order_id']);
    $steps = $ful === 'pickup' ? ['confirmed', 'processing', 'ready_for_pickup', 'completed'] : ['confirmed', 'processing', 'out_for_delivery', 'completed'];
    if ($status !== 'pending') {
        foreach ($steps as $st) {
            order_update_status($r['order_id'], $st, '', false);
            if ($st === $status) break;
        }
    }
    $when = date('Y-m-d H:i:s', strtotime("-$daysAgo days -" . random_int(1, 8) . ' hours'));
    db_exec('UPDATE orders SET created_at = ? WHERE id = ?', [$when, $r['order_id']]);
    db_exec('UPDATE order_status_history SET created_at = ? WHERE order_id = ? AND from_status IS NULL', [$when, $r['order_id']]);
}
unset($_SESSION['admin_id']);
// A grocery list for the sample customer
$gl = db_insert('INSERT INTO grocery_lists (customer_id, name, monthly_budget, notes) VALUES (?, ?, ?, ?)', [$cust['id'], 'Monthly Rashan - Family', 30000, 'Deliver on 1st of every month']);
foreach ([[$pi['atta'], 2, 2], [$pi['oil'], 3, 1], [$pi['tea'], 2, 1]] as [$prod, $vi, $q]) {
    $vid = (int) db_val('SELECT id FROM product_variations WHERE product_id = ? ORDER BY sort_order LIMIT 1 OFFSET ' . ($vi - 1), [$prod]);
    db_exec('INSERT INTO grocery_list_items (list_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)', [$gl, $prod, $vid, $q]);
}
db_exec("INSERT INTO reviews (product_id, customer_id, reviewer_name, rating, title, body, status) VALUES (?, ?, 'Ayesha Khan', 5, 'Fresh atta', 'Always fresh and soft rotis. Delivered on time.', 'approved')", [$pi['atta'], $cust['id']]);
db_exec("UPDATE products SET rating_avg = 5, rating_count = 1 WHERE id = ?", [$pi['atta']]);
db_exec('DELETE FROM notifications');
notify_admin('new_order', 'Welcome to Fahim Store admin', 'Sample data has been installed. Update settings, delivery areas and products to get started.', 'settings.php');
db_exec('DELETE FROM login_attempts');
out('done');
