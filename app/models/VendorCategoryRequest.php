<?php

/**
 * Governs which categories a Business Shop vendor is allowed to sell
 * in. A category only becomes available on the "add product" form
 * once a request for it has status = approved and is_enabled = 1.
 */
class VendorCategoryRequest extends Model
{
    protected string $table = 'vendor_category_requests';

    public function requestCategories(int $vendorId, array $categoryIds): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO vendor_category_requests (vendor_id, category_id) VALUES (:vendor_id, :category_id)'
        );

        foreach ($categoryIds as $categoryId) {
            $stmt->execute(['vendor_id' => $vendorId, 'category_id' => (int) $categoryId]);
        }
    }

    public function forVendor(int $vendorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT vendor_category_requests.*, categories.name AS category_name, categories.slug AS category_slug
             FROM vendor_category_requests
             JOIN categories ON categories.id = vendor_category_requests.category_id
             WHERE vendor_category_requests.vendor_id = :vendor_id
             ORDER BY vendor_category_requests.created_at DESC'
        );
        $stmt->execute(['vendor_id' => $vendorId]);
        return $stmt->fetchAll();
    }

    public function pending(): array
    {
        $stmt = $this->db->query(
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

    /** Category IDs a vendor may currently list products in. */
    public function approvedCategoryIds(int $vendorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT category_id FROM vendor_category_requests
             WHERE vendor_id = :vendor_id AND status = 'approved' AND is_enabled = 1"
        );
        $stmt->execute(['vendor_id' => $vendorId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function decide(int $requestId, string $status, int $adminId, ?string $notes = null, ?int $usageLimit = null): bool
    {
        return $this->update($requestId, [
            'status'      => $status,
            'admin_notes' => $notes,
            'usage_limit' => $usageLimit,
            'decided_at'  => date('Y-m-d H:i:s'),
            'decided_by'  => $adminId,
        ]);
    }

    public function setEnabled(int $requestId, bool $enabled): bool
    {
        return $this->update($requestId, ['is_enabled' => $enabled ? 1 : 0]);
    }
}
