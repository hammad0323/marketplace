<?php
/**
 * addresses — a customer's saved shipping addresses. Orders snapshot
 * the address fields onto the order itself at checkout time, so
 * editing or deleting a saved address here never rewrites past orders.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_addresses_for_customer(int $customerId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC',
        [$customerId]
    );
}

/** Scoped to the owning customer so an address ID from a form can never read/target someone else's address. */
function mp_find_address(int $addressId, int $customerId): ?array
{
    return mp_db_fetch_one(
        'SELECT * FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1',
        [$addressId, $customerId]
    );
}

function mp_insert_address(array $data): int
{
    if (!empty($data['is_default'])) {
        mp_db_execute('UPDATE addresses SET is_default = 0 WHERE customer_id = ?', [$data['customer_id']]);
    }

    return mp_db_insert('addresses', $data);
}
