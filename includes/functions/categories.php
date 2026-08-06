<?php
/**
 * categories — scoped per marketplace type, sharing one auto-increment
 * ID space across all marketplace types.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_category(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM categories WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_category_by_slug_in_marketplace(string $slug, int $marketplaceTypeId): ?array
{
    return mp_db_fetch_one(
        'SELECT * FROM categories WHERE slug = ? AND marketplace_type_id = ? LIMIT 1',
        [$slug, $marketplaceTypeId]
    );
}

function mp_active_categories_by_marketplace(int $marketplaceTypeId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM categories
         WHERE marketplace_type_id = ? AND is_active = 1
         ORDER BY sort_order ASC, name ASC',
        [$marketplaceTypeId]
    );
}

function mp_all_categories_by_marketplace(int $marketplaceTypeId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM categories WHERE marketplace_type_id = ? ORDER BY sort_order ASC, name ASC',
        [$marketplaceTypeId]
    );
}

/** Active category count for one marketplace type — for stat counters. */
function mp_count_active_categories(int $marketplaceTypeId): int
{
    return (int) mp_db_fetch_value(
        'SELECT COUNT(*) FROM categories WHERE marketplace_type_id = ? AND is_active = 1',
        [$marketplaceTypeId]
    );
}

/** All categories site-wide (both marketplaces), for admin/categories.php. */
function mp_all_categories_admin(): array
{
    return mp_db_fetch_all(
        'SELECT categories.*, marketplace_types.name AS marketplace_name, marketplace_types.slug AS marketplace_slug,
                (SELECT COUNT(*) FROM products WHERE products.category_id = categories.id) AS product_count
         FROM categories
         JOIN marketplace_types ON marketplace_types.id = categories.marketplace_type_id
         ORDER BY marketplace_types.slug ASC, categories.sort_order ASC, categories.name ASC'
    );
}

function mp_insert_category(array $data): int
{
    return mp_db_insert('categories', $data);
}

function mp_update_category(int $id, array $data): void
{
    mp_db_update('categories', $data, 'id = ?', [$id]);
}

function mp_delete_category(int $id): void
{
    mp_db_execute('DELETE FROM categories WHERE id = ?', [$id]);
}

function mp_set_category_active(int $id, bool $active): void
{
    mp_db_execute('UPDATE categories SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
}

/**
 * Filters an arbitrary list of category IDs down to only those that
 * actually belong to the given marketplace type. Used to sanitize
 * user-submitted category_ids[] before creating vendor category
 * requests, since categories share one auto-increment ID space
 * across all marketplace types.
 */
function mp_filter_category_ids_by_marketplace(array $categoryIds, int $marketplaceTypeId): array
{
    $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
    if (!$categoryIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $sql = "SELECT id FROM categories WHERE marketplace_type_id = ? AND id IN ({$placeholders})";

    return array_map('intval', mp_db_fetch_column($sql, [$marketplaceTypeId, ...$categoryIds]));
}
