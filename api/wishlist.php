<?php
/** POST /api/wishlist action=toggle&product_id= (logged-in customers) */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (!is_post()) json_error('Method not allowed', 405);
csrf_check();
$cid = customer_id();
if (!$cid) json_error('Please log in to use your wishlist.', 401);
$pid = input_int('product_id');
if (!$pid || !db_val("SELECT id FROM products WHERE id = ? AND deleted_at IS NULL", [$pid])) json_error('Invalid product.');
$wl = wishlist_get_id($cid);
if (db_val('SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wl, $pid])) {
    db_exec('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wl, $pid]);
    $added = false;
} else {
    db_exec('INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wl, $pid]);
    $added = true;
}
json_out(['ok' => true, 'added' => $added, 'count' => (int) db_val('SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id = ?', [$wl])]);
