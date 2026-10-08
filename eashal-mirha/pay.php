<?php
/**
 * (Re)starts an online payment for an order:  /pay/{order_no}?k={access_key}
 */
require __DIR__ . '/includes/bootstrap.php';

$order = row('SELECT * FROM orders WHERE order_no = ?', [get('no')]);
if (!$order || !(hash_equals($order['access_key'], (string)get('k')) || can_view_order($order))) {
    not_found();
}
if ($order['payment_status'] === 'paid' || payment_mode($order['payment_method']) !== 'gateway') {
    redirect('order-success/' . $order['order_no'] . '?k=' . $order['access_key']);
}
start_gateway_payment($order);
