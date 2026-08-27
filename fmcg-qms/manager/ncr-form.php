<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$batches = db_all("SELECT id, batch_number FROM batches WHERE company_id=? ORDER BY created_at DESC LIMIT 100", [$cid]);
$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ncrId = create_ncr($cid, current_user_id(), [
        'product_id' => post_int('product_id') ?: null, 'batch_id' => post_int('batch_id') ?: null,
        'department_id' => post_int('department_id') ?: null, 'process' => post('process'), 'issue_id' => null,
        'severity' => post('severity', 'medium'), 'description' => post('description'), 'containment' => post('containment'),
        'root_cause' => post('root_cause'), 'corrective_action' => post('corrective_action'), 'preventive_action' => post('preventive_action'),
        'responsible_person' => post_int('responsible_person') ?: null, 'due_date' => post('due_date') ?: date('Y-m-d', strtotime('+7 days')),
    ]);
    flash_set('success', 'NCR created.');
    redirect(base_url('manager/ncr-view.php?id=' . $ncrId));
}

$pageTitle = 'New NCR';
$activeMenu = 'ncr';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">New Non-Conformance Report</h4></div>
<form method="POST" class="qc-card" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label small fw-semibold">Product</label><select class="form-select" name="product_id"><option value="">--</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small fw-semibold">Batch</label><select class="form-select" name="batch_id"><option value="">--</option><?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>"><?= out($b['batch_number']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label small fw-semibold">Department</label><select class="form-select" name="department_id"><option value="">--</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Process</label><input class="form-control" name="process"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Severity *</label><select class="form-select" name="severity" required><?php foreach (['critical','high','medium','low','observation'] as $s): ?><option value="<?= $s ?>" <?= $s==='medium'?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-12"><label class="form-label small fw-semibold">Description *</label><textarea class="form-control" name="description" rows="3" required></textarea></div>
    <div class="col-md-12"><label class="form-label small fw-semibold">Containment</label><textarea class="form-control" name="containment" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Root Cause (if known)</label><textarea class="form-control" name="root_cause" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Corrective Action (if known)</label><textarea class="form-control" name="corrective_action" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Responsible Person</label><select class="form-select" name="responsible_person"><option value="">--</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Due Date</label><input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Create NCR</button><a href="<?= base_url('manager/ncr.php') ?>" class="btn btn-light border">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
