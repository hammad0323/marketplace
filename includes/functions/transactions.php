<?php
/**
 * transactions — payment attempts against an order. 'cod' and
 * 'manual' are what this build actually processes; 'stripe'/'paypal'
 * are already valid rows in the schema so wiring up real payment
 * processing later (Stripe/PayPal, per the project's "future ready"
 * requirement) needs no schema change.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_create_transaction(array $data): int
{
    return mp_db_insert('transactions', $data);
}

function mp_transactions_for_order(int $orderId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM transactions WHERE order_id = ? AND tenant_id = ? ORDER BY created_at DESC',
        [$orderId, mp_tenant_id()]
    );
}

function mp_update_transaction_status(int $transactionId, string $status, ?string $gatewayReference = null): void
{
    $data = ['status' => $status];
    if ($gatewayReference !== null) {
        $data['gateway_reference'] = $gatewayReference;
    }

    mp_db_update('transactions', $data, 'id = ?', [$transactionId]);
}
