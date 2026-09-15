<?php
$pageTitle = 'Brands';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        if ($name === '') {
            flash_set('danger', 'Brand name required.');
        } else {
            $slug = unique_slug($mysqli, 'brands', slugify($name), $id);
            $logo = handle_upload('logo', 'brands');
            if ($id) {
                if ($logo) {
                    $stmt = mysqli_prepare($mysqli, "UPDATE brands SET name=?, slug=?, logo=?, status=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $slug, $logo, $status, $id);
                } else {
                    $stmt = mysqli_prepare($mysqli, "UPDATE brands SET name=?, slug=?, status=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, 'sssi', $name, $slug, $status, $id);
                }
                mysqli_stmt_execute($stmt);
                flash_set('success', 'Brand updated.');
            } else {
                $logo = $logo ?: null;
                $stmt = mysqli_prepare($mysqli, "INSERT INTO brands (name, slug, logo, status) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'ssss', $name, $slug, $logo, $status);
                mysqli_stmt_execute($stmt);
                flash_set('success', 'Brand created.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        mysqli_query($mysqli, "DELETE FROM brands WHERE id = " . $id);
        flash_set('success', 'Brand deleted.');
    }
    redirect('brands.php');
}

$brands = mysqli_query($mysqli, "SELECT * FROM brands ORDER BY name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Brands</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#brandModal" onclick="resetBrandForm()"><i class="bi bi-plus-lg"></i> Add Brand</button>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Logo</th><th>Name</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($b = mysqli_fetch_assoc($brands)): ?>
      <tr>
        <td><?php if ($b['logo']): ?><img src="<?= e(BASE_URL.'/'.$b['logo']) ?>" class="thumb-sm"><?php endif; ?></td>
        <td><?= e($b['name']) ?></td>
        <td><span class="badge <?= $b['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($b['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editBrand(<?= json_encode($b) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="brandModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="brand_id">
        <div class="modal-header"><h5 class="modal-title" id="brandModalTitle">Add Brand</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Name</label>
          <input type="text" name="name" id="brand_name" class="form-control mb-3" required>
          <label class="form-label">Logo</label>
          <input type="file" name="logo" class="form-control mb-3">
          <label class="form-label">Status</label>
          <select name="status" id="brand_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetBrandForm(){document.getElementById('brandModalTitle').textContent='Add Brand';document.getElementById('brand_id').value='';document.getElementById('brand_name').value='';document.getElementById('brand_status').value='active';}
function editBrand(b){document.getElementById('brandModalTitle').textContent='Edit Brand';document.getElementById('brand_id').value=b.id;document.getElementById('brand_name').value=b.name;document.getElementById('brand_status').value=b.status;new bootstrap.Modal(document.getElementById('brandModal')).show();}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
