<?php
/**
 * Payment routes:
 *   /payment/start/go/{order}?key=…        start or retry an online payment
 *   /payment/jazzcash/return|ipn           JazzCash response (signed POST)
 *   /payment/easypaisa/return|status|ipn   Easypay postbacks / IPN
 *   /payment/card/return|webhook           Stripe Checkout return / webhook
 * Callbacks are exempt from CSRF (they come from the gateway) but are
 * verified by signature and server-to-server status checks instead.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

header('Cache-Control: no-store');
$orderLink = fn(?array $o, string $extra = '') => $o ? url('order/' . $o['order_number'] . '?key=' . $o['lookup_key'] . $extra) : url();

switch ($gateway . '/' . $action) {
    case 'start/go':
        $order = $param ? db_one('SELECT * FROM orders WHERE order_number = ?', [strtoupper($param)]) : null;
        if (!$order || !hash_equals($order['lookup_key'], (string)get('key'))) not_found();
        rate_limit_or_fail('paystart', 15, 600);
        payment_start($order);
        exit;

    case 'jazzcash/return':
        $in = array_map(fn($v) => is_string($v) ? $v : '', $_POST);
        $order = $in ? jazzcash_handle($in, 'return') : null;
        redirect($order ? $orderLink($order, '&paid=' . ($order['payment_status'] === 'paid' ? '1' : '0')) : url());

    case 'jazzcash/ipn':
        $raw = file_get_contents('php://input');
        $in = json_decode($raw, true) ?: array_map(fn($v) => is_string($v) ? $v : '', $_POST);
        jazzcash_handle($in, 'callback');
        json_out(['pp_ResponseCode' => '000', 'pp_ResponseMessage' => 'Received']);

    case 'easypaisa/return':
        $token = preg_replace('/[^A-Za-z0-9\-_=+\/]/', '', (string)($_REQUEST['auth_token'] ?? ''));
        if ($token === '') redirect(url());
        easypaisa_confirm_step($token);
        exit;

    case 'easypaisa/status':
    case 'easypaisa/ipn':
        $ref = preg_replace('/[^A-Za-z0-9]/', '', (string)($_REQUEST['orderRefNumber'] ?? ($_REQUEST['orderRefNum'] ?? '')));
        $order = $ref !== '' ? easypaisa_verify($ref, $action === 'ipn' ? 'callback' : 'return', ['status' => (string)($_REQUEST['status'] ?? ''), 'desc' => (string)($_REQUEST['desc'] ?? '')]) : null;
        if ($action === 'ipn') json_out(['ok' => true]);
        redirect($order ? $orderLink($order, '&paid=' . ($order['payment_status'] === 'paid' ? '1' : '0')) : url());

    case 'card/return':
        $ref = preg_replace('/[^A-Za-z0-9]/', '', (string)get('ref'));
        $order = card_handle_return($ref, (string)get('session_id'), get('cancelled') === '1');
        redirect($order ? $orderLink($order, '&paid=' . ($order['payment_status'] === 'paid' ? '1' : '0')) : url());

    case 'card/webhook':
        if (!is_post()) not_found();
        $code = card_handle_webhook((string)file_get_contents('php://input'), (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
        http_response_code($code);
        echo $code === 200 ? 'ok' : 'invalid';
        exit;
}
not_found();
