<?php
/**
 * Cart API.  GET ?action=mini|count   POST action=add|update|remove|gift|coupon|coupon_remove
 */
$action = input('action', input('action', '', 'get'));
$respond = function (array $r, string $view = 'none') {
    $lines = cart_lines();
    if ($view !== 'none') {
        ob_start();
        partial('cart-contents', ['lines' => $lines, 'mode' => $view]);
        $r['html'] = ob_get_clean();
    }
    $r['count'] = array_sum(array_column($lines, 'quantity'));
    json_response($r, $r['ok'] ? 200 : 422);
};

if (!is_post()) {
    if ($action === 'mini') {
        $respond(['ok' => true], 'mini');
    }
    json_response(['ok' => true, 'count' => cart_count()]);
}
require_csrf();
if (!rate_limit('cart_api', session_id() ?: client_ip(), 120, 60)) {
    json_response(['ok' => false, 'message' => 'Please slow down.'], 429);
}
$view = in_array(input('view'), ['mini', 'page'], true) ? input('view') : 'none';
switch ($action) {
    case 'add':
        $vid = input_int('variant_id');
        $r = cart_add(input_int('product_id'), $vid > 0 ? $vid : null, max(1, input_int('quantity', 1)), (bool) input_bool('gift_wrap'));
        $respond($r, $view);
    case 'update':
        $respond(cart_update_qty(input_int('item_id'), input_int('quantity')), $view);
    case 'remove':
        $respond(cart_remove(input_int('item_id')), $view);
    case 'gift':
        $respond(cart_set_gift_wrap(input_int('item_id'), (bool) input_bool('on')), $view);
    case 'coupon':
        $code = strtoupper(mb_substr(input('code'), 0, 40));
        if (!rate_limit('coupon', client_ip(), 15, 600)) {
            $respond(['ok' => false, 'message' => 'Too many attempts. Please wait a few minutes.'], $view);
        }
        $lines = cart_lines();
        $sub = array_sum(array_column(array_filter($lines, fn($l) => !$l['error']), 'line_total'));
        [$coupon, $err] = coupon_validate($code, $sub, customer_id(), current_customer()['email'] ?? null);
        if (!$coupon) {
            $respond(['ok' => false, 'message' => $err], $view);
        }
        cart_set_coupon($coupon['code']);
        $respond(['ok' => true, 'message' => 'Coupon applied: ' . coupon_summary($coupon)], $view);
    case 'coupon_remove':
        cart_set_coupon(null);
        $respond(['ok' => true, 'message' => 'Coupon removed.'], $view);
}
json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
