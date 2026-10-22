<?php
/**
 * Easypaisa (Easypay) hosted checkout return.
 *  step=token : Easypay sends auth_token → we post it to Confirm.jsf (required by Easypay's flow)
 *  step=done  : Easypay redirects back with the result → we verify by transaction inquiry
 */
require dirname(__DIR__) . '/_init.php';
$pm = payment_method_get('easypaisa');
$creds = $pm ? payment_credentials($pm) : [];
$step = input('step', 'done', 'get');

if ($step === 'token') {
    $token = input('auth_token');
    payment_log('easypaisa', 'auth_token', ['payload' => ['auth_token' => $token ? 'received' : 'missing', 'orderRefNum' => input('orderRefNum')]]);
    if (!$pm || $token === '') {
        payment_return_redirect(null, 'invalid', 'The payment session could not be started.');
    }
    $confirm = easypaisa_endpoints($pm, $creds)['confirm'];
    ?><!doctype html><html><head><meta charset="utf-8"><title>Redirecting…</title><meta name="robots" content="noindex"></head>
    <body style="font-family:sans-serif;text-align:center;padding:60px">
    <p>Completing your Easypaisa payment…</p>
    <form id="f" method="post" action="<?= e($confirm) ?>">
        <input type="hidden" name="auth_token" value="<?= e($token) ?>">
        <input type="hidden" name="postBackURL" value="<?= e(url('api/payments/easypaisa-return.php?step=done')) ?>">
        <noscript><button type="submit">Continue</button></noscript>
    </form>
    <script>document.getElementById('f').submit();</script>
    </body></html><?php
    exit;
}

$ref = input('orderRefNumber') ?: input('orderRefNum');
$payment = $ref !== '' ? payment_by_reference($ref) : null;
payment_log('easypaisa', 'return', [
    'payment_id' => $payment['id'] ?? null, 'order_id' => $payment['order_id'] ?? null, 'status' => input('status'),
    'payload' => ['status' => input('status'), 'desc' => input('desc'), 'orderRefNumber' => $ref],
]);
if (!$payment) {
    payment_return_redirect(null, 'invalid', 'Unknown payment reference.');
}
$order = db_row('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]);
// Never trust the redirect's status — always ask Easypaisa directly.
$check = easypaisa_verify_payment($payment, $pm, $creds);
$order = db_row('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]);
$state = !empty($check['paid']) ? 'paid' : (strtolower(input('status')) === 'success' ? 'pending' : 'failed');
payment_return_redirect($order, $state, $check['message'] ?? input('desc'));
