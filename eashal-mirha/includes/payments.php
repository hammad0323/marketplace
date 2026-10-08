<?php
/**
 * Payment methods: Cash on Delivery, EasyPaisa, JazzCash and Debit/Credit Card.
 *
 * Every wallet/card method can run in one of two modes (set in Admin → Payments):
 *   manual  – customer pays to your account number, enters the Transaction ID
 *             and (optionally) a screenshot; you verify and mark "Paid".
 *   gateway – customer is redirected to the JazzCash / EasyPaisa hosted
 *             checkout using YOUR merchant credentials.
 */

/** Returns the enabled methods in display order. */
function payment_methods(): array
{
    $defs = [
        'cod'       => ['icon' => 'cash'],
        'card'      => ['icon' => 'shield'],
        'jazzcash'  => ['icon' => 'phone'],
        'easypaisa' => ['icon' => 'phone'],
    ];
    $out = [];
    foreach ($defs as $key => $d) {
        if (setting('pay_' . $key . '_enabled', '0') !== '1') {
            continue;
        }
        $out[$key] = [
            'key'   => $key,
            'title' => setting('pay_' . $key . '_title', ucfirst($key)),
            'note'  => setting('pay_' . $key . '_note', ''),
            'mode'  => payment_mode($key),
            'icon'  => $d['icon'],
        ];
    }
    return $out;
}

/** cod | manual | gateway */
function payment_mode(string $key): string
{
    if ($key === 'cod') {
        return 'cod';
    }
    if ($key === 'card') {
        $m = setting('pay_card_mode', 'bank');
        if ($m === 'jazzcash' && jazzcash_ready()) {
            return 'gateway';
        }
        if ($m === 'easypaisa' && easypaisa_ready()) {
            return 'gateway';
        }
        return 'manual';
    }
    if (setting('pay_' . $key . '_mode', 'manual') === 'gateway') {
        if (($key === 'jazzcash' && jazzcash_ready()) || ($key === 'easypaisa' && easypaisa_ready())) {
            return 'gateway';
        }
    }
    return 'manual';
}

/** Account details shown to the customer for manual payment. */
function payment_account_lines(string $key): array
{
    if ($key === 'card') {
        return array_filter([
            'Bank'           => setting('pay_card_bank_name'),
            'Account Title'  => setting('pay_card_account_title'),
            'Account Number' => setting('pay_card_account_no'),
            'IBAN'           => setting('pay_card_iban'),
        ]);
    }
    return array_filter([
        'Account Title'  => setting('pay_' . $key . '_account_title'),
        'Account Number' => setting('pay_' . $key . '_account_no'),
    ]);
}

/* ---------------------------------------------------------------
 *  JazzCash — Page Redirection v1.1 (HMAC-SHA256 secure hash)
 * --------------------------------------------------------------- */
function jazzcash_ready(): bool
{
    return setting('pay_jazzcash_merchant_id') !== '' && setting('pay_jazzcash_password') !== '' && setting('pay_jazzcash_salt') !== '';
}

function jazzcash_endpoint(): string
{
    return setting('pay_jazzcash_sandbox', '1') === '1'
        ? 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
        : 'https://payments.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/';
}

function jazzcash_hash(array $data, string $salt): string
{
    ksort($data);
    $parts = [];
    foreach ($data as $k => $v) {
        if ($k === 'pp_SecureHash' || stripos($k, 'pp') !== 0 || $v === '' || $v === null) {
            continue;
        }
        $parts[] = $v;
    }
    return strtoupper(hash_hmac('sha256', $salt . '&' . implode('&', $parts), $salt));
}

/** @param string $txnType MWALLET (wallet) or MPAY (card) */
function jazzcash_fields(array $order, string $txnType): array
{
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $exp = (clone $now)->modify('+1 day');
    $ref = 'T' . $now->format('YmdHis') . random_int(10, 99);
    q('UPDATE orders SET txn_ref = ? WHERE id = ?', [$ref, $order['id']]);

    $data = [
        'pp_Version'           => '1.1',
        'pp_TxnType'           => $txnType,
        'pp_Language'          => 'EN',
        'pp_MerchantID'        => setting('pay_jazzcash_merchant_id'),
        'pp_SubMerchantID'     => '',
        'pp_Password'          => setting('pay_jazzcash_password'),
        'pp_BankID'            => 'TBANK',
        'pp_ProductID'         => 'RETL',
        'pp_TxnRefNo'          => $ref,
        'pp_Amount'            => (string)(int)round((float)$order['total'] * 100),
        'pp_TxnCurrency'       => 'PKR',
        'pp_TxnDateTime'       => $now->format('YmdHis'),
        'pp_BillReference'     => $order['order_no'],
        'pp_Description'       => 'Order ' . $order['order_no'],
        'pp_TxnExpiryDateTime' => $exp->format('YmdHis'),
        'pp_ReturnURL'         => abs_url('payment-return?gw=jazzcash'),
        'ppmpf_1'              => $order['order_no'],
        'ppmpf_2'              => '',
        'ppmpf_3'              => '',
        'ppmpf_4'              => '',
        'ppmpf_5'              => '',
    ];
    $data['pp_SecureHash'] = jazzcash_hash($data, setting('pay_jazzcash_salt'));
    return $data;
}

