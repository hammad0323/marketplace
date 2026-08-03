<?php

/**
 * Admin gate for the vendor onboarding workflow: a vendor cannot sell
 * or become publicly visible until approved here.
 */
class VendorApprovalController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $vendorModel = new Vendor();

        View::render('admin/vendors', [
            'title' => 'Vendor Approvals',
            'pending' => $vendorModel->pending(),
            'all' => $vendorModel->all('created_at DESC'),
        ], 'admin');
    }

    public function approve(array $params): void
    {
        $admin = Auth::requireAdmin();
        verify_csrf();

        $vendorModel = new Vendor();
        $vendor = $vendorModel->find((int) $params['id']);
        if ($vendor) {
            $vendorModel->approve($vendor['id'], $admin['id']);
            Notifier::log('vendor.approved', $vendor['email'], ['store_name' => $vendor['store_name']]);
            flash('success', $vendor['store_name'] . ' approved. Store is now public.');
        }

        redirect('/admin/vendors');
    }

    public function reject(array $params): void
    {
        Auth::requireAdmin();
        verify_csrf();

        $vendorModel = new Vendor();
        $vendor = $vendorModel->find((int) $params['id']);
        $reason = trim($_POST['reason'] ?? 'Not specified');

        if ($vendor) {
            $vendorModel->reject($vendor['id'], $reason);
            Notifier::log('vendor.rejected', $vendor['email'], ['store_name' => $vendor['store_name'], 'reason' => $reason]);
            flash('success', $vendor['store_name'] . ' rejected.');
        }

        redirect('/admin/vendors');
    }
}
