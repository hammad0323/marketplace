<?php
/** Custom Size Studio: size finder, size measurements, add made-to-measure item to bag. */
require __DIR__ . '/_init.php';

$action = input('action');
$product = db_row("SELECT * FROM products WHERE id = ? AND status = 'published'", [input_int('product_id')]);
$garment = $product && $product['custom_garment_id'] ? studio_garment((int) $product['custom_garment_id']) : null;
if (!$garment) {
    json_response(['ok' => false, 'message' => 'Custom sizing is not available for this product.'], 404);
}
if (!rate_limit('studio:' . client_ip(), 120, 60)) {
    json_response(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
}

switch ($action) {
    case 'recommend':
        $h = (float) input_float('height', 0);
        $rec = studio_recommend((int) $garment['id'], $h, (float) input_float('weight', 0), input('fit', 'regular'));
        if (!$rec) {
            json_response(['ok' => false, 'message' => 'We could not suggest a size. Please choose one manually.']);
        }
        json_response(['ok' => true, 'size' => $rec['size'], 'note' => $rec['note'], 'measurements' => studio_size_measurements((int) $garment['id'], $rec['size'], $h)]);
        // no break: json_response exits
    case 'add':
        ajax_require_post();
        $in = [
            'color' => input('color', '', 'post'), 'base_size' => input('base_size', '', 'post'), 'notes' => input('notes', '', 'post'),
            'style' => array_map('strval', array_filter((array) ($_POST['style'] ?? []), 'is_scalar')),
            'm' => array_map('strval', array_filter((array) ($_POST['m'] ?? []), 'is_scalar')),
            'save_profile' => input_bool('save_profile'), 'profile_name' => input('profile_name', '', 'post'),
        ];
        $res = cart_add_custom((int) $product['id'], $in, input_int('qty', 1));
        $res['count'] = cart_count();
        json_response($res, $res['ok'] ? 200 : 422);
}
json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
