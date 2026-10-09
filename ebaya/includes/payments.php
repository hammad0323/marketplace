<?php
/**
 * Payment gateway registry and shared helpers.
 *
 * A gateway is offered to customers only when it is enabled, all required
 * credentials are saved, and — in live mode — an administrator has ticked
 * "Testing completed". In sandbox mode a configured gateway is visible only
 * to logged-in administrators so it can be tested safely.
 *
 * Orders are never marked paid from a browser redirect alone: each gateway
 * verifies the result server-to-server (signature + status inquiry / API
 * lookup) before calling order_mark_paid().
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

require __DIR__ . '/gateways/jazzcash.php';
require __DIR__ . '/gateways/easypaisa.php';
require __DIR__ . '/gateways/card.php';

/** code => [name, fields[key => [label, secret?, required?, help]]] */
function payment_gateway_defs(): array
{
    return [
        'cod' => ['Cash on Delivery', [
            'instructions' => ['Instructions shown at checkout', false, false, 'e.g. Please keep the exact amount ready.'],
        ]],
        'jazzcash' => ['JazzCash', [
            'merchant_id' => ['Merchant ID', false, true, 'From the JazzCash merchant portal'],
            'password' => ['Password', true, true, ''],
            'integrity_salt' => ['Integrity salt (hash key)', true, true, ''],
            'txn_type' => ['Transaction type (blank = let customer choose)', false, false, 'MWALLET, MPAY (card) or OTC'],
        ]],
        'easypaisa' => ['Easypaisa', [
            'store_id' => ['Store ID', false, true, 'From the Easypay merchant portal'],
            'hash_key' => ['Hash key', true, true, '16-character key used to sign requests'],
            'account_number' => ['Merchant account number', false, true, 'Used for transaction inquiry'],
            'api_username' => ['Inquiry API username', false, true, ''],
            'api_password' => ['Inquiry API password', true, true, ''],
            'payment_method' => ['Payment method code (blank = all)', false, false, 'MA_PAYMENT_METHOD, CC_PAYMENT_METHOD or OTC_PAYMENT_METHOD'],
        ]],
        'card' => ['Debit / Credit Card', [
            'provider' => ['Provider', false, false, 'Stripe Checkout (hosted). Card data never touches this server.'],
            'secret_key' => ['Secret key (sk_...)', true, true, ''],
            'webhook_secret' => ['Webhook signing secret (whsec_...)', true, true, ''],
        ]],
    ];
}

function payment_gateway(string $code): ?array
{
    static $cache = [];
    if (array_key_exists($code, $cache)) return $cache[$code];
    $g = db_one('SELECT * FROM payment_gateways WHERE code = ?', [$code]);
    if (!$g) return $cache[$code] = null;
    $g['config'] = [];
    foreach (db_all('SELECT setting_key, setting_value, is_secret FROM payment_gateway_settings WHERE gateway_code = ?', [$code]) as $s) {
        $g['config'][$s['setting_key']] = $s['is_secret'] ? decrypt_secret($s['setting_value']) : (string)$s['setting_value'];
    }
    return $cache[$code] = $g;
}

function payment_gateway_configured(string $code): bool
{
    $g = payment_gateway($code);
    $defs = payment_gateway_defs()[$code] ?? null;
    if (!$g || !$defs) return false;
    foreach ($defs[1] as $k => $f) {
        if ($f[2] && trim((string)($g['config'][$k] ?? '')) === '') return false;
    }
    return true;
}

/** Human status for admin screens. */
function payment_gateway_state(string $code): array
{
    $g = payment_gateway($code);
    if (!$g) return ['Missing', 'danger'];
    if (!payment_gateway_configured($code)) return ['Unavailable — credentials required', 'secondary'];
    if (!$g['enabled']) return ['Disabled', 'secondary'];
    if ($code === 'cod') return ['Live', 'success'];
    if ($g['mode'] === 'sandbox') return ['Sandbox — visible to admins only', 'warning'];
    if (!$g['tested']) return ['Unavailable — mark testing completed to go live', 'warning'];
    return ['Live', 'success'];
}

function payment_gateway_available(string $code): bool
{
    $g = payment_gateway($code);
    if (!$g || !$g['enabled'] || !payment_gateway_configured($code)) return false;
    if ($code === 'cod') return true;
    if ($g['mode'] === 'sandbox') return admin_id() !== null;
    return (bool)$g['tested'];
}

/** Methods the customer can choose for a zone and total: [code => [name, note, fee]] */
function payment_methods_for_checkout(?array $zone, float $total): array
{
    $out = [];
    foreach (db_all('SELECT code, name, description FROM payment_gateways ORDER BY sort_order') as $g) {
        $code = $g['code'];
        if ($code === 'cod') {
            if (!cod_available($zone, $total)) continue;
            $fee = $zone ? (float)$zone['cod_fee'] : 0;
            $note = trim((payment_gateway('cod')['config']['instructions'] ?? '') . ($fee > 0 ? ' A COD fee of ' . money($fee) . ' applies.' : ''));
            $out[$code] = ['name' => $g['name'], 'note' => $note, 'fee' => $fee];
        } elseif (payment_gateway_available($code)) {
            $sandbox = payment_gateway($code)['mode'] === 'sandbox';
            $out[$code] = ['name' => $g['name'] . ($sandbox ? ' (sandbox — admin test)' : ''), 'note' => (string)$g['description'], 'fee' => 0];
        }
    }
    return $out;
}

