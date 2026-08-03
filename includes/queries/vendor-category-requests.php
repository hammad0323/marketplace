<?php
/**
 * Governs which categories a Business Shop vendor is allowed to sell
 * in. A category only becomes available on the "add product" form
 * once a request for it has status = approved and is_enabled = 1.
 */

function find_category_request(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM vendor_category_requests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function request_vendor_categories(int $vendorId, array $categoryIds): void
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO vendor_category_requests (vendor_id, category_id) VALUES (:vendor_id, :category_id)'
    );

    foreach ($categoryIds as $categoryId) {
        $stmt->execute(['vendor_id' => $vendorId, 'category_id' => (int) $categoryId]);
    }
}

function vendor_category_requests_for_vendor(int $vendorId): array
{
    $stmt = db()->prepare(
        'SELECT vendor_category_requests.*, categories.name AS category_name, categories.slug AS category_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         WHERE vendor_category_requests.vendor_id = :vendor_id
         ORDER BY vendor_category_requests.created_at DESC'
    );
    $stmt->execute(['vendor_id' => $vendorId]);
    return $stmt->fetchAll();
}

function pending_category_requests(): array
{
    $stmt = db()->query(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         WHERE vendor_category_requests.status = 'pending'
         ORDER BY vendor_category_requests.created_at ASC"
    );
    return $stmt->fetchAll();
}

function all_category_requests(): array
{
    return db()->query(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         ORDER BY vendor_category_requests.created_at DESC"
    )->fetchAll();
}

/** Category IDs a vendor may currently list products in. */
function approved_category_ids_for_vendor(int $vendorId): array
{
    $stmt = db()->prepare(
        "SELECT category_id FROM vendor_category_requests
         WHERE vendor_id = :vendor_id AND status = 'approved' AND is_enabled = 1"
    );
    $stmt->execute(['vendor_id' => $vendorId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function decide_category_request(int $requestId, string $status, int $adminId, ?string $notes, ?int $usageLimit): void
{
    $stmt = db()->prepare(
        'UPDATE vendor_category_requests
         SET status = :status, admin_notes = :notes, usage_limit = :usage_limit,
             decided_at = NOW(), decided_by = :admin_id
         WHERE id = :id'
    );
    $stmt->execute([
        'status'      => $status,
        'notes'       => $notes,
        'usage_limit' => $usageLimit,
        'admin_id'    => $adminId,
        'id'          => $requestId,
    ]);
}

function set_category_request_enabled(int $requestId, bool $enabled): void
{
    $stmt = db()->prepare('UPDATE vendor_category_requests SET is_enabled = :enabled WHERE id = :id');
    $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $requestId]);
}
