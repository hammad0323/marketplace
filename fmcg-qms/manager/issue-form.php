<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$batches = db_all("SELECT id, batch_number FROM batches WHERE company_id=? ORDER BY created_at DESC LIMIT 100", [$cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $issueId = create_manual_issue($cid, current_user_id(), [
        'department_id' => post_int('department_id') ?: null, 'product_id' => post_int('product_id') ?: null,
        'batch_id' => post_int('batch_id') ?: null, 'shift_id' => null,
        'defect_type' => post('defect_type'), 'severity' => post('severity', 'medium'), 'description' => post('description'),
    ]);
    flash_set('success', 'Quality issue logged.');
    redirect(base_url('manager/issue-view.php?id=' . $issueId));
}

$pageTitle = 'New Quality Issue';
$activeMenu = 'issues';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Log a Quality Issue</h4></div>
<form method="POST" class="qc-card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-semibold">Department</label>
      <select class="form-select" name="department_id"><option value="">-- Select --</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Severity *</label>
      <select class="form-select" name="severity" required>
        <?php foreach (['critical','high','medium','low','observation'] as $s): ?><option value="<?= $s ?>" <?= $s==='medium'?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Product</label>
      <select class="form-select" name="product_id"><option value="">-- Select --</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Batch</label>
      <select class="form-select" name="batch_id"><option value="">-- Select --</option><?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>"><?= out($b['batch_number']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-12"><label class="form-label small fw-semibold">Defect / Problem Type *</label><input class="form-control" name="defect_type" required></div>
    <div class="col-md-12"><label class="form-label small fw-semibold">Description *</label><textarea class="form-control" name="description" rows="4" required></textarea></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Log Issue</button><a href="<?= base_url('manager/issues.php') ?>" class="btn btn-light border">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
