<?php

class Customer extends Model
{
    protected string $table = 'customers';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function follow(int $customerId, int $vendorId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO vendor_follows (customer_id, vendor_id) VALUES (:customer_id, :vendor_id)'
        );
        $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
    }

    public function unfollow(int $customerId, int $vendorId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM vendor_follows WHERE customer_id = :customer_id AND vendor_id = :vendor_id'
        );
        $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
    }

    public function rate(int $customerId, int $vendorId, int $rating, ?string $review): void
    {
        $stmt = $this->db->prepare(
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
}
