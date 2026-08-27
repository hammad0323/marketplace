<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$plan = db_one("SELECT * FROM haccp_plans WHERE id=? AND company_id=?", [$id, $cid]);
if (!$plan) { flash_set('danger', 'HACCP plan not found.'); redirect(base_url('manager/haccp.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'add_hazard') {
        $severity = post_int('severity', 1); $likelihood = post_int('likelihood', 1);
        $riskScore = $severity * $likelihood;
        $riskLevel = $riskScore >= 20 ? 'critical' : ($riskScore >= 12 ? 'high' : ($riskScore >= 6 ? 'medium' : 'low'));
        db_exec("INSERT INTO haccp_hazards (haccp_plan_id,process_step,hazard_type,hazard_description,severity,likelihood,risk_level,is_ccp,control_measure,critical_limit)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$id, post('process_step'), post('hazard_type','biological'), post('hazard_description'), $severity, $likelihood, $riskLevel,
             post('is_ccp') ? 1 : 0, post('control_measure'), post('critical_limit')]);
        flash_set('success', "Hazard added (Risk: $riskLevel).");
    } elseif ($action === 'add_monitoring') {
        $hazardId = post_int('haccp_hazard_id');
        $result = post('result', 'pass');
        db_exec("INSERT INTO ccp_monitoring (company_id,haccp_hazard_id,ccp_name,critical_limit,actual_reading,unit,reading_time,operator_id,result,corrective_action,batch_id,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [$cid, $hazardId, post('ccp_name'), post('critical_limit'), post('actual_reading'), post('unit'), date('Y-m-d H:i:s'),
             current_user_id(), $result, post('corrective_action'), post_int('batch_id') ?: null]);
        if ($result === 'fail') {
            create_manual_issue($cid, current_user_id(), [
                'department_id' => null, 'product_id' => $plan['product_id'], 'batch_id' => post_int('batch_id') ?: null, 'shift_id' => null,
                'defect_type' => 'CCP Failure - ' . post('ccp_name'), 'severity' => 'critical',
                'description' => 'CCP monitoring failure: ' . post('ccp_name') . ' reading ' . post('actual_reading') . ' vs limit ' . post('critical_limit'),
            ]);
        }
        flash_set($result === 'fail' ? 'danger' : 'success', $result === 'fail' ? 'CCP FAILURE recorded - quality issue auto-created.' : 'CCP monitoring recorded.');
    }
    redirect(base_url('manager/haccp-view.php?id=' . $id));
}

$hazards = db_all("SELECT * FROM haccp_hazards WHERE haccp_plan_id=? ORDER BY id", [$id]);
$ccpList = array_filter($hazards, fn($h) => $h['is_ccp']);
$monitoring = db_all("SELECT cm.*, hh.process_step FROM ccp_monitoring cm JOIN haccp_hazards hh ON hh.id=cm.haccp_hazard_id WHERE hh.haccp_plan_id=? ORDER BY cm.created_at DESC LIMIT 30", [$id]);
$batches = db_all("SELECT id, batch_number FROM batches WHERE company_id=? ORDER BY created_at DESC LIMIT 100", [$cid]);

$pageTitle = $plan['title'];
$activeMenu = 'haccp';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/haccp.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> HACCP</a>
<div class="mb-4 mt-1"><h4 class="fw-bold mb-0"><?= out($plan['title']) ?></h4></div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#hazards">Hazard Analysis</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#monitoring">CCP Monitoring</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="hazards">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Add Hazard</h3></div>
      <form method="POST" class="row g-2">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="add_hazard">
        <div class="col-md-3"><input class="form-control form-control-sm" name="process_step" placeholder="Process Step" required></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="hazard_type"><option value="biological">Biological</option><option value="chemical">Chemical</option><option value="physical">Physical</option><option value="allergen">Allergen</option></select></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="hazard_description" placeholder="Hazard Description"></div>
        <div class="col-md-1"><input type="number" min="1" max="10" class="form-control form-control-sm" name="severity" placeholder="Sev" value="5"></div>
        <div class="col-md-2"><input type="number" min="1" max="10" class="form-control form-control-sm" name="likelihood" placeholder="Likelihood" value="3"></div>
        <div class="col-md-4"><input class="form-control form-control-sm" name="control_measure" placeholder="Control Measure"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="critical_limit" placeholder="Critical Limit (if CCP)"></div>
        <div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_ccp" id="isCcp"><label class="form-check-label small" for="isCcp">Is CCP?</label></div></div>
        <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Add Hazard</button></div>
      </form>
    </div>
    <div class="qc-card">
      <table class="table table-sm mb-0">
        <thead><tr><th>Step</th><th>Type</th><th>Description</th><th>Risk</th><th>CCP</th><th>Control Measure</th></tr></thead>
        <tbody>
        <?php foreach ($hazards as $h): ?>
          <tr><td class="small"><?= out($h['process_step']) ?></td><td class="small text-capitalize"><?= out($h['hazard_type']) ?></td>
            <td class="small"><?= out($h['hazard_description']) ?></td>
            <td><span class="badge <?= in_array($h['risk_level'],['critical','high']) ? 'bg-danger' : 'bg-warning' ?>"><?= ucfirst($h['risk_level']) ?></span></td>
            <td><?= $h['is_ccp'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '' ?></td>
            <td class="small"><?= out($h['control_measure']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$hazards): ?><tr><td colspan="6" class="text-center text-muted py-3">No hazards recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="tab-pane fade" id="monitoring">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Record CCP Monitoring</h3></div>
      <form method="POST" class="row g-2">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="add_monitoring">
        <div class="col-md-3"><select class="form-select form-select-sm" name="haccp_hazard_id" required><option value="">-- CCP --</option><?php foreach ($ccpList as $c): ?><option value="<?= $c['id'] ?>"><?= out($c['process_step']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="ccp_name" placeholder="CCP Name"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="critical_limit" placeholder="Critical Limit"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="actual_reading" placeholder="Actual Reading" required></div>
        <div class="col-md-1"><input class="form-control form-control-sm" name="unit" placeholder="Unit"></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="result"><option value="pass">Pass</option><option value="fail">Fail</option></select></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="batch_id"><option value="">Batch (optional)</option><?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>"><?= out($b['batch_number']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><input class="form-control form-control-sm" name="corrective_action" placeholder="Corrective action (if fail)"></div>
        <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Record</button></div>
      </form>
    </div>
    <div class="qc-card">
      <table class="table table-sm mb-0">
        <thead><tr><th>Time</th><th>CCP</th><th>Limit</th><th>Reading</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($monitoring as $m): ?>
          <tr><td class="small"><?= fmt_datetime($m['reading_time']) ?></td><td class="small"><?= out($m['ccp_name'] ?: $m['process_step']) ?></td>
            <td class="small"><?= out($m['critical_limit']) ?></td><td class="small"><?= out($m['actual_reading']) ?> <?= out($m['unit']) ?></td>
            <td><?= status_badge($m['result']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$monitoring): ?><tr><td colspan="5" class="text-center text-muted py-3">No monitoring records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
