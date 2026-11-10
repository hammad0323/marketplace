<?php
/**
 * POST /api/cart  action=add|update|remove|clear|coupon|remove_coupon|summary
 * All totals are recalculated on the server.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (!is_post()) json_error('Method not allowed', 405);
csrf_check();

$action = input('action');
$pid = input_int('product_id');
$vid = input_int('variation_id');
$qty = max(0, min(9999, input_int('quantity', 1)));

$summaryJson = function (array $extra = []) {
    $s = cart_summary(['fulfillment' => input('fulfillment', 'delivery') === 'pickup' ? 'pickup' : 'delivery', 'zone_id' => input_int('zone_id') ?: ((selected_zone()['id'] ?? 0))]);
    $lines = array_map(function ($l) {
        return ['product_id' => $l['product_id'], 'variation_id' => $l['variation_id'], 'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'], 'unit_price_fmt' => money($l['unit_price']), 'line_total' => $l['line_total'], 'line_total_fmt' => money($l['line_total']),
            'problem' => $l['problem'], 'stock' => $l['track_stock'] ? $l['stock'] : null];
    }, $s['lines']);
    return json_out(array_merge([
        'ok' => true, 'count' => $s['count'], 'lines' => $lines,
        'subtotal' => $s['subtotal'], 'subtotal_fmt' => money($s['subtotal']),
        'discount' => $s['discount'], 'discount_fmt' => '- ' . money($s['discount']),
        'coupon_code' => $s['coupon_code'], 'coupon_error' => $s['coupon_error'],
        'delivery_fee' => $s['delivery_fee'], 'delivery_fee_fmt' => $s['fulfillment'] === 'pickup' ? 'Free (pickup)' : ($s['delivery_fee'] > 0 ? money($s['delivery_fee']) : 'FREE'),
        'delivery_note' => $s['delivery_note'],
        'total' => $s['total'], 'total_fmt' => money($s['total']), 'issues' => $s['issues'],
    ], $extra));
};

switch ($action) {
    case 'add':
        if (!$pid) json_error('Invalid product.');
        $err = cart_set($pid, $vid, max(1, $qty), true);
        if ($err) json_error($err);
        json_out(['ok' => true, 'count' => cart_count(), 'message' => 'Added to cart']);
    case 'update':
        $err = cart_set($pid, $vid, $qty, false);
        if ($err) json_error($err, 400, ['count' => cart_count()]);
        $summaryJson();
    case 'remove':
        cart_remove($pid, $vid);
        $summaryJson(['message' => 'Item removed']);
    case 'clear':
        cart_clear();
        $summaryJson();
    case 'coupon':
        $code = strtoupper(input('code'));
        if ($code === '') json_error('Please enter a coupon code.');
        $sum = cart_summary(['coupon' => $code]);
        if ($sum['coupon_error']) json_error($sum['coupon_error']);
        cart_set_coupon($code);
        $summaryJson(['message' => 'Coupon applied: ' . $code]);
    case 'remove_coupon':
        cart_set_coupon(null);
        $summaryJson(['message' => 'Coupon removed']);
    case 'summary':
        $summaryJson();
    default:
        json_error('Unknown action.');
}
