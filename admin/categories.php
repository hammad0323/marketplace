<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $catId = (int) ($_POST['category_id'] ?? 0);
    $category = $catId ? db_select_one($conn, 'SELECT * FROM categories WHERE id = ?', [$catId]) : null;

    if (!$category) {
        flash_set('danger', 'Category not found.');
        redirect('/admin/categories.php');
    }

    if ($action === 'toggle') {
        db_execute($conn, 'UPDATE categories SET is_active = ? WHERE id = ?', [$category['is_active'] ? 0 : 1, $catId]);
        flash_set('success', 'Category visibility updated.');
    } elseif ($action === 'delete') {
        $childCount = db_count($conn, 'SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$catId]);
        $serviceCount = db_count($conn, 'SELECT COUNT(*) FROM services WHERE category_id = ?', [$catId]);
        if ($childCount > 0 || $serviceCount > 0) {
            flash_set('danger', 'Cannot delete a category that has subcategories or services. Hide it instead.');
        } else {
            db_execute($conn, 'DELETE FROM categories WHERE id = ?', [$catId]);
            flash_set('success', 'Category deleted.');
        }
    }
    log_audit($conn, (int) $admin['id'], 'category', $catId, $action);
    redirect('/admin/categories.php');
}

$categories = db_select(
    $conn,
    'SELECT c.*, parent.name AS parent_name,
        (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS service_count
     FROM categories c LEFT JOIN categories parent ON parent.id = c.parent_id
     ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order'
);

$adminPageTitle = 'Categories';
$adminActive = 'categories';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All categories</h3>
    <a href="/admin/category-form.php" class="btn-w btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New category</a>
  </div>

  <?php if ($categories): ?>
  <table class="table-w">
    <thead><tr><th>Name</th><th>Parent</th><th>Services</th><th>Order</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td>
            <?php echo $cat['parent_id'] ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' : ''; ?>
            <i class="bi <?php echo e($cat['icon'] ?: 'bi-tag'); ?>" style="color:var(--purple-600);margin-right:6px;"></i>
            <strong><?php echo e($cat['name']); ?></strong>
          </td>
          <td><?php echo e($cat['parent_name'] ?? '—'); ?></td>
          <td><?php echo (int) $cat['service_count']; ?></td>
          <td><?php echo (int) $cat['sort_order']; ?></td>
          <td><?php echo status_badge($cat['is_active'] ? 'active' : 'blocked'); ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="/admin/category-form.php?id=<?php echo (int) $cat['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a>
            <form method="post" style="display:inline;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="category_id" value="<?php echo (int) $cat['id']; ?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit" class="btn-w btn-outline btn-sm"><?php echo $cat['is_active'] ? 'Hide' : 'Show'; ?></button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo e($cat['name']); ?>?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="category_id" value="<?php echo (int) $cat['id']; ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-grid"></i></div><h4>No categories yet</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
