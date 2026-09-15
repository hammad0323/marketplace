<?php
$pageTitle = 'Shipping';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $fee = (float)($_POST['fee'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        if ($city === '') {
            flash_set('danger', 'City is required.');
        } elseif ($id) {
            $stmt = mysqli_prepare($mysqli, "UPDATE shipping_methods SET city=?, province=?, fee=?, sort_order=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssdisi', $city, $province, $fee, $sortOrder, $status, $id);
            mysqli_stmt_execute($stmt);
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO shipping_methods (city, province, fee, sort_order, status) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssdis', $city, $province, $fee, $sortOrder, $status);
            mysqli_stmt_execute($stmt);
        }
        flash_set('success', 'Shipping rate saved.');
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM shipping_methods WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Shipping rate deleted.');
    } elseif ($action === 'save_threshold') {
        set_setting('free_shipping_threshold', (string)(float)$_POST['free_shipping_threshold']);
        flash_set('success', 'Free shipping threshold updated.');
    }
    redirect('shipping.php');
}

$methods = mysqli_query($mysqli, "SELECT * FROM shipping_methods ORDER BY sort_order ASC");
?>
<h1 class="page-title mb-4">Shipping Management</h1>

<div class="admin-card mb-4">
  <h2 class="h6 mb-3">Free Shipping Threshold</h2>
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_threshold">
    <div class="col-auto">
      <label class="form-label">Orders above (Rs.) qualify for free shipping</label>
      <input type="number" step="0.01" name="free_shipping_threshold" class="form-control" value="<?= e(get_setting('free_shipping_threshold', '5000')) ?>">
    </div>
    <div class="col-auto"><button class="btn btn-outline-dark">Save</button></div>
  </form>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="h6 mb-0">City-Based Rates</h2>
  <button class="btn btn-primary text-white btn-sm" data-bs-toggle="modal" data-bs-target="#shipModal" onclick="resetShipForm()"><i class="bi bi-plus-lg"></i> Add Rate</button>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>City</th><th>Province</th><th>Fee</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($m = mysqli_fetch_assoc($methods)): ?>
      <tr>
        <td><?= e($m['city']) ?></td>
        <td><?= e($m['province']) ?></td>
        <td><?= format_price($m['fee']) ?></td>
        <td><span class="badge <?= $m['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($m['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editShip(<?= json_encode($m) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="shipModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="sh_id">
        <div class="modal-header"><h5 class="modal-title">Shipping Rate</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">City</label><input type="text" name="city" id="sh_city" class="form-control mb-3" required>
          <label class="form-label">Province</label><input type="text" name="province" id="sh_province" class="form-control mb-3">
          <label class="form-label">Fee (Rs.)</label><input type="number" step="0.01" name="fee" id="sh_fee" class="form-control mb-3">
          <label class="form-label">Sort Order</label><input type="number" name="sort_order" id="sh_sort" class="form-control mb-3" value="0">
          <label class="form-label">Status</label><select name="status" id="sh_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetShipForm(){document.getElementById('sh_id').value='';document.getElementById('sh_city').value='';document.getElementById('sh_province').value='';document.getElementById('sh_fee').value='';document.getElementById('sh_sort').value=0;document.getElementById('sh_status').value='active';}
function editShip(m){document.getElementById('sh_id').value=m.id;document.getElementById('sh_city').value=m.city;document.getElementById('sh_province').value=m.province||'';document.getElementById('sh_fee').value=m.fee;document.getElementById('sh_sort').value=m.sort_order;document.getElementById('sh_status').value=m.status;new bootstrap.Modal(document.getElementById('shipModal')).show();}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
