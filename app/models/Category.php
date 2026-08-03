<?php

class Category extends Model
{
    protected string $table = 'categories';

    public function findBySlugInMarketplace(string $slug, int $marketplaceTypeId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM categories WHERE slug = :slug AND marketplace_type_id = :type LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'type' => $marketplaceTypeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function activeByMarketplace(int $marketplaceTypeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM categories
             WHERE marketplace_type_id = :type AND is_active = 1
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute(['type' => $marketplaceTypeId]);
        return $stmt->fetchAll();
    }

    public function allByMarketplace(int $marketplaceTypeId): array
    {
        $stmt = $this->db->prepare(
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
    public function filterIdsByMarketplace(array $categoryIds, int $marketplaceTypeId): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (!$categoryIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id FROM categories WHERE marketplace_type_id = ? AND id IN ({$placeholders})"
        );
        $stmt->execute([$marketplaceTypeId, ...$categoryIds]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
