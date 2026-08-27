<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$ncr = db_one(
    "SELECT n.*, p.name AS product_name, b.batch_number, d.name AS department_name, u.name AS responsible_name
     FROM ncr n LEFT JOIN products p ON p.id=n.product_id LEFT JOIN batches b ON b.id=n.batch_id
     LEFT JOIN departments d ON d.id=n.department_id LEFT JOIN users u ON u.id=n.responsible_person WHERE n.id=?", [$id]
);
if (!$ncr) { flash_set('danger', 'NCR not found.'); redirect(base_url('manager/ncr.php')); }
assert_company_owns((int)$ncr['company_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'update_details') {
        db_exec("UPDATE ncr SET root_cause=?, corrective_action=?, preventive_action=? WHERE id=? AND company_id=?",
            [post('root_cause'), post('corrective_action'), post('preventive_action'), $id, $cid]);
        log_activity($cid, current_user_id(), 'update', 'ncr', $id, 'Updated NCR investigation details');
        flash_set('success', 'NCR details updated.');
    } elseif ($action === 'transition') {
        update_ncr_status($id, $cid, post('new_status'), current_user_id());
        flash_set('success', 'Status updated.');
    } elseif ($action === 'create_capa') {
        $capaId = create_capa($cid, current_user_id(), [
            'source_type' => 'ncr', 'source_id' => $id, 'problem_statement' => $ncr['description'], 'root_cause' => $ncr['root_cause'],
            'corrective_action' => $ncr['corrective_action'], 'preventive_action' => $ncr['preventive_action'],
            'responsible_person' => $ncr['responsible_person'], 'due_date' => $ncr['due_date'],
        ]);
        redirect(base_url('manager/capa-view.php?id=' . $capaId));
    }
    redirect(base_url('manager/ncr-view.php?id=' . $id));
}

$relatedCapa = db_one("SELECT * FROM capa WHERE source_type='ncr' AND source_id=?", [$id]);

$pageTitle = $ncr['ncr_number'];
$activeMenu = 'ncr';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/ncr.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> NCR</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <h4 class="fw-bold mb-0"><?= out($ncr['ncr_number']) ?> <?= severity_badge($ncr['severity']) ?> <?= status_badge($ncr['status']) ?></h4>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Details</h3></div>
      <p><?= nl2br(out($ncr['description'])) ?></p>
      <div class="row g-2 small text-muted">
        <div class="col-4">Product: <?= out($ncr['product_name'] ?: '-') ?></div>
        <div class="col-4">Batch: <?= out($ncr['batch_number'] ?: '-') ?></div>
        <div class="col-4">Department: <?= out($ncr['department_name'] ?: '-') ?></div>
      </div>
      <?php if ($ncr['containment']): ?><div class="mt-2"><strong class="small">Containment:</strong> <span class="small"><?= out($ncr['containment']) ?></span></div><?php endif; ?>
    </div>

    <form method="POST" class="qc-card mb-3">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="update_details">
      <h3 class="mb-3">Investigation</h3>
      <div class="mb-2"><label class="form-label small fw-semibold">Root Cause</label><textarea class="form-control" name="root_cause" rows="2"><?= out($ncr['root_cause']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Corrective Action</label><textarea class="form-control" name="corrective_action" rows="2"><?= out($ncr['corrective_action']) ?></textarea></div>
      <div class="mb-3"><label class="form-label small fw-semibold">Preventive Action</label><textarea class="form-control" name="preventive_action" rows="2"><?= out($ncr['preventive_action']) ?></textarea></div>
      <button class="btn btn-sm btn-soft-primary">Save Investigation</button>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Workflow</h3></div>
      <form method="POST" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="transition">
        <select name="new_status" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
          <?php foreach (NCR_STATUSES as $s): ?><option value="<?= $s ?>" <?= $ncr['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
        </select>
      </form>
      <div class="small text-muted">Responsible: <?= out($ncr['responsible_name'] ?: 'Unassigned') ?></div>
      <div class="small <?= (strtotime($ncr['due_date']) < time() && $ncr['status'] !== 'closed') ? 'text-danger fw-semibold' : 'text-muted' ?>">Due: <?= fmt_date($ncr['due_date']) ?></div>
    </div>
    <div class="qc-card">
      <div class="qc-card-header"><h3>CAPA</h3></div>
      <?php if ($relatedCapa): ?>
        <a href="<?= base_url('manager/capa-view.php?id=' . $relatedCapa['id']) ?>" class="btn btn-sm btn-light border w-100"><i class="bi bi-clipboard-data"></i> View <?= out($relatedCapa['capa_number']) ?></a>
      <?php else: ?>
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="form_action" value="create_capa"><button class="btn btn-sm btn-soft-primary w-100">Raise CAPA from this NCR</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
