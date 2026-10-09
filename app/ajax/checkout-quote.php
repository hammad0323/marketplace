<?php
if (!is_post()) {
    json_response(['ok' => false], 405);
}
require_csrf();
$customer = current_customer();
$q = checkout_quote(cart_lines(), [
    'city' => mb_substr(input('city'), 0, 100), 'region' => mb_substr(input('region'), 0, 100),
    'shipping_method' => input('shipping_method') === 'express' ? 'express' : 'standard',
    'payment_method' => input('payment_method'), 'coupon_code' => cart_coupon_code(),
    'customer_id' => customer_id(), 'email' => $customer['email'] ?? input('email'),
]);
json_response([
    'ok' => true,
    'options' => array_map(fn($o) => ['method' => $o['method'], 'name' => $o['name'], 'estimate' => $o['estimate'], 'cost' => $o['cost'], 'cost_label' => $o['cost'] > 0 ? money($o['cost']) : 'Free'], $q['shipping_options']),
    'selected' => $q['shipping']['method'] ?? null,
    'cod_available' => $q['cod_available'],
    'cod_message' => $q['cod_message'],
    'totals' => [
        'subtotal' => money($q['subtotal']), 'gift' => money($q['gift_wrap_total']), 'gift_raw' => $q['gift_wrap_total'],
        'discount' => '− ' . money($q['discount']), 'discount_raw' => $q['discount'],
        'shipping' => $q['shipping'] ? ($q['shipping_total'] > 0 ? money($q['shipping_total']) : 'Free') : '—',
        'cod' => money($q['cod_fee']), 'cod_raw' => $q['cod_fee'], 'total' => money($q['total']),
        'estimate' => $q['shipping'] ? 'Estimated delivery: ' . $q['shipping']['estimate'] : '',
    ],
    'errors' => array_values(array_filter($q['errors'], fn($e) => strpos($e, 'empty') === false)),
]);
