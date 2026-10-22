<?php
/** Instant search suggestions. */
require __DIR__ . '/_init.php';
$q = substr(input('q', '', 'get'), 0, 80);
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'products' => [], 'categories' => []]);
}
if (!rate_limit('search:' . client_ip(), 90, 60)) {
    json_response(['ok' => false, 'products' => [], 'categories' => []], 429);
}
$res = products_search(['q' => $q], 'featured', 6);
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$cats = db_all('SELECT name, slug FROM categories WHERE is_active = 1 AND name LIKE ? ORDER BY sort_order LIMIT 4', [$like]);
json_response([
    'ok' => true,
    'total' => $res['total'],
    'all_url' => url('search?q=' . urlencode($q)),
    'products' => array_map(fn($p) => ['name' => $p['name'], 'url' => $p['url'], 'image' => img_sized($p['image'], 200), 'price' => money($p['price_info']['final'])], $res['items']),
    'categories' => array_map(fn($c) => ['name' => $c['name'], 'url' => url('category/' . $c['slug'])], $cats),
]);
