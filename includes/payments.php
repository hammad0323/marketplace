<?php
/**
 * Payment methods, payment state changes and the transaction log.
 * Gateway-specific code lives in includes/gateways/.
 *
 * Rules enforced here:
 *  - An order is only marked paid from a server-side verification (signed callback/webhook
 *    or a server-to-server status inquiry), never from a browser redirect alone.
 *  - Marking paid is idempotent (row lock + status check), so duplicate callbacks are harmless.
 *  - The paid amount must match the amount we asked the gateway to collect.
 */

require_once __DIR__ . '/gateways/jazzcash.php';
require_once __DIR__ . '/gateways/easypaisa.php';
require_once __DIR__ . '/gateways/stripe.php';

/** Credential fields each gateway needs. 'secret' fields are never echoed back to the admin form. */
function payment_gateway_fields(): array
{
    return [
        'jazzcash' => [
            'merchant_id' => ['label' => 'Merchant ID', 'secret' => false, 'required' => true],
            'password' => ['label' => 'Password', 'secret' => true, 'required' => true],
            'integrity_salt' => ['label' => 'Integrity Salt (Hash Key)', 'secret' => true, 'required' => true],
            'endpoint' => ['label' => 'Checkout endpoint (leave blank for default)', 'secret' => false, 'required' => false],
            'inquiry_endpoint' => ['label' => 'Status-inquiry endpoint (leave blank for default)', 'secret' => false, 'required' => false],
        ],
        'easypaisa' => [
            'store_id' => ['label' => 'Store ID', 'secret' => false, 'required' => true],
            'hash_key' => ['label' => 'Hash Key', 'secret' => true, 'required' => true],
            'username' => ['label' => 'API Username (transaction inquiry)', 'secret' => false, 'required' => true],
            'password' => ['label' => 'API Password', 'secret' => true, 'required' => true],
            'account_number' => ['label' => 'Merchant Account Number', 'secret' => false, 'required' => true],
            'payment_method' => ['label' => 'Payment method (MA_PAYMENT_METHOD, CC_PAYMENT_METHOD, OTC_PAYMENT_METHOD)', 'secret' => false, 'required' => false],
            'endpoint' => ['label' => 'Checkout endpoint (leave blank for default)', 'secret' => false, 'required' => false],
            'inquiry_endpoint' => ['label' => 'Inquiry endpoint (leave blank for default)', 'secret' => false, 'required' => false],
        ],
        'card' => [
            'secret_key' => ['label' => 'Stripe Secret Key (sk_...)', 'secret' => true, 'required' => true],
            'webhook_secret' => ['label' => 'Webhook Signing Secret (whsec_...)', 'secret' => true, 'required' => true],
            'currency' => ['label' => 'Charge currency (ISO code, e.g. pkr, usd)', 'secret' => false, 'required' => false],
        ],
        'cod' => [],
    ];
}

function payment_methods_all(): array
{
    return db_all('SELECT * FROM payment_methods ORDER BY sort_order, id');
}

function payment_method_get(string $code): ?array
{
    static $cache = [];
    if (!array_key_exists($code, $cache)) {
        $cache[$code] = db_row('SELECT * FROM payment_methods WHERE code = ?', [$code]);
    }
    return $cache[$code];
}

function payment_credentials(array $pm): array
{
    $json = decrypt_secret($pm['credentials_encrypted'] ?? null);
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) ? $data : [];
}

