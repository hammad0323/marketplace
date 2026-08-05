<?php
/**
 * customers, plus vendor follows and ratings.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_customer(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM customers WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_customer_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM customers WHERE email = ? LIMIT 1', [$email]);
}

function mp_insert_customer(array $data): int
{
    return mp_db_insert('customers', $data);
}

function mp_customer_follow_vendor(int $customerId, int $vendorId): void
{
    mp_db_execute(
        'INSERT IGNORE INTO vendor_follows (customer_id, vendor_id) VALUES (?, ?)',
        [$customerId, $vendorId]
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
        'INSERT INTO vendor_ratings (customer_id, vendor_id, rating, review)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)',
        [$customerId, $vendorId, $rating, $review]
    );
}
