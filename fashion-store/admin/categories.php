<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $description = $_POST['description'] ?? '';
        $seoTitle = trim($_POST['seo_title'] ?? '');
        $seoDescription = trim($_POST['seo_description'] ?? '');
        $seoKeywords = trim($_POST['seo_keywords'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        if ($name === '') {
            flash_set('danger', 'Category name is required.');
        } else {
            $slug = unique_slug($mysqli, 'categories', slugify($name), $id);
            $image = handle_upload('image', 'categories');

            if ($id) {
                if ($image === false) {
                    flash_set('danger', 'Invalid image file.');
                } else {
                    if ($image) {
                        $stmt = mysqli_prepare($mysqli, "UPDATE categories SET parent_id=?, name=?, slug=?, image=?, description=?, seo_title=?, seo_description=?, seo_keywords=?, sort_order=?, status=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, 'isssssssisi', $parentId, $name, $slug, $image, $description, $seoTitle, $seoDescription, $seoKeywords, $sortOrder, $status, $id);
                    } else {
                        $stmt = mysqli_prepare($mysqli, "UPDATE categories SET parent_id=?, name=?, slug=?, description=?, seo_title=?, seo_description=?, seo_keywords=?, sort_order=?, status=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, 'issssssisi', $parentId, $name, $slug, $description, $seoTitle, $seoDescription, $seoKeywords, $sortOrder, $status, $id);
                    }
                    mysqli_stmt_execute($stmt);
                    flash_set('success', 'Category updated.');
                }
            } else {
                $image = $image ?: 'assets/img/placeholder.svg';
                $stmt = mysqli_prepare($mysqli, "INSERT INTO categories (parent_id, name, slug, image, description, seo_title, seo_description, seo_keywords, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'isssssssis', $parentId, $name, $slug, $image, $description, $seoTitle, $seoDescription, $seoKeywords, $sortOrder, $status);
                mysqli_stmt_execute($stmt);
                flash_set('success', 'Category created.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        mysqli_query($mysqli, "DELETE FROM categories WHERE id = " . $id);
        flash_set('success', 'Category deleted.');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        mysqli_query($mysqli, "UPDATE categories SET status = IF(status='active','inactive','active') WHERE id = " . $id);
        flash_set('success', 'Status updated.');
    }
    redirect('categories.php');
}

$categories = mysqli_query($mysqli, "SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY (c.parent_id IS NOT NULL), c.parent_id, c.sort_order, c.name");
$parentOptions = mysqli_query($mysqli, "SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name");
$parentList = [];
while ($r = mysqli_fetch_assoc($parentOptions)) $parentList[] = $r;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Categories</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetCatForm()"><i class="bi bi-plus-lg"></i> Add Category</button>
</div>

<div class="admin-card">
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Image</th><th>Name</th><th>Parent</th><th>Sort</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($c = mysqli_fetch_assoc($categories)): ?>
      <tr>
        <td><img src="<?= e(BASE_URL . '/' . $c['image']) ?>" class="thumb-sm"></td>
        <td><?= $c['parent_id'] ? '&mdash; ' : '' ?><?= e($c['name']) ?></td>
        <td><?= e($c['parent_name'] ?: '-') ?></td>
        <td><?= (int)$c['sort_order'] ?></td>
        <td><span class="badge <?= $c['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($c['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editCat(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle2-on"></i></button>
          </form>
          <form method="post" class="d-inline confirm-delete" data-message="Delete this category?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal fade" id="catModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="cat_id">
        <div class="modal-header"><h5 class="modal-title" id="catModalTitle">Add Category</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Name</label>
              <input type="text" name="name" id="cat_name" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Parent Category</label>
              <select name="parent_id" id="cat_parent" class="form-select">
                <option value="">None (top level)</option>
                <?php foreach ($parentList as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Image</label>
              <input type="file" name="image" class="form-control" data-preview="#cat_preview">
              <img id="cat_preview" class="thumb-preview mt-2 d-none">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" id="cat_sort" class="form-control" value="0">
              <label class="form-label mt-2">Status</label>
              <select name="status" id="cat_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" id="cat_description" class="form-control richtext" rows="4"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">SEO Title</label>
              <input type="text" name="seo_title" id="cat_seo_title" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">SEO Description</label>
              <input type="text" name="seo_description" id="cat_seo_description" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">SEO Keywords</label>
              <input type="text" name="seo_keywords" id="cat_seo_keywords" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary text-white">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetCatForm(){
  document.getElementById('catModalTitle').textContent = 'Add Category';
  document.getElementById('cat_id').value = '';
  document.getElementById('cat_name').value = '';
  document.getElementById('cat_parent').value = '';
  document.getElementById('cat_sort').value = 0;
  document.getElementById('cat_status').value = 'active';
  document.getElementById('cat_seo_title').value = '';
  document.getElementById('cat_seo_description').value = '';
  document.getElementById('cat_seo_keywords').value = '';
}
function editCat(c){
  document.getElementById('catModalTitle').textContent = 'Edit Category';
  document.getElementById('cat_id').value = c.id;
  document.getElementById('cat_name').value = c.name;
  document.getElementById('cat_parent').value = c.parent_id || '';
  document.getElementById('cat_sort').value = c.sort_order;
  document.getElementById('cat_status').value = c.status;
  document.getElementById('cat_seo_title').value = c.seo_title || '';
  document.getElementById('cat_seo_description').value = c.seo_description || '';
  document.getElementById('cat_seo_keywords').value = c.seo_keywords || '';
  var modal = new bootstrap.Modal(document.getElementById('catModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
