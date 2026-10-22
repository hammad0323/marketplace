<?php
/**
 * Stripe webhook. In the Stripe dashboard add an endpoint:
 *   https://your-domain/api/payments/stripe-webhook.php
 * listening to: checkout.session.completed, checkout.session.async_payment_succeeded,
 *               checkout.session.async_payment_failed, checkout.session.expired
 */
require dirname(__DIR__) . '/_init.php';
$payload = (string) file_get_contents('php://input');
$pm = payment_method_get('card');
$creds = $pm ? payment_credentials($pm) : [];
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if (!$pm || empty($creds['webhook_secret']) || !stripe_verify_signature($payload, $sig, $creds['webhook_secret'])) {
    payment_log('card', 'webhook_rejected', ['signature_valid' => false, 'message' => 'Invalid Stripe signature']);
    http_response_code(400);
    exit('Invalid signature');
}
$event = json_decode($payload, true) ?: [];
$type = (string) ($event['type'] ?? '');
$obj = $event['data']['object'] ?? [];
if (str_starts_with($type, 'checkout.session.')) {
    if ($type === 'checkout.session.async_payment_failed') {
        $p = payment_by_reference((string) ($obj['client_reference_id'] ?? ''));
        if ($p) {
            payment_mark_failed((int) $p['id'], 'failed', 'Card payment failed', $obj['payment_intent'] ?? null);
        }
    } else {
        stripe_apply_session($obj, 'webhook:' . $type);
    }
}
http_response_code(200);
echo 'ok';
