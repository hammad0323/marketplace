<?php
/**
 * GET /api/search?q=...&suggest=1   -> instant suggestions (products + categories)
 * GET /api/search?popular=1          -> popular searches
 * GET /api/search?q=...              -> full JSON results (paged)
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

if (input('popular') === '1') {
    json_out(['ok' => true, 'popular' => search_popular(8)]);
}
$q = mb_substr(input('q'), 0, 100);
if (mb_strlen($q) < 2) {
    json_out(['ok' => true, 'products' => [], 'categories' => [], 'total' => 0]);
}
$ids = search_product_ids($q, ['limit' => 300]);
$total = count($ids);

$format = function (array $p) use ($q) {
    $price = product_display_price($p);
    return [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'name_html' => highlight($p['name'], $q),
        'url' => product_url($p),
        'image' => product_thumb_url($p),
        'price' => ($price['from'] ? 'From ' : '') . money($price['price']),
        'old_price' => $price['old'] ? money($price['old']) : null,
        'meta' => trim(($p['brand_name'] ?: '') . ($p['isbn'] ? ' · ISBN ' . $p['isbn'] : '') . (product_in_stock($p) ? '' : ' · Out of stock'), ' ·'),
        'in_stock' => product_in_stock($p),
    ];
};

if (input('suggest') === '1') {
    $products = $ids ? products_query(['ids' => array_slice($ids, 0, 7), 'sort' => 'ids', 'limit' => 7])['items'] : [];
    $cats = array_map(function ($c) use ($q) {
        return ['name' => $c['name'], 'label_html' => highlight($c['path_label'], $q), 'url' => category_url($c)];
    }, search_categories($q, 4));
    json_out(['ok' => true, 'products' => array_map($format, $products), 'categories' => $cats, 'total' => $total]);
}

$per = 24;
$page = max(1, input_int('page', 1));
$slice = array_slice($ids, ($page - 1) * $per, $per);
$products = $slice ? products_query(['ids' => $slice, 'sort' => 'ids', 'limit' => $per])['items'] : [];
json_out(['ok' => true, 'total' => $total, 'page' => $page, 'products' => array_map($format, $products)]);
