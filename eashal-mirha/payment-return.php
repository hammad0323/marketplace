<?php
/**
 * Return URL for JazzCash / EasyPaisa hosted checkouts.
 *   /payment-return?gw=jazzcash
 *   /payment-return?gw=easypaisa&step=1  (auth_token → confirm)
 *   /payment-return?gw=easypaisa&step=2  (final status)
 */
define('NO_PRETTY_REDIRECT', true);
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

$gw = get('gw');

if ($gw === 'jazzcash') {
    [$order, $status, $msg] = jazzcash_handle_return($_POST ?: $_GET);
    if (!$order) {
        redirect('?pay=unknown');
    }
    redirect('order-success/' . $order['order_no'] . '?k=' . $order['access_key'] . '&pay=' . $status);
}

if ($gw === 'easypaisa') {
    if (get('step') === '1' && !empty($_REQUEST['auth_token'])) {
        // Step 2 of Easypay: post the token back to Confirm.jsf
        gateway_redirect(easypaisa_base() . 'Confirm.jsf', [
            'auth_token'  => (string)$_REQUEST['auth_token'],
            'postBackURL' => abs_url('payment-return?gw=easypaisa&step=2'),
        ], 'EasyPaisa');
    }
    $ref = (string)($_REQUEST['orderRefNumber'] ?? $_REQUEST['orderRefNum'] ?? '');
    $order = $ref !== '' ? row('SELECT * FROM orders WHERE order_no = ?', [$ref]) : null;
    if (!$order) {
        redirect('?pay=unknown');
    }
    $status = (string)($_REQUEST['status'] ?? '');
    q('UPDATE orders SET gateway_response = ? WHERE id = ?', [json_encode($_REQUEST), $order['id']]);
    if ($status === '0000') {
        // Easypay's redirect is not signed, so the payment is marked for verification
        // in the EasyPaisa merchant portal rather than auto-marked as paid.
        q("UPDATE orders SET payment_status = 'pending_verification' WHERE id = ? AND payment_status <> 'paid'", [$order['id']]);
        order_add_history((int)$order['id'], 'payment', 'EasyPaisa reported a successful payment — verify in merchant portal.');
        $result = 'received';
    } else {
        q("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'", [$order['id']]);
        order_add_history((int)$order['id'], 'payment', 'EasyPaisa payment not completed: ' . ($_REQUEST['desc'] ?? $status));
        $result = 'failed';
    }
    redirect('order-success/' . $order['order_no'] . '?k=' . $order['access_key'] . '&pay=' . $result);
}

redirect('');
