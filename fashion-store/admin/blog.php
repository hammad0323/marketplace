<?php
$pageTitle = 'Blog';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM blog_posts WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Post deleted.');
    } elseif ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $slug = unique_slug($mysqli, 'blog_categories', slugify($name), 0);
            $stmt = mysqli_prepare($mysqli, "INSERT INTO blog_categories (name, slug) VALUES (?,?)");
            mysqli_stmt_bind_param($stmt, 'ss', $name, $slug);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Blog category added.');
        }
    }
    redirect('blog.php');
}

$posts = mysqli_query($mysqli, "SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.blog_category_id ORDER BY p.created_at DESC");
$categories = mysqli_query($mysqli, "SELECT * FROM blog_categories ORDER BY name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Blog</h1>
  <a href="blog_form.php" class="btn btn-primary text-white"><i class="bi bi-plus-lg"></i> New Post</a>
</div>

<div class="row g-4">
  <div class="col-lg-9">
    <div class="admin-card">
      <table class="table table-hover align-middle">
        <thead><tr><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($posts)): ?>
          <tr>
            <td><?= e($p['title']) ?></td>
            <td><?= e($p['category_name']) ?></td>
            <td><?= e($p['author']) ?></td>
            <td><span class="badge <?= $p['status']==='published'?'text-bg-success':'text-bg-secondary' ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
            <td class="text-end">
              <a href="blog_form.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="admin-card">
      <h2 class="h6 mb-3">Blog Categories</h2>
      <ul class="list-unstyled small mb-3">
        <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
          <li><?= e($c['name']) ?></li>
        <?php endwhile; ?>
      </ul>
      <form method="post" class="d-flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_category">
        <input type="text" name="name" class="form-control form-control-sm" placeholder="New category" required>
        <button class="btn btn-sm btn-outline-primary">Add</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
