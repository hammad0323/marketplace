<?php
/**
 * Payment gateway framework.
 *
 * Gateways: cod, easypaisa, jazzcash, card (hosted card checkout routed through
 * the JazzCash or Easypaisa hosted page — card data never touches this server).
 *
 * Rules enforced here:
 *  - Secrets are encrypted at rest (APP_KEY) and never rendered back into HTML.
 *  - A gateway is only offered at checkout when enabled AND fully configured.
 *  - Orders are only marked paid after server-side verification
 *    (signed server-to-server notification or a status-inquiry API call),
 *    never on a browser redirect alone.
 */

require_once APP_PATH . '/gateways/jazzcash.php';
require_once APP_PATH . '/gateways/easypaisa.php';

/** Field definitions per gateway — drives the admin form and "configured" checks. */
function gateway_definitions(): array
{
    return [
        'cod' => [
            'label' => 'Cash on Delivery',
            'fields' => [
                'fee' => ['label' => 'Additional COD fee', 'type' => 'number', 'required' => false, 'help' => 'Added to the order total when COD is selected. 0 for none.'],
                'max_order_amount' => ['label' => 'Maximum order value for COD', 'type' => 'number', 'required' => false, 'help' => '0 = no limit.'],
                'instructions' => ['label' => 'Checkout note', 'type' => 'text', 'required' => false, 'help' => 'Shown under the COD option.'],
            ],
        ],
        'jazzcash' => [
            'label' => 'JazzCash (Hosted Checkout)',
            'fields' => [
                'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text', 'required' => true],
                'password' => ['label' => 'Password', 'type' => 'secret', 'required' => true],
                'integrity_salt' => ['label' => 'Integrity salt (hash key)', 'type' => 'secret', 'required' => true],
                'sandbox_url' => ['label' => 'Sandbox checkout URL', 'type' => 'url', 'required' => false, 'default' => 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'],
                'live_url' => ['label' => 'Live checkout URL', 'type' => 'url', 'required' => false, 'default' => 'https://payments.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'],
                'sandbox_inquiry_url' => ['label' => 'Sandbox status inquiry API', 'type' => 'url', 'required' => false, 'default' => 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire'],
                'live_inquiry_url' => ['label' => 'Live status inquiry API', 'type' => 'url', 'required' => false, 'default' => 'https://payments.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire'],
                'txn_type' => ['label' => 'Wallet transaction type', 'type' => 'select', 'options' => ['MWALLET' => 'MWALLET (mobile account)', '' => 'Let customer choose on JazzCash page'], 'required' => false, 'default' => 'MWALLET'],
                'expiry_hours' => ['label' => 'Payment link expiry (hours)', 'type' => 'number', 'required' => false, 'default' => '2'],
            ],
        ],
        'easypaisa' => [
            'label' => 'Easypaisa (Easypay Hosted Checkout)',
            'fields' => [
                'store_id' => ['label' => 'Store ID', 'type' => 'text', 'required' => true],
                'hash_key' => ['label' => 'Hash key', 'type' => 'secret', 'required' => true, 'help' => 'Provided by Easypaisa for the merchantHashedReq (AES) signature.'],
                'account_num' => ['label' => 'Merchant account number', 'type' => 'text', 'required' => true, 'help' => 'Used by the transaction inquiry API.'],
                'api_username' => ['label' => 'API username', 'type' => 'text', 'required' => true],
                'api_password' => ['label' => 'API password', 'type' => 'secret', 'required' => true],
                'sandbox_url' => ['label' => 'Sandbox checkout URL', 'type' => 'url', 'required' => false, 'default' => 'https://easypaystg.easypaisa.com.pk/easypay/Index.jsf'],
                'live_url' => ['label' => 'Live checkout URL', 'type' => 'url', 'required' => false, 'default' => 'https://easypay.easypaisa.com.pk/easypay/Index.jsf'],
                'sandbox_confirm_url' => ['label' => 'Sandbox confirm URL', 'type' => 'url', 'required' => false, 'default' => 'https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf'],
                'live_confirm_url' => ['label' => 'Live confirm URL', 'type' => 'url', 'required' => false, 'default' => 'https://easypay.easypaisa.com.pk/easypay/Confirm.jsf'],
                'sandbox_inquiry_url' => ['label' => 'Sandbox inquiry API', 'type' => 'url', 'required' => false, 'default' => 'https://easypaystg.easypaisa.com.pk/easypay-service/rest/v4/inquire-transaction'],
                'live_inquiry_url' => ['label' => 'Live inquiry API', 'type' => 'url', 'required' => false, 'default' => 'https://easypay.easypaisa.com.pk/easypay-service/rest/v4/inquire-transaction'],
                'ipn_hosts' => ['label' => 'Trusted IPN hosts', 'type' => 'text', 'required' => false, 'default' => 'easypay.easypaisa.com.pk,easypaystg.easypaisa.com.pk', 'help' => 'Comma-separated hosts allowed in IPN notification URLs.'],
                'expiry_hours' => ['label' => 'Payment link expiry (hours)', 'type' => 'number', 'required' => false, 'default' => '2'],
            ],
        ],
        'card' => [
            'label' => 'Debit / Credit Card (hosted)',
            'fields' => [
                'provider' => ['label' => 'Card processor', 'type' => 'select', 'options' => ['jazzcash' => 'JazzCash hosted card page (MPAY)', 'easypaisa' => 'Easypaisa hosted card page (CC)'], 'required' => true, 'default' => 'jazzcash',
                    'help' => 'Cards are entered on the provider\'s hosted page; the selected provider must be configured above and have card acquiring enabled on your merchant account.'],
            ],
        ],
    ];
}

function payment_gateway(string $code): ?array
{
    static $cache = [];
    if (!array_key_exists($code, $cache)) {
        $cache[$code] = db_one('SELECT * FROM payment_gateways WHERE code = ?', [$code]);
    }
    return $cache[$code];
}

/** Decrypted configuration (secrets included) — server-side use only. */
function gateway_config(array $gw): array
{
    $cfg = json_decode((string) $gw['config'], true) ?: [];
    $defs = gateway_definitions()[$gw['code']]['fields'] ?? [];
    foreach ($defs as $key => $def) {
        if (!isset($cfg[$key]) || $cfg[$key] === '') {
            $cfg[$key] = $def['default'] ?? '';
        }
        if ($def['type'] === 'secret') {
            $cfg[$key] = decrypt_secret($cfg[$key]);
        }
    }
    return $cfg;
}

/** Required credentials present? */
function gateway_is_configured(string $code): bool
{
    $gw = payment_gateway($code);
    if (!$gw) {
        return false;
    }
    if ($code === 'cod') {
        return true;
    }
    if ($code === 'card') {
        $provider = gateway_config($gw)['provider'] ?? '';
        return in_array($provider, ['jazzcash', 'easypaisa'], true) && gateway_is_configured($provider);
    }
    $cfg = gateway_config($gw);
    foreach (gateway_definitions()[$code]['fields'] as $key => $def) {
        if (!empty($def['required']) && trim((string) ($cfg[$key] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

function card_provider(): string
{
    $gw = payment_gateway('card');
    return $gw ? (gateway_config($gw)['provider'] ?? 'jazzcash') : 'jazzcash';
}

/** Payment options shown at checkout. */
function checkout_payment_methods(): array
{
    $out = [];
    foreach (db_all('SELECT * FROM payment_gateways WHERE is_enabled = 1 ORDER BY sort_order') as $gw) {
        if (!gateway_is_configured($gw['code'])) {
            continue;
        }
        $out[] = [
            'code' => $gw['code'],
            'name' => $gw['display_name'],
            'description' => $gw['description'],
            'sandbox' => $gw['code'] !== 'cod' && $gw['mode'] === 'sandbox',
            'instructions' => $gw['code'] === 'cod' ? (gateway_config($gw)['instructions'] ?? '') : '',
        ];
    }
    return $out;
}

/** Log every interaction with a provider for reconciliation. */
function payment_log(array $payment, string $type, string $status, array $extra = []): void
{
    $payload = $extra['payload'] ?? null;
    if (is_array($payload)) {
        // Never persist credentials/hashes that could be replayed.
        foreach (['pp_Password', 'password', 'pp_SecureHash', 'merchantHashedReq', 'hash_key', 'Credentials'] as $k) {
            if (isset($payload[$k])) {
                $payload[$k] = '[redacted]';
            }
        }
    }
    db_insert('payment_transactions', [
        'payment_id' => (int) $payment['id'],
        'order_id' => (int) $payment['order_id'],
        'txn_type' => $type,
        'status' => mb_substr($status, 0, 30),
        'amount' => $extra['amount'] ?? null,
        'provider_txn_id' => isset($extra['provider_txn_id']) ? mb_substr((string) $extra['provider_txn_id'], 0, 100) : null,
        'provider_code' => isset($extra['code']) ? mb_substr((string) $extra['code'], 0, 40) : null,
        'provider_message' => isset($extra['message']) ? mb_substr((string) $extra['message'], 0, 255) : null,
        'signature_valid' => $extra['signature_valid'] ?? null,
        'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'admin_id' => admin_id(),
        'ip_address' => client_ip(),
    ]);
}

function payment_by_reference(string $ref): ?array
{
    return db_one('SELECT * FROM payments WHERE reference = ?', [$ref]);
}

/**
 * Build the hosted-checkout redirect for an online payment.
 * @return array{action:string, method:string, fields:array}|null
 */
function payment_build_redirect(array $payment, array $order): ?array
{
    $provider = $payment['provider'];
    $gw = payment_gateway($provider);
    if (!$gw || !gateway_is_configured($provider)) {
        return null;
    }
    $cfg = gateway_config($gw);
    $isCard = $payment['method'] === 'card';
    if ($provider === 'jazzcash') {
        return jazzcash_build_request($payment, $order, $cfg, $gw['mode'], $isCard);
    }
    if ($provider === 'easypaisa') {
        return easypaisa_build_request($payment, $order, $cfg, $gw['mode'], $isCard);
    }
    return null;
}

/**
 * Ask the provider for the authoritative status of a payment and apply it.
 * Returns a short human-readable result.
 */
function payment_verify_with_provider(array $payment): string
{
    $gw = payment_gateway($payment['provider']);
    if (!$gw || !gateway_is_configured($payment['provider'])) {
        return 'Gateway is not configured — cannot verify.';
    }
    $cfg = gateway_config($gw);
    if ($payment['provider'] === 'jazzcash') {
        $r = jazzcash_inquire($payment, $cfg, $gw['mode']);
    } elseif ($payment['provider'] === 'easypaisa') {
        $r = easypaisa_inquire($payment, $cfg, $gw['mode']);
    } else {
        return 'This payment method has no provider inquiry.';
    }
    payment_log($payment, 'inquiry', $r['status'], ['code' => $r['code'] ?? null, 'message' => $r['message'] ?? null, 'provider_txn_id' => $r['provider_txn_id'] ?? null, 'amount' => $r['amount'] ?? null, 'payload' => $r['raw'] ?? null]);
    if ($r['status'] === 'paid') {
        payment_mark_paid((int) $payment['id'], (float) ($r['amount'] ?? $payment['amount']), $r['provider_txn_id'] ?? null, 'Payment verified with ' . ucfirst($payment['provider']) . ' (status inquiry).');
        return 'Paid — verified with provider.';
    }
    if (in_array($r['status'], ['failed', 'cancelled'], true)) {
        payment_mark_failed((int) $payment['id'], $r['status'], 'Provider reported the payment as ' . $r['status'] . '.');
        return 'Provider reports: ' . $r['status'] . '.';
    }
    if ($r['status'] === 'pending') {
        db_exec("UPDATE payments SET status = 'processing' WHERE id = ? AND status = 'pending'", [$payment['id']]);
    }
    return 'Provider status: ' . $r['status'] . ($r['message'] ? ' — ' . $r['message'] : '');
}

// ---- HTTP helpers for server-to-server calls ----------------------------------

function http_request(string $method, string $url, $body = null, array $headers = [], int $timeout = 20): array
{
    if (!preg_match('#^https://#i', $url)) {
        return ['status' => 0, 'body' => '', 'error' => 'Only HTTPS endpoints are allowed.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $resp === false ? '' : (string) $resp, 'error' => $err ?: null];
}

function payment_method_label(string $method): string
{
    $gw = payment_gateway($method);
    if ($gw) {
        return $gw['display_name'];
    }
    return ['cod' => 'Cash on Delivery', 'easypaisa' => 'Easypaisa', 'jazzcash' => 'JazzCash', 'card' => 'Debit / Credit Card'][$method] ?? ucfirst($method);
}
