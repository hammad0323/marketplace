<?php
/** Plain query functions for the products table. */

function find_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function find_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function published_products(int $marketplaceTypeId, int $limit = 12): array
{
    $stmt = db()->prepare(
        "SELECT * FROM products
         WHERE marketplace_type_id = :type AND status = 'published'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function best_seller_products(int $marketplaceTypeId, int $limit = 8): array
{
    $stmt = db()->prepare(
        "SELECT * FROM products
         WHERE marketplace_type_id = :type AND status = 'published' AND is_best_seller = 1
         ORDER BY created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function products_by_vendor(int $vendorId): array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE vendor_id = :vendor_id ORDER BY created_at DESC');
    $stmt->execute(['vendor_id' => $vendorId]);
    return $stmt->fetchAll();
}

function products_by_category(int $categoryId, int $limit = 24): array
{
    $stmt = db()->prepare(
        "SELECT * FROM products WHERE category_id = :category_id AND status = 'published'
         ORDER BY created_at DESC LIMIT :limit"
    );
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_vendor_products_in_category(int $vendorId, int $categoryId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM products WHERE vendor_id = :vendor_id AND category_id = :category_id'
    );
    $stmt->execute(['vendor_id' => $vendorId, 'category_id' => $categoryId]);
    return (int) $stmt->fetchColumn();
}

function insert_product(array $data): int
{
    $columns = array_keys($data);
    $placeholders = array_map(fn ($c) => ":{$c}", $columns);
    $stmt = db()->prepare(
        'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($data);
    return (int) db()->lastInsertId();
}

/**
 * Global search across every marketplace, tagged with marketplace
 * slug/badge so results can be labelled Handmade / Business Shop /
 * Official Store regardless of which marketplace they came from.
 */
function search_products(string $term, int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT products.*, marketplace_types.slug AS marketplace_slug,
                marketplace_types.badge_label, vendors.store_name, vendors.slug AS vendor_slug
         FROM products
         JOIN marketplace_types ON marketplace_types.id = products.marketplace_type_id
         JOIN vendors ON vendors.id = products.vendor_id
         WHERE products.status = 'published'
           AND (products.title LIKE :term_title OR products.description LIKE :term_description)
         ORDER BY products.is_featured DESC, products.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':term_title', '%' . $term . '%');
    $stmt->bindValue(':term_description', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
