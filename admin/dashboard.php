<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$pendingVendors = count(mp_pending_vendors());
$pendingCategoryRequests = count(mp_pending_category_requests());
$allOrders = mp_all_orders();
$totalRevenue = array_sum(array_map(fn ($o) => (float) $o['total_amount'], $allOrders));

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Admin Dashboard</h1>

<div class="admin-stat-grid">
    <a class="admin-stat" href="vendors.php">
        <strong><?= $pendingVendors ?></strong>
        Pending Vendor Approvals
    </a>
    <a class="admin-stat" href="category-requests.php">
        <strong><?= $pendingCategoryRequests ?></strong>
        Pending Category Requests
    </a>
    <a class="admin-stat" href="orders.php">
        <strong><?= count($allOrders) ?></strong>
        Total Orders
    </a>
    <a class="admin-stat" href="orders.php">
        <strong>$<?= number_format($totalRevenue, 2) ?></strong>
        Total Order Value
    </a>
</div>

<div class="admin-panel" style="margin-top:1.5rem;">
    <p>This is the core marketplace-architecture slice of the admin panel:
       vendor approval, category approval, and order oversight. Full CRUD over
       products, SEO, email templates, CMS, etc. is intentionally out of scope
       for this PR.</p>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
