<?php
/**
 * vendors — an Artisan (handmade creator) or a Business Shop. The
 * platform's own Official Store is represented as a pre-approved
 * vendor with marketplace_type 'official'.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_vendor(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_vendor_by_slug(string $slug): ?array
{
    return mp_db_fetch_one('SELECT * FROM vendors WHERE slug = ? LIMIT 1', [$slug]);
}

function mp_find_vendor_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM vendors WHERE email = ? LIMIT 1', [$email]);
}

function mp_insert_vendor(array $data): int
{
    return mp_db_insert('vendors', $data);
}

/** Approved vendors of a given marketplace type, for landing/listing pages. */
function mp_approved_vendors_by_marketplace(int $marketplaceTypeId, int $limit = 12): array
{
    return mp_db_fetch_all(
        "SELECT * FROM vendors
         WHERE marketplace_type_id = ? AND status = 'approved'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT ?",
        [$marketplaceTypeId, $limit]
    );
}

function mp_pending_vendors(): array
{
    return mp_db_fetch_all(
        "SELECT vendors.*, marketplace_types.name AS marketplace_name
         FROM vendors
         JOIN marketplace_types ON marketplace_types.id = vendors.marketplace_type_id
         WHERE vendors.status = 'pending'
         ORDER BY vendors.created_at ASC"
    );
}

function mp_all_vendors(): array
{
    return mp_db_fetch_all('SELECT * FROM vendors ORDER BY created_at DESC');
}

/** Approved vendor count, optionally scoped to one marketplace type — for stat counters. */
function mp_count_approved_vendors(?int $marketplaceTypeId = null): int
{
    if ($marketplaceTypeId !== null) {
        return (int) mp_db_fetch_value(
            "SELECT COUNT(*) FROM vendors WHERE status = 'approved' AND marketplace_type_id = ?",
            [$marketplaceTypeId]
        );
    }
    return (int) mp_db_fetch_value("SELECT COUNT(*) FROM vendors WHERE status = 'approved'");
}

function mp_approve_vendor(int $vendorId, int $adminId): void
{
    mp_db_execute(
        "UPDATE vendors SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?",
        [$adminId, $vendorId]
    );
}

function mp_reject_vendor(int $vendorId, string $reason): void
{
    mp_db_update(
        'vendors',
        ['status' => 'rejected', 'rejection_reason' => $reason],
        'id = ?',
        [$vendorId]
    );
}

function mp_vendor_average_rating(int $vendorId): array
{
    $row = mp_db_fetch_one(
        'SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM vendor_ratings WHERE vendor_id = ?',
        [$vendorId]
    );

    return [
        'average' => $row['avg_rating'] ? (float) $row['avg_rating'] : 0.0,
        'total'   => (int) ($row['total'] ?? 0),
    ];
}

function mp_vendor_follower_count(int $vendorId): int
{
    return (int) mp_db_fetch_value('SELECT COUNT(*) FROM vendor_follows WHERE vendor_id = ?', [$vendorId]);
}

function mp_vendor_is_followed_by(int $vendorId, int $customerId): bool
{
    return (bool) mp_db_fetch_value(
        'SELECT 1 FROM vendor_follows WHERE vendor_id = ? AND customer_id = ?',
        [$vendorId, $customerId]
    );
}
