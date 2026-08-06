<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();

$category = mp_find_category((int) ($_GET['id'] ?? 0));
if (!$category) {
    require __DIR__ . '/../404.php';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        mp_flash('error', 'Category name is required.');
        mp_redirect('category-edit.php?id=' . $category['id']);
    }

    mp_update_category($category['id'], [
        'name'       => $name,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ]);

    mp_log_activity('admin', $admin['id'], 'category.updated', 'category', $category['id'], $name);
    mp_flash('success', 'Category updated.');
    mp_redirect('categories.php');
}

$pageTitle = 'Edit Category';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Edit Category</h1>

<div class="admin-panel">
    <form method="post" action="category-edit.php?id=<?= (int) $category['id'] ?>">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required value="<?= mp_e($category['name']) ?>">
        </div>
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int) $category['sort_order'] ?>">
        </div>
        <p style="color:var(--ink-500); font-size:.85rem;">Slug (<code><?= mp_e($category['slug']) ?></code>) can't be changed here — it's part of live category URLs.</p>
        <button type="submit" class="btn">Save Changes</button>
        <a class="btn btn-secondary" href="categories.php">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
