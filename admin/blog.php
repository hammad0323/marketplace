<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    db_execute($conn, 'DELETE FROM blogs WHERE id = ?', [(int) $_POST['id']]);
    flash_set('success', 'Post deleted.');
    redirect('/admin/blog.php');
}

$posts = db_select($conn, 'SELECT b.*, bc.name AS category_name FROM blogs b LEFT JOIN blog_categories bc ON bc.id = b.category_id ORDER BY b.created_at DESC');

$adminPageTitle = 'Blog';
$adminActive = 'blog';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All posts</h3>
    <a href="<?php echo url('/admin/blog-form.php'); ?>" class="btn-w btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New post</a>
  </div>
  <?php if ($posts): ?>
    <table class="table-w">
      <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td><strong><?php echo e($p['title']); ?></strong></td>
            <td><?php echo e($p['category_name'] ?? '—'); ?></td>
            <td><?php echo status_badge($p['status'] === 'published' ? 'approved' : 'pending'); ?></td>
            <td><?php echo $p['published_at'] ? e(format_date($p['published_at'])) : '—'; ?></td>
            <td style="text-align:right;">
              <a href="<?php echo url('/admin/blog-form.php'); ?>?id=<?php echo (int) $p['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-journal-text"></i></div><h4>No posts yet</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
