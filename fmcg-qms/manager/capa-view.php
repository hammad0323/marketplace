<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$capa = db_one("SELECT c.*, u.name AS responsible_name FROM capa c LEFT JOIN users u ON u.id=c.responsible_person WHERE c.id=?", [$id]);
if (!$capa) { flash_set('danger', 'CAPA not found.'); redirect(base_url('manager/capa.php')); }
assert_company_owns((int)$capa['company_id']);

$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'update_details') {
        db_exec("UPDATE capa SET problem_statement=?, root_cause=?, correction=?, corrective_action=?, preventive_action=?, responsible_person=?, due_date=? WHERE id=? AND company_id=?",
            [post('problem_statement'), post('root_cause'), post('correction'), post('corrective_action'), post('preventive_action'),
             post_int('responsible_person') ?: null, post('due_date'), $id, $cid]);
        log_activity($cid, current_user_id(), 'update', 'capa', $id, 'Updated CAPA details');
        flash_set('success', 'CAPA updated.');
    } elseif ($action === 'add_action') {
        add_capa_action($id, $cid, post('action_text'), post_int('action_responsible') ?: null, post('action_due') ?: null, current_user_id());
        flash_set('success', 'Action added.');
    } elseif ($action === 'transition') {
        update_capa_status($id, $cid, post('new_status'), current_user_id(), [
            'verification_notes' => post('verification_notes', $capa['verification_notes']),
            'effectiveness_notes' => post('effectiveness_notes', $capa['effectiveness_notes']),
        ]);
        flash_set('success', 'CAPA status updated.');
    }
    redirect(base_url('manager/capa-view.php?id=' . $id));
}

$actions = db_all("SELECT ca.*, u.name AS responsible_name FROM capa_actions ca LEFT JOIN users u ON u.id=ca.responsible_user WHERE ca.capa_id=? ORDER BY ca.created_at DESC", [$id]);

$pageTitle = $capa['capa_number'];
$activeMenu = 'capa';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/capa.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> CAPA</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <h4 class="fw-bold mb-0"><?= out($capa['capa_number']) ?> <?= status_badge($capa['status']) ?></h4>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="POST" class="qc-card mb-3">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="update_details">
      <h3 class="mb-3">Problem & Actions</h3>
      <div class="mb-2"><label class="form-label small fw-semibold">Problem Statement</label><textarea class="form-control" name="problem_statement" rows="2"><?= out($capa['problem_statement']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Root Cause</label><textarea class="form-control" name="root_cause" rows="2"><?= out($capa['root_cause']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Correction</label><textarea class="form-control" name="correction" rows="2"><?= out($capa['correction']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Corrective Action</label><textarea class="form-control" name="corrective_action" rows="2"><?= out($capa['corrective_action']) ?></textarea></div>
      <div class="mb-3"><label class="form-label small fw-semibold">Preventive Action</label><textarea class="form-control" name="preventive_action" rows="2"><?= out($capa['preventive_action']) ?></textarea></div>
      <div class="row g-2 mb-3">
        <div class="col-md-6"><label class="form-label small fw-semibold">Responsible</label><select class="form-select" name="responsible_person"><option value="">--</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $capa['responsible_person']==$e['id']?'selected':'' ?>><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Due Date</label><input type="date" class="form-control" name="due_date" value="<?= out($capa['due_date']) ?>"></div>
      </div>
      <button class="btn btn-sm btn-soft-primary">Save Changes</button>
    </form>

    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Action Items</h3></div>
      <form method="POST" class="row g-2 mb-3">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="add_action">
        <div class="col-md-6"><input class="form-control form-control-sm" name="action_text" placeholder="Action description" required></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="action_responsible"><option value="">Responsible</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="action_due"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
      </form>
      <?php foreach ($actions as $a): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <div><?= out($a['action_text']) ?><div class="text-muted"><?= out($a['responsible_name'] ?: 'Unassigned') ?> - Due <?= fmt_date($a['due_date']) ?></div></div>
          <?= status_badge($a['status']) ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$actions): ?><p class="small text-muted mb-0 mt-2">No action items yet.</p><?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <form method="POST" class="qc-card">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="transition">
      <h3 class="mb-3">Workflow</h3>
      <label class="form-label small fw-semibold">Status</label>
      <select name="new_status" class="form-select form-select-sm mb-3">
        <?php foreach (CAPA_STATUSES as $s): ?><option value="<?= $s ?>" <?= $capa['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
      </select>
      <label class="form-label small fw-semibold">Verification Notes</label>
      <textarea class="form-control form-control-sm mb-3" name="verification_notes" rows="2"><?= out($capa['verification_notes']) ?></textarea>
      <label class="form-label small fw-semibold">Effectiveness Notes</label>
      <textarea class="form-control form-control-sm mb-3" name="effectiveness_notes" rows="2"><?= out($capa['effectiveness_notes']) ?></textarea>
      <button class="btn btn-primary btn-sm w-100">Update Status</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