/** Validates the JazzCash response. Returns [order|null, status, message]. */
function jazzcash_handle_return(array $resp): array
{
    $ref = $resp['pp_TxnRefNo'] ?? '';
    $order = $ref !== '' ? row('SELECT * FROM orders WHERE txn_ref = ?', [$ref]) : null;
    if (!$order) {
        return [null, 'failed', 'We could not match this payment to an order.'];
    }
    $given = strtoupper($resp['pp_SecureHash'] ?? '');
    $calc = jazzcash_hash($resp, setting('pay_jazzcash_salt'));
    $code = $resp['pp_ResponseCode'] ?? '';
    $msg  = $resp['pp_ResponseMessage'] ?? '';
    q('UPDATE orders SET gateway_response = ? WHERE id = ?', [json_encode($resp), $order['id']]);

    if (!hash_equals($calc, $given)) {
        order_add_history((int)$order['id'], 'payment', 'JazzCash response failed hash verification.');
        return [$order, 'failed', 'Payment could not be verified.'];
    }
    if (in_array($code, ['000', '121'], true)) {
        q("UPDATE orders SET payment_status = 'paid', txn_id = ?, status = IF(status = 'pending', 'confirmed', status) WHERE id = ?", [$resp['pp_RetreivalReferenceNo'] ?? $ref, $order['id']]);
        order_add_history((int)$order['id'], 'paid', 'JazzCash payment received (' . $code . ').');
        return [$order, 'paid', 'Payment successful.'];
    }
    if ($code === '124') {
        q("UPDATE orders SET payment_status = 'pending_verification' WHERE id = ?", [$order['id']]);
        order_add_history((int)$order['id'], 'payment', 'JazzCash voucher issued, awaiting payment.');
        return [$order, 'pending', $msg ?: 'Awaiting payment.'];
    }
    q("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$order['id']]);
    order_add_history((int)$order['id'], 'payment', 'JazzCash payment failed: ' . $code . ' ' . $msg);
    return [$order, 'failed', $msg ?: 'Payment was not completed.'];
}

/* ---------------------------------------------------------------
 *  EasyPaisa — Easypay hosted checkout (AES-128-ECB merchant hash)
 * --------------------------------------------------------------- */
function easypaisa_ready(): bool
{
    return setting('pay_easypaisa_store_id') !== '' && setting('pay_easypaisa_hash_key') !== '';
}

function easypaisa_base(): string
{
    return setting('pay_easypaisa_sandbox', '1') === '1'
        ? 'https://easypaystg.easypaisa.com.pk/easypay/'
        : 'https://easypay.easypaisa.com.pk/easypay/';
}

/** @param string $method MA_PAYMENT_METHOD (wallet) or CC_PAYMENT_METHOD (card) */
function easypaisa_fields(array $order, string $method): array
{
    $ref = $order['order_no'];
    q('UPDATE orders SET txn_ref = ? WHERE id = ?', [$ref, $order['id']]);
    $data = [
        'amount'        => number_format((float)$order['total'], 1, '.', ''),
        'autoRedirect'  => '1',
        'emailAddr'     => (string)$order['email'],
        'expiryDate'    => date('Ymd His', strtotime('+1 day')),
        'mobileNum'     => preg_replace('~\D~', '', (string)$order['phone']),
        'orderRefNum'   => $ref,
        'paymentMethod' => $method,
        'postBackURL'   => abs_url('payment-return?gw=easypaisa&step=1'),
        'storeId'       => setting('pay_easypaisa_store_id'),
    ];
    ksort($data);
    $plain = urldecode(http_build_query($data));
    $enc = openssl_encrypt($plain, 'AES-128-ECB', setting('pay_easypaisa_hash_key'), OPENSSL_RAW_DATA);
    $data['merchantHashedReq'] = base64_encode((string)$enc);
    return $data;
}

/** Renders an auto-submitting form to a payment gateway and stops. */
function gateway_redirect(string $action, array $fields, string $label): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Redirecting to ' . e($label) . '…</title>'
        . '<style>body{background:#0b0b0b;color:#c9a24a;font-family:Georgia,serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}'
        . 'button{background:#c9a24a;border:0;padding:12px 28px;color:#0b0b0b;cursor:pointer;letter-spacing:2px;text-transform:uppercase;margin-top:18px}</style></head>'
        . '<body><form id="gw" method="post" action="' . e($action) . '">';
    foreach ($fields as $k => $v) {
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<div><p style="font-size:22px">Taking you to ' . e($label) . ' secure checkout…</p><button type="submit">Continue</button></div></form>'
        . '<script>document.getElementById("gw").submit();</script></body></html>';
    exit;
}

/** Sends the customer to the right gateway for this order. */
function start_gateway_payment(array $order): void
{
    $method = $order['payment_method'];
    $provider = $method === 'card' ? setting('pay_card_mode') : $method;
    if ($provider === 'jazzcash') {
        gateway_redirect(jazzcash_endpoint(), jazzcash_fields($order, $method === 'card' ? 'MPAY' : 'MWALLET'), 'JazzCash');
    }
    if ($provider === 'easypaisa') {
        gateway_redirect(easypaisa_base() . 'Index.jsf', easypaisa_fields($order, $method === 'card' ? 'CC_PAYMENT_METHOD' : 'MA_PAYMENT_METHOD'), 'EasyPaisa');
    }
    redirect('order-success/' . $order['order_no'] . '?k=' . $order['access_key']);
}
