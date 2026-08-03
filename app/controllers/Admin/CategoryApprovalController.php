<?php

/**
 * Admin gate for which categories a Business Shop vendor may sell in.
 * Only requests this controller marks 'approved' (and left enabled)
 * make a category selectable on the vendor's add-product form.
 */
class CategoryApprovalController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $requestModel = new VendorCategoryRequest();
        $db = Database::connection();
        $all = $db->query(
            "SELECT vendor_category_requests.*, categories.name AS category_name,
                    vendors.store_name, vendors.slug AS vendor_slug
             FROM vendor_category_requests
             JOIN categories ON categories.id = vendor_category_requests.category_id
             JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
             ORDER BY vendor_category_requests.created_at DESC"
        )->fetchAll();

        View::render('admin/category_requests', [
            'title' => 'Category Approvals',
            'pending' => $requestModel->pending(),
            'all' => $all,
        ], 'admin');
    }

    public function decide(array $params): void
    {
        $admin = Auth::requireAdmin();
        verify_csrf();

        $status = $_POST['decision'] ?? '';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            redirect('/admin/category-requests');
        }

        $notes = trim($_POST['notes'] ?? '') ?: null;
        $usageLimit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;

        $requestModel = new VendorCategoryRequest();
        $requestModel->decide((int) $params['id'], $status, $admin['id'], $notes, $usageLimit);

        flash('success', 'Category request updated.');
        redirect('/admin/category-requests');
    }

    public function toggle(array $params): void
    {
        Auth::requireAdmin();
        verify_csrf();

        $requestModel = new VendorCategoryRequest();
        $request = $requestModel->find((int) $params['id']);
        if ($request) {
            $requestModel->setEnabled($request['id'], !$request['is_enabled']);
        }

        redirect('/admin/category-requests');
    }
}
