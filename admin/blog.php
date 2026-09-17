<?php
require __DIR__ . '/../config.php';
wh_require_page_access('blog');
$businessId = wh_current_business_id();

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    wh_execute('DELETE FROM blog_posts WHERE id=? AND business_id=?', 'ii', [(int) $_GET['delete'], $businessId]);
    wh_flash_set('success', 'Post deleted.');
    wh_redirect(BASE_URL . '/admin/blog.php');
}
if (isset($_GET['toggle'])) {
    wh_csrf_verify();
    $id = (int) $_GET['toggle'];
    $post = wh_fetch_one('SELECT status FROM blog_posts WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
    $newStatus = ($post['status'] ?? 'draft') === 'published' ? 'draft' : 'published';
    wh_update('blog_posts', ['status' => $newStatus, 'published_at' => $newStatus === 'published' ? date('Y-m-d H:i:s') : null], 'id = ? AND business_id = ?', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/blog.php');
}

$posts = wh_fetch_all('SELECT * FROM blog_posts WHERE business_id=? ORDER BY created_at DESC', 'i', [$businessId]);

$pageTitle = 'Blog';
$activePage = 'blog';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Blog Posts</h3>
    <a href="<?= e(BASE_URL) ?>/admin/blog-form.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Post</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Published</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><strong><?= e($p['title']) ?></strong></td>
        <td><?= e($p['category'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $p['status'] === 'published' ? 'active' : 'inactive' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td><?= $p['published_at'] ? wh_format_date($p['published_at']) : '—' ?></td>
        <td style="white-space:nowrap;">
          <a href="<?= e(BASE_URL) ?>/admin/blog-form.php?id=<?= (int) $p['id'] ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/blog.php?toggle=<?= (int) $p['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-right-arrow-left"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/blog.php?delete=<?= (int) $p['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this post?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$posts): ?><tr><td colspan="5">No blog posts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/footer.php'; ?>
