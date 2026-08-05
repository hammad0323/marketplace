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
    return mp_db_fetch_one('SELECT * FROM products WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_product_by_slug(string $slug): ?array
{
    return mp_db_fetch_one('SELECT * FROM products WHERE slug = ? LIMIT 1', [$slug]);
}

function mp_published_products(int $marketplaceTypeId, int $limit = 12): array
{
    return mp_db_fetch_all(
        "SELECT * FROM products
         WHERE marketplace_type_id = ? AND status = 'published'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT ?",
        [$marketplaceTypeId, $limit]
    );
}

function mp_best_seller_products(int $marketplaceTypeId, int $limit = 8): array
{
    return mp_db_fetch_all(
        "SELECT * FROM products
         WHERE marketplace_type_id = ? AND status = 'published' AND is_best_seller = 1
         ORDER BY created_at DESC
         LIMIT ?",
        [$marketplaceTypeId, $limit]
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
            "SELECT COUNT(*) FROM products WHERE status = 'published' AND marketplace_type_id = ?",
            [$marketplaceTypeId]
        );
    }
    return (int) mp_db_fetch_value("SELECT COUNT(*) FROM products WHERE status = 'published'");
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

/**
 * Global search across every marketplace, tagged with marketplace
 * slug/badge so results can be labelled Handmade / Business Shop /
 * Official Store regardless of which marketplace they came from.
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
         WHERE products.status = 'published'
           AND (products.title LIKE ? OR products.description LIKE ?)
         ORDER BY products.is_featured DESC, products.created_at DESC
         LIMIT ?",
        [$wildcard, $wildcard, $limit]
    );
}
