<?php
/**
 * Easypaisa IPN listener. Configure in the Easypay merchant portal:
 *   https://your-domain/api/payments/easypaisa-ipn.php
 * The notification only tells us *which* order to check; the result always comes from our
 * own server-to-server transaction inquiry.
 */
require dirname(__DIR__) . '/_init.php';
$ref = input('orderRefNumber') ?: input('orderRefNum');
$ipnUrl = input('url');
if ($ref === '' && $ipnUrl !== '') {
    $host = (string) parse_url($ipnUrl, PHP_URL_HOST);
    if (preg_match('/(^|\.)easypaisa\.com\.pk$/i', $host) && str_starts_with($ipnUrl, 'https://')) {
        try {
            $r = http_request('GET', $ipnUrl);
            $ref = (string) ($r['json']['order_id'] ?? ($r['json']['orderRefNumber'] ?? ''));
        } catch (Throwable $e) {
            payment_log('easypaisa', 'ipn_fetch_error', ['message' => $e->getMessage()]);
        }
    }
}
$payment = $ref !== '' ? payment_by_reference($ref) : null;
payment_log('easypaisa', 'ipn', ['payment_id' => $payment['id'] ?? null, 'order_id' => $payment['order_id'] ?? null, 'payload' => ['ref' => $ref]]);
if ($payment && $payment['status'] === 'pending') {
    $pm = payment_method_get('easypaisa');
    easypaisa_verify_payment($payment, $pm, payment_credentials($pm));
}
http_response_code(200);
echo 'OK';
