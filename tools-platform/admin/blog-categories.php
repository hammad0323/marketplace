<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $name = tp_sanitize_text($_POST['name'] ?? '', 150);
    if ($name !== '') {
        $slug = tp_unique_slug('blog_categories', $name);
        tp_execute('INSERT INTO blog_categories (name, slug) VALUES (?, ?)', 'ss', [$name, $slug]);
        tp_flash_set('success', 'Blog category added.');
    }
    header('Location: ' . tp_url('admin/blog-categories.php'));
    exit;
}

if (isset($_GET['delete'])) {
    tp_execute('DELETE FROM blog_categories WHERE id = ?', 'i', [(int) $_GET['delete']]);
    header('Location: ' . tp_url('admin/blog-categories.php'));
    exit;
}

$cats = tp_query('SELECT bc.*, (SELECT COUNT(*) FROM blog_posts WHERE blog_category_id = bc.id) AS post_count FROM blog_categories bc ORDER BY name');
$adminPageTitle = 'Blog Categories';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-card mb-3">
  <form method="post" class="d-flex gap-2">
    <?= tp_csrf_field() ?>
    <input type="text" name="name" class="form-control" placeholder="New category name" required>
    <button class="btn tp-btn-calc" style="width:auto;">Add</button>
  </form>
</div>
<div class="admin-card">
  <table class="table">
    <thead><tr><th>Name</th><th>Posts</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($cats as $c): ?>
      <tr><td><?= e($c['name']) ?></td><td><?= (int) $c['post_count'] ?></td>
        <td><a href="?delete=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</a></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