function payment_log(string $gateway, string $event, string $status, ?int $paymentId, ?int $orderId, array $payload = [], ?string $reference = null, ?bool $sigValid = null, ?float $amount = null, string $message = ''): void
{
    // Never log secrets or card data.
    foreach (['pp_Password', 'pp_SecureHash', 'password', 'merchantHashedReq', 'card_number', 'cvv', 'cvc'] as $k) {
        if (isset($payload[$k])) $payload[$k] = '[redacted]';
    }
    db_insert('INSERT INTO payment_transactions (payment_id, order_id, gateway, event_type, status, amount, reference, signature_valid, payload, message, admin_id, ip)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$paymentId, $orderId, $gateway, $event, mb_substr($status, 0, 30), $amount, $reference, $sigValid === null ? null : ($sigValid ? 1 : 0),
         json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), mb_substr($message, 0, 255), admin_id(), client_ip()]);
}

function http_request(string $method, string $url, $body = null, array $headers = [], int $timeout = 25): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status' => $code, 'body' => $resp === false ? '' : $resp, 'error' => $err];
}

/** Latest pending payment row for an order, creating a fresh attempt if the last one failed. */
function payment_attempt_for_order(array $order): array
{
    $p = db_one("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1", [(int)$order['id']]);
    if ($p && $p['status'] === 'pending' && $p['method'] === $order['payment_method']) return $p;
    $g = payment_gateway($order['payment_method']);
    $id = db_insert("INSERT INTO payments (order_id, method, amount, currency, status, mode) VALUES (?, ?, ?, ?, 'pending', ?)",
        [(int)$order['id'], $order['payment_method'], $order['grand_total'], $order['currency'], $g['mode'] ?? null]);
    db_exec("UPDATE orders SET payment_status = 'pending' WHERE id = ? AND payment_status = 'failed'", [(int)$order['id']]);
    return db_one('SELECT * FROM payments WHERE id = ?', [$id]);
}

/** A unique reference sent to the gateway for this attempt. */
function payment_reference(array $payment, string $prefix): string
{
    if (!empty($payment['gateway_reference'])) return $payment['gateway_reference'];
    $ref = $prefix . date('ymdHis') . str_pad((string)$payment['id'], 6, '0', STR_PAD_LEFT);
    db_exec('UPDATE payments SET gateway_reference = ? WHERE id = ?', [$ref, (int)$payment['id']]);
    return $ref;
}

/** Start an online payment for an order (renders a redirect form or sends a Location header). */
function payment_start(array $order): void
{
    if ($order['payment_status'] === 'paid' || in_array($order['status'], ['cancelled', 'returned'], true)) {
        redirect('order/' . $order['order_number'] . '?key=' . $order['lookup_key']);
    }
    if (!payment_gateway_available($order['payment_method'])) {
        flash('danger', 'This payment method is currently unavailable. Please contact us to complete your order.');
        redirect('order/' . $order['order_number'] . '?key=' . $order['lookup_key']);
    }
    $payment = payment_attempt_for_order($order);
    match ($order['payment_method']) {
        'jazzcash' => jazzcash_start($order, $payment),
        'easypaisa' => easypaisa_start($order, $payment),
        'card' => card_start($order, $payment),
        default => redirect('order/' . $order['order_number'] . '?key=' . $order['lookup_key']),
    };
}

/** Auto-submitting POST form used by redirect-based gateways. */
function payment_redirect_form(string $action, array $fields, string $title = 'Redirecting to secure payment…'): void
{
    header('Cache-Control: no-store');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>' . e($title) . '</title>'
        . '<style>body{font-family:Georgia,serif;background:#F8F5EF;color:#332820;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}button{background:#354638;color:#fff;border:0;padding:12px 28px;letter-spacing:.1em;cursor:pointer}</style>'
        . '</head><body><form id="pf" method="post" action="' . e($action) . '">';
    foreach ($fields as $k => $v) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    echo '<p>' . e($title) . '</p><noscript><button type="submit">Continue to payment</button></noscript></form>'
        . '<script>document.getElementById("pf").submit();</script></body></html>';
    exit;
}

/** Record a refund (manual for wallet gateways; via API for cards). */
function payment_refund(int $orderId, float $amount, string $reason): string
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) throw new InvalidArgumentException('Order not found.');
    if ($o['payment_status'] !== 'paid' && $o['payment_status'] !== 'partially_refunded') throw new InvalidArgumentException('Only paid orders can be refunded.');
    $remaining = (float)$o['grand_total'] - (float)$o['refunded_total'];
    if ($amount <= 0 || $amount > $remaining + 0.001) throw new InvalidArgumentException('Refund amount must be between 0 and ' . money($remaining) . '.');
    $p = db_one("SELECT * FROM payments WHERE order_id = ? AND status = 'success' ORDER BY id DESC LIMIT 1", [$orderId]);
    $msg = 'Refund recorded';
    if ($o['payment_method'] === 'card' && $p) {
        $msg = card_refund($p, $amount);
    } else {
        payment_log($o['payment_method'], 'refund', 'manual', $p['id'] ?? null, $orderId, ['reason' => $reason], null, null, $amount, 'Manual refund recorded — process it in the gateway portal or by bank transfer.');
    }
    $newTotal = (float)$o['refunded_total'] + $amount;
    $status = $newTotal + 0.001 >= (float)$o['grand_total'] ? 'refunded' : 'partially_refunded';
    db_exec('UPDATE orders SET refunded_total = ?, payment_status = ? WHERE id = ?', [$newTotal, $status, $orderId]);
    if ($status === 'refunded' && $p) db_exec("UPDATE payments SET status = 'refunded' WHERE id = ?", [(int)$p['id']]);
    order_history_add($orderId, null, $status, 'Refund of ' . money($amount) . ($reason ? ': ' . $reason : ''), true);
    return $msg;
}
