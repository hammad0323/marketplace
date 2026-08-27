<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $batchNumber = post('batch_number') ?: generate_code('B', 4);
    db_exec("INSERT INTO batches (company_id,batch_number,product_id,production_date,expiry_date,production_line_id,shift_id,quantity_produced,status)
             VALUES (?,?,?,?,?,?,?,?,'in_production')",
        [$cid, $batchNumber, post_int('product_id'), post('production_date') ?: date('Y-m-d'), post('expiry_date') ?: null,
         post_int('production_line_id') ?: null, post_int('shift_id') ?: null, post_float('quantity_produced')]);
    log_activity($cid, current_user_id(), 'create', 'batch', null, "Created batch $batchNumber");
    flash_set('success', 'Batch created.');
    redirect(base_url('manager/batches.php'));
}

$batches = db_all(
    "SELECT b.*, p.name AS product_name, pl.name AS line_name FROM batches b
     LEFT JOIN products p ON p.id=b.product_id LEFT JOIN production_lines pl ON pl.id=b.production_line_id
     WHERE b.company_id=? ORDER BY b.created_at DESC LIMIT 200", [$cid]
);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$lines = db_all("SELECT id, name FROM production_lines WHERE company_id=? ORDER BY name", [$cid]);
$shifts = db_all("SELECT id, name FROM shifts WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'Batches';
$activeMenu = 'batches';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Batches</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#batchModal"><i class="bi bi-plus-lg"></i> New Batch</button>
</div>
<div class="qc-card">
  <table id="batchTable" class="table table-hover align-middle">
    <thead><tr><th>Batch #</th><th>Product</th><th>Production Date</th><th>Expiry</th><th>Line</th><th>Qty</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
      <tr>
        <td class="fw-semibold small"><?= out($b['batch_number']) ?></td>
        <td class="small"><?= out($b['product_name']) ?></td>
        <td class="small"><?= fmt_date($b['production_date']) ?></td>
        <td class="small"><?= fmt_date($b['expiry_date']) ?></td>
        <td class="small"><?= out($b['line_name']) ?></td>
        <td class="small"><?= fmt_number($b['quantity_produced'],0) ?></td>
        <td><?= status_badge($b['status']) ?></td>
        <td class="text-end"><a href="<?= base_url('manager/traceability.php?batch=' . urlencode($b['batch_number'])) ?>" class="btn btn-sm btn-light border"><i class="bi bi-diagram-2"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$batches): ?><tr><td colspan="8" class="text-center text-muted py-4">No batches yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="batchModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label small fw-semibold">Batch Number</label><input class="form-control" name="batch_number" placeholder="auto-generated"></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Product *</label><select class="form-select" name="product_id" required><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small fw-semibold">Production Date</label><input type="date" class="form-control" name="production_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-6"><label class="form-label small fw-semibold">Expiry Date</label><input type="date" class="form-control" name="expiry_date"></div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small fw-semibold">Production Line</label><select class="form-select" name="production_line_id"><option value="">--</option><?php foreach ($lines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small fw-semibold">Shift</label><select class="form-select" name="shift_id"><option value="">--</option><?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>"><?= out($s['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-1"><label class="form-label small fw-semibold">Quantity Produced</label><input type="number" step="0.01" class="form-control" name="quantity_produced"></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Create Batch</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = '<script>$(function(){ $("#batchTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
