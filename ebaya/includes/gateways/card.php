<?php
/**
 * Card payments via Stripe Checkout (hosted payment page).
 *
 * Card numbers and CVVs are entered on Stripe's page and never reach this
 * server. Payment is confirmed by (a) retrieving the Checkout Session with
 * the secret key when the customer returns and (b) the signed webhook
 * checkout.session.completed — never from the redirect alone.
 *
 * If your business needs a Pakistan-acquired card gateway instead, add a
 * driver with the same three functions (card_start, card_handle_return,
 * card_handle_webhook) — see docs/PAYMENTS.md.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function stripe_api(string $method, string $path, array $params = []): array
{
    $key = payment_gateway('card')['config']['secret_key'] ?? '';
    $res = http_request($method, 'https://api.stripe.com/v1/' . ltrim($path, '/'), $params ? http_build_query($params) : null, [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    $data = json_decode($res['body'], true) ?: [];
    if ($res['status'] >= 400 || isset($data['error'])) {
        throw new RuntimeException('Card gateway error: ' . ($data['error']['message'] ?? $res['error'] ?: 'HTTP ' . $res['status']));
    }
    return $data;
}

function card_start(array $order, array $payment): void
{
    $ref = payment_reference($payment, 'C');
    $orderUrl = 'order/' . $order['order_number'] . '?key=' . $order['lookup_key'];
    try {
        $session = stripe_api('POST', 'checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $ref,
            'customer_email' => $order['email'],
            'success_url' => abs_url('payment/card/return') . '?ref=' . urlencode($ref) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => abs_url('payment/card/return') . '?ref=' . urlencode($ref) . '&cancelled=1',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order['currency']),
                    'unit_amount' => (int)round((float)$payment['amount'] * 100),
                    'product_data' => ['name' => 'Ebaya order ' . $order['order_number']],
                ],
            ]],
            'metadata' => ['order_number' => $order['order_number'], 'payment_ref' => $ref],
            'payment_intent_data' => ['metadata' => ['order_number' => $order['order_number'], 'payment_ref' => $ref]],
        ]);
    } catch (Throwable $e) {
        payment_log('card', 'initiate', 'error', (int)$payment['id'], (int)$order['id'], [], $ref, null, null, $e->getMessage());
        flash('danger', 'We could not start the card payment. Please try again or choose another method.');
        redirect($orderUrl);
    }
    db_exec('UPDATE payments SET provider_txn_id = ? WHERE id = ?', [$session['id'], (int)$payment['id']]);
    payment_log('card', 'initiate', 'redirect', (int)$payment['id'], (int)$order['id'], ['session' => $session['id']], $ref, null, (float)$payment['amount']);
    redirect($session['url']);
}

/** Apply a Checkout Session object (retrieved from Stripe's API or a verified webhook). */
function card_apply_session(array $session, string $event): ?array
{
    $ref = (string)($session['client_reference_id'] ?? '');
    $payment = db_one("SELECT * FROM payments WHERE gateway_reference = ? AND method = 'card'", [$ref]);
    if (!$payment) return null;
    $amount = isset($session['amount_total']) ? ((int)$session['amount_total']) / 100 : null;
    payment_log('card', $event, (string)($session['payment_status'] ?? 'unknown'), (int)$payment['id'], (int)$payment['order_id'],
        ['session' => $session['id'] ?? null, 'payment_intent' => $session['payment_intent'] ?? null], $ref, true, $amount);
    if (($session['payment_status'] ?? '') === 'paid') {
        db_exec('UPDATE payments SET provider_txn_id = ? WHERE id = ?', [$session['payment_intent'] ?? $session['id'], (int)$payment['id']]);
        order_mark_paid((int)$payment['id'], $session['payment_intent'] ?? $session['id'], 'Card', $amount);
    }
    return db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
}

function card_handle_return(string $ref, string $sessionId, bool $cancelled): ?array
{
    $payment = db_one("SELECT * FROM payments WHERE gateway_reference = ? AND method = 'card'", [$ref]);
    if (!$payment) return null;
    if ($cancelled) {
        payment_log('card', 'return', 'cancelled', (int)$payment['id'], (int)$payment['order_id'], [], $ref);
        order_mark_payment_failed((int)$payment['id'], 'Customer cancelled card payment', 'cancelled');
        return db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
    }
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) return db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
    try {
        $session = stripe_api('GET', 'checkout/sessions/' . $sessionId);
        if (($session['client_reference_id'] ?? '') !== $ref) throw new RuntimeException('Session does not match payment');
        return card_apply_session($session, 'verify');
    } catch (Throwable $e) {
        payment_log('card', 'verify', 'error', (int)$payment['id'], (int)$payment['order_id'], [], $ref, null, null, $e->getMessage());
        return db_one('SELECT * FROM orders WHERE id = ?', [(int)$payment['order_id']]);
    }
}

/** Verify Stripe-Signature header (HMAC-SHA256 of "timestamp.payload"). */
function card_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') $t = (int)$v;
        if ($k === 'v1') $sigs[] = $v;
    }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) {
        if (hash_equals($expected, $s)) return true;
    }
    return false;
}

function card_handle_webhook(string $payload, string $sigHeader): int
{
    $secret = payment_gateway('card')['config']['webhook_secret'] ?? '';
    if ($secret === '' || !card_verify_signature($payload, $sigHeader, $secret)) {
        payment_log('card', 'callback', 'bad_signature', null, null, [], null, false);
        return 400;
    }
    $event = json_decode($payload, true);
    if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        card_apply_session($event['data']['object'] ?? [], 'callback');
    } elseif (($event['type'] ?? '') === 'checkout.session.async_payment_failed') {
        $ref = $event['data']['object']['client_reference_id'] ?? '';
        $p = db_one("SELECT id FROM payments WHERE gateway_reference = ? AND method = 'card'", [$ref]);
        if ($p) order_mark_payment_failed((int)$p['id'], 'Card payment failed');
    }
    return 200;
}

function card_refund(array $payment, float $amount): string
{
    $pi = (string)$payment['provider_txn_id'];
    if (!str_starts_with($pi, 'pi_')) throw new InvalidArgumentException('No card payment reference found for this order.');
    $r = stripe_api('POST', 'refunds', ['payment_intent' => $pi, 'amount' => (int)round($amount * 100)]);
    payment_log('card', 'refund', (string)($r['status'] ?? 'submitted'), (int)$payment['id'], (int)$payment['order_id'], ['refund' => $r['id'] ?? null], $payment['gateway_reference'], null, $amount);
    return 'Card refund submitted (' . ($r['status'] ?? 'pending') . ').';
}
