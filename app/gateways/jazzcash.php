<?php
/**
 * JazzCash Hosted Checkout (Page Redirection, API v1.1) adapter.
 *
 * Flow:
 *  1. Browser auto-posts a signed form to the JazzCash checkout URL.
 *  2. JazzCash posts the result back to /payment/jazzcash/return (browser) and,
 *     when configured in the merchant portal, to /payment/jazzcash/ipn (server).
 *  3. The return post is signature-checked and then CONFIRMED with the
 *     Status Inquiry API before the order is marked paid. A signed IPN is a
 *     server-to-server notification and is also confirmed via inquiry.
 *
 * IMPORTANT: field names, response codes and endpoints follow the JazzCash
 * merchant integration guide v1.1. Verify them against the guide issued with
 * your merchant account and test in sandbox before going live.
 */

/** HMAC-SHA256 secure hash: salt & sorted non-empty pp_* values. */
function jazzcash_secure_hash(array $fields, string $salt): string
{
    $data = [];
    foreach ($fields as $k => $v) {
        if (stripos($k, 'pp_') === 0 || stripos($k, 'ppmpf_') === 0) {
            if (strcasecmp($k, 'pp_SecureHash') === 0) {
                continue;
            }
            if ((string) $v !== '') {
                $data[$k] = (string) $v;
            }
        }
    }
    ksort($data, SORT_STRING | SORT_FLAG_CASE);
    $string = $salt . '&' . implode('&', $data);
    return strtoupper(hash_hmac('sha256', $string, $salt));
}

function jazzcash_endpoint(array $cfg, string $mode, string $kind = ''): string
{
    $key = ($mode === 'live' ? 'live' : 'sandbox') . ($kind ? '_' . $kind : '') . '_url';
    return (string) ($cfg[$key] ?? '');
}

function jazzcash_build_request(array $payment, array $order, array $cfg, string $mode, bool $isCard): array
{
    $now = time();
    $fields = [
        'pp_Version' => '1.1',
        'pp_TxnType' => $isCard ? 'MPAY' : (string) ($cfg['txn_type'] ?? 'MWALLET'),
        'pp_Language' => 'EN',
        'pp_MerchantID' => $cfg['merchant_id'],
        'pp_SubMerchantID' => '',
        'pp_Password' => $cfg['password'],
        'pp_BankID' => '',
        'pp_ProductID' => '',
        'pp_TxnRefNo' => $payment['reference'],
        'pp_Amount' => (string) (int) round((float) $payment['amount'] * 100), // paisa
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnDateTime' => date('YmdHis', $now),
        'pp_BillReference' => $order['order_number'],
        'pp_Description' => 'Order ' . $order['order_number'],
        'pp_TxnExpiryDateTime' => date('YmdHis', $now + 3600 * max(1, (int) ($cfg['expiry_hours'] ?? 2))),
        'pp_ReturnURL' => url('payment/jazzcash/return'),
        'ppmpf_1' => $order['phone'],
        'ppmpf_2' => '',
        'ppmpf_3' => '',
        'ppmpf_4' => '',
        'ppmpf_5' => '',
    ];
    $fields['pp_SecureHash'] = jazzcash_secure_hash($fields, $cfg['integrity_salt']);
    payment_log($payment, 'initiate', 'redirected', ['amount' => (float) $payment['amount'], 'payload' => $fields]);
    db_exec("UPDATE payments SET status = 'processing' WHERE id = ? AND status = 'pending'", [$payment['id']]);
    return ['action' => jazzcash_endpoint($cfg, $mode), 'method' => 'POST', 'fields' => $fields];
}

/**
 * Handle a JazzCash post (browser return or IPN).
 * @return array{payment:?array, message:string, outcome:string}
 */
