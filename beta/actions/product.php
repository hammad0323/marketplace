<?php
require __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$do = $_GET['do'] ?? '';

if ($do === 'suggest') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $rows = db_fetch_all("SELECT p.name, p.slug, p.main_image, COALESCE(p.sale_price,p.regular_price) price
        FROM products p JOIN shops s ON s.id=p.shop_id
        WHERE p.status='active' AND s.status='active' AND p.name LIKE ? LIMIT 6", 's', ['%' . $q . '%']);
    foreach ($rows as &$r) { $r['image'] = product_image_or_default($r['main_image']); $r['url'] = base_url('product.php?slug=' . $r['slug']); $r['price_formatted'] = format_price($r['price']); }
    echo json_encode($rows);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
