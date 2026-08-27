<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $id = post_int('id');
    $data = ['name' => post('name'), 'sku' => post('sku'), 'product_code' => post('product_code'),
        'specification' => post('specification'), 'unit' => post('unit'), 'production_line_id' => post_int('production_line_id') ?: null];
    if ($id) {
        db_exec("UPDATE products SET name=?,sku=?,product_code=?,specification=?,unit=?,production_line_id=? WHERE id=? AND company_id=?",
            [...array_values($data), $id, $cid]);
    } else {
        db_exec("INSERT INTO products (company_id,name,sku,product_code,specification,unit,production_line_id,status) VALUES (?,?,?,?,?,?,?,'active')",
            [$cid, ...array_values($data)]);
    }
    log_activity($cid, current_user_id(), $id ? 'update' : 'create', 'product', $id, 'Saved product ' . $data['name']);
    flash_set('success', 'Product saved.');
    redirect(base_url('manager/products.php'));
}

$products = db_all("SELECT p.*, pl.name AS line_name FROM products p LEFT JOIN production_lines pl ON pl.id=p.production_line_id WHERE p.company_id=? ORDER BY p.name", [$cid]);
$lines = db_all("SELECT id, name FROM production_lines WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'Products';
$activeMenu = 'products';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Products</h4>
  <button class="btn btn-primary btn-sm" onclick="openProdModal()"><i class="bi bi-plus-lg"></i> New Product</button>
</div>
<div class="qc-card">
  <table id="prodTable" class="table table-hover align-middle">
    <thead><tr><th>Product</th><th>SKU</th><th>Code</th><th>Line</th><th>Specification</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td class="fw-semibold small"><?= out($p['name']) ?></td>
        <td class="small"><?= out($p['sku']) ?></td>
        <td class="small"><?= out($p['product_code']) ?></td>
        <td class="small"><?= out($p['line_name']) ?></td>
        <td class="small text-truncate" style="max-width:260px;"><?= out($p['specification']) ?></td>
        <td class="text-end"><button class="btn btn-sm btn-light border" onclick='openProdModal(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i></button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="6" class="text-center text-muted py-4">No products yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="prodModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="id" id="pr_id">
      <div class="mb-2"><label class="form-label small fw-semibold">Name *</label><input class="form-control" name="name" id="pr_name" required></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small fw-semibold">SKU</label><input class="form-control" name="sku" id="pr_sku"></div>
        <div class="col-6"><label class="form-label small fw-semibold">Product Code</label><input class="form-control" name="product_code" id="pr_code"></div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small fw-semibold">Unit</label><input class="form-control" name="unit" id="pr_unit" placeholder="g, kg, L..."></div>
        <div class="col-6"><label class="form-label small fw-semibold">Production Line</label>
          <select class="form-select" name="production_line_id" id="pr_line"><option value="">--</option><?php foreach ($lines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-1"><label class="form-label small fw-semibold">Specification</label><textarea class="form-control" name="specification" id="pr_spec" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Product</button></div>
  </form>
</div></div></div>

<?php
$extraScripts = '<script>
$(function(){ $("#prodTable").DataTable({ order: [], pageLength: 20 }); });
function openProdModal(p){
  p = p || {};
  document.getElementById("pr_id").value = p.id || "";
  document.getElementById("pr_name").value = p.name || "";
  document.getElementById("pr_sku").value = p.sku || "";
  document.getElementById("pr_code").value = p.product_code || "";
  document.getElementById("pr_unit").value = p.unit || "";
  document.getElementById("pr_line").value = p.production_line_id || "";
  document.getElementById("pr_spec").value = p.specification || "";
  new bootstrap.Modal(document.getElementById("prodModal")).show();
}
</script>';
include __DIR__ . '/../includes/layout_end.php';
