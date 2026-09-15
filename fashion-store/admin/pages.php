<?php
$pageTitle = 'Pages';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $seoTitle = trim($_POST['seo_title'] ?? '');
        $seoDescription = trim($_POST['seo_description'] ?? '');
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        if ($title === '') {
            flash_set('danger', 'Title is required.');
        } else {
            $slug = unique_slug($mysqli, 'pages', slugify($title), $id);
            if ($id) {
                $stmt = mysqli_prepare($mysqli, "UPDATE pages SET title=?, slug=?, content=?, seo_title=?, seo_description=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssssi', $title, $slug, $content, $seoTitle, $seoDescription, $status, $id);
            } else {
                $stmt = mysqli_prepare($mysqli, "INSERT INTO pages (title, slug, content, seo_title, seo_description, status) VALUES (?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'ssssss', $title, $slug, $content, $seoTitle, $seoDescription, $status);
            }
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Page saved.');
        }
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM pages WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Page deleted.');
    }
    redirect('pages.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editPage = $editId ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM pages WHERE id = $editId")) : null;
$pages = mysqli_query($mysqli, "SELECT * FROM pages ORDER BY title");
?>
<h1 class="page-title mb-4">Pages</h1>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="admin-card">
      <table class="table table-hover align-middle">
        <thead><tr><th>Title</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($pages)): ?>
          <tr>
            <td><?= e($p['title']) ?><br><span class="small text-muted">/page.php?slug=<?= e($p['slug']) ?></span></td>
            <td><span class="badge <?= $p['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($p['status']) ?></span></td>
            <td class="text-end">
              <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="admin-card">
      <h2 class="h6 mb-3"><?= $editPage ? 'Edit Page' : 'New Page' ?></h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editPage['id'] ?? 0) ?>">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control mb-3" required value="<?= e($editPage['title'] ?? '') ?>">
        <label class="form-label">Content</label>
        <textarea name="content" class="form-control richtext mb-3" rows="10"><?= $editPage['content'] ?? '' ?></textarea>
        <label class="form-label">SEO Title</label>
        <input type="text" name="seo_title" class="form-control mb-3" value="<?= e($editPage['seo_title'] ?? '') ?>">
        <label class="form-label">SEO Description</label>
        <textarea name="seo_description" class="form-control mb-3" rows="2"><?= e($editPage['seo_description'] ?? '') ?></textarea>
        <label class="form-label">Status</label>
        <select name="status" class="form-select mb-3">
          <option value="active" <?= (($editPage['status'] ?? 'active')==='active')?'selected':'' ?>>Active</option>
          <option value="inactive" <?= (($editPage['status'] ?? '')==='inactive')?'selected':'' ?>>Inactive</option>
        </select>
        <button class="btn btn-primary text-white">Save Page</button>
        <?php if ($editPage): ?><a href="pages.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
