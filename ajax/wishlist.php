<?php
require __DIR__ . '/_init.php';
ajax_require_post();
if (!rate_limit('wish:' . client_ip(), 60, 60)) {
    json_response(['ok' => false, 'message' => 'Too many requests.'], 429);
}
$pid = input_int('product_id');
if (!db_val("SELECT 1 FROM products WHERE id = ? AND status = 'published'", [$pid])) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}
$added = wishlist_toggle($pid);
$wid = wishlist_id();
json_response([
    'ok' => true,
    'added' => $added,
    'count' => $wid ? (int) db_val('SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id = ?', [$wid]) : 0,
    'message' => $added ? 'Saved to your wishlist.' : 'Removed from your wishlist.',
]);
