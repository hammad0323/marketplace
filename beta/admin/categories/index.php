<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$categories = db_fetch_all("SELECT c.*, (SELECT COUNT(*) FROM subcategories sc WHERE sc.category_id=c.id) sub_count,
    (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) product_count FROM categories c ORDER BY c.sort_order");

$dashRole = 'admin'; $pageTitle = 'Categories'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Categories</h1>
  <a href="<?= admin_url('categories/form.php') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Category</a>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>#</th><th>Image</th><th>Name</th><th>Commission</th><th>Subcategories</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td><?= $c['sort_order'] ?></td>
        <td><img class="table-thumb" src="<?= category_image_or_default($c['image']) ?>"></td>
        <td><?= clean($c['name']) ?><br><small class="text-muted"><i class="fa-solid <?= clean($c['icon']) ?>"></i> /<?= clean($c['slug']) ?></small></td>
        <td><?= $c['commission_type'] === 'percentage' ? $c['commission_value'] . '%' : format_price($c['commission_value']) ?></td>
        <td><a href="<?= admin_url('categories/subcategories.php?category_id=' . $c['id']) ?>"><?= $c['sub_count'] ?> subcategories</a></td>
        <td><?= $c['product_count'] ?></td>
        <td><span class="badge badge-<?= $c['status'] === 'active' ? 'success' : 'danger' ?>"><?= clean($c['status']) ?></span></td>
        <td class="action-cell">
          <a href="<?= admin_url('categories/form.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
          <form method="post" action="<?= admin_url('categories/delete.php') ?>" class="inline-form" onsubmit="return confirm('Delete this category and all its subcategories?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
