<?php
/**
 * Easypaisa (Easypay) Hosted Checkout.
 *
 * Flow:
 *  1. Signed form auto-posted to Easypay Index.jsf (merchantHashedReq = AES-128-ECB of the
 *     sorted request string with the store's hash key, base64 encoded).
 *  2. Easypay posts auth_token back to api/payments/easypaisa-return.php?step=token, which
 *     posts it on to Confirm.jsf. Easypay then redirects to ...easypaisa-return.php?step=done.
 *  3. Easypay calls the IPN URL configured in the merchant portal (api/payments/easypaisa-ipn.php).
 *  4. In every case the order is only marked paid after the server-to-server
 *     "inquire transaction" API returns PAID for that order reference.
 */

function easypaisa_endpoints(array $pm, array $creds): array
{
    $sandbox = $pm['mode'] === 'sandbox';
    $base = $sandbox ? 'https://easypaystg.easypaisa.com.pk' : 'https://easypay.easypaisa.com.pk';
    return [
        'checkout' => ($creds['endpoint'] ?? '') ?: $base . '/easypay/Index.jsf',
        'confirm' => $base . '/easypay/Confirm.jsf',
        'inquiry' => ($creds['inquiry_endpoint'] ?? '') ?: $base . '/easypay-service/rest/v4/inquire-transaction',
    ];
}

function easypaisa_hash(array $fields, string $hashKey): string
{
    ksort($fields);
    $parts = [];
    foreach ($fields as $k => $v) {
        $parts[] = $k . '=' . $v;
    }
    $cipher = openssl_encrypt(implode('&', $parts), 'aes-128-ecb', substr(str_pad($hashKey, 16, "\0"), 0, 16), OPENSSL_RAW_DATA);
    return base64_encode($cipher);
}

function easypaisa_build_request(array $order, array $payment, array $pm, array $creds): array
{
    $fields = [
        'amount' => number_format((float) $payment['amount'], 1, '.', ''),
        'autoRedirect' => '1',
        'emailAddr' => $order['email'],
        'expiryDate' => date('Ymd His', time() + 86400),
        'mobileNum' => preg_replace('/\D/', '', $order['phone']),
        'orderRefNum' => $payment['gateway_reference'],
        'paymentMethod' => ($creds['payment_method'] ?? '') ?: 'MA_PAYMENT_METHOD',
        'postBackURL' => url('api/payments/easypaisa-return.php?step=token'),
        'storeId' => $creds['store_id'],
    ];
    $fields['merchantHashedReq'] = easypaisa_hash(array_diff_key($fields, ['merchantHashedReq' => 1]), $creds['hash_key']);
    return ['type' => 'form', 'action' => easypaisa_endpoints($pm, $creds)['checkout'], 'fields' => $fields];
}

/** Server-to-server transaction inquiry. */
function easypaisa_verify_payment(array $payment, array $pm, array $creds): array
{
    $body = json_encode([
        'orderId' => $payment['gateway_reference'],
        'storeId' => $creds['store_id'] ?? '',
        'accountNum' => $creds['account_number'] ?? '',
    ]);
    try {
        $res = http_request('POST', easypaisa_endpoints($pm, $creds)['inquiry'], $body, [
            'Content-Type: application/json',
            'Credentials: ' . base64_encode(($creds['username'] ?? '') . ':' . ($creds['password'] ?? '')),
        ]);
    } catch (Throwable $e) {
        payment_log('easypaisa', 'inquiry_error', ['payment_id' => (int) $payment['id'], 'order_id' => (int) $payment['order_id'], 'message' => $e->getMessage()]);
        return ['ok' => false, 'paid' => false, 'message' => 'We are confirming your Easypaisa payment. Your order will update shortly.'];
    }
    $j = $res['json'] ?? [];
    $status = strtoupper((string) ($j['transactionStatus'] ?? ''));
    payment_log('easypaisa', 'inquiry', [
        'payment_id' => (int) $payment['id'], 'order_id' => (int) $payment['order_id'], 'payload' => $j ?: $res['body'],
        'status' => $status ?: ($j['responseCode'] ?? null), 'provider_txn_id' => $j['transactionId'] ?? null,
        'amount' => isset($j['transactionAmount']) ? (float) $j['transactionAmount'] : null, 'message' => $j['responseDesc'] ?? null,
    ]);
    if (($j['responseCode'] ?? '') === '0000' && $status === 'PAID') {
        $amount = isset($j['transactionAmount']) ? (float) $j['transactionAmount'] : (float) $payment['amount'];
        payment_mark_paid((int) $payment['id'], $j['transactionId'] ?? null, $amount, 'easypaisa inquiry');
        return ['ok' => true, 'paid' => true, 'message' => 'Payment confirmed.'];
    }
    if (in_array($status, ['FAILED', 'REVERSED', 'EXPIRED', 'DROPPED'], true)) {
        payment_mark_failed((int) $payment['id'], 'failed', 'Easypaisa status ' . $status, $j['transactionId'] ?? null);
    }
    return ['ok' => true, 'paid' => false, 'message' => 'Easypaisa reports: ' . ($status ?: ($j['responseDesc'] ?? 'not completed'))];
}
