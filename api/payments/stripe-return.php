<?php
/** Stripe Checkout success URL — confirms the session with Stripe's API before showing "paid". */
require dirname(__DIR__) . '/_init.php';
$payment = payment_by_reference(input('ref', '', 'get'));
if (!$payment) {
    payment_return_redirect(null, 'invalid', 'Unknown payment reference.');
}
$pm = payment_method_get('card');
$sid = input('session_id', '', 'get');
$state = 'pending';
$msg = '';
if ($pm && preg_match('/^cs_[A-Za-z0-9_]+$/', $sid) && $sid === $payment['provider_txn_id']) {
    $res = stripe_verify_payment($payment, $pm, payment_credentials($pm));
    $state = !empty($res['paid']) ? 'paid' : 'pending';
    $msg = $res['message'] ?? '';
}
payment_return_redirect(db_row('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]), $state, $msg);
