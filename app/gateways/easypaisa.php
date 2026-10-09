<?php
/**
 * Easypaisa "Easypay" Hosted Checkout adapter.
 *
 * Flow:
 *  1. Browser auto-posts to Index.jsf with an AES-encrypted merchantHashedReq.
 *  2. Easypay redirects to /payment/easypaisa/return?auth_token=… ; we auto-post
 *     the token to Confirm.jsf with postBackURL = /payment/easypaisa/complete.
 *  3. /payment/easypaisa/complete receives status + orderRefNumber from the
 *     browser — informational only. The order is marked paid only after the
 *     Inquire Transaction API (server-to-server, authenticated) returns PAID.
 *  4. The IPN endpoint /payment/easypaisa/ipn triggers the same inquiry.
 *
 * Verify field names, endpoints and status values against the Easypay
 * integration guide issued with your merchant account before going live.
 */

function easypaisa_endpoint(array $cfg, string $mode, string $kind = ''): string
{
    $key = ($mode === 'live' ? 'live' : 'sandbox') . ($kind ? '_' . $kind : '') . '_url';
    return (string) ($cfg[$key] ?? '');
}

/** merchantHashedReq: AES-128-ECB (PKCS5) of the sorted query string, base64. */
function easypaisa_hash_request(array $fields, string $hashKey): string
{
    ksort($fields);
    $pairs = [];
    foreach ($fields as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $cipher = strlen($hashKey) === 32 ? 'AES-256-ECB' : (strlen($hashKey) === 24 ? 'AES-192-ECB' : 'AES-128-ECB');
    return base64_encode((string) openssl_encrypt(implode('&', $pairs), $cipher, $hashKey, OPENSSL_RAW_DATA));
}

function easypaisa_build_request(array $payment, array $order, array $cfg, string $mode, bool $isCard): array
{
    $fields = [
        'amount' => number_format((float) $payment['amount'], 1, '.', ''),
        'autoRedirect' => '1',
        'emailAddr' => $order['email'],
        'expiryDate' => date('Ymd His', time() + 3600 * max(1, (int) ($cfg['expiry_hours'] ?? 2))),
        'mobileNum' => preg_replace('/[^0-9]/', '', preg_replace('/^\+92/', '0', $order['phone'])),
        'orderRefNum' => $payment['reference'],
        'paymentMethod' => $isCard ? 'CC_PAYMENT_METHOD' : 'MA_PAYMENT_METHOD',
        'postBackURL' => url('payment/easypaisa/return'),
        'storeId' => $cfg['store_id'],
    ];
    $fields['merchantHashedReq'] = easypaisa_hash_request(array_diff_key($fields, ['merchantHashedReq' => 1]), $cfg['hash_key']);
    payment_log($payment, 'initiate', 'redirected', ['amount' => (float) $payment['amount'], 'payload' => $fields]);
    db_exec("UPDATE payments SET status = 'processing' WHERE id = ? AND status = 'pending'", [$payment['id']]);
    return ['action' => easypaisa_endpoint($cfg, $mode), 'method' => 'POST', 'fields' => $fields];
}

/** Step 2: build the Confirm.jsf auto-post with the auth token. */
function easypaisa_confirm_form(string $authToken): ?array
{
    $gw = payment_gateway('easypaisa');
    if (!$gw || !preg_match('/^[A-Za-z0-9+\/=_\-.]{4,512}$/', $authToken)) {
        return null;
    }
    $cfg = gateway_config($gw);
    return [
        'action' => easypaisa_endpoint($cfg, $gw['mode'], 'confirm'),
        'method' => 'POST',
        'fields' => ['auth_token' => $authToken, 'postBackURL' => url('payment/easypaisa/complete')],
    ];
}

/**
 * Inquire Transaction API (REST).
 * @return array{status:string, code:?string, message:?string, amount:?float, provider_txn_id:?string, raw:?array}
 */
function easypaisa_inquire(array $payment, array $cfg, string $mode): array
{
    $url = easypaisa_endpoint($cfg, $mode, 'inquiry');
    $body = json_encode(['orderId' => $payment['reference'], 'storeId' => $cfg['store_id'], 'accountNum' => $cfg['account_num']]);
    $res = http_request('POST', $url, $body, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Credentials: ' . base64_encode($cfg['api_username'] . ':' . $cfg['api_password']),
    ]);
    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['status' => 'unknown', 'code' => (string) $res['status'], 'message' => $res['error'] ?: 'Unexpected inquiry response', 'amount' => null, 'provider_txn_id' => null, 'raw' => null];
    }
    $code = (string) ($data['responseCode'] ?? '');
    $txStatus = strtoupper((string) ($data['transactionStatus'] ?? ''));
    $status = 'unknown';
    if ($code === '0000') {
        if ($txStatus === 'PAID') {
            $status = 'paid';
        } elseif (in_array($txStatus, ['PENDING', 'INITIATED'], true)) {
            $status = 'pending';
        } elseif (in_array($txStatus, ['FAILED', 'DECLINED', 'EXPIRED', 'REVERSED'], true)) {
            $status = 'failed';
        } elseif ($txStatus === 'CANCELLED') {
            $status = 'cancelled';
        }
    }
    return [
        'status' => $status,
        'code' => $code . ($txStatus ? '/' . $txStatus : ''),
        'message' => $data['responseDesc'] ?? null,
        'amount' => isset($data['transactionAmount']) ? (float) $data['transactionAmount'] : null,
        'provider_txn_id' => $data['transactionId'] ?? null,
        'raw' => $data,
    ];
}

/** IPN: Easypay calls our endpoint with ?url=<their listener>. We only use it as a trigger. */
function easypaisa_handle_ipn(array $query): string
{
    $gw = payment_gateway('easypaisa');
    if (!$gw) {
        return 'disabled';
    }
    $cfg = gateway_config($gw);
    $ipnUrl = (string) ($query['url'] ?? '');
    $ref = null;
    $host = strtolower((string) parse_url($ipnUrl, PHP_URL_HOST));
    $trusted = array_filter(array_map('trim', explode(',', strtolower((string) $cfg['ipn_hosts']))));
    if ($ipnUrl !== '' && in_array($host, $trusted, true)) {
        $res = http_request('GET', $ipnUrl, null, ['Accept: application/json'], 15);
        $data = json_decode($res['body'], true);
        $ref = is_array($data) ? ($data['order_id'] ?? ($data['orderRefNumber'] ?? null)) : null;
    }
    $ref = $ref ?: ($query['orderRefNumber'] ?? null);
    $payment = $ref ? payment_by_reference((string) $ref) : null;
    if (!$payment || $payment['provider'] !== 'easypaisa') {
        app_log('payments', 'Easypaisa IPN for unknown reference', ['ref' => $ref]);
        return 'unknown';
    }
    payment_log($payment, 'callback', 'ipn_received', ['payload' => ['url_host' => $host]]);
    return payment_verify_with_provider($payment);
}
