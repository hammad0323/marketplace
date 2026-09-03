<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$category = $id ? db_fetch_one("SELECT * FROM categories WHERE id=?", 'i', [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name']); $description = clean_html($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-solid fa-tag');
    $commissionType = $_POST['commission_type']; $commissionValue = (float)$_POST['commission_value'];
    $sortOrder = (int)$_POST['sort_order']; $status = $_POST['status'];
    $slug = unique_slug('categories', slugify($name), 'id', $id ?: null);

    $imageUpload = handle_image_upload('image', 'categories');
    if (isset($imageUpload['error'])) { flash('error', $imageUpload['error']); redirect(admin_url('categories/form.php' . ($id ? "?id=$id" : ''))); }
    $imagePath = $imageUpload['path'] ?? ($category['image'] ?? null);

    if ($category) {
        db_exec("UPDATE categories SET name=?,slug=?,description=?,image=?,icon=?,commission_type=?,commission_value=?,sort_order=?,status=? WHERE id=?",
            'ssssssdisi', [$name, $slug, $description, $imagePath, $icon, $commissionType, $commissionValue, $sortOrder, $status, $id]);
        flash('success', 'Category updated.');
    } else {
        db_insert("INSERT INTO categories (name,slug,description,image,icon,commission_type,commission_value,sort_order,status) VALUES (?,?,?,?,?,?,?,?,?)",
            'ssssssdis', [$name, $slug, $description, $imagePath, $icon, $commissionType, $commissionValue, $sortOrder, $status]);
        flash('success', 'Category created.');
    }
    redirect(admin_url('categories/index.php'));
}

$dashRole = 'admin'; $pageTitle = $category ? 'Edit Category' : 'Add Category'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1><?= $category ? 'Edit' : 'Add' ?> Category</h1></div>
<div class="dash-form-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Name</label>
    <input type="text" name="name" value="<?= clean($category['name'] ?? '') ?>" required>
    <label>Description</label>
    <textarea name="description" rows="4"><?= clean($category['description'] ?? '') ?></textarea>
    <div class="form-row">
      <div><label>Icon (Font Awesome class)</label><input type="text" name="icon" value="<?= clean($category['icon'] ?? 'fa-solid fa-tag') ?>"></div>
      <div><label>Image</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        <?php if (!empty($category['image'])): ?><img class="table-thumb" src="<?= category_image_or_default($category['image']) ?>"><?php endif; ?>
      </div>
    </div>
    <div class="form-row">
      <div><label>Commission Type</label>
        <select name="commission_type">
          <option value="percentage" <?= ($category['commission_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
          <option value="fixed" <?= ($category['commission_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
        </select>
      </div>
      <div><label>Commission Value</label><input type="number" step="0.01" name="commission_value" value="<?= clean($category['commission_value'] ?? '10') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Sort Order</label><input type="number" name="sort_order" value="<?= clean($category['sort_order'] ?? '0') ?>"></div>
      <div><label>Status</label>
        <select name="status">
          <option value="active" <?= ($category['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($category['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Category</button>
    <a href="<?= admin_url('categories/index.php') ?>" class="btn btn-outline">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
