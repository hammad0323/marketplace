<?php

class Product extends Model
{
    protected string $table = 'products';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function published(int $marketplaceTypeId, int $limit = 12): array
    {
        $stmt = $this->db->prepare(
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

    public function bestSellers(int $marketplaceTypeId, int $limit = 8): array
    {
        $stmt = $this->db->prepare(
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

    public function byVendor(int $vendorId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE vendor_id = :vendor_id ORDER BY created_at DESC');
        $stmt->execute(['vendor_id' => $vendorId]);
        return $stmt->fetchAll();
    }

    public function byCategory(int $categoryId, int $limit = 24): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE category_id = :category_id AND status = 'published'
             ORDER BY created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countInCategoryForVendor(int $vendorId, int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM products WHERE vendor_id = :vendor_id AND category_id = :category_id'
        );
        $stmt->execute(['vendor_id' => $vendorId, 'category_id' => $categoryId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Global search across every marketplace, tagged with marketplace
     * slug/badge so results can be labelled Handmade / Business Shop /
     * Official Store regardless of which marketplace they came from.
     */
    public function search(string $term, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
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
}
