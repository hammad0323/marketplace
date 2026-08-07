<?php
/**
 * customers, plus vendor follows and ratings.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

/** Defense-in-depth: reached via a raw id from a session cookie, so scoped even though the id alone already uniquely identifies a row. */
function mp_find_customer(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_find_customer_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM customers WHERE tenant_id = ? AND email = ? LIMIT 1', [mp_tenant_id(), $email]);
}

function mp_insert_customer(array $data): int
{
    return mp_db_insert('customers', $data);
}

/** All customers in this tenant with their order count/total spend, for admin/customers.php. */
function mp_all_customers_admin(): array
{
    return mp_db_fetch_all(
        'SELECT customers.*,
                COUNT(orders.id) AS order_count,
                COALESCE(SUM(orders.total_amount), 0) AS total_spent
         FROM customers
         LEFT JOIN orders ON orders.customer_id = customers.id
         WHERE customers.tenant_id = ?
         GROUP BY customers.id
         ORDER BY customers.created_at DESC',
        [mp_tenant_id()]
    );
}

function mp_count_customers(): int
{
    return (int) mp_db_fetch_value('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [mp_tenant_id()]);
}

function mp_customer_follow_vendor(int $customerId, int $vendorId): void
{
    mp_db_execute(
        'INSERT IGNORE INTO vendor_follows (tenant_id, customer_id, vendor_id) VALUES (?, ?, ?)',
        [mp_tenant_id(), $customerId, $vendorId]
    );
}

function mp_customer_unfollow_vendor(int $customerId, int $vendorId): void
{
    mp_db_execute(
        'DELETE FROM vendor_follows WHERE customer_id = ? AND vendor_id = ?',
        [$customerId, $vendorId]
    );
}

function mp_customer_rate_vendor(int $customerId, int $vendorId, int $rating, ?string $review): void
{
    mp_db_execute(
        'INSERT INTO vendor_ratings (tenant_id, customer_id, vendor_id, rating, review)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)',
        [mp_tenant_id(), $customerId, $vendorId, $rating, $review]
    );
}
