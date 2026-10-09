<?php
/** Live delivery/payment/total quote while the customer fills the checkout form. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!is_post()) json_out(['ok' => false], 405);
csrf_check();
rate_limit_or_fail('quote', 120, 60);

$in = [
    'city' => mb_substr(post('city'), 0, 100),
    'province' => in_list(post('province'), shipping_provinces(), ''),
    'method' => in_list(post('method'), ['standard', 'express'], 'standard'),
    'payment' => mb_substr(post('payment'), 0, 30),
    'email' => mb_substr(post('email'), 0, 190),
];
$lines = cart_lines();
$totals = checkout_totals($lines, $in + ['coupon' => cart_coupon(), 'customer_id' => customer_id(), 'estimate_default' => true]);
$methods = payment_methods_for_checkout($totals['zone'], $totals['grand_total']);
if (!isset($methods[$in['payment']])) {
    // Selected method no longer available (e.g. COD not offered here): recalc with the first available.
    $in['payment'] = (string)array_key_first($methods);
    $totals = checkout_totals($lines, $in + ['coupon' => cart_coupon(), 'customer_id' => customer_id(), 'estimate_default' => true]);
}
$render = function (string $tpl) use ($totals, $methods, $in) {
    ob_start();
    include ROOT_PATH . '/templates/' . $tpl . '.php';
    return ob_get_clean();
};
json_out(['ok' => true, 'shipping' => $render('checkout-shipping'), 'payment' => $render('checkout-payment'), 'totals' => $render('checkout-totals')]);
