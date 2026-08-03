<h1>Admin Dashboard</h1>

<div class="admin-stat-grid">
    <a class="admin-stat" href="/admin/vendors">
        <strong><?= $pendingVendors ?></strong>
        Pending Vendor Approvals
    </a>
    <a class="admin-stat" href="/admin/category-requests">
        <strong><?= $pendingCategoryRequests ?></strong>
        Pending Category Requests
    </a>
</div>

<div class="admin-panel" style="margin-top:1.5rem;">
    <p>This is the core marketplace-architecture slice of the admin panel:
       vendor approval and category approval. Full CRUD over products, orders,
       SEO, email templates, CMS, etc. is intentionally out of scope for this PR.</p>
</div>
