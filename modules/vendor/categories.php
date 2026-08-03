<?php
$vendor = require_vendor();
$marketplaceSlug = find_marketplace_type($vendor['marketplace_type_id'])['slug'];
if ($marketplaceSlug !== 'business') {
    redirect('/vendor/dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $categoryIds = array_map('intval', $_POST['category_ids'] ?? []);
    $validCategoryIds = filter_category_ids_by_marketplace($categoryIds, $vendor['marketplace_type_id']);
    if ($validCategoryIds) {
        request_vendor_categories($vendor['id'], $validCategoryIds);
        flash('success', 'Category request submitted for admin approval.');
    }

    redirect('/vendor/dashboard/categories');
}

$requests = vendor_category_requests_for_vendor($vendor['id']);
$requestedIds = array_column($requests, 'category_id');
$businessType = find_marketplace_type_by_slug('business');
$availableCategories = array_filter(
    active_categories_by_marketplace($businessType['id']),
    fn ($c) => !in_array($c['id'], $requestedIds, true)
);

$pageTitle = 'Selling Categories';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<h1>Selling Categories</h1>

<div class="content-panel">
    <h2>Your Requests</h2>
    <?php if (!$requests): ?>
        <p>You haven't requested any categories yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Category</th><th>Status</th><th>Enabled</th><th>Limit</th><th>Admin Notes</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['category_name']) ?></td>
                    <td><span class="badge badge-<?= e($request['status']) ?>"><?= e(ucfirst($request['status'])) ?></span></td>
                    <td><?= $request['is_enabled'] ? 'Yes' : 'No' ?></td>
                    <td><?= $request['usage_limit'] !== null ? (int) $request['usage_limit'] : 'Unlimited' ?></td>
                    <td><?= e($request['admin_notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($availableCategories): ?>
<div class="content-panel">
    <h2>Request More Categories</h2>
    <form method="post" action="/vendor/dashboard/categories">
        <?= csrf_field() ?>
        <div class="checkbox-grid">
            <?php foreach ($availableCategories as $category): ?>
                <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= e($category['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:1rem;">Submit Request</button>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
