<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$period = get_param('period', '30d');
if (!in_array($period, ['7d', '30d', '90d'], true)) $period = '30d';
$days = ['7d' => 7, '30d' => 30, '90d' => 90][$period];

[$from, $to] = kpi_date_range($period);
$prevFrom = date('Y-m-d 00:00:00', strtotime($from . " -$days days"));
$prevTo = date('Y-m-d 23:59:59', strtotime($from . ' -1 second'));

$score = get_company_quality_score_from_snapshot($cid, get_kpi_snapshot_range($cid, $from, $to));
$prevScore = get_company_quality_score_from_snapshot($cid, get_kpi_snapshot_range($cid, $prevFrom, $prevTo));
$heatmap = get_department_heatmap($cid, $period);

// Daily multi-line trend: issues / NCR / CAPA opened per day across the selected period
$dateLabels = [];
for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
    $dateLabels[] = date('Y-m-d', $d);
}
$issuesByDay = array_fill_keys($dateLabels, 0);
$ncrByDay = array_fill_keys($dateLabels, 0);
$capaByDay = array_fill_keys($dateLabels, 0);
foreach (db_all("SELECT DATE(created_at) d, COUNT(*) c FROM quality_issues WHERE company_id=? AND created_at BETWEEN ? AND ? GROUP BY d", [$cid, $from, $to]) as $r) {
    $issuesByDay[$r['d']] = (int)$r['c'];
}
foreach (db_all("SELECT DATE(created_at) d, COUNT(*) c FROM ncr WHERE company_id=? AND created_at BETWEEN ? AND ? GROUP BY d", [$cid, $from, $to]) as $r) {
    $ncrByDay[$r['d']] = (int)$r['c'];
}
foreach (db_all("SELECT DATE(created_at) d, COUNT(*) c FROM capa WHERE company_id=? AND created_at BETWEEN ? AND ? GROUP BY d", [$cid, $from, $to]) as $r) {
    $capaByDay[$r['d']] = (int)$r['c'];
}

$severityData = db_all(
    "SELECT severity, COUNT(*) c FROM quality_issues WHERE company_id=? AND created_at BETWEEN ? AND ? GROUP BY severity",
    [$cid, $from, $to]
);

$paretoData = db_all(
    "SELECT defect_type, COUNT(*) c FROM quality_issues WHERE company_id=? AND defect_type IS NOT NULL AND created_at BETWEEN ? AND ?
     GROUP BY defect_type ORDER BY c DESC LIMIT 8", [$cid, $from, $to]
);
$paretoTotal = array_sum(array_column($paretoData, 'c')) ?: 1;
$paretoCumulative = [];
$running = 0;
foreach ($paretoData as $p) {
    $running += (int)$p['c'];
    $paretoCumulative[] = round($running / $paretoTotal * 100, 1);
}

$supplierData = db_all(
    "SELECT s.name, sc.overall_score FROM supplier_scorecards sc
     JOIN suppliers s ON s.id = sc.supplier_id
     JOIN (SELECT supplier_id, MAX(created_at) mx FROM supplier_scorecards WHERE company_id=? GROUP BY supplier_id) latest
       ON latest.supplier_id = sc.supplier_id AND latest.mx = sc.created_at
     WHERE sc.company_id=? ORDER BY sc.overall_score DESC LIMIT 10", [$cid, $cid]
);

$oeeData = db_all(
    "SELECT COALESCE(pl.name, 'Unassigned') AS line_name, AVG(o.oee) avg_oee FROM oee_records o
     LEFT JOIN production_lines pl ON pl.id = o.production_line_id
     WHERE o.company_id=? AND o.record_date BETWEEN ? AND ? GROUP BY pl.id ORDER BY avg_oee DESC", [$cid, $from, $to]
);