function payment_gateway_configured(array $pm): bool
{
    $creds = payment_credentials($pm);
    foreach (payment_gateway_fields()[$pm['code']] ?? [] as $key => $f) {
        if ($f['required'] && trim((string) ($creds[$key] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

/**
 * Status shown in admin and at checkout:
 *  ready        — customers can use it
 *  testing      — sandbox mode: visible only to signed-in admins so they can test
 *  unverified   — live credentials saved but "testing completed" not confirmed
 *  unconfigured — credentials missing
 *  disabled
 */
function payment_method_state(array $pm): string
{
    if (!(int) $pm['is_enabled']) {
        return 'disabled';
    }
    if ($pm['code'] === 'cod') {
        return 'ready';
    }
    if (!payment_gateway_configured($pm)) {
        return 'unconfigured';
    }
    if ($pm['mode'] === 'sandbox') {
        return 'testing';
    }
    return (int) $pm['is_verified'] ? 'ready' : 'unverified';
}

/** Returns true or a customer-facing reason why the method cannot be used. */
function payment_method_available(?array $pm, float $total, array $shipping)
{
    if (!$pm) {
        return 'Please choose a payment method.';
    }
    $state = payment_method_state($pm);
    $usable = $state === 'ready' || ($state === 'testing' && current_admin());
    if (!$usable) {
        return $pm['name'] . ' is currently unavailable. Please choose another payment method.';
    }
    if ($pm['code'] === 'cod' && empty($shipping['cod_available'])) {
        return 'Cash on Delivery is not available for this delivery area.';
    }
    if ($pm['min_order'] !== null && $total < (float) $pm['min_order']) {
        return $pm['name'] . ' is available for orders above ' . money($pm['min_order']) . '.';
    }
    if ($pm['max_order'] !== null && $total > (float) $pm['max_order']) {
        return $pm['name'] . ' is available for orders up to ' . money($pm['max_order']) . '.';
    }
    return true;
}

/** Unique reference sent to the gateway for one payment attempt. */
function payment_reference(string $orderNumber): string
{
    return 'T' . date('ymdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function payment_log(string $gateway, string $event, array $d = []): void
{
    $payload = $d['payload'] ?? null;
    if (is_array($payload)) {
        // Never log secrets that a gateway might echo back.
        foreach (['pp_Password', 'password', 'card_number', 'cvv', 'pp_SecureHash'] as $k) {
            if (isset($payload[$k])) {
                $payload[$k] = '***';
            }
        }
        $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    try {
        db_insert('payment_transactions', [
            'payment_id' => $d['payment_id'] ?? null,
            'order_id' => $d['order_id'] ?? null,
            'gateway' => $gateway,
            'event_type' => $event,
            'status' => $d['status'] ?? null,
            'amount' => $d['amount'] ?? null,
            'provider_txn_id' => $d['provider_txn_id'] ?? null,
            'signature_valid' => isset($d['signature_valid']) ? (int) $d['signature_valid'] : null,
            'payload' => $payload !== null ? substr((string) $payload, 0, 60000) : null,
            'message' => isset($d['message']) ? substr($d['message'], 0, 255) : null,
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('payment_log failed: ' . $e->getMessage());
    }
}

function payment_by_reference(string $ref): ?array
{
    return db_row('SELECT * FROM payments WHERE gateway_reference = ?', [$ref]);
}

/** Creates a fresh payment attempt for an unpaid order (used for "retry payment"). */
function payment_new_attempt(array $order): array
{
    db_exec("UPDATE payments SET status = 'cancelled' WHERE order_id = ? AND status IN ('pending','failed') AND method = ?", [(int) $order['id'], $order['payment_method']]);
    $id = db_insert('payments', [
        'order_id' => (int) $order['id'], 'method' => $order['payment_method'], 'amount' => $order['grand_total'],
        'status' => 'pending', 'gateway_reference' => payment_reference($order['order_number']),
    ]);
    return db_row('SELECT * FROM payments WHERE id = ?', [$id]);
}

function payment_latest_pending(int $orderId): ?array
{
    return db_row("SELECT * FROM payments WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$orderId]);
}

/**
 * Marks a payment (and its order) as paid. Safe to call repeatedly.
 * Returns true when this call changed the state.
 */
function payment_mark_paid(int $paymentId, ?string $providerTxnId, float $amountReceived, string $source): bool
{
    $changed = db_transaction(function () use ($paymentId, $providerTxnId, $amountReceived, $source) {
        $p = db_row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$p || $p['status'] === 'paid') {
            return false;
        }
        if (abs((float) $p['amount'] - $amountReceived) > 0.01) {
            payment_log($p['method'], 'amount_mismatch', [
                'payment_id' => $paymentId, 'order_id' => (int) $p['order_id'], 'amount' => $amountReceived,
                'message' => 'Expected ' . $p['amount'] . ', gateway reported ' . $amountReceived, 'status' => 'rejected',
            ]);
            order_history_add((int) $p['order_id'], ['type' => 'payment', 'note' => 'Payment amount mismatch reported by gateway (' . $amountReceived . '). Manual review required.']);
            return false;
        }
        $o = db_row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [(int) $p['order_id']]);
        db_exec("UPDATE payments SET status = 'paid', provider_txn_id = ?, paid_at = NOW() WHERE id = ?", [$providerTxnId, $paymentId]);
        $newStatus = $o['status'] === 'pending' ? 'confirmed' : $o['status'];
        db_exec("UPDATE orders SET payment_status = 'paid', amount_paid = ?, status = ? WHERE id = ?", [$amountReceived, $newStatus, (int) $o['id']]);
        $note = 'Payment received via ' . (payment_method_get($p['method'])['name'] ?? $p['method']) . ($providerTxnId ? ' (Txn ' . $providerTxnId . ')' : '') . '.';
        if (in_array($o['status'], ['cancelled', 'refunded'], true)) {
            $note .= ' NOTE: the order had already been cancelled — review and refund or reinstate.';
        }
        order_history_add((int) $o['id'], ['status' => $newStatus, 'payment_status' => 'paid', 'note' => $note, 'visible' => true, 'type' => 'payment']);
        payment_log($p['method'], 'marked_paid', ['payment_id' => $paymentId, 'order_id' => (int) $o['id'], 'amount' => $amountReceived, 'provider_txn_id' => $providerTxnId, 'status' => 'paid', 'message' => 'Source: ' . $source]);
        return true;
    });
    if ($changed) {
        $p = db_row('SELECT order_id FROM payments WHERE id = ?', [$paymentId]);
        $order = db_row('SELECT * FROM orders WHERE id = ?', [(int) $p['order_id']]);
        mail_order_confirmation($order, true);
    }
    return $changed;
}

function payment_mark_failed(int $paymentId, string $status, string $message, ?string $providerTxnId = null): void
{
    $status = in_array($status, ['failed', 'cancelled'], true) ? $status : 'failed';
    db_transaction(function () use ($paymentId, $status, $message, $providerTxnId) {
        $p = db_row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$p || $p['status'] !== 'pending') {
            return;
        }
        db_exec('UPDATE payments SET status = ?, provider_txn_id = COALESCE(?, provider_txn_id) WHERE id = ?', [$status, $providerTxnId, $paymentId]);
        db_exec("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status IN ('pending','unpaid')", [(int) $p['order_id']]);
        order_history_add((int) $p['order_id'], ['payment_status' => 'failed', 'note' => 'Payment ' . $status . ': ' . $message, 'visible' => true, 'type' => 'payment']);
    });
}

/**
 * Builds what the browser needs to start payment for an order:
 *  ['type' => 'form', 'action' => url, 'fields' => [...]]  — auto-submitted POST form
 *  ['type' => 'redirect', 'url' => ...]
 *  ['type' => 'error', 'message' => ...]
 */
function payment_start(array $order): array
{
    $pm = payment_method_get($order['payment_method']);
    if (!$pm || $pm['code'] === 'cod') {
        return ['type' => 'error', 'message' => 'This order does not need online payment.'];
    }
    $state = payment_method_state($pm);
    if (!($state === 'ready' || ($state === 'testing' && current_admin()))) {
        return ['type' => 'error', 'message' => $pm['name'] . ' is currently unavailable.'];
    }
    $payment = payment_latest_pending((int) $order['id']) ?: payment_new_attempt($order);
    // A gateway reference can only be used once; start a fresh attempt if this one was already sent.
    if (db_val("SELECT 1 FROM payment_transactions WHERE payment_id = ? AND event_type = 'initiate'", [(int) $payment['id']])) {
        $payment = payment_new_attempt($order);
    }
    $creds = payment_credentials($pm);
    try {
        $result = match ($pm['code']) {
            'jazzcash' => jazzcash_build_request($order, $payment, $pm, $creds),
            'easypaisa' => easypaisa_build_request($order, $payment, $pm, $creds),
            'card' => stripe_create_session($order, $payment, $pm, $creds),
            default => ['type' => 'error', 'message' => 'Unknown payment method.'],
        };
    } catch (Throwable $e) {
        payment_log($pm['code'], 'initiate_error', ['payment_id' => (int) $payment['id'], 'order_id' => (int) $order['id'], 'message' => $e->getMessage()]);
        return ['type' => 'error', 'message' => 'We could not connect to the payment provider. Please try again or choose another method.'];
    }
    payment_log($pm['code'], 'initiate', ['payment_id' => (int) $payment['id'], 'order_id' => (int) $order['id'], 'amount' => (float) $payment['amount'], 'status' => 'pending']);
    return $result;
}

/** Server-to-server status check for a pending payment (admin "Verify with gateway"). */
function payment_verify(array $payment): array
{
    $pm = payment_method_get($payment['method']);
    if (!$pm || $pm['code'] === 'cod') {
        return ['ok' => false, 'message' => 'Nothing to verify for this method.'];
    }
    $creds = payment_credentials($pm);
    return match ($pm['code']) {
        'jazzcash' => jazzcash_verify_payment($payment, $pm, $creds),
        'easypaisa' => easypaisa_verify_payment($payment, $pm, $creds),
        'card' => stripe_verify_payment($payment, $pm, $creds),
        default => ['ok' => false, 'message' => 'Unsupported gateway.'],
    };
}

/** Minimal HTTPS client used by the gateway drivers. */
function http_request(string $method, string $url, $body = null, array $headers = [], int $timeout = 25): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        throw new RuntimeException('HTTP request failed: ' . $err);
    }
    return ['status' => $code, 'body' => $resp, 'json' => json_decode($resp, true)];
}
