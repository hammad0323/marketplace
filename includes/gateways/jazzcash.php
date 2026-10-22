<?php
/**
 * JazzCash Hosted Checkout ("Page Redirection", API v1.1).
 *
 * Flow:
 *  1. payment_start() → jazzcash_build_request(): a signed form auto-posted to JazzCash.
 *  2. Customer pays on JazzCash; JazzCash POSTs the result to api/payments/jazzcash-return.php
 *     (and to the IPN URL if configured in the merchant portal: api/payments/jazzcash-ipn.php).
 *  3. We verify pp_SecureHash, then confirm with a server-to-server Payment Inquiry
 *     before marking the order paid.
 *
 * Endpoints default to JazzCash's published URLs and can be overridden in Admin » Payments
 * if JazzCash issues different ones for your merchant account.
 */

function jazzcash_endpoints(array $pm, array $creds): array
{
    $sandbox = $pm['mode'] === 'sandbox';
    return [
        'checkout' => ($creds['endpoint'] ?? '') ?: ($sandbox
            ? 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
            : 'https://payments.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'),
        'inquiry' => ($creds['inquiry_endpoint'] ?? '') ?: ($sandbox
            ? 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire'
            : 'https://payments.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire'),
    ];
}

/**
 * pp_SecureHash: HMAC-SHA256 (key = integrity salt) over
 * salt & value1 & value2 … where values are the non-empty pp_* fields sorted by key.
 */
function jazzcash_hash(array $fields, string $salt): string
{
    $data = [];
    foreach ($fields as $k => $v) {
        if (stripos($k, 'pp_') === 0 && strcasecmp($k, 'pp_SecureHash') !== 0 && (string) $v !== '') {
            $data[$k] = (string) $v;
        }
    }
    ksort($data, SORT_STRING | SORT_FLAG_CASE);
    $string = $salt . '&' . implode('&', $data);
    return strtoupper(hash_hmac('sha256', $string, $salt));
}

function jazzcash_build_request(array $order, array $payment, array $pm, array $creds): array
{
    $now = time();
    $fields = [
        'pp_Version' => '1.1',
        'pp_TxnType' => '',
        'pp_Language' => 'EN',
        'pp_MerchantID' => $creds['merchant_id'],
        'pp_SubMerchantID' => '',
        'pp_Password' => $creds['password'],
        'pp_BankID' => '',
        'pp_ProductID' => '',
        'pp_TxnRefNo' => $payment['gateway_reference'],
        'pp_Amount' => (string) (int) round((float) $payment['amount'] * 100), // in paisa
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnDateTime' => date('YmdHis', $now),
        'pp_BillReference' => $order['order_number'],
        'pp_Description' => 'Order ' . $order['order_number'],
        'pp_TxnExpiryDateTime' => date('YmdHis', $now + 3600 * 24),
        'pp_ReturnURL' => url('api/payments/jazzcash-return.php'),
        'ppmpf_1' => $order['order_number'],
        'ppmpf_2' => '',
        'ppmpf_3' => '',
        'ppmpf_4' => '',
        'ppmpf_5' => '',
    ];
    $fields['pp_SecureHash'] = jazzcash_hash($fields, $creds['integrity_salt']);
    return ['type' => 'form', 'action' => jazzcash_endpoints($pm, $creds)['checkout'], 'fields' => $fields];
}

/**
 * Handles a POST from JazzCash (browser return or IPN). Returns
 * ['order' => row|null, 'state' => 'paid'|'failed'|'pending'|'invalid', 'message' => string]
 */
