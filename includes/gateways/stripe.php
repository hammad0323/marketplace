<?php
/**
 * Debit / credit cards via Stripe Checkout (hosted payment page).
 * Card details are entered on Stripe's page — this site never sees or stores card numbers or CVVs.
 *
 * The order is marked paid by either:
 *  - the signed webhook  checkout.session.completed  (api/payments/stripe-webhook.php), or
 *  - the success page retrieving the Checkout Session from Stripe's API (server to server).
 *
 * Stripe availability depends on where the merchant's business is registered. If Stripe is not
 * available to you, keep this method disabled and accept cards through JazzCash / Easypaisa
 * hosted checkout (both offer card payment on their hosted pages).
 */

function stripe_api(string $method, string $path, array $creds, ?array $params = null): array
{
    $res = http_request(
        $method,
        'https://api.stripe.com/v1/' . ltrim($path, '/'),
        $params !== null ? http_build_query($params) : null,
        ['Authorization: Bearer ' . $creds['secret_key'], 'Content-Type: application/x-www-form-urlencoded']
    );
    if ($res['status'] >= 400) {
        throw new RuntimeException('Stripe API error: ' . ($res['json']['error']['message'] ?? $res['status']));
    }
    return $res['json'] ?? [];
}

function stripe_currency(array $creds): string
{
    $c = strtolower(trim((string) ($creds['currency'] ?? '')));
    return preg_match('/^[a-z]{3}$/', $c) ? $c : strtolower(setting('currency_code', 'PKR'));
}

function stripe_create_session(array $order, array $payment, array $pm, array $creds): array
{
    $session = stripe_api('POST', 'checkout/sessions', $creds, [
        'mode' => 'payment',
        'client_reference_id' => $payment['gateway_reference'],
        'customer_email' => $order['email'],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => stripe_currency($creds),
                'unit_amount' => (int) round((float) $payment['amount'] * 100),
                'product_data' => ['name' => store_name() . ' — Order ' . $order['order_number']],
            ],
        ]],
        'metadata' => ['order_number' => $order['order_number'], 'payment_ref' => $payment['gateway_reference']],
        'payment_intent_data' => ['metadata' => ['order_number' => $order['order_number'], 'payment_ref' => $payment['gateway_reference']]],
        'success_url' => url('api/payments/stripe-return.php?ref=' . urlencode($payment['gateway_reference']) . '&session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => url('checkout/pay/' . $order['order_number'] . '?t=' . $order['access_token'] . '&cancelled=1'),
    ]);
    if (empty($session['url']) || empty($session['id'])) {
        throw new RuntimeException('Stripe did not return a checkout URL.');
    }
    db_exec('UPDATE payments SET provider_txn_id = ? WHERE id = ?', [$session['id'], (int) $payment['id']]);
    return ['type' => 'redirect', 'url' => $session['url']];
}

/** Applies a Checkout Session object (from the API or a verified webhook) to our payment. */
function stripe_apply_session(array $session, string $source): array
{
    $ref = (string) ($session['client_reference_id'] ?? ($session['metadata']['payment_ref'] ?? ''));
    $payment = $ref !== '' ? payment_by_reference($ref) : null;
    if (!$payment) {
        return ['ok' => false, 'paid' => false, 'message' => 'Unknown payment reference.'];
    }
    payment_log('card', $source, [
        'payment_id' => (int) $payment['id'], 'order_id' => (int) $payment['order_id'], 'status' => $session['payment_status'] ?? null,
        'provider_txn_id' => $session['payment_intent'] ?? ($session['id'] ?? null),
        'amount' => isset($session['amount_total']) ? $session['amount_total'] / 100 : null,
        'payload' => ['id' => $session['id'] ?? null, 'status' => $session['status'] ?? null, 'payment_status' => $session['payment_status'] ?? null],
    ]);
    if (($session['payment_status'] ?? '') === 'paid') {
        payment_mark_paid((int) $payment['id'], $session['payment_intent'] ?? $session['id'], ((int) $session['amount_total']) / 100, $source);
        return ['ok' => true, 'paid' => true, 'message' => 'Payment confirmed.', 'payment' => $payment];
    }
    if (($session['status'] ?? '') === 'expired') {
        payment_mark_failed((int) $payment['id'], 'cancelled', 'Checkout session expired');
    }
    return ['ok' => true, 'paid' => false, 'message' => 'Payment not completed.', 'payment' => $payment];
}

function stripe_verify_payment(array $payment, array $pm, array $creds): array
{
    $sid = (string) $payment['provider_txn_id'];
    if (!str_starts_with($sid, 'cs_')) {
        return ['ok' => false, 'message' => 'No Stripe Checkout Session recorded for this payment.'];
    }
    try {
        $session = stripe_api('GET', 'checkout/sessions/' . rawurlencode($sid), $creds);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
    return stripe_apply_session($session, 'api_retrieve');
}

/** Verifies the Stripe-Signature header (HMAC-SHA256 of "timestamp.payload"). */
function stripe_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $ts = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') {
            $ts = (int) $v;
        } elseif ($k === 'v1') {
            $sigs[] = $v;
        }
    }
    if (!$ts || !$sigs || abs(time() - $ts) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    foreach ($sigs as $s) {
        if (hash_equals($expected, $s)) {
            return true;
        }
    }
    return false;
}
