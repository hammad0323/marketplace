<?php
/**
 * JazzCash browser return (pp_ReturnURL). The response hash is verified and the payment is
 * confirmed with a server-to-server inquiry before the order is marked paid.
 */
require dirname(__DIR__) . '/_init.php';
if (!is_post()) {
    redirect('');
}
$res = jazzcash_handle_response($_POST, 'return');
payment_return_redirect($res['order'], $res['state'], $res['message']);
