<?php

class Vendor extends Model
{
    protected string $table = 'vendors';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /** Approved vendors of a given marketplace type, for landing/listing pages. */
    public function approvedByMarketplace(int $marketplaceTypeId, int $limit = 12): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM vendors
             WHERE marketplace_type_id = :type AND status = 'approved'
             ORDER BY is_featured DESC, created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function pending(): array
    {
        $stmt = $this->db->query(
            "SELECT vendors.*, marketplace_types.name AS marketplace_name
             FROM vendors
             JOIN marketplace_types ON marketplace_types.id = vendors.marketplace_type_id
             WHERE vendors.status = 'pending'
             ORDER BY vendors.created_at ASC"
        );

        return $stmt->fetchAll();
    }

    public function approve(int $vendorId, int $adminId): bool
    {
        return $this->update($vendorId, [
            'status'      => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $adminId,
        ]);
    }

    public function reject(int $vendorId, string $reason): bool
    {
        return $this->update($vendorId, [
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    public function isApproved(array $vendor): bool
    {
        return $vendor['status'] === 'approved';
    }

    public function averageRating(int $vendorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
             FROM vendor_ratings WHERE vendor_id = :id'
        );
        $stmt->execute(['id' => $vendorId]);
        $row = $stmt->fetch();

        return [
            'average' => $row['avg_rating'] ? (float) $row['avg_rating'] : 0.0,
            'total'   => (int) $row['total'],
        ];
    }

    public function followerCount(int $vendorId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM vendor_follows WHERE vendor_id = :id');
        $stmt->execute(['id' => $vendorId]);
        return (int) $stmt->fetch()['total'];
    }

    public function isFollowedBy(int $vendorId, int $customerId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM vendor_follows WHERE vendor_id = :vendor AND customer_id = :customer'
        );
        $stmt->execute(['vendor' => $vendorId, 'customer' => $customerId]);
        return (bool) $stmt->fetchColumn();
    }
}
