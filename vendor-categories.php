<?php
require __DIR__ . '/config.php';

$vendor = mp_require_vendor();
$marketplaceSlug = mp_find_marketplace_type($vendor['marketplace_type_id'])['slug'];
if ($marketplaceSlug !== 'business') {
    mp_redirect('/vendor-dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $categoryIds = array_map('intval', $_POST['category_ids'] ?? []);
    $validCategoryIds = mp_filter_category_ids_by_marketplace($categoryIds, $vendor['marketplace_type_id']);
    if ($validCategoryIds) {
        mp_request_vendor_categories($vendor['id'], $validCategoryIds);
        mp_flash('success', 'Category request submitted for admin approval.');
    }

    mp_redirect('/vendor-categories.php');
}

$requests = mp_vendor_category_requests_for_vendor($vendor['id']);
$requestedIds = array_column($requests, 'category_id');
$businessType = mp_find_marketplace_type_by_slug('business');
$availableCategories = array_filter(
    mp_active_categories_by_marketplace($businessType['id']),
    fn ($c) => !in_array($c['id'], $requestedIds, true)
);

$pageTitle = 'Selling Categories';
$theme = 'main';
require __DIR__ . '/header.php';
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
                    <td><?= mp_e($request['category_name']) ?></td>
                    <td><span class="badge badge-<?= mp_e($request['status']) ?>"><?= mp_e(ucfirst($request['status'])) ?></span></td>
                    <td><?= $request['is_enabled'] ? 'Yes' : 'No' ?></td>
                    <td><?= $request['usage_limit'] !== null ? (int) $request['usage_limit'] : 'Unlimited' ?></td>
                    <td><?= mp_e($request['admin_notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($availableCategories): ?>
<div class="content-panel">
    <h2>Request More Categories</h2>
    <form method="post" action="/vendor-categories.php">
        <?= mp_csrf_field() ?>
        <div class="checkbox-grid">
            <?php foreach ($availableCategories as $category): ?>
                <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= mp_e($category['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:1rem;">Submit Request</button>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
