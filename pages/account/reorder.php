<?php
/** POST /my-account/reorder/ - add a past order's items to the cart */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
if (!is_post()) redirect('my-account/orders/');
csrf_check();
$o = db_row('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?', [input('order'), $c['id']]);
if (!$o) route_404();
$added = 0; $skipped = [];
foreach (order_items((int) $o['id']) as $it) {
    if (!$it['product_id']) { $skipped[] = $it['product_name']; continue; }
    $err = cart_set((int) $it['product_id'], (int) $it['variation_id'], (int) $it['quantity'], true);
    if ($err) $skipped[] = $it['product_name'] . ' (' . $err . ')'; else $added++;
}
if ($o['order_type'] === 'rashan') $_SESSION['checkout_order_type'] = 'rashan';
flash($skipped ? 'warning' : 'success', $added . ' items added to your cart.' . ($skipped ? ' Not available: ' . implode(', ', $skipped) : ''));
redirect('cart/');
