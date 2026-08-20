<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $action = $_POST['bulk_action'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if ($ids && $action) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        if ($action === 'publish') {
            tp_execute("UPDATE categories SET status='published' WHERE id IN ($placeholders)", $types, $ids);
        } elseif ($action === 'hide') {
            tp_execute("UPDATE categories SET status='hidden' WHERE id IN ($placeholders)", $types, $ids);
        } elseif ($action === 'delete') {
            tp_execute("DELETE FROM categories WHERE id IN ($placeholders)", $types, $ids);
        }
        tp_log_activity($_SESSION['admin_id'], 'bulk_' . $action, 'category', null, implode(',', $ids));
        tp_flash_set('success', 'Bulk action applied to ' . count($ids) . ' categor' . (count($ids) === 1 ? 'y' : 'ies') . '.');
    }
    header('Location: ' . tp_url('admin/categories.php'));
    exit;
}

$categories = tp_query(
    "SELECT c.*, (SELECT COUNT(*) FROM tools t WHERE t.category_id = c.id) AS tool_count FROM categories c ORDER BY c.sort_order ASC"
);

$adminPageTitle = 'Categories';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <div></div>
  <a href="<?= tp_url('admin/category-form.php') ?>" class="btn tp-btn-calc" style="width:auto;"><i class="bi bi-plus-lg"></i> Add New Category</a>
</div>

<form method="post" class="admin-card">
  <?= tp_csrf_field() ?>
  <div class="d-flex gap-2 mb-3">
    <select name="bulk_action" id="bulkActionSelect" class="form-select" style="max-width:220px;">
      <option value="">Bulk action...</option>
      <option value="publish">Publish</option>
      <option value="hide">Hide</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" id="applyBulkAction" class="btn btn-outline-dark">Apply</button>
  </div>
  <table class="table tp-datatable">
    <thead><tr><th><input type="checkbox" id="selectAllRows"></th><th>Icon</th><th>Name</th><th>Tools</th><th>Status</th><th>Order</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
      <tr>
        <td><input type="checkbox" class="row-checkbox" name="ids[]" value="<?= (int) $cat['id'] ?>"></td>
        <td><i class="bi <?= e($cat['icon']) ?>" style="color:<?= e($cat['color']) ?>;font-size:1.2rem;"></i></td>
        <td><?= e($cat['name']) ?> <br><small class="text-muted">/<?= e($cat['slug']) ?>.php</small></td>
        <td><?= (int) $cat['tool_count'] ?></td>
        <td><span class="badge bg-<?= $cat['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($cat['status']) ?></span></td>
        <td><?= (int) $cat['sort_order'] ?></td>
        <td>
          <a href="<?= tp_url('admin/category-form.php?id=' . $cat['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <a href="<?= tp_url($cat['slug'] . '.php') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Preview</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</form>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
