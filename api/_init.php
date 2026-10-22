<?php
/** Shared bootstrap for payment callback endpoints. */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_init.php') {
    http_response_code(404);
    exit;
}
define('UC_ROOT', dirname(__DIR__));
// Gateways POST back cross-site, so the shopper's session cookie is not sent (SameSite=Lax).
// Starting a session here would replace it and sign the customer out, so callbacks run sessionless.
define('UC_NO_SESSION', true);
require UC_ROOT . '/includes/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

/** Sends the shopper to their order page after a gateway return. */
function payment_return_redirect(?array $order, string $state, string $message): never
{
    if (!$order) {
        redirect('track-order?payment=unverified');
    }
    $base = 'order/' . $order['order_number'] . '?t=' . $order['access_token'];
    if ($state === 'paid') {
        redirect($base . '&paid=1');
    }
    redirect($base . '&payment=' . ($state === 'failed' ? 'failed' : 'pending') . '&msg=' . rawurlencode(mb_substr($message, 0, 160)));
}
