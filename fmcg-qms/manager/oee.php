<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $planned = post_float('planned_minutes'); $downtime = post_float('downtime_minutes');
    $cycleTime = post_float('ideal_cycle_time'); $total = post_float('total_count'); $good = post_float('good_count');
    $availability = calc_availability($planned, $downtime);
    $performance = calc_performance($cycleTime, $total, max($planned - $downtime, 1));
    $quality = calc_quality_rate($good, $total);
    $oee = calc_oee($availability, $performance, $quality);
    db_exec("INSERT INTO oee_records (company_id,production_line_id,shift_id,record_date,planned_minutes,downtime_minutes,ideal_cycle_time,total_count,good_count,availability,performance,quality,oee)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$cid, post_int('production_line_id') ?: null, post_int('shift_id') ?: null, post('record_date') ?: date('Y-m-d'),
         $planned, $downtime, $cycleTime, $total, $good, $availability, $performance, $quality, $oee]);
    log_activity($cid, current_user_id(), 'create', 'oee', null, "Recorded OEE $oee%");
    flash_set('success', "OEE recorded: $oee% (Availability $availability%, Performance $performance%, Quality $quality%)");
    redirect(base_url('manager/oee.php'));
}

$lines = db_all("SELECT id, name FROM production_lines WHERE company_id=? ORDER BY name", [$cid]);
$shifts = db_all("SELECT id, name FROM shifts WHERE company_id=? ORDER BY name", [$cid]);
$records = db_all(
    "SELECT o.*, pl.name AS line_name FROM oee_records o LEFT JOIN production_lines pl ON pl.id=o.production_line_id
     WHERE o.company_id=? ORDER BY o.record_date DESC LIMIT 30", [$cid]
);
$trend = array_reverse($records);

$pageTitle = 'OEE';
$activeMenu = 'oee';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Overall Equipment Effectiveness</h4><p class="text-muted mb-0 small">OEE = Availability &times; Performance &times; Quality</p></div>

<div class="row g-3">
  <div class="col-lg-4">
    <form method="POST" class="qc-card">
      <?= csrf_field() ?>
      <h3 class="mb-3">Record Shift Data</h3>
      <div class="mb-2"><label class="form-label small fw-semibold">Production Line</label><select class="form-select form-select-sm" name="production_line_id"><?php foreach ($lines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Shift</label><select class="form-select form-select-sm" name="shift_id"><?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>"><?= out($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Date</label><input type="date" class="form-control form-control-sm" name="record_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Planned Minutes</label><input type="number" step="0.01" class="form-control form-control-sm" name="planned_minutes" value="480" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Downtime Minutes</label><input type="number" step="0.01" class="form-control form-control-sm" name="downtime_minutes" value="0" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Ideal Cycle Time (min/unit)</label><input type="number" step="0.0001" class="form-control form-control-sm" name="ideal_cycle_time" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Total Count</label><input type="number" step="1" class="form-control form-control-sm" name="total_count" required></div>
      <div class="mb-3"><label class="form-label small fw-semibold">Good Count</label><input type="number" step="1" class="form-control form-control-sm" name="good_count" required></div>
      <button class="btn btn-primary btn-sm w-100">Calculate & Save</button>
    </form>
  </div>
  <div class="col-lg-8">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>OEE Trend</h3></div>
      <canvas id="oeeChart" height="100"></canvas>
    </div>
    <div class="qc-card">
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Line</th><th>Availability</th><th>Performance</th><th>Quality</th><th>OEE</th></tr></thead>
        <tbody>
        <?php foreach ($records as $r): ?>
          <tr><td class="small"><?= fmt_date($r['record_date']) ?></td><td class="small"><?= out($r['line_name']) ?></td>
            <td class="small"><?= $r['availability'] ?>%</td><td class="small"><?= $r['performance'] ?>%</td><td class="small"><?= $r['quality'] ?>%</td>
            <td class="fw-bold small"><?= $r['oee'] ?>%</td></tr>
        <?php endforeach; ?>
        <?php if (!$records): ?><tr><td colspan="6" class="text-center text-muted py-3">No OEE records yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php
$extraScripts = '<script>
new Chart(document.getElementById("oeeChart"), { type: "line", data: {
  labels: ' . json_encode(array_column($trend, 'record_date')) . ',
  datasets: [{ label: "OEE %", data: ' . json_encode(array_map('floatval', array_column($trend, 'oee'))) . ', borderColor:"#2563EB", backgroundColor:"rgba(37,99,235,.1)", fill:true, tension:.3 }]
}, options: { animation: { duration: 900 }, scales: { y: { suggestedMin:0, suggestedMax:100 } } } });
</script>';
include __DIR__ . '/../includes/layout_end.php';