function jazzcash_handle_response(array $post, string $channel): array
{
    $gw = payment_gateway('jazzcash');
    $ref = (string) ($post['pp_TxnRefNo'] ?? '');
    $payment = $ref !== '' ? payment_by_reference($ref) : null;
    if (!$gw || !$payment || $payment['provider'] !== 'jazzcash') {
        app_log('payments', 'JazzCash response for unknown reference', ['ref' => $ref, 'channel' => $channel]);
        return ['payment' => null, 'message' => 'We could not match this payment to an order.', 'outcome' => 'unknown'];
    }
    $cfg = gateway_config($gw);
    $valid = hash_equals(jazzcash_secure_hash($post, $cfg['integrity_salt']), strtoupper((string) ($post['pp_SecureHash'] ?? '')));
    $code = (string) ($post['pp_ResponseCode'] ?? '');
    $amount = isset($post['pp_Amount']) ? ((int) $post['pp_Amount']) / 100 : null;
    payment_log($payment, $channel === 'ipn' ? 'callback' : 'return', $valid ? ($code === '000' ? 'success_reported' : 'failure_reported') : 'invalid_signature', [
        'code' => $code, 'message' => $post['pp_ResponseMessage'] ?? null,
        'provider_txn_id' => $post['pp_RetreivalReferenceNo'] ?? ($post['pp_RetrievalReferenceNo'] ?? null),
        'amount' => $amount, 'signature_valid' => $valid ? 1 : 0, 'payload' => $post,
    ]);
    if (!$valid) {
        return ['payment' => $payment, 'message' => 'The payment response could not be verified.', 'outcome' => 'invalid'];
    }
    if ($amount !== null && abs($amount - (float) $payment['amount']) > 0.01) {
        return ['payment' => $payment, 'message' => 'Payment amount mismatch — our team will review this order.', 'outcome' => 'invalid'];
    }

    // Authoritative confirmation, server-to-server.
    $result = payment_verify_with_provider($payment);
    $fresh = payment_by_reference($ref);
    if ($fresh['status'] === 'paid') {
        return ['payment' => $fresh, 'message' => 'Payment received — thank you!', 'outcome' => 'paid'];
    }
    if (in_array($fresh['status'], ['failed', 'cancelled'], true)) {
        return ['payment' => $fresh, 'message' => 'The payment was not completed. ' . ($post['pp_ResponseMessage'] ?? ''), 'outcome' => 'failed'];
    }
    if ($code !== '000' && $code !== '124' && $code !== '') {
        // Provider reported failure on a signed response; inquiry did not say paid.
        payment_mark_failed((int) $payment['id'], 'failed', 'JazzCash: ' . ($post['pp_ResponseMessage'] ?? 'payment failed') . ' (' . $code . ')');
        return ['payment' => payment_by_reference($ref), 'message' => 'The payment was not completed: ' . ($post['pp_ResponseMessage'] ?? 'declined') . '.', 'outcome' => 'failed'];
    }
    return ['payment' => $fresh, 'message' => 'Your payment is being confirmed. We will email you as soon as it is verified. (' . $result . ')', 'outcome' => 'pending'];
}

/**
 * Status Inquiry API.
 * @return array{status:string, code:?string, message:?string, amount:?float, provider_txn_id:?string, raw:?array}
 */
function jazzcash_inquire(array $payment, array $cfg, string $mode): array
{
    $url = jazzcash_endpoint($cfg, $mode, 'inquiry');
    if ($url === '') {
        return ['status' => 'unknown', 'code' => null, 'message' => 'No inquiry URL configured', 'amount' => null, 'provider_txn_id' => null, 'raw' => null];
    }
    $req = [
        'pp_TxnRefNo' => $payment['reference'],
        'pp_MerchantID' => $cfg['merchant_id'],
        'pp_Password' => $cfg['password'],
    ];
    $req['pp_SecureHash'] = jazzcash_secure_hash($req, $cfg['integrity_salt']);
    $res = http_request('POST', $url, json_encode($req), ['Content-Type: application/json', 'Accept: application/json']);
    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['status' => 'unknown', 'code' => (string) $res['status'], 'message' => $res['error'] ?: 'Unexpected inquiry response', 'amount' => null, 'provider_txn_id' => null, 'raw' => null];
    }
    if (isset($data['pp_SecureHash']) && !hash_equals(jazzcash_secure_hash($data, $cfg['integrity_salt']), strtoupper((string) $data['pp_SecureHash']))) {
        return ['status' => 'unknown', 'code' => null, 'message' => 'Inquiry response signature invalid', 'amount' => null, 'provider_txn_id' => null, 'raw' => $data];
    }
    $code = (string) ($data['pp_ResponseCode'] ?? '');
    $payCode = (string) ($data['pp_PaymentResponseCode'] ?? '');
    $status = 'unknown';
    if ($code === '000') {
        // pp_PaymentResponseCode: 121 = completed; 124/157 = pending; others = failed/cancelled.
        if ($payCode === '121' || $payCode === '000') {
            $status = 'paid';
        } elseif (in_array($payCode, ['124', '157', ''], true)) {
            $status = 'pending';
        } else {
            $status = 'failed';
        }
    }
    $amount = isset($data['pp_Amount']) ? ((int) $data['pp_Amount']) / 100 : null;
    return [
        'status' => $status, 'code' => $code . ($payCode !== '' ? '/' . $payCode : ''),
        'message' => $data['pp_PaymentResponseMessage'] ?? ($data['pp_ResponseMessage'] ?? null),
        'amount' => $amount ?: (float) $payment['amount'],
        'provider_txn_id' => $data['pp_RetreivalReferenceNo'] ?? ($data['pp_AuthCode'] ?? null),
        'raw' => $data,
    ];
}
