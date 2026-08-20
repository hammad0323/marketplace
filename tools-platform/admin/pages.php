<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $page = tp_query_one('SELECT * FROM pages WHERE id = ?', 'i', [$delId]);
    if ($page && !$page['is_system']) {
        tp_delete_route_file($page['slug']);
        tp_execute('DELETE FROM pages WHERE id = ?', 'i', [$delId]);
        tp_flash_set('success', 'Page deleted.');
    } else {
        tp_flash_set('error', 'System pages cannot be deleted.');
    }
    header('Location: ' . tp_url('admin/pages.php'));
    exit;
}

$pages = tp_query('SELECT * FROM pages ORDER BY title ASC');
$adminPageTitle = 'Pages';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <div></div>
  <a href="<?= tp_url('admin/page-form.php') ?>" class="btn tp-btn-calc" style="width:auto;"><i class="bi bi-plus-lg"></i> Add New Page</a>
</div>
<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Title</th><th>URL</th><th>Status</th><th>Type</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($pages as $p): ?>
      <tr>
        <td><?= e($p['title']) ?></td>
        <td>/<?= e($p['slug']) ?>.php</td>
        <td><span class="badge bg-<?= $p['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($p['status']) ?></span></td>
        <td><?= $p['is_system'] ? 'System' : 'Custom' ?></td>
        <td>
          <a href="<?= tp_url('admin/page-form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <a href="<?= tp_url($p['slug'] . '.php') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Preview</a>
          <?php if (!$p['is_system']): ?>
            <a href="<?= tp_url('admin/pages.php?delete=' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this page?')">Delete</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
