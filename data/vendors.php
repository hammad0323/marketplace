<?php
/** Plain query functions for the vendors table. */

function mp_find_vendor(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_vendor_by_slug(string $slug): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function mp_find_vendor_by_email(string $email): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function mp_insert_vendor(array $data): int
{
    $stmt = mp_db()->prepare(
        'INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, phone, status)
         VALUES (:marketplace_type_id, :store_name, :slug, :email, :password_hash, :phone, :status)'
    );
    $stmt->execute($data);
    return (int) mp_db()->lastInsertId();
}

/** Approved vendors of a given marketplace type, for landing/listing pages. */
function mp_approved_vendors_by_marketplace(int $marketplaceTypeId, int $limit = 12): array
{
    $stmt = mp_db()->prepare(
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

function mp_pending_vendors(): array
{
    $stmt = mp_db()->query(
        "SELECT vendors.*, marketplace_types.name AS marketplace_name
         FROM vendors
         JOIN marketplace_types ON marketplace_types.id = vendors.marketplace_type_id
         WHERE vendors.status = 'pending'
         ORDER BY vendors.created_at ASC"
    );

    return $stmt->fetchAll();
}

function mp_all_vendors(): array
{
    return mp_db()->query('SELECT * FROM vendors ORDER BY created_at DESC')->fetchAll();
}

function mp_approve_vendor(int $vendorId, int $adminId): void
{
    $stmt = mp_db()->prepare(
        "UPDATE vendors SET status = 'approved', approved_at = NOW(), approved_by = :admin_id WHERE id = :id"
    );
    $stmt->execute(['admin_id' => $adminId, 'id' => $vendorId]);
}

function mp_reject_vendor(int $vendorId, string $reason): void
{
    $stmt = mp_db()->prepare(
        "UPDATE vendors SET status = 'rejected', rejection_reason = :reason WHERE id = :id"
    );
    $stmt->execute(['reason' => $reason, 'id' => $vendorId]);
}

function mp_vendor_average_rating(int $vendorId): array
{
    $stmt = mp_db()->prepare(
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

function mp_vendor_follower_count(int $vendorId): int
{
    $stmt = mp_db()->prepare('SELECT COUNT(*) AS total FROM vendor_follows WHERE vendor_id = :id');
    $stmt->execute(['id' => $vendorId]);
    return (int) $stmt->fetch()['total'];
}

function mp_vendor_is_followed_by(int $vendorId, int $customerId): bool
{
    $stmt = mp_db()->prepare(
        'SELECT 1 FROM vendor_follows WHERE vendor_id = :vendor AND customer_id = :customer'
    );
    $stmt->execute(['vendor' => $vendorId, 'customer' => $customerId]);
    return (bool) $stmt->fetchColumn();
}
