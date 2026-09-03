<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$categoryId = (int)($_GET['category_id'] ?? 0);
$category = db_fetch_one("SELECT * FROM categories WHERE id=?", 'i', [$categoryId]);
if (!$category) { flash('error', 'Category not found.'); redirect(admin_url('categories/index.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name']); $slug = unique_slug('subcategories', slugify($name), 'id', $id ?: null);
    $description = clean_html($_POST['description'] ?? ''); $sortOrder = (int)$_POST['sort_order']; $status = $_POST['status'];
    if ($id) {
        db_exec("UPDATE subcategories SET name=?,slug=?,description=?,sort_order=?,status=? WHERE id=?", 'sssiis', [$name, $slug, $description, $sortOrder, $status, $id]);
        flash('success', 'Subcategory updated.');
    } else {
        db_insert("INSERT INTO subcategories (category_id,name,slug,description,sort_order,status) VALUES (?,?,?,?,?,?)",
            'isssis', [$categoryId, $name, $slug, $description, $sortOrder, $status]);
        flash('success', 'Subcategory added.');
    }
    redirect(admin_url('categories/subcategories.php?category_id=' . $categoryId));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    db_exec("DELETE FROM subcategories WHERE id=?", 'i', [(int)$_POST['id']]);
    flash('success', 'Subcategory deleted.');
    redirect(admin_url('categories/subcategories.php?category_id=' . $categoryId));
}

$subcategories = db_fetch_all("SELECT * FROM subcategories WHERE category_id=? ORDER BY sort_order", 'i', [$categoryId]);

$dashRole = 'admin'; $pageTitle = 'Subcategories'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Subcategories — <?= clean($category['name']) ?></h1>
  <a href="<?= admin_url('categories/index.php') ?>" class="btn btn-outline">&larr; Back to Categories</a>
</div>
<div class="dash-two-col">
  <div class="dash-table-card">
    <table class="dash-table">
      <thead><tr><th>Name</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($subcategories as $sc): ?>
          <tr>
            <td><?= clean($sc['name']) ?></td><td><?= $sc['sort_order'] ?></td>
            <td><span class="badge badge-<?= $sc['status'] === 'active' ? 'success' : 'danger' ?>"><?= clean($sc['status']) ?></span></td>
            <td class="action-cell">
              <button type="button" class="btn btn-sm btn-outline" onclick='fillSubForm(<?= json_encode($sc) ?>)'>Edit</button>
              <form method="post" class="inline-form" onsubmit="return confirm('Delete?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $sc['id'] ?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$subcategories): ?><tr><td colspan="4" class="text-muted">No subcategories yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="dash-form-card">
    <h3 id="sub-form-title">Add Subcategory</h3>
    <form method="post" id="sub-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="sub-id" value="">
      <label>Name</label><input type="text" name="name" id="sub-name" required>
      <label>Description</label><textarea name="description" id="sub-description" rows="3"></textarea>
      <div class="form-row">
        <div><label>Sort Order</label><input type="number" name="sort_order" id="sub-sort" value="0"></div>
        <div><label>Status</label><select name="status" id="sub-status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      </div>
      <button type="submit" class="btn btn-primary">Save</button>
      <button type="button" class="btn btn-outline" onclick="resetSubForm()">New</button>
    </form>
  </div>
</div>
<script>
function fillSubForm(sc) {
  document.getElementById('sub-form-title').textContent = 'Edit Subcategory';
  document.getElementById('sub-id').value = sc.id;
  document.getElementById('sub-name').value = sc.name;
  document.getElementById('sub-description').value = sc.description || '';
  document.getElementById('sub-sort').value = sc.sort_order;
  document.getElementById('sub-status').value = sc.status;
}
function resetSubForm() { document.getElementById('sub-form').reset(); document.getElementById('sub-id').value=''; document.getElementById('sub-form-title').textContent='Add Subcategory'; }
</script>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
