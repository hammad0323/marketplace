<?php
/**
 * products — listed by a vendor within one category, scoped to one
 * marketplace type.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_product(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM products WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_find_product_by_slug(string $slug): ?array
{
    return mp_db_fetch_one('SELECT * FROM products WHERE tenant_id = ? AND slug = ? LIMIT 1', [mp_tenant_id(), $slug]);
}

function mp_published_products(int $marketplaceTypeId, int $limit = 12): array
{
    return mp_db_fetch_all(
        "SELECT * FROM products
         WHERE tenant_id = ? AND marketplace_type_id = ? AND status = 'published'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT ?",
        [mp_tenant_id(), $marketplaceTypeId, $limit]
    );
}

function mp_best_seller_products(int $marketplaceTypeId, int $limit = 8): array
{
    return mp_db_fetch_all(
        "SELECT * FROM products
         WHERE tenant_id = ? AND marketplace_type_id = ? AND status = 'published' AND is_best_seller = 1
         ORDER BY created_at DESC
         LIMIT ?",
        [mp_tenant_id(), $marketplaceTypeId, $limit]
    );
}

function mp_products_by_vendor(int $vendorId): array
{
    return mp_db_fetch_all('SELECT * FROM products WHERE vendor_id = ? ORDER BY created_at DESC', [$vendorId]);
}

/** Published product count, optionally scoped to one marketplace type — for stat counters. */
function mp_count_published_products(?int $marketplaceTypeId = null): int
{
    if ($marketplaceTypeId !== null) {
        return (int) mp_db_fetch_value(
            "SELECT COUNT(*) FROM products WHERE tenant_id = ? AND status = 'published' AND marketplace_type_id = ?",
            [mp_tenant_id(), $marketplaceTypeId]
        );
    }
    return (int) mp_db_fetch_value("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND status = 'published'", [mp_tenant_id()]);
}

function mp_products_by_category(int $categoryId, int $limit = 24): array
{
    return mp_db_fetch_all(
        "SELECT * FROM products WHERE category_id = ? AND status = 'published'
         ORDER BY created_at DESC LIMIT ?",
        [$categoryId, $limit]
    );
}

function mp_count_vendor_products_in_category(int $vendorId, int $categoryId): int
{
    return (int) mp_db_fetch_value(
        'SELECT COUNT(*) FROM products WHERE vendor_id = ? AND category_id = ?',
        [$vendorId, $categoryId]
    );
}

function mp_insert_product(array $data): int
{
    return mp_db_insert('products', $data);
}

function mp_update_product(int $id, array $data): void
{
    mp_db_update('products', $data, 'id = ?', [$id]);
}

function mp_delete_product(int $id): void
{
    mp_db_execute('DELETE FROM products WHERE id = ?', [$id]);
}

function mp_set_product_status(int $id, string $status): void
{
    mp_db_execute('UPDATE products SET status = ? WHERE id = ?', [$status, $id]);
}

/** Every product in this tenant, for admin/products.php — newest first, with vendor/category/marketplace names joined in. */
function mp_all_products_admin(): array
{
    return mp_db_fetch_all(
        'SELECT products.*, vendors.store_name, categories.name AS category_name,
                marketplace_types.slug AS marketplace_slug, marketplace_types.badge_label
         FROM products
         JOIN vendors ON vendors.id = products.vendor_id
         JOIN categories ON categories.id = products.category_id
         JOIN marketplace_types ON marketplace_types.id = products.marketplace_type_id
         WHERE products.tenant_id = ?
         ORDER BY products.created_at DESC',
        [mp_tenant_id()]
    );
}

function mp_count_all_products(): int
{
    return (int) mp_db_fetch_value('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [mp_tenant_id()]);
}

/** Products with stock at or below $threshold — for the admin dashboard/reports low-stock widget. */
function mp_low_stock_products(int $threshold = 5, int $limit = 10): array
{
    return mp_db_fetch_all(
        "SELECT products.*, vendors.store_name
         FROM products
         JOIN vendors ON vendors.id = products.vendor_id
         WHERE products.tenant_id = ? AND products.status = 'published' AND products.stock_quantity <= ?
         ORDER BY products.stock_quantity ASC
         LIMIT ?",
        [mp_tenant_id(), $threshold, $limit]
    );
}

/**
 * Search across every marketplace type within this tenant, tagged
 * with marketplace slug/badge so results can be labelled Handmade /
 * Business Shop / Official Store regardless of which marketplace they
 * came from. Scoped to the current tenant — this is one tenant's own
 * cross-marketplace search, not a platform-wide search.
 */
function mp_search_products(string $term, int $limit = 30): array
{
    $wildcard = '%' . $term . '%';

    return mp_db_fetch_all(
        "SELECT products.*, marketplace_types.slug AS marketplace_slug,
                marketplace_types.badge_label, vendors.store_name, vendors.slug AS vendor_slug
         FROM products
         JOIN marketplace_types ON marketplace_types.id = products.marketplace_type_id
         JOIN vendors ON vendors.id = products.vendor_id
         WHERE products.tenant_id = ? AND products.status = 'published'
           AND (products.title LIKE ? OR products.description LIKE ?)
         ORDER BY products.is_featured DESC, products.created_at DESC
         LIMIT ?",
        [mp_tenant_id(), $wildcard, $wildcard, $limit]
    );
}
