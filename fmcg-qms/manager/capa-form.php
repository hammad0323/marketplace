<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $capaId = create_capa($cid, current_user_id(), [
        'source_type' => 'manual', 'problem_statement' => post('problem_statement'), 'root_cause' => post('root_cause'),
        'correction' => post('correction'), 'corrective_action' => post('corrective_action'), 'preventive_action' => post('preventive_action'),
        'responsible_person' => post_int('responsible_person') ?: null, 'due_date' => post('due_date') ?: date('Y-m-d', strtotime('+7 days')),
    ]);
    flash_set('success', 'CAPA created.');
    redirect(base_url('manager/capa-view.php?id=' . $capaId));
}

$pageTitle = 'New CAPA';
$activeMenu = 'capa';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">New CAPA</h4></div>
<form method="POST" class="qc-card" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label small fw-semibold">Problem Statement *</label><textarea class="form-control" name="problem_statement" rows="3" required></textarea></div>
  <div class="mb-3"><label class="form-label small fw-semibold">Root Cause</label><textarea class="form-control" name="root_cause" rows="2"></textarea></div>
  <div class="mb-3"><label class="form-label small fw-semibold">Correction (immediate fix)</label><textarea class="form-control" name="correction" rows="2"></textarea></div>
  <div class="mb-3"><label class="form-label small fw-semibold">Corrective Action</label><textarea class="form-control" name="corrective_action" rows="2"></textarea></div>
  <div class="mb-3"><label class="form-label small fw-semibold">Preventive Action</label><textarea class="form-control" name="preventive_action" rows="2"></textarea></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-semibold">Responsible Person</label><select class="form-select" name="responsible_person"><option value="">--</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Due Date</label><input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Create CAPA</button><a href="<?= base_url('manager/capa.php') ?>" class="btn btn-light border">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
