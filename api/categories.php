<?php
/** GET /api/categories[?parent=ID] - public category tree / children (JSON) */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$fmt = function ($c) {
    return ['id' => (int) $c['id'], 'name' => $c['name'], 'slug' => $c['slug'], 'url' => category_url($c), 'image' => category_image_url($c),
        'product_count' => category_product_count((int) $c['id'])];
};
if (isset($_GET['parent'])) {
    $pid = input_int('parent') ?: null;
    json_out(['ok' => true, 'categories' => array_map($fmt, category_children($pid))]);
}
$walk = function (array $nodes) use (&$walk, $fmt) {
    return array_map(function ($n) use ($walk, $fmt) { return $fmt($n) + ['children' => $walk($n['children'])]; }, $nodes);
};
json_out(['ok' => true, 'categories' => $walk(category_tree(null, 0, 3))]);
