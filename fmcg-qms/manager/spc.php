<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'create_record') {
        $id = db_exec("INSERT INTO spc_records (company_id,product_id,department_id,chart_type,parameter_name,subgroup_size,usl,lsl,target,created_by,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
            [$cid, post_int('product_id') ?: null, post_int('department_id') ?: null, post('chart_type','xbar_r'), post('parameter_name'),
             post_int('subgroup_size', 5), post_float('usl') ?: null, post_float('lsl') ?: null, post_float('target') ?: null, current_user_id()]);
        redirect(base_url('manager/spc.php?id=' . $id));
    } elseif ($action === 'add_subgroup') {
        $recordId = post_int('spc_record_id');
        $values = array_map('floatval', array_filter([$_POST['value1'] ?? null, $_POST['value2'] ?? null, $_POST['value3'] ?? null, $_POST['value4'] ?? null, $_POST['value5'] ?? null], fn($v) => $v !== null && $v !== ''));
        $mean = count($values) ? array_sum($values) / count($values) : 0;
        $range = count($values) ? max($values) - min($values) : 0;
        $subgroupNo = (int)(db_val("SELECT COUNT(*) FROM control_chart_data WHERE spc_record_id=?", [$recordId]) ?? 0) + 1;
        db_exec("INSERT INTO control_chart_data (spc_record_id, subgroup_no, value1, value2, value3, value4, value5, mean_value, range_value, recorded_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW())", [
            $recordId, $subgroupNo, $values[0] ?? null, $values[1] ?? null, $values[2] ?? null, $values[3] ?? null, $values[4] ?? null, $mean, $range,
        ]);
        redirect(base_url('manager/spc.php?id=' . $recordId));
    } elseif ($action === 'capability') {
        $recordId = post_int('spc_record_id');
        $record = db_one("SELECT * FROM spc_records WHERE id=? AND company_id=?", [$recordId, $cid]);
        $usl = post_float('usl') ?: (float)$record['usl']; $lsl = post_float('lsl') ?: (float)$record['lsl'];
        $mean = post_float('mean_value'); $stdev = post_float('std_dev'); $n = post_int('sample_size');
        $cp = calc_cp($usl, $lsl, $stdev); $cpk = calc_cpk($usl, $lsl, $mean, $stdev);
        db_exec("INSERT INTO capability_studies (company_id,spc_record_id,product_id,parameter_name,usl,lsl,mean_value,std_dev,sample_size,cp,cpk,pp,ppk,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [$cid, $recordId, $record['product_id'], $record['parameter_name'], $usl, $lsl, $mean, $stdev, $n, $cp, $cpk, $cp, $cpk]);
        redirect(base_url('manager/spc.php?id=' . $recordId));
    }
}

$viewId = get_int('id');
$records = db_all("SELECT sr.*, p.name AS product_name FROM spc_records sr LEFT JOIN products p ON p.id=sr.product_id WHERE sr.company_id=? ORDER BY sr.created_at DESC", [$cid]);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);

$activeRecord = null; $subgroups = []; $limits = null; $capability = null; $violations = [];
if ($viewId) {
    $activeRecord = db_one("SELECT * FROM spc_records WHERE id=? AND company_id=?", [$viewId, $cid]);
    if ($activeRecord) {
        $subgroups = db_all("SELECT * FROM control_chart_data WHERE spc_record_id=? ORDER BY subgroup_no", [$viewId]);
        if (count($subgroups) >= 2) {
            $limits = calc_control_limits('xbar_r', [
                'xbars' => array_map('floatval', array_column($subgroups, 'mean_value')),
                'ranges' => array_map('floatval', array_column($subgroups, 'range_value')),
                'subgroup_size' => $activeRecord['subgroup_size'],
            ]);
            $violations = detect_control_violations(array_map('floatval', array_column($subgroups, 'mean_value')), $limits['cl'], $limits['ucl'], $limits['lcl']);
        }
        $capability = db_one("SELECT * FROM capability_studies WHERE spc_record_id=? ORDER BY created_at DESC LIMIT 1", [$viewId]);
    }
}

$pageTitle = 'SPC';
$activeMenu = 'spc';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Statistical Process Control</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#spcModal"><i class="bi bi-plus-lg"></i> New Control Chart</button>
</div>

