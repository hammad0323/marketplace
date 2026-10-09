<?php
if (!is_post() || !wishlist_enabled()) {
    json_response(['ok' => false, 'message' => 'Not available.'], 405);
}
require_csrf();
$pid = input_int('product_id');
if (!product_by_id($pid)) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}
$added = wishlist_toggle($pid);
json_response(['ok' => true, 'added' => $added, 'count' => count(db_col('SELECT product_id FROM wishlist_items WHERE wishlist_id = ?', [wishlist_id()])),
    'message' => $added ? 'Saved to your wishlist.' : 'Removed from your wishlist.']);
