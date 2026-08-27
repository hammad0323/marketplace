<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$supplier = db_one("SELECT * FROM suppliers WHERE id=? AND company_id=?", [$id, $cid]);
if (!$supplier) { flash_set('danger', 'Supplier not found.'); redirect(base_url('manager/suppliers.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'add_scorecard') {
        $q = post_float('quality_score'); $d = post_float('delivery_score'); $c = post_float('cost_score'); $r = post_float('responsiveness_score'); $comp = post_float('compliance_score');
        $overall = calc_weighted_score([
            ['value'=>$q,'weight'=>0.35], ['value'=>$d,'weight'=>0.25], ['value'=>$c,'weight'=>0.15], ['value'=>$r,'weight'=>0.1], ['value'=>$comp,'weight'=>0.15],
        ]);
        db_exec("INSERT INTO supplier_scorecards (company_id,supplier_id,period,quality_score,delivery_score,cost_score,responsiveness_score,compliance_score,overall_score) VALUES (?,?,?,?,?,?,?,?,?)",
            [$cid, $id, post('period') ?: date('Y-m'), $q, $d, $c, $r, $comp, $overall]);
        flash_set('success', "Scorecard added. Overall score: $overall%");
    } elseif ($action === 'add_inspection') {
        db_exec("INSERT INTO incoming_inspections (company_id,supplier_id,material_name,batch_number,quantity,sample_size,accept_qty,reject_qty,result,inspector_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$cid, $id, post('material_name'), post('batch_number'), post_float('quantity'), post_int('sample_size'), post_int('accept_qty'), post_int('reject_qty'), post('result','accepted'), current_user_id()]);
        flash_set('success', 'Incoming inspection recorded.');
    } elseif ($action === 'add_audit') {
        db_exec("INSERT INTO supplier_audits (company_id,supplier_id,audit_date,auditor_id,score,max_score,status,findings) VALUES (?,?,?,?,?,?, 'completed',?)",
            [$cid, $id, post('audit_date') ?: date('Y-m-d'), current_user_id(), post_float('score'), post_float('max_score', 100), post('findings')]);
        flash_set('success', 'Supplier audit recorded.');
    } elseif ($action === 'add_coa') {
        db_exec("INSERT INTO coa_records (company_id,supplier_id,material_name,batch_number,specification,actual_result,result,reviewed_by) VALUES (?,?,?,?,?,?,?,?)",
            [$cid, $id, post('material_name'), post('batch_number'), post('specification'), post('actual_result'), post('result','pass'), current_user_id()]);
        flash_set('success', 'CoA recorded.');
    }
    redirect(base_url('manager/supplier-view.php?id=' . $id));
}

$scorecards = db_all("SELECT * FROM supplier_scorecards WHERE supplier_id=? ORDER BY period DESC", [$id]);
$inspections = db_all("SELECT * FROM incoming_inspections WHERE supplier_id=? ORDER BY created_at DESC LIMIT 10", [$id]);
$audits = db_all("SELECT * FROM supplier_audits WHERE supplier_id=? ORDER BY audit_date DESC", [$id]);
$coaRecords = db_all("SELECT * FROM coa_records WHERE supplier_id=? ORDER BY created_at DESC LIMIT 10", [$id]);
$avgScore = $scorecards ? round(array_sum(array_column($scorecards, 'overall_score')) / count($scorecards), 1) : null;

$pageTitle = $supplier['name'];
$activeMenu = 'suppliers';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/suppliers.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Suppliers</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <div><h4 class="fw-bold mb-0"><?= out($supplier['name']) ?></h4><p class="text-muted mb-0 small"><?= out($supplier['material_category']) ?></p></div>
  <div class="fs-3 fw-bold"><?= $avgScore !== null ? $avgScore . '%' : 'N/A' ?></div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#scorecard">Scorecards</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#inspection">Incoming Inspection</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#audit">Supplier Audits</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#coa">CoA Review</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="scorecard">
    <div class="row g-3">
      <div class="col-lg-4">
        <form method="POST" class="qc-card">
          <?= csrf_field() ?><input type="hidden" name="form_action" value="add_scorecard">
          <h3 class="mb-3">Add Scorecard</h3>
          <div class="mb-2"><label class="form-label small">Period</label><input class="form-control form-control-sm" name="period" value="<?= date('Y-m') ?>"></div>
          <?php foreach (['quality_score'=>'Quality','delivery_score'=>'Delivery','cost_score'=>'Cost','responsiveness_score'=>'Responsiveness','compliance_score'=>'Compliance'] as $k=>$label): ?>
            <div class="mb-2"><label class="form-label small"><?= $label ?> (0-100)</label><input type="number" min="0" max="100" class="form-control form-control-sm" name="<?= $k ?>" required></div>
          <?php endforeach; ?>
          <button class="btn btn-sm btn-primary w-100">Save Scorecard</button>
        </form>
      </div>
      <div class="col-lg-8">
        <div class="qc-card"><table class="table table-sm mb-0">
          <thead><tr><th>Period</th><th>Quality</th><th>Delivery</th><th>Cost</th><th>Responsiveness</th><th>Compliance</th><th>Overall</th></tr></thead>
          <tbody><?php foreach ($scorecards as $sc): ?><tr><td class="small"><?= out($sc['period']) ?></td><td><?= $sc['quality_score'] ?></td><td><?= $sc['delivery_score'] ?></td><td><?= $sc['cost_score'] ?></td><td><?= $sc['responsiveness_score'] ?></td><td><?= $sc['compliance_score'] ?></td><td class="fw-bold"><?= $sc['overall_score'] ?></td></tr><?php endforeach; ?>
          <?php if (!$scorecards): ?><tr><td colspan="7" class="text-center text-muted py-3">No scorecards yet.</td></tr><?php endif; ?></tbody>
        </table></div>
      </div>
    </div>
  </div>
  <div class="tab-pane fade" id="inspection">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_inspection">
        <h3 class="mb-3">Incoming Inspection</h3>
        <div class="mb-2"><label class="form-label small">Material</label><input class="form-control form-control-sm" name="material_name" required></div>
        <div class="mb-2"><label class="form-label small">Batch Number</label><input class="form-control form-control-sm" name="batch_number"></div>
        <div class="mb-2"><label class="form-label small">Quantity</label><input type="number" step="0.01" class="form-control form-control-sm" name="quantity"></div>
        <div class="mb-2"><label class="form-label small">Sample Size</label><input type="number" class="form-control form-control-sm" name="sample_size"></div>
        <div class="mb-2"><label class="form-label small">Accepted Qty</label><input type="number" class="form-control form-control-sm" name="accept_qty"></div>
        <div class="mb-2"><label class="form-label small">Rejected Qty</label><input type="number" class="form-control form-control-sm" name="reject_qty"></div>
        <div class="mb-2"><label class="form-label small">Result</label><select class="form-select form-select-sm" name="result"><option value="accepted">Accepted</option><option value="rejected">Rejected</option><option value="conditional">Conditional</option></select></div>
        <button class="btn btn-sm btn-primary w-100">Save</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0">
        <thead><tr><th>Material</th><th>Batch</th><th>Sample</th><th>Result</th></tr></thead>
        <tbody><?php foreach ($inspections as $i): ?><tr><td class="small"><?= out($i['material_name']) ?></td><td class="small"><?= out($i['batch_number']) ?></td><td class="small"><?= $i['accept_qty'] ?>/<?= $i['sample_size'] ?></td><td><?= status_badge($i['result']) ?></td></tr><?php endforeach; ?>
        <?php if (!$inspections): ?><tr><td colspan="4" class="text-center text-muted py-3">None yet.</td></tr><?php endif; ?></tbody>
      </table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="audit">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_audit">
        <h3 class="mb-3">Supplier Audit</h3>
        <div class="mb-2"><label class="form-label small">Audit Date</label><input type="date" class="form-control form-control-sm" name="audit_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label small">Score</label><input type="number" step="0.1" class="form-control form-control-sm" name="score" required></div>
        <div class="mb-2"><label class="form-label small">Max Score</label><input type="number" step="0.1" class="form-control form-control-sm" name="max_score" value="100"></div>
        <div class="mb-2"><label class="form-label small">Findings</label><textarea class="form-control form-control-sm" name="findings" rows="2"></textarea></div>
        <button class="btn btn-sm btn-primary w-100">Save</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Score</th><th>Findings</th></tr></thead>
        <tbody><?php foreach ($audits as $a): ?><tr><td class="small"><?= fmt_date($a['audit_date']) ?></td><td><?= $a['score'] ?>/<?= $a['max_score'] ?></td><td class="small"><?= out($a['findings']) ?></td></tr><?php endforeach; ?>
        <?php if (!$audits): ?><tr><td colspan="3" class="text-center text-muted py-3">None yet.</td></tr><?php endif; ?></tbody>
      </table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="coa">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_coa">
        <h3 class="mb-3">CoA Review</h3>
        <div class="mb-2"><label class="form-label small">Material</label><input class="form-control form-control-sm" name="material_name" required></div>
        <div class="mb-2"><label class="form-label small">Batch Number</label><input class="form-control form-control-sm" name="batch_number"></div>
        <div class="mb-2"><label class="form-label small">Specification</label><input class="form-control form-control-sm" name="specification"></div>
        <div class="mb-2"><label class="form-label small">Actual Result</label><input class="form-control form-control-sm" name="actual_result"></div>
        <div class="mb-2"><label class="form-label small">Result</label><select class="form-select form-select-sm" name="result"><option value="pass">Pass</option><option value="fail">Fail</option></select></div>
        <button class="btn btn-sm btn-primary w-100">Save</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0">
        <thead><tr><th>Material</th><th>Batch</th><th>Spec</th><th>Actual</th><th>Result</th></tr></thead>
        <tbody><?php foreach ($coaRecords as $c): ?><tr><td class="small"><?= out($c['material_name']) ?></td><td class="small"><?= out($c['batch_number']) ?></td><td class="small"><?= out($c['specification']) ?></td><td class="small"><?= out($c['actual_result']) ?></td><td><?= status_badge($c['result']) ?></td></tr><?php endforeach; ?>
        <?php if (!$coaRecords): ?><tr><td colspan="5" class="text-center text-muted py-3">None yet.</td></tr><?php endif; ?></tbody>
      </table></div></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
