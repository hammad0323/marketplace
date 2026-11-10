<?php
/** GET /api/products?category=ID&brand=ID&sort=&page=&ids=1,2 - public product listing (JSON) */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$per = max(1, min(48, input_int('per_page', 24)));
$page = max(1, input_int('page', 1));
$o = ['category_id' => input_int('category'), 'brand_ids' => input_int('brand') ? [input_int('brand')] : [], 'sort' => input('sort', 'newest'),
    'limit' => $per, 'offset' => ($page - 1) * $per, 'with_total' => true, 'rashan' => input('rashan') === '1'];
if (input('ids') !== '') {
    $o['ids'] = array_filter(array_map('intval', explode(',', input('ids'))));
    $o['sort'] = 'ids';
}
$r = products_query($o);
json_out(['ok' => true, 'total' => $r['total'], 'page' => $page, 'products' => array_map(function ($p) {
    $pr = product_display_price($p);
    return ['id' => (int) $p['id'], 'name' => $p['name'], 'url' => product_url($p), 'image' => product_thumb_url($p), 'price' => $pr['price'],
        'old_price' => $pr['old'], 'from' => $pr['from'], 'in_stock' => product_in_stock($p), 'has_variations' => (bool) $p['has_variations']];
}, $r['items'])]);
