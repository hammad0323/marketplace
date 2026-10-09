<?php
/**
 * Payment routes:
 *   /payment/start/{reference}         → auto-post to provider hosted checkout
 *   /payment/jazzcash/return  (POST)    → browser return (signature + inquiry)
 *   /payment/jazzcash/ipn     (POST)    → server notification
 *   /payment/easypaisa/return (GET)     → auth_token → Confirm.jsf
 *   /payment/easypaisa/complete (GET)   → browser return (inquiry decides)
 *   /payment/easypaisa/ipn    (GET)     → server notification trigger
 */
$seg = $GLOBALS['route']['segments'];
$action = implode('/', array_slice($seg, 0, 2));
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function render_autopost(array $redirect, string $message): void
{
    meta_set(['title' => 'Redirecting to secure payment', 'noindex' => true]); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Redirecting…</title>
<style>body{font-family:system-ui,sans-serif;background:#101D35;color:#F7F5F0;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center}.s{width:42px;height:42px;border:2px solid rgba(255,255,255,.2);border-top-color:#B99A5B;border-radius:50%;animation:r 1s linear infinite;margin:0 auto 20px}@keyframes r{to{transform:rotate(360deg)}}button{background:#214E9B;color:#fff;border:0;padding:12px 24px;margin-top:16px;cursor:pointer}</style></head>
<body><div><div class="s"></div><p><?= e($message) ?></p>
<form id="pay" method="<?= e($redirect['method']) ?>" action="<?= e($redirect['action']) ?>">
<?php foreach ($redirect['fields'] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
<noscript><button type="submit">Continue to payment</button></noscript>
</form></div><script>document.getElementById('pay').submit();</script></body></html>
<?php
    exit;
}

function payment_back_to_order(?array $payment, string $type, string $msg): void
{
    if ($payment) {
        $order = db_one('SELECT order_number FROM orders WHERE id = ?', [$payment['order_id']]);
        flash($type, $msg);
        redirect(path_url('order/' . $order['order_number']));
    }
    flash($type, $msg);
    redirect(path_url('track-order'));
}

switch (true) {
    case ($seg[0] ?? '') === 'start' && isset($seg[1]):
        $payment = payment_by_reference($seg[1]);
        $order = $payment ? db_one('SELECT * FROM orders WHERE id = ?', [$payment['order_id']]) : null;
        if (!$payment || !$order || !order_visible_to_visitor($order, null)) {
            not_found();
        }
        if ($payment['method'] === 'cod' || !in_array($payment['status'], ['pending', 'processing'], true) || $order['status'] !== 'pending') {
            redirect(path_url('order/' . $order['order_number']));
        }
        $redirectData = payment_build_redirect($payment, $order);
        if (!$redirectData || $redirectData['action'] === '') {
            app_log('payments', 'Gateway not configured at payment start', ['ref' => $payment['reference']]);
            payment_mark_failed((int) $payment['id'], 'failed', 'Payment gateway unavailable.');
            payment_back_to_order($payment, 'error', 'This payment method is temporarily unavailable. Your order was cancelled and no payment was taken — please choose another method.');
        }
        render_autopost($redirectData, 'Taking you to ' . payment_method_label($payment['method']) . ' secure checkout…');

    case $action === 'jazzcash/return':
        $r = jazzcash_handle_response($_POST, 'return');
        payment_back_to_order($r['payment'], $r['outcome'] === 'paid' ? 'success' : ($r['outcome'] === 'pending' ? 'info' : 'error'), $r['message']);

    case $action === 'jazzcash/ipn':
        $r = jazzcash_handle_response($_POST, 'ipn');
        header('Content-Type: application/json');
        echo json_encode(['pp_ResponseCode' => $r['outcome'] === 'invalid' ? '199' : '000', 'pp_ResponseMessage' => $r['outcome'], 'pp_SecureHash' => '']);
        exit;

    case $action === 'easypaisa/return':
        $form = easypaisa_confirm_form(input('auth_token', '', 'get'));
        if (!$form) {
            payment_back_to_order(null, 'error', 'The payment could not be continued. Please check your order status.');
        }
        render_autopost($form, 'Confirming your Easypaisa payment…');

    case $action === 'easypaisa/complete':
        $ref = input('orderRefNumber', '', 'get');
        $payment = $ref !== '' ? payment_by_reference($ref) : null;
        if (!$payment || $payment['provider'] !== 'easypaisa') {
            payment_back_to_order(null, 'error', 'We could not match this payment to an order.');
        }
        payment_log($payment, 'return', mb_substr(input('status', 'unknown', 'get'), 0, 30), ['message' => input('desc', '', 'get'), 'payload' => array_intersect_key($_GET, array_flip(['status', 'desc', 'orderRefNumber']))]);
        payment_verify_with_provider($payment);
        $payment = payment_by_reference($ref);
        if ($payment['status'] === 'paid') {
            payment_back_to_order($payment, 'success', 'Payment received — thank you!');
        }
        if (in_array($payment['status'], ['failed', 'cancelled'], true)) {
            payment_back_to_order($payment, 'error', 'The payment was not completed.');
        }
        payment_back_to_order($payment, 'info', 'Your payment is being confirmed with Easypaisa. We will email you once it is verified.');

    case $action === 'easypaisa/ipn':
        $result = easypaisa_handle_ipn($_GET);
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
}
not_found();
