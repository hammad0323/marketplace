<?php
/**
 * Shared search/filter query builder used by pages/search.php (full page)
 * and ajax/search-results.php (AJAX filter refresh). Reads $_GET, sets
 * $results, $pg, $sort, $hasGeo for the including page to render.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

$destination = clean_input($_GET['destination'] ?? '');
$categorySlug = clean_input($_GET['category'] ?? '');
$citySlug = clean_input($_GET['city'] ?? '');
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$minRating = (float) ($_GET['min_rating'] ?? 0);
$verifiedOnly = !empty($_GET['verified']);
$guests = (int) ($_GET['guests'] ?? 0);
$sort = clean_input($_GET['sort'] ?? 'relevance');
$page = max(1, (int) ($_GET['page'] ?? 1));

$lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
$lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
$hasGeo = $lat !== null && $lng !== null;

$where = ['s.status = "approved"'];
$params = [];

if ($destination !== '') {
    $where[] = '(c.name LIKE ? OR s.title LIKE ? OR s.address LIKE ?)';
    $like = '%' . $destination . '%';
    array_push($params, $like, $like, $like);
}
if ($categorySlug !== '') {
    $where[] = 'cat.slug = ?';
    $params[] = $categorySlug;
}
if ($citySlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $citySlug;
}
if ($minPrice !== null) {
    $where[] = 's.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 's.price <= ?';
    $params[] = $maxPrice;
}
if ($minRating > 0) {
    $where[] = 's.avg_rating >= ?';
    $params[] = $minRating;
}
if ($verifiedOnly) {
    $where[] = 'p.is_verified = 1';
}
if ($guests > 0) {
    $where[] = '(s.max_guests IS NULL OR s.max_guests >= ?)';
    $params[] = $guests;
}

$distanceSelect = '';
$distanceParams = [];
if ($hasGeo) {
    $distanceSelect = ', (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(s.latitude)) * COS(RADIANS(s.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(s.latitude))))) AS distance_km';
    $distanceParams = [$lat, $lng, $lat];
}

$orderMap = [
    'price_low' => 's.price ASC',
    'price_high' => 's.price DESC',
    'rating' => 's.avg_rating DESC',
    'newest' => 's.created_at DESC',
    'distance' => $hasGeo ? 'distance_km ASC' : 's.is_featured DESC, s.avg_rating DESC',
    'relevance' => 's.is_featured DESC, s.avg_rating DESC',
];
$orderBy = $orderMap[$sort] ?? $orderMap['relevance'];
if ($hasGeo && $sort === 'relevance') {
    $orderBy = 'distance_km ASC, ' . $orderBy;
}

$whereSql = implode(' AND ', $where);
$baseFrom = 'FROM services s
     JOIN providers p ON p.id = s.provider_id
     LEFT JOIN cities c ON c.id = s.city_id
     LEFT JOIN categories cat ON cat.id = s.category_id
     WHERE ' . $whereSql;

$pg = paginate($conn, "SELECT COUNT(*) $baseFrom", $params, $page, 12);

$results = db_select(
    $conn,
    "SELECT s.*, c.name AS city_name, c.slug AS city_slug, cat.name AS category_name, cat.icon AS category_icon,
        p.business_name, p.slug AS provider_slug, p.is_verified,
        (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
        $distanceSelect
     $baseFrom
     ORDER BY $orderBy
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    array_merge($distanceParams, $params)
);

$activeCategory = $categorySlug !== '' ? db_select_one($conn, 'SELECT * FROM categories WHERE slug = ?', [$categorySlug]) : null;
$activeCity = $citySlug !== '' ? db_select_one($conn, 'SELECT * FROM cities WHERE slug = ?', [$citySlug]) : null;
$allCategories = db_select($conn, 'SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order');
$allCities = db_select($conn, 'SELECT id, name, slug FROM cities WHERE is_active = 1 ORDER BY sort_order');
