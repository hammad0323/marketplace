<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!is_post()) json_out(['ok' => false], 405);
csrf_check();
rate_limit_or_fail('wishlist', 60, 60);
$pid = (int)post('product_id');
if (!db_val("SELECT id FROM products WHERE id = ? AND status = 'published'", [$pid])) json_out(['ok' => false, 'message' => 'Product not found.'], 404);
$added = wishlist_toggle($pid);
json_out(['ok' => true, 'added' => $added, 'count' => count(customer_id() ? db_col('SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ?', [customer_id()]) : ($_SESSION['wishlist'] ?? [])),
    'message' => $added ? 'Saved to your wishlist' : 'Removed from your wishlist']);
