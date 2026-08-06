<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $marketplaceTypeId = (int) ($_POST['marketplace_type_id'] ?? 0);

    if ($name === '' || $marketplaceTypeId <= 0) {
        mp_flash('error', 'Category name and marketplace are required.');
        mp_redirect('categories.php');
    }

    $slugBase = mp_slugify($name);
    $newSlug = $slugBase;
    $suffix = 1;
    while (mp_find_category_by_slug_in_marketplace($newSlug, $marketplaceTypeId)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $categoryId = mp_insert_category([
        'marketplace_type_id' => $marketplaceTypeId,
        'name'                => $name,
        'slug'                => $newSlug,
        'sort_order'          => (int) ($_POST['sort_order'] ?? 0),
    ]);

    mp_log_activity('admin', $admin['id'], 'category.created', 'category', $categoryId, $name);
    mp_flash('success', $name . ' category added.');
    mp_redirect('categories.php');
}

$categories = mp_all_categories_admin();
$artisanType = mp_find_marketplace_type_by_slug('artisan');
$businessType = mp_find_marketplace_type_by_slug('business');

$pageTitle = 'Categories';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Categories</h1>

<div class="admin-panel">
    <h2 style="margin-top:0;">Add Category</h2>
    <form method="post" action="categories.php" class="checkout-address-grid" style="align-items:end;">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="marketplace_type_id">Marketplace</label>
            <select id="marketplace_type_id" name="marketplace_type_id" required>
                <option value="<?= (int) $artisanType['id'] ?>">Artisan Marketplace</option>
                <option value="<?= (int) $businessType['id'] ?>">Business Shops</option>
            </select>
        </div>
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" value="0" min="0">
        </div>
        <div class="form-group">
            <button type="submit" class="btn">Add Category</button>
        </div>
    </form>
</div>

<div class="admin-panel">
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Marketplace</th><th>Products</th><th>Sort</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= mp_e($category['name']) ?></td>
                <td><span class="badge"><?= mp_e($category['marketplace_name']) ?></span></td>
                <td><?= (int) $category['product_count'] ?></td>
                <td><?= (int) $category['sort_order'] ?></td>
                <td>
                    <form method="post" action="category-toggle-active.php" class="inline-form">
                        <?= mp_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <button type="submit" class="status-chip status-<?= $category['is_active'] ? 'completed' : 'cancelled' ?>" style="border:none; cursor:pointer;">
                            <?= $category['is_active'] ? 'Active' : 'Disabled' ?>
                        </button>
                    </form>
                </td>
                <td style="white-space:nowrap;">
                    <a href="category-edit.php?id=<?= (int) $category['id'] ?>">Edit</a>
                    <?php if ((int) $category['product_count'] === 0): ?>
                        &nbsp;·&nbsp;
                        <form method="post" action="category-delete.php" class="inline-form" onsubmit="return confirm('Delete this category?');">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                            <button type="submit" class="link-button" style="color:var(--danger);">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
