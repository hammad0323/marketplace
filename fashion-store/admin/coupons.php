<?php
$pageTitle = 'Coupons';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] === 'fixed' ? 'fixed' : 'percentage';
        $value = (float)$_POST['value'];
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $maxDiscount = $_POST['max_discount'] !== '' ? (float)$_POST['max_discount'] : null;
        $startDate = $_POST['start_date'] ?: null;
        $endDate = $_POST['end_date'] ?: null;
        $usageLimit = $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null;
        $perCustomerLimit = $_POST['per_customer_limit'] !== '' ? (int)$_POST['per_customer_limit'] : null;
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        if ($code === '' || $value <= 0) {
            flash_set('danger', 'Coupon code and value are required.');
        } elseif ($id) {
            $stmt = mysqli_prepare($mysqli, "UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_discount=?, start_date=?, end_date=?, usage_limit=?, per_customer_limit=?, category_id=?, product_id=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssdddssiiiisi', $code, $type, $value, $minOrder, $maxDiscount, $startDate, $endDate, $usageLimit, $perCustomerLimit, $categoryId, $productId, $status, $id);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Coupon updated.');
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO coupons (code, type, value, min_order, max_discount, start_date, end_date, usage_limit, per_customer_limit, category_id, product_id, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssdddssiiiis', $code, $type, $value, $minOrder, $maxDiscount, $startDate, $endDate, $usageLimit, $perCustomerLimit, $categoryId, $productId, $status);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Coupon created.');
        }
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM coupons WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Coupon deleted.');
    } elseif ($action === 'toggle') {
        mysqli_query($mysqli, "UPDATE coupons SET status = IF(status='active','inactive','active') WHERE id = " . (int)$_POST['id']);
    }
    redirect('coupons.php');
}

$coupons = mysqli_query($mysqli, "SELECT * FROM coupons ORDER BY created_at DESC");
$categories = mysqli_query($mysqli, "SELECT id, name FROM categories ORDER BY name");
$categoryList = [];
while ($c = mysqli_fetch_assoc($categories)) $categoryList[] = $c;
$products = mysqli_query($mysqli, "SELECT id, name FROM products ORDER BY name");
$productList = [];
while ($p = mysqli_fetch_assoc($products)) $productList[] = $p;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Coupons</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#couponModal" onclick="resetCouponForm()"><i class="bi bi-plus-lg"></i> Add Coupon</button>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Usage</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($c = mysqli_fetch_assoc($coupons)): ?>
      <tr>
        <td><strong><?= e($c['code']) ?></strong></td>
        <td><?= e(ucfirst($c['type'])) ?></td>
        <td><?= $c['type']==='percentage' ? $c['value'].'%' : format_price($c['value']) ?></td>
        <td><?= format_price($c['min_order']) ?></td>
        <td><?= (int)$c['used_count'] ?> / <?= $c['usage_limit'] ?: '∞' ?></td>
        <td><?= $c['end_date'] ? e(date('d M Y', strtotime($c['end_date']))) : '—' ?></td>
        <td><span class="badge <?= $c['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($c['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editCoupon(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="couponModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="c_id">
        <div class="modal-header"><h5 class="modal-title" id="couponModalTitle">Add Coupon</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Coupon Code</label><input type="text" name="code" id="c_code" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Type</label><select name="type" id="c_type" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select></div>
            <div class="col-md-3"><label class="form-label">Value</label><input type="number" step="0.01" name="value" id="c_value" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Minimum Order</label><input type="number" step="0.01" name="min_order" id="c_min" class="form-control" value="0"></div>
            <div class="col-md-4"><label class="form-label">Max Discount</label><input type="number" step="0.01" name="max_discount" id="c_max" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="c_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" id="c_start" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" id="c_end" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Usage Limit (total)</label><input type="number" name="usage_limit" id="c_usage" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Per-Customer Limit</label><input type="number" name="per_customer_limit" id="c_per_customer" class="form-control" value="1"></div>
            <div class="col-md-6"><label class="form-label">Restrict to Category</label><select name="category_id" id="c_category" class="form-select select2"><option value="">— Any —</option><?php foreach ($categoryList as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Restrict to Product</label><select name="product_id" id="c_product" class="form-select select2"><option value="">— Any —</option><?php foreach ($productList as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save Coupon</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetCouponForm(){document.getElementById('couponModalTitle').textContent='Add Coupon';['id','code','value','max','start','end','usage'].forEach(function(f){document.getElementById('c_'+f).value='';});document.getElementById('c_min').value=0;document.getElementById('c_per_customer').value=1;document.getElementById('c_type').value='percentage';document.getElementById('c_status').value='active';document.getElementById('c_category').value='';document.getElementById('c_product').value='';}
function editCoupon(c){
  document.getElementById('couponModalTitle').textContent='Edit Coupon';
  document.getElementById('c_id').value=c.id;
  document.getElementById('c_code').value=c.code;
  document.getElementById('c_type').value=c.type;
  document.getElementById('c_value').value=c.value;
  document.getElementById('c_min').value=c.min_order;
  document.getElementById('c_max').value=c.max_discount||'';
  document.getElementById('c_start').value=c.start_date||'';
  document.getElementById('c_end').value=c.end_date||'';
  document.getElementById('c_usage').value=c.usage_limit||'';
  document.getElementById('c_per_customer').value=c.per_customer_limit||'';
  document.getElementById('c_category').value=c.category_id||'';
  document.getElementById('c_product').value=c.product_id||'';
  document.getElementById('c_status').value=c.status;
  new bootstrap.Modal(document.getElementById('couponModal')).show();
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
