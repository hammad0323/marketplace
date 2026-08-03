<?php
/** Plain query functions for the vendors table. */

function find_vendor(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function find_vendor_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM vendors WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function find_vendor_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM vendors WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function insert_vendor(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, phone, status)
         VALUES (:marketplace_type_id, :store_name, :slug, :email, :password_hash, :phone, :status)'
    );
    $stmt->execute($data);
    return (int) db()->lastInsertId();
}

/** Approved vendors of a given marketplace type, for landing/listing pages. */
function approved_vendors_by_marketplace(int $marketplaceTypeId, int $limit = 12): array
{
    $stmt = db()->prepare(
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

function pending_vendors(): array
{
    $stmt = db()->query(
        "SELECT vendors.*, marketplace_types.name AS marketplace_name
         FROM vendors
         JOIN marketplace_types ON marketplace_types.id = vendors.marketplace_type_id
         WHERE vendors.status = 'pending'
         ORDER BY vendors.created_at ASC"
    );

    return $stmt->fetchAll();
}

function all_vendors(): array
{
    return db()->query('SELECT * FROM vendors ORDER BY created_at DESC')->fetchAll();
}

function approve_vendor(int $vendorId, int $adminId): void
{
    $stmt = db()->prepare(
        "UPDATE vendors SET status = 'approved', approved_at = NOW(), approved_by = :admin_id WHERE id = :id"
    );
    $stmt->execute(['admin_id' => $adminId, 'id' => $vendorId]);
}

function reject_vendor(int $vendorId, string $reason): void
{
    $stmt = db()->prepare(
        "UPDATE vendors SET status = 'rejected', rejection_reason = :reason WHERE id = :id"
    );
    $stmt->execute(['reason' => $reason, 'id' => $vendorId]);
}

function vendor_average_rating(int $vendorId): array
{
    $stmt = db()->prepare(
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

function vendor_follower_count(int $vendorId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM vendor_follows WHERE vendor_id = :id');
    $stmt->execute(['id' => $vendorId]);
    return (int) $stmt->fetch()['total'];
}

function vendor_is_followed_by(int $vendorId, int $customerId): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM vendor_follows WHERE vendor_id = :vendor AND customer_id = :customer'
    );
    $stmt->execute(['vendor' => $vendorId, 'customer' => $customerId]);
    return (bool) $stmt->fetchColumn();
}
