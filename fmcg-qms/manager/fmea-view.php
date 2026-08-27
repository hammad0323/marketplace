<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$fmea = db_one("SELECT * FROM fmea WHERE id=? AND company_id=?", [$id, $cid]);
if (!$fmea) { flash_set('danger', 'FMEA not found.'); redirect(base_url('manager/fmea.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $severity = post_int('severity', 1); $occurrence = post_int('occurrence', 1); $detection = post_int('detection', 1);
    $rpn = calc_rpn($severity, $occurrence, $detection);
    db_exec("INSERT INTO fmea_items (fmea_id,process_step,failure_mode,effect,cause,current_controls,severity,occurrence,detection,rpn,action_recommended,responsible_person,target_date,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'open')",
        [$id, post('process_step'), post('failure_mode'), post('effect'), post('cause'), post('current_controls'),
         $severity, $occurrence, $detection, $rpn, post('action_recommended'), post_int('responsible_person') ?: null, post('target_date') ?: null]);
    flash_set('success', "Failure mode added (RPN: $rpn).");
    redirect(base_url('manager/fmea-view.php?id=' . $id));
}

$items = db_all("SELECT fi.*, u.name AS responsible_name FROM fmea_items fi LEFT JOIN users u ON u.id=fi.responsible_person WHERE fi.fmea_id=? ORDER BY fi.rpn DESC", [$id]);
$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);

$pageTitle = $fmea['title'];
$activeMenu = 'fmea';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/fmea.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> FMEA</a>
<div class="mb-4 mt-1"><h4 class="fw-bold mb-0"><?= out($fmea['title']) ?></h4></div>

<div class="qc-card mb-3">
  <div class="qc-card-header"><h3>Add Failure Mode</h3></div>
  <form method="POST" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-3"><input class="form-control form-control-sm" name="process_step" placeholder="Process Step"></div>
    <div class="col-md-3"><input class="form-control form-control-sm" name="failure_mode" placeholder="Failure Mode" required></div>
    <div class="col-md-3"><input class="form-control form-control-sm" name="effect" placeholder="Effect"></div>
    <div class="col-md-3"><input class="form-control form-control-sm" name="cause" placeholder="Cause"></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="current_controls" placeholder="Current Controls"></div>
    <div class="col-md-2 col-6"><label class="form-label small mb-0">Severity (1-10)</label><input type="number" min="1" max="10" class="form-control form-control-sm" name="severity" value="5" required></div>
    <div class="col-md-2 col-6"><label class="form-label small mb-0">Occurrence (1-10)</label><input type="number" min="1" max="10" class="form-control form-control-sm" name="occurrence" value="5" required></div>
    <div class="col-md-2 col-6"><label class="form-label small mb-0">Detection (1-10)</label><input type="number" min="1" max="10" class="form-control form-control-sm" name="detection" value="5" required></div>
    <div class="col-md-2 col-6"><select class="form-select form-select-sm" name="responsible_person"><option value="">Responsible</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><input class="form-control form-control-sm" name="action_recommended" placeholder="Recommended Action"></div>
    <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="target_date"></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Add Row</button></div>
  </form>
</div>

<div class="qc-card">
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Process Step</th><th>Failure Mode</th><th>Effect</th><th>Cause</th><th>S</th><th>O</th><th>D</th><th>RPN</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): $risk = rpn_risk_level($it['rpn']); ?>
      <tr class="<?= $risk==='critical' ? 'table-danger' : ($risk==='high' ? 'table-warning' : '') ?>">
        <td class="small"><?= out($it['process_step']) ?></td>
        <td class="small fw-semibold"><?= out($it['failure_mode']) ?></td>
        <td class="small"><?= out($it['effect']) ?></td>
        <td class="small"><?= out($it['cause']) ?></td>
        <td><?= $it['severity'] ?></td><td><?= $it['occurrence'] ?></td><td><?= $it['detection'] ?></td>
        <td class="fw-bold"><?= $it['rpn'] ?></td>
        <td class="small"><?= out($it['action_recommended']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="9" class="text-center text-muted py-4">No failure modes recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
