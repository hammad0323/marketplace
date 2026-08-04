<?php
/** Plain query functions for customers, follows, and ratings. */

function mp_find_customer_by_email(string $email): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function mp_insert_customer(array $data): int
{
    $stmt = mp_db()->prepare(
        'INSERT INTO customers (name, email, password_hash) VALUES (:name, :email, :password_hash)'
    );
    $stmt->execute($data);
    return (int) mp_db()->lastInsertId();
}

function mp_customer_follow_vendor(int $customerId, int $vendorId): void
{
    $stmt = mp_db()->prepare(
        'INSERT IGNORE INTO vendor_follows (customer_id, vendor_id) VALUES (:customer_id, :vendor_id)'
    );
    $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
}

function mp_customer_unfollow_vendor(int $customerId, int $vendorId): void
{
    $stmt = mp_db()->prepare(
        'DELETE FROM vendor_follows WHERE customer_id = :customer_id AND vendor_id = :vendor_id'
    );
    $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
}

function mp_customer_rate_vendor(int $customerId, int $vendorId, int $rating, ?string $review): void
{
    $stmt = mp_db()->prepare(
        'INSERT INTO vendor_ratings (customer_id, vendor_id, rating, review)
         VALUES (:customer_id, :vendor_id, :rating, :review)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)'
    );
    $stmt->execute([
        'customer_id' => $customerId,
        'vendor_id'   => $vendorId,
        'rating'      => $rating,
        'review'      => $review,
    ]);
}
