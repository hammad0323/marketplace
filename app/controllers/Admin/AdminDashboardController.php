<?php

class AdminDashboardController
{
    public function index(): void
    {
        Auth::requireAdmin();

        View::render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'pendingVendors' => count((new Vendor())->pending()),
            'pendingCategoryRequests' => count((new VendorCategoryRequest())->pending()),
        ], 'admin');
    }
}
