<?php
/** Plain query functions for the categories table. */

function find_category(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function find_category_by_slug_in_marketplace(string $slug, int $marketplaceTypeId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM categories WHERE slug = :slug AND marketplace_type_id = :type LIMIT 1'
    );
    $stmt->execute(['slug' => $slug, 'type' => $marketplaceTypeId]);
    return $stmt->fetch() ?: null;
}

function active_categories_by_marketplace(int $marketplaceTypeId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM categories
         WHERE marketplace_type_id = :type AND is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute(['type' => $marketplaceTypeId]);
    return $stmt->fetchAll();
}

function all_categories_by_marketplace(int $marketplaceTypeId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM categories WHERE marketplace_type_id = :type ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute(['type' => $marketplaceTypeId]);
    return $stmt->fetchAll();
}

/**
 * Filters an arbitrary list of category IDs down to only those that
 * actually belong to the given marketplace type. Used to sanitize
 * user-submitted category_ids[] before creating vendor category
 * requests, since categories share one auto-increment ID space
 * across all marketplace types.
 */
function filter_category_ids_by_marketplace(array $categoryIds, int $marketplaceTypeId): array
{
    $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
    if (!$categoryIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = db()->prepare(
        "SELECT id FROM categories WHERE marketplace_type_id = ? AND id IN ({$placeholders})"
    );
    $stmt->execute([$marketplaceTypeId, ...$categoryIds]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