<div class="row g-3">
  <div class="col-lg-3">
    <div class="qc-card p-2">
      <?php foreach ($records as $r): ?>
        <a href="<?= base_url('manager/spc.php?id=' . $r['id']) ?>" class="d-block px-3 py-2 rounded-3 text-decoration-none mb-1 <?= $r['id']==$viewId ? 'bg-primary text-white' : 'text-dark' ?>">
          <div class="small fw-semibold"><?= out($r['parameter_name']) ?></div>
          <div class="small <?= $r['id']==$viewId ? 'text-white-50' : 'text-muted' ?>"><?= out($r['product_name']) ?> - <?= strtoupper(str_replace('_',' ',$r['chart_type'])) ?></div>
        </a>
      <?php endforeach; ?>
      <?php if (!$records): ?><p class="text-muted small p-2">No control charts yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-9">
    <?php if ($activeRecord): ?>
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3><?= out($activeRecord['parameter_name']) ?> (<?= strtoupper(str_replace('_',' ',$activeRecord['chart_type'])) ?>)</h3></div>
        <canvas id="spcChart" height="90"></canvas>
        <?php if ($limits): ?>
          <div class="row g-2 mt-2 small text-muted">
            <div class="col-4">CL: <?= $limits['cl'] ?></div><div class="col-4">UCL: <?= $limits['ucl'] ?></div><div class="col-4">LCL: <?= $limits['lcl'] ?></div>
          </div>
          <?php if ($violations): ?><div class="alert alert-danger small mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> <?= count($violations) ?> out-of-control point(s) detected.</div><?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3>Add Subgroup</h3></div>
        <form method="POST" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="form_action" value="add_subgroup"><input type="hidden" name="spc_record_id" value="<?= $viewId ?>">
          <?php for ($i=1;$i<=$activeRecord['subgroup_size'];$i++): ?><div class="col"><input type="number" step="any" class="form-control form-control-sm" name="value<?= $i ?>" placeholder="V<?= $i ?>"></div><?php endfor; ?>
          <div class="col"><button class="btn btn-sm btn-primary w-100">Add</button></div>
        </form>
      </div>
      <div class="qc-card">
        <div class="qc-card-header"><h3>Process Capability</h3></div>
        <form method="POST" class="row g-2 mb-3">
          <?= csrf_field() ?><input type="hidden" name="form_action" value="capability"><input type="hidden" name="spc_record_id" value="<?= $viewId ?>">
          <div class="col-md-2"><input type="number" step="any" class="form-control form-control-sm" name="usl" placeholder="USL" value="<?= out($activeRecord['usl']) ?>"></div>
          <div class="col-md-2"><input type="number" step="any" class="form-control form-control-sm" name="lsl" placeholder="LSL" value="<?= out($activeRecord['lsl']) ?>"></div>
          <div class="col-md-2"><input type="number" step="any" class="form-control form-control-sm" name="mean_value" placeholder="Mean" required></div>
          <div class="col-md-2"><input type="number" step="any" class="form-control form-control-sm" name="std_dev" placeholder="Std Dev" required></div>
          <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="sample_size" placeholder="n"></div>
          <div class="col-md-2"><button class="btn btn-sm btn-soft-primary w-100">Calculate</button></div>
        </form>
        <?php if ($capability): ?>
          <div class="row g-3 text-center">
            <div class="col-3"><div class="fs-4 fw-bold"><?= $capability['cp'] ?></div><div class="small text-muted">Cp</div></div>
            <div class="col-3"><div class="fs-4 fw-bold"><?= $capability['cpk'] ?></div><div class="small text-muted">Cpk</div></div>
            <div class="col-6 text-start small text-muted d-flex align-items-center"><?= out(capability_interpretation((float)$capability['cpk'])) ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?><p class="text-muted">Select or create a control chart to begin.</p><?php endif; ?>
  </div>
</div>

<div class="modal fade" id="spcModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New Control Chart</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="create_record">
      <div class="mb-2"><label class="form-label small">Parameter Name *</label><input class="form-control" name="parameter_name" required></div>
      <div class="mb-2"><label class="form-label small">Chart Type</label>
        <select class="form-select" name="chart_type"><?php foreach (['xbar_r'=>'X-bar & R','xbar_s'=>'X-bar & S','p'=>'p-chart','np'=>'np-chart','c'=>'c-chart','u'=>'u-chart'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label small">Product</label><select class="form-select" name="product_id"><option value="">--</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="row g-2 mb-2">
        <div class="col-4"><label class="form-label small">USL</label><input type="number" step="any" class="form-control" name="usl"></div>
        <div class="col-4"><label class="form-label small">LSL</label><input type="number" step="any" class="form-control" name="lsl"></div>
        <div class="col-4"><label class="form-label small">Subgroup Size</label><input type="number" class="form-control" name="subgroup_size" value="5"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Create</button></div>
  </form>
</div></div></div>

<?php
$extraScripts = $activeRecord ? '<script>
new Chart(document.getElementById("spcChart"), { type: "line", data: {
  labels: ' . json_encode(array_column($subgroups, 'subgroup_no')) . ',
  datasets: [
    { label: "Subgroup Mean", data: ' . json_encode(array_map('floatval', array_column($subgroups, 'mean_value'))) . ', borderColor:"#2563EB", tension:.2 },
    ' . ($limits ? '{ label:"UCL", data: Array(' . count($subgroups) . ').fill(' . $limits['ucl'] . '), borderColor:"#DC2626", borderDash:[6,6], pointRadius:0 },
    { label:"CL", data: Array(' . count($subgroups) . ').fill(' . $limits['cl'] . '), borderColor:"#16A34A", borderDash:[3,3], pointRadius:0 },
    { label:"LCL", data: Array(' . count($subgroups) . ').fill(' . $limits['lcl'] . '), borderColor:"#DC2626", borderDash:[6,6], pointRadius:0 }' : '') . '
  ]
}, options: { animation: { duration: 800 } } });
</script>' : '';
include __DIR__ . '/../includes/layout_end.php';