$pageTitle = 'Analytics';
$activeMenu = 'analytics';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div><h4 class="fw-bold mb-0">Analytics</h4><p class="text-muted mb-0 small">Power BI-style quality intelligence - drill into any chart to see the underlying issues</p></div>
  <form method="GET" class="d-flex gap-2">
    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach (['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= $period === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($score['components'] as $key => $c):
      $prev = $prevScore['components'][$key] ?? null;
      $delta = $prev ? round($c['value'] - $prev['value'], 1) : null;
      $improved = $delta !== null ? (($c['direction'] === 'lower_better' && $delta <= 0) || ($c['direction'] !== 'lower_better' && $delta >= 0)) : null;
  ?>
    <div class="col-md-3 col-6">
      <div class="stat-card h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div class="stat-label mb-1"><?= out($c['name']) ?></div>
          <?= rag_badge($c['rag']) ?>
        </div>
        <div class="stat-value"><?= $c['value'] ?>%</div>
        <?php if ($delta !== null && $delta != 0): ?>
          <div class="small <?= $improved ? 'text-success' : 'text-danger' ?>">
            <i class="bi <?= $delta > 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i> <?= abs($delta) ?>pts vs prior period
          </div>
        <?php elseif ($delta === 0.0): ?>
          <div class="small text-muted"><i class="bi bi-dash"></i> No change</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Quality Events Trend</h3></div>
      <canvas id="trendChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Issue Severity Distribution</h3></div>
      <canvas id="severityChart" height="110"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Department Quality Comparison</h3><span class="small text-muted">Click a bar to view its issues</span></div>
      <canvas id="deptChart" height="130"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Pareto - Top Defects</h3></div>
      <canvas id="paretoChart" height="130"></canvas>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Supplier Score Comparison</h3><a href="<?= base_url('manager/suppliers.php') ?>" class="small">View all</a></div>
      <?php if ($supplierData): ?><canvas id="supplierChart" height="130"></canvas>
      <?php else: ?><p class="text-muted small mb-0">No supplier scorecards recorded yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>OEE by Production Line</h3><a href="<?= base_url('manager/oee.php') ?>" class="small">View all</a></div>
      <?php if ($oeeData): ?><canvas id="oeeChart" height="130"></canvas>
      <?php else: ?><p class="text-muted small mb-0">No OEE records for this period.</p><?php endif; ?>
    </div>
  </div>
</div>

<?php
$deptUrl = base_url('manager/issues.php');
$extraScripts = '<script>
new Chart(document.getElementById("trendChart"), { type: "line", data: {
  labels: ' . json_encode(array_map(fn($d) => date('M j', strtotime($d)), $dateLabels)) . ',
  datasets: [
    { label: "Issues", data: ' . json_encode(array_values($issuesByDay)) . ', borderColor: "#DC2626", backgroundColor: "rgba(220,38,38,.08)", tension: .35 },
    { label: "NCR", data: ' . json_encode(array_values($ncrByDay)) . ', borderColor: "#D97706", backgroundColor: "rgba(217,119,6,.08)", tension: .35 },
    { label: "CAPA", data: ' . json_encode(array_values($capaByDay)) . ', borderColor: "#2563EB", backgroundColor: "rgba(37,99,235,.08)", tension: .35 }
  ]
}, options: { animation: { duration: 900 }, interaction: { mode: "index", intersect: false } } });

new Chart(document.getElementById("severityChart"), { type: "doughnut", data: {
  labels: ' . json_encode(array_map('ucfirst', array_column($severityData, 'severity'))) . ',
  datasets: [{ data: ' . json_encode(array_map('intval', array_column($severityData, 'c'))) . ',
    backgroundColor: ["#DC2626","#EA580C","#D97706","#2563EB","#64748B"] }]
}, options: { animation: { duration: 900 } } });

const deptLabels = ' . json_encode(array_column($heatmap, 'department')) . ';
const deptIds = ' . json_encode(array_column($heatmap, 'id')) . ';
const deptScores = ' . json_encode(array_column($heatmap, 'quality_score')) . ';
const deptColors = ' . json_encode(array_map(fn($d) => ['green' => '#16A34A', 'amber' => '#D97706', 'red' => '#DC2626'][$d['rag']], $heatmap)) . ';
const deptChart = new Chart(document.getElementById("deptChart"), { type: "bar", data: {
  labels: deptLabels, datasets: [{ label: "Quality Score", data: deptScores, backgroundColor: deptColors, borderRadius: 6 }]
}, options: { plugins: { legend: { display:false } }, animation: { duration: 900 },
  onClick: (evt, els) => { if (els.length) { window.location.href = "' . $deptUrl . '?department_id=" + deptIds[els[0].index]; } },
  onHover: (evt, els) => { evt.native.target.style.cursor = els.length ? "pointer" : "default"; } } });

new Chart(document.getElementById("paretoChart"), { data: {
  labels: ' . json_encode(array_column($paretoData, 'defect_type')) . ',
  datasets: [
    { type: "bar", label: "Occurrences", data: ' . json_encode(array_map('intval', array_column($paretoData, 'c'))) . ', backgroundColor: "#2563EB", borderRadius: 6, yAxisID: "y" },
    { type: "line", label: "Cumulative %", data: ' . json_encode($paretoCumulative) . ', borderColor: "#DC2626", backgroundColor: "#DC2626", yAxisID: "y1", tension: .3 }
  ]
}, options: { animation: { duration: 900 },
  scales: { y: { position: "left", beginAtZero: true }, y1: { position: "right", beginAtZero: true, max: 100, grid: { drawOnChartArea: false } } } } });
' . ($supplierData ? '
new Chart(document.getElementById("supplierChart"), { type: "bar", data: {
  labels: ' . json_encode(array_column($supplierData, 'name')) . ',
  datasets: [{ label: "Overall Score", data: ' . json_encode(array_map('floatval', array_column($supplierData, 'overall_score'))) . ', backgroundColor: "#16A34A", borderRadius: 6 }]
}, options: { indexAxis: "y", plugins: { legend: { display:false } }, animation: { duration: 900 } } });' : '') . '
' . ($oeeData ? '
new Chart(document.getElementById("oeeChart"), { type: "bar", data: {
  labels: ' . json_encode(array_column($oeeData, 'line_name')) . ',
  datasets: [{ label: "Avg OEE %", data: ' . json_encode(array_map(fn($v) => round((float)$v, 1), array_column($oeeData, 'avg_oee'))) . ', backgroundColor: "#7C3AED", borderRadius: 6 }]
}, options: { plugins: { legend: { display:false } }, animation: { duration: 900 } } });' : '') . '
</script>';
include __DIR__ . '/../includes/layout_end.php';
