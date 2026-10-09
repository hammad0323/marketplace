<?php
/**
 * JazzCash Hosted Checkout (Page Redirection, API v1.1).
 *
 * Flow: browser POSTs signed pp_* fields to JazzCash → customer pays →
 * JazzCash POSTs signed result back to /payment/jazzcash/return. We verify
 * the HMAC-SHA256 secure hash AND confirm with the Payment Inquiry API
 * server-to-server before marking the order paid. The IPN endpoint
 * (/payment/jazzcash/ipn) follows the same verification.
 *
 * Confirm endpoint URLs and field requirements against the integration
 * guide JazzCash issues with your merchant account before going live.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function jazzcash_endpoints(string $mode): array
{
    $base = $mode === 'live' ? 'https://payments.jazzcash.com.pk' : 'https://sandbox.jazzcash.com.pk';
    return [
        'form' => $base . '/CustomerPortal/transactionmanagement/merchantform/',
        'inquiry' => $base . '/ApplicationAPI/API/PaymentInquiry/Inquire',
    ];
}

/** Secure hash: salt & values of non-empty pp_* fields sorted by key, HMAC-SHA256 with the salt. */
function jazzcash_hash(array $fields, string $salt): string
{
    ksort($fields);
    $parts = [$salt];
    foreach ($fields as $k => $v) {
        if ($k === 'pp_SecureHash' || (string)$v === '') continue;
        if (!str_starts_with($k, 'pp_') && !str_starts_with($k, 'ppmpf_')) continue;
        $parts[] = $v;
    }
    return strtoupper(hash_hmac('sha256', implode('&', $parts), $salt));
}

function jazzcash_start(array $order, array $payment): void
{
    $g = payment_gateway('jazzcash');
    $c = $g['config'];
    $ref = payment_reference($payment, 'T');
    $fields = [
        'pp_Version' => '1.1',
        'pp_TxnType' => $c['txn_type'] ?? '',
        'pp_Language' => 'EN',
        'pp_MerchantID' => $c['merchant_id'],
        'pp_SubMerchantID' => '',
        'pp_Password' => $c['password'],
        'pp_BankID' => '',
        'pp_ProductID' => '',
        'pp_TxnRefNo' => $ref,
        'pp_Amount' => (string)(int)round((float)$payment['amount'] * 100),
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnDateTime' => date('YmdHis'),
        'pp_BillReference' => $order['order_number'],
        'pp_Description' => 'Ebaya order ' . $order['order_number'],
        'pp_TxnExpiryDateTime' => date('YmdHis', strtotime('+1 day')),
        'pp_ReturnURL' => abs_url('payment/jazzcash/return'),
        'ppmpf_1' => $order['order_number'],
        'ppmpf_2' => '', 'ppmpf_3' => '', 'ppmpf_4' => '', 'ppmpf_5' => '',
    ];
    $fields['pp_SecureHash'] = jazzcash_hash($fields, $c['integrity_salt']);
    payment_log('jazzcash', 'initiate', 'redirect', (int)$payment['id'], (int)$order['id'], $fields, $ref, null, (float)$payment['amount']);
    payment_redirect_form(jazzcash_endpoints($g['mode'])['form'], $fields);
}

/** Server-to-server status check. Returns ['paid'=>bool, 'final'=>bool, 'code'=>..., 'raw'=>[]] */
function jazzcash_inquiry(string $ref): array
{
    $g = payment_gateway('jazzcash');
    $c = $g['config'];
    $req = ['pp_TxnRefNo' => $ref, 'pp_MerchantID' => $c['merchant_id'], 'pp_Password' => $c['password']];
    $req['pp_SecureHash'] = jazzcash_hash($req, $c['integrity_salt']);
    $res = http_request('POST', jazzcash_endpoints($g['mode'])['inquiry'], json_encode($req), ['Content-Type: application/json']);
    $data = json_decode($res['body'], true) ?: [];
    $paid = ($data['pp_ResponseCode'] ?? '') === '000'
        && (($data['pp_PaymentResponseCode'] ?? '') === '121' || strcasecmp($data['pp_Status'] ?? '', 'Completed') === 0);
    return ['paid' => $paid, 'code' => $data['pp_PaymentResponseCode'] ?? ($data['pp_ResponseCode'] ?? ''), 'raw' => $data, 'http' => $res['status']];
}

/** Handle return POST or IPN. Returns the order (for redirecting the customer) or null. */
function jazzcash_handle(array $in, string $event): ?array
{
    $g = payment_gateway('jazzcash');
    $ref = (string)($in['pp_TxnRefNo'] ?? '');
    $payment = $ref !== '' ? db_one("SELECT * FROM payments WHERE gateway_reference = ? AND method = 'jazzcash'", [$ref]) : null;
    if (!$payment || !$g) {
        payment_log('jazzcash', $event, 'unknown_ref', null, null, $in, $ref, null, null, 'No matching payment');
        return null;
    }
    $order = db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
    $valid = isset($in['pp_SecureHash']) && hash_equals(jazzcash_hash($in, $g['config']['integrity_salt']), strtoupper((string)$in['pp_SecureHash']));
    $code = (string)($in['pp_ResponseCode'] ?? '');
    $amount = isset($in['pp_Amount']) ? ((int)$in['pp_Amount']) / 100 : null;
    payment_log('jazzcash', $event, $code ?: 'n/a', (int)$payment['id'], (int)$order['id'], $in, $ref, $valid, $amount, (string)($in['pp_ResponseMessage'] ?? ''));

    if (!$valid) return $order; // ignore tampered/unsigned data entirely

    if ($code === '000' || $code === '121') {
        $inq = jazzcash_inquiry($ref);
        payment_log('jazzcash', 'verify', $inq['paid'] ? 'paid' : 'unconfirmed', (int)$payment['id'], (int)$order['id'], $inq['raw'], $ref, null, $amount);
        if ($inq['paid']) {
            order_mark_paid((int)$payment['id'], (string)($in['pp_RetreivalReferenceNo'] ?? $ref), 'JazzCash', $amount);
        }
    } elseif (in_array($code, ['124', '157'], true)) {
        // Voucher issued / awaiting customer action: stay pending.
    } elseif ($code !== '') {
        order_mark_payment_failed((int)$payment['id'], (string)($in['pp_ResponseMessage'] ?? 'Declined'), $code === '110' ? 'cancelled' : 'failed');
    }
    return db_one('SELECT * FROM orders WHERE id = ?', [(int)$order['id']]);
}