function jazzcash_handle_response(array $post, string $source): array
{
    $pm = payment_method_get('jazzcash');
    $creds = $pm ? payment_credentials($pm) : [];
    $ref = (string) ($post['pp_TxnRefNo'] ?? '');
    $payment = $ref !== '' ? payment_by_reference($ref) : null;
    $valid = $pm && !empty($creds['integrity_salt']) && isset($post['pp_SecureHash'])
        && hash_equals(jazzcash_hash($post, $creds['integrity_salt']), strtoupper((string) $post['pp_SecureHash']));

    payment_log('jazzcash', $source, [
        'payment_id' => $payment['id'] ?? null, 'order_id' => $payment['order_id'] ?? null, 'signature_valid' => $valid,
        'status' => $post['pp_ResponseCode'] ?? null, 'provider_txn_id' => $post['pp_RetreivalReferenceNo'] ?? null,
        'amount' => isset($post['pp_Amount']) ? ((float) $post['pp_Amount']) / 100 : null, 'payload' => $post,
        'message' => $post['pp_ResponseMessage'] ?? null,
    ]);

    if (!$valid || !$payment) {
        return ['order' => null, 'state' => 'invalid', 'message' => 'The payment response could not be verified.'];
    }
    $order = db_row('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]);
    $code = (string) ($post['pp_ResponseCode'] ?? '');

    if ($code === '000' || $code === '121') {
        $check = jazzcash_verify_payment($payment, $pm, $creds);
        $order = db_row('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]);
        return ['order' => $order, 'state' => $check['paid'] ?? false ? 'paid' : 'pending', 'message' => $check['message']];
    }
    if (in_array($code, ['124', '157'], true)) {
        // 124/157: transaction pending (e.g. voucher awaiting payment) — leave as pending.
        return ['order' => $order, 'state' => 'pending', 'message' => $post['pp_ResponseMessage'] ?? 'Payment pending.'];
    }
    payment_mark_failed((int) $payment['id'], 'failed', ($post['pp_ResponseMessage'] ?? 'Declined') . ' (code ' . $code . ')', $post['pp_RetreivalReferenceNo'] ?? null);
    return ['order' => $order, 'state' => 'failed', 'message' => $post['pp_ResponseMessage'] ?? 'Payment was not completed.'];
}

/** Server-to-server Payment Inquiry. Marks paid only on a confirmed successful status. */
function jazzcash_verify_payment(array $payment, array $pm, array $creds): array
{
    $req = [
        'pp_TxnRefNo' => $payment['gateway_reference'],
        'pp_MerchantID' => $creds['merchant_id'] ?? '',
        'pp_Password' => $creds['password'] ?? '',
    ];
    $req['pp_SecureHash'] = jazzcash_hash($req, $creds['integrity_salt'] ?? '');
    try {
        $res = http_request('POST', jazzcash_endpoints($pm, $creds)['inquiry'], json_encode($req), ['Content-Type: application/json']);
    } catch (Throwable $e) {
        payment_log('jazzcash', 'inquiry_error', ['payment_id' => (int) $payment['id'], 'order_id' => (int) $payment['order_id'], 'message' => $e->getMessage()]);
        return ['ok' => false, 'paid' => false, 'message' => 'Payment received by JazzCash is being confirmed. We will update your order shortly.'];
    }
    $j = $res['json'] ?? [];
    $sigOk = isset($j['pp_SecureHash']) ? hash_equals(jazzcash_hash($j, $creds['integrity_salt']), strtoupper((string) $j['pp_SecureHash'])) : null;
    payment_log('jazzcash', 'inquiry', [
        'payment_id' => (int) $payment['id'], 'order_id' => (int) $payment['order_id'], 'payload' => $j ?: $res['body'],
        'status' => $j['pp_PaymentResponseCode'] ?? ($j['pp_ResponseCode'] ?? null), 'signature_valid' => $sigOk,
        'message' => $j['pp_PaymentResponseMessage'] ?? ($j['pp_ResponseMessage'] ?? null),
    ]);
    $success = ($j['pp_ResponseCode'] ?? '') === '000'
        && (($j['pp_PaymentResponseCode'] ?? '') === '121' || strcasecmp((string) ($j['pp_Status'] ?? ''), 'Completed') === 0)
        && $sigOk !== false;
    if ($success) {
        $amount = isset($j['pp_Amount']) ? ((float) $j['pp_Amount']) / 100 : (float) $payment['amount'];
        payment_mark_paid((int) $payment['id'], $j['pp_RetreivalReferenceNo'] ?? ($j['pp_AuthCode'] ?? null), $amount, 'jazzcash inquiry');
        return ['ok' => true, 'paid' => true, 'message' => 'Payment confirmed.'];
    }
    return ['ok' => true, 'paid' => false, 'message' => 'JazzCash reports: ' . ($j['pp_PaymentResponseMessage'] ?? ($j['pp_ResponseMessage'] ?? 'not completed'))];
}
