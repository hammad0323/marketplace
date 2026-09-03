<?php
require __DIR__ . '/config.php';

mp_require_admin();

$pendingVendors = count(mp_pending_vendors());
$pendingCategoryRequests = count(mp_pending_category_requests());

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/admin-header.php';
?>

<h1>Admin Dashboard</h1>

<div class="admin-stat-grid">
    <a class="admin-stat" href="/admin-vendors.php">
        <strong><?= $pendingVendors ?></strong>
        Pending Vendor Approvals
    </a>
    <a class="admin-stat" href="/admin-category-requests.php">
        <strong><?= $pendingCategoryRequests ?></strong>
        Pending Category Requests
    </a>
</div>

<div class="admin-panel" style="margin-top:1.5rem;">
    <p>This is the core marketplace-architecture slice of the admin panel:
       vendor approval and category approval. Full CRUD over products, orders,
       SEO, email templates, CMS, etc. is intentionally out of scope for this PR.</p>
</div>

<?php require __DIR__ . '/admin-footer.php'; ?>
