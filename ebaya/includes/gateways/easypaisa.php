<?php
/**
 * Easypaisa (Easypay) Hosted Checkout.
 *
 * Flow: POST signed request to Index.jsf → Easypay redirects to our first
 * postback (/payment/easypaisa/return) with auth_token → we POST auth_token
 * to Confirm.jsf → Easypay redirects to /payment/easypaisa/status with the
 * result. Neither redirect is trusted: we call the Inquire Transaction API
 * server-to-server and only mark the order paid if it reports PAID with the
 * exact amount. The IPN endpoint (/payment/easypaisa/ipn) triggers the same
 * inquiry.
 *
 * Confirm endpoint URLs against the Easypay integration guide supplied with
 * your merchant account before going live.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function easypaisa_endpoints(string $mode): array
{
    $base = $mode === 'live' ? 'https://easypay.easypaisa.com.pk' : 'https://easypaystg.easypaisa.com.pk';
    return [
        'index' => $base . '/easypay/Index.jsf',
        'confirm' => $base . '/easypay/Confirm.jsf',
        'inquiry' => $base . '/easypay-service/rest/v4/inquire-transaction',
    ];
}

function easypaisa_hash(array $fields, string $key): string
{
    ksort($fields);
    $pairs = [];
    foreach ($fields as $k => $v) {
        if ((string)$v === '' || $k === 'merchantHashedReq') continue;
        $pairs[] = $k . '=' . $v;
    }
    $cipher = openssl_encrypt(implode('&', $pairs), 'aes-128-ecb', substr(str_pad($key, 16, "\0"), 0, 16), OPENSSL_RAW_DATA);
    return base64_encode((string)$cipher);
}

function easypaisa_start(array $order, array $payment): void
{
    $g = payment_gateway('easypaisa');
    $c = $g['config'];
    $ref = payment_reference($payment, 'E');
    $fields = [
        'amount' => number_format((float)$payment['amount'], 1, '.', ''),
        'storeId' => $c['store_id'],
        'postBackURL' => abs_url('payment/easypaisa/return'),
        'orderRefNum' => $ref,
        'expiryDate' => date('Ymd His', strtotime('+1 day')),
        'autoRedirect' => '1',
        'paymentMethod' => $c['payment_method'] ?? '',
        'emailAddr' => $order['email'],
        'mobileNum' => substr(preg_replace('/\D/', '', $order['phone']), -11),
    ];
    $fields['merchantHashedReq'] = easypaisa_hash($fields, $c['hash_key']);
    payment_log('easypaisa', 'initiate', 'redirect', (int)$payment['id'], (int)$order['id'], $fields, $ref, null, (float)$payment['amount']);
    payment_redirect_form(easypaisa_endpoints($g['mode'])['index'], array_filter($fields, fn($v) => $v !== ''));
}

/** Step 2: forward the auth token to Easypay's confirm page. */
function easypaisa_confirm_step(string $authToken): void
{
    $g = payment_gateway('easypaisa');
    payment_redirect_form(easypaisa_endpoints($g['mode'])['confirm'], [
        'auth_token' => $authToken,
        'postBackURL' => abs_url('payment/easypaisa/status'),
    ]);
}

function easypaisa_inquiry(string $ref): array
{
    $g = payment_gateway('easypaisa');
    $c = $g['config'];
    $body = json_encode(['orderId' => $ref, 'storeId' => $c['store_id'], 'accountNum' => $c['account_number']]);
    $res = http_request('POST', easypaisa_endpoints($g['mode'])['inquiry'], $body, [
        'Content-Type: application/json',
        'Credentials: ' . base64_encode($c['api_username'] . ':' . $c['api_password']),
    ]);
    $data = json_decode($res['body'], true) ?: [];
    $status = strtoupper((string)($data['transactionStatus'] ?? ''));
    return [
        'paid' => ($data['responseCode'] ?? '') === '0000' && $status === 'PAID',
        'failed' => in_array($status, ['FAILED', 'REVERSED', 'EXPIRED', 'DROPPED'], true),
        'amount' => isset($data['transactionAmount']) ? (float)$data['transactionAmount'] : null,
        'txn' => $data['transactionId'] ?? null,
        'status' => $status ?: 'UNKNOWN',
        'raw' => $data,
    ];
}

/** Verify a reference with the inquiry API and update the order. */
function easypaisa_verify(string $ref, string $event, array $in = []): ?array
{
    $payment = db_one("SELECT * FROM payments WHERE gateway_reference = ? AND method = 'easypaisa'", [$ref]);
    if (!$payment) {
        payment_log('easypaisa', $event, 'unknown_ref', null, null, $in, $ref);
        return null;
    }
    payment_log('easypaisa', $event, (string)($in['status'] ?? 'received'), (int)$payment['id'], (int)$payment['order_id'], $in, $ref);
    $inq = easypaisa_inquiry($ref);
    payment_log('easypaisa', 'verify', $inq['status'], (int)$payment['id'], (int)$payment['order_id'], $inq['raw'], $ref, null, $inq['amount']);
    if ($inq['paid']) {
        order_mark_paid((int)$payment['id'], $inq['txn'], 'Easypaisa', $inq['amount']);
    } elseif ($inq['failed']) {
        order_mark_payment_failed((int)$payment['id'], 'Easypaisa status ' . $inq['status']);
    }
    return db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
}
