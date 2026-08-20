<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $post = tp_query_one('SELECT slug FROM blog_posts WHERE id = ?', 'i', [$delId]);
    if ($post) {
        tp_delete_route_file($post['slug'], 'blog');
        tp_execute('DELETE FROM blog_posts WHERE id = ?', 'i', [$delId]);
        tp_flash_set('success', 'Post deleted.');
    }
    header('Location: ' . tp_url('admin/blog.php'));
    exit;
}

$posts = tp_query(
    "SELECT bp.*, bc.name AS category_name FROM blog_posts bp LEFT JOIN blog_categories bc ON bc.id = bp.blog_category_id ORDER BY bp.created_at DESC"
);
$adminPageTitle = 'Blog';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <a href="<?= tp_url('admin/blog-categories.php') ?>" class="btn btn-outline-secondary">Manage Blog Categories</a>
  <a href="<?= tp_url('admin/blog-form.php') ?>" class="btn tp-btn-calc" style="width:auto;"><i class="bi bi-plus-lg"></i> Add New Post</a>
</div>
<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><?= e($p['title']) ?></td>
        <td><?= e($p['category_name'] ?? '—') ?></td>
        <td><span class="badge bg-<?= $p['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($p['status']) ?></span></td>
        <td><?= number_format($p['views']) ?></td>
        <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
        <td>
          <a href="<?= tp_url('admin/blog-form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <a href="<?= tp_url('blog/' . $p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Preview</a>
          <a href="<?= tp_url('admin/blog.php?delete=' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this post?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
