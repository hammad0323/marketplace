<?php
/**
 * vendor_category_requests — governs which categories a Business Shop
 * vendor is allowed to sell in. A category only becomes available on
 * the "add product" form once a request for it has status = approved
 * and is_enabled = 1.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_category_request(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM vendor_category_requests WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_request_vendor_categories(int $vendorId, array $categoryIds): void
{
    foreach ($categoryIds as $categoryId) {
        mp_db_execute(
            'INSERT IGNORE INTO vendor_category_requests (tenant_id, vendor_id, category_id) VALUES (?, ?, ?)',
            [mp_tenant_id(), $vendorId, (int) $categoryId]
        );
    }
}

function mp_vendor_category_requests_for_vendor(int $vendorId): array
{
    return mp_db_fetch_all(
        'SELECT vendor_category_requests.*, categories.name AS category_name, categories.slug AS category_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         WHERE vendor_category_requests.vendor_id = ?
         ORDER BY vendor_category_requests.created_at DESC',
        [$vendorId]
    );
}

function mp_pending_category_requests(): array
{
    return mp_db_fetch_all(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         WHERE vendor_category_requests.tenant_id = ? AND vendor_category_requests.status = 'pending'
         ORDER BY vendor_category_requests.created_at ASC",
        [mp_tenant_id()]
    );
}

function mp_all_category_requests(): array
{
    return mp_db_fetch_all(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         WHERE vendor_category_requests.tenant_id = ?
         ORDER BY vendor_category_requests.created_at DESC",
        [mp_tenant_id()]
    );
}

/** Category IDs a vendor may currently list products in. */
function mp_approved_category_ids_for_vendor(int $vendorId): array
{
    return array_map('intval', mp_db_fetch_column(
        "SELECT category_id FROM vendor_category_requests
         WHERE vendor_id = ? AND status = 'approved' AND is_enabled = 1",
        [$vendorId]
    ));
}

function mp_decide_category_request(int $requestId, string $status, int $adminId, ?string $notes, ?int $usageLimit): void
{
    mp_db_execute(
        'UPDATE vendor_category_requests
         SET status = ?, admin_notes = ?, usage_limit = ?, decided_at = NOW(), decided_by = ?
         WHERE id = ?',
        [$status, $notes, $usageLimit, $adminId, $requestId]
    );
}

function mp_set_category_request_enabled(int $requestId, bool $enabled): void
{
    mp_db_execute(
        'UPDATE vendor_category_requests SET is_enabled = ? WHERE id = ?',
        [$enabled ? 1 : 0, $requestId]
    );
}
