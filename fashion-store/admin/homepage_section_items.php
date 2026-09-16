<?php
$pageTitle = 'Section Items';
require_once __DIR__ . '/includes/admin_header.php';

$sectionId = (int)($_GET['section_id'] ?? 0);
$section = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM homepage_sections WHERE id = " . $sectionId));
if (!$section) redirect('homepage_sections.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $iconClass = trim($_POST['icon_class'] ?? '');
        $productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        $image = handle_upload('image', 'general');

        if ($id) {
            if ($image) {
                $stmt = mysqli_prepare($mysqli, "UPDATE homepage_section_items SET title=?, subtitle=?, link=?, product_id=?, image=?, icon_class=?, sort_order=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssissisi', $title, $subtitle, $link, $productId, $image, $iconClass, $sortOrder, $status, $id);
            } else {
                $stmt = mysqli_prepare($mysqli, "UPDATE homepage_section_items SET title=?, subtitle=?, link=?, product_id=?, icon_class=?, sort_order=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssisisi', $title, $subtitle, $link, $productId, $iconClass, $sortOrder, $status, $id);
            }
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Item updated.');
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO homepage_section_items (section_id, title, subtitle, link, product_id, image, icon_class, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'isssissis', $sectionId, $title, $subtitle, $link, $productId, $image, $iconClass, $sortOrder, $status);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Item added.');
        }
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM homepage_section_items WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Item deleted.');
    }
    redirect('homepage_section_items.php?section_id=' . $sectionId);
}

$items = mysqli_query($mysqli, "SELECT i.*, p.name AS product_name FROM homepage_section_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.section_id = $sectionId ORDER BY i.sort_order");
$products = mysqli_query($mysqli, "SELECT id, name FROM products ORDER BY name");
$productList = [];
while ($p = mysqli_fetch_assoc($products)) $productList[] = $p;
?>
<a href="homepage_sections.php?homepage=<?= (int)$section['homepage'] ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Back to Sections</a>
<div class="d-flex justify-content-between align-items-center my-3">
  <h1 class="page-title mb-0">Items — <?= e($section['title'] ?: $section['section_type']) ?></h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetItemForm()"><i class="bi bi-plus-lg"></i> Add Item</button>
</div>

<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Image</th><th>Title</th><th>Product</th><th>Link</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($it = mysqli_fetch_assoc($items)): ?>
      <tr>
        <td><?php if ($it['image']): ?><img src="<?= e(BASE_URL.'/'.$it['image']) ?>" class="thumb-sm"><?php endif; ?></td>
        <td><?= e($it['title']) ?></td>
        <td><?= e($it['product_name'] ?? '-') ?></td>
        <td class="small text-muted"><?= e($it['link']) ?></td>
        <td><span class="badge <?= $it['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($it['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editItem(<?= json_encode($it) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="itemModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="it_id">
        <div class="modal-header"><h5 class="modal-title" id="itemModalTitle">Add Item</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Title</label><input type="text" name="title" id="it_title" class="form-control mb-3">
          <label class="form-label">Subtitle</label><input type="text" name="subtitle" id="it_subtitle" class="form-control mb-3">
          <label class="form-label">Image</label><input type="file" name="image" class="form-control mb-3">
          <label class="form-label">Link URL</label><input type="text" name="link" id="it_link" class="form-control mb-3">
          <label class="form-label">Icon Class (for Features / Counters sections, e.g. bi-truck)</label><input type="text" name="icon_class" id="it_icon" class="form-control mb-3" placeholder="bi-truck">
          <label class="form-label">Link to Product (optional)</label>
          <select name="product_id" id="it_product" class="form-select select2 mb-3">
            <option value="">— None —</option>
            <?php foreach ($productList as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
          <label class="form-label">Sort Order</label><input type="number" name="sort_order" id="it_sort" class="form-control mb-3" value="0">
          <label class="form-label">Status</label>
          <select name="status" id="it_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetItemForm(){document.getElementById('itemModalTitle').textContent='Add Item';document.getElementById('it_id').value='';document.getElementById('it_title').value='';document.getElementById('it_subtitle').value='';document.getElementById('it_link').value='';document.getElementById('it_icon').value='';document.getElementById('it_product').value='';document.getElementById('it_sort').value=0;document.getElementById('it_status').value='active';}
function editItem(it){
  document.getElementById('itemModalTitle').textContent='Edit Item';
  document.getElementById('it_id').value=it.id;
  document.getElementById('it_title').value=it.title||'';
  document.getElementById('it_subtitle').value=it.subtitle||'';
  document.getElementById('it_link').value=it.link||'';
  document.getElementById('it_icon').value=it.icon_class||'';
  document.getElementById('it_product').value=it.product_id||'';
  document.getElementById('it_sort').value=it.sort_order;
  document.getElementById('it_status').value=it.status;
  new bootstrap.Modal(document.getElementById('itemModal')).show();
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
