<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$quality = get_company_quality_score($cid);
$heatmap = get_department_heatmap($cid);
[$from30, $to30] = kpi_date_range('30d');

$openIssues = db_count('quality_issues', "company_id=? AND status<>'closed'", 'i', [$cid]);
$criticalIssues = db_count('quality_issues', "company_id=? AND severity='critical' AND status<>'closed'", 'i', [$cid]);
$openNcr = db_count('ncr', "company_id=? AND status<>'closed'", 'i', [$cid]);
$openCapa = db_count('capa', "company_id=? AND status NOT IN ('closed','rejected','effective')", 'i', [$cid]);
$overdueCapa = count_overdue_capa($cid);
$employeeCompliance = kpi_employee_compliance($cid, $from30, $to30);
$auditScore = kpi_audit_score($cid, $from30, $to30);

$trend = db_all(
    "SELECT DATE(created_at) d, COUNT(*) c FROM quality_issues WHERE company_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
    [$cid]
);
$paretoData = db_all(
    "SELECT defect_type, COUNT(*) c FROM quality_issues WHERE company_id=? AND defect_type IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY defect_type ORDER BY c DESC LIMIT 8", [$cid]
);
$insights = ai_dashboard_insights($cid, current_user_id());
$recentIssues = get_issues($cid, [], 6);

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Quality Dashboard</h4><p class="text-muted mb-0 small">Real-time view of quality performance across your operation</p></div>
  <div class="text-end">
    <div class="fs-3 fw-bold"><?= $quality['score'] ?>% <?= rag_badge($quality['rag']) ?></div>
    <div class="small text-muted">Overall Quality Score</div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div><div class="stat-value"><?= $quality['snapshot']['fpy'] ?>%</div><div class="stat-label">First Pass Yield</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-percent"></i></div><div class="stat-value"><?= $quality['snapshot']['defect_rate'] ?>%</div><div class="stat-label">Defect Rate</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-speedometer"></i></div><div class="stat-value"><?= $quality['snapshot']['oee'] ?>%</div><div class="stat-label">OEE</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-person-check"></i></div><div class="stat-value"><?= $employeeCompliance ?>%</div><div class="stat-label">Employee Compliance</div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon text-danger" style="background:#FEF2F2;"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value"><?= $openIssues ?></div><div class="stat-label"><?= $criticalIssues ?> critical open issues</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-file-earmark-excel"></i></div><div class="stat-value"><?= $openNcr ?></div><div class="stat-label">Open NCR</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon text-warning" style="background:#FFFBEB;"><i class="bi bi-clipboard-data"></i></div><div class="stat-value"><?= $openCapa ?></div><div class="stat-label"><?= $overdueCapa ?> overdue CAPA</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clipboard2-check"></i></div><div class="stat-value"><?= $auditScore ?>%</div><div class="stat-label">Audit Score</div></div></div>
</div>

<div class="qc-card mb-4">
  <div class="qc-card-header"><h3><i class="bi bi-robot text-primary"></i> AI Insights</h3><a href="<?= base_url('manager/ai-assistant.php') ?>" class="small">Ask a question &rarr;</a></div>
  <div class="row g-2">
    <?php foreach ($insights as $ins): ?>
      <div class="col-md-6"><div class="ai-suggestion-box"><i class="bi bi-stars mt-1"></i><span><?= out($ins) ?></span></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Quality Issue Trend (14 days)</h3></div>
      <canvas id="trendChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Pareto - Top Defects (30 days)</h3></div>
      <canvas id="paretoChart" height="130"></canvas>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Department Quality Heatmap</h3></div>
      <div class="row g-2">
        <?php foreach ($heatmap as $d): ?>
          <div class="col-md-4 col-6"><div class="heatmap-cell <?= $d['rag'] ?>">
            <div class="small"><?= out($d['department']) ?></div><div class="fs-5"><?= $d['quality_score'] ?>%</div>
            <div class="small opacity-75"><?= $d['open_issues'] ?> open</div></div></div>
        <?php endforeach; ?>
        <?php if (!$heatmap): ?><p class="text-muted small">No departments configured yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Recent Issues</h3><a href="<?= base_url('manager/issues.php') ?>" class="small">View all</a></div>
      <?php foreach ($recentIssues as $i): ?>
        <a href="<?= base_url('manager/issue-view.php?id=' . $i['id']) ?>" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none text-dark">
          <div><div class="small fw-semibold"><?= out($i['issue_number']) ?></div><div class="small text-muted text-truncate" style="max-width:220px;"><?= out($i['description']) ?></div></div>
          <?= severity_badge($i['severity']) ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$recentIssues): ?><p class="text-muted small mb-0 mt-2">No issues recorded yet.</p><?php endif; ?>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
new Chart(document.getElementById("trendChart"), { type: "line", data: {
  labels: ' . json_encode(array_column($trend, 'd')) . ',
  datasets: [{ label: "Issues", data: ' . json_encode(array_map('intval', array_column($trend, 'c'))) . ',
    borderColor: "#DC2626", backgroundColor: "rgba(220,38,38,.08)", fill: true, tension: .35 }]
}, options: { plugins: { legend: { display:false } }, animation: { duration: 900 } } });

new Chart(document.getElementById("paretoChart"), { type: "bar", data: {
  labels: ' . json_encode(array_column($paretoData, 'defect_type')) . ',
  datasets: [{ label: "Occurrences", data: ' . json_encode(array_map('intval', array_column($paretoData, 'c'))) . ',
    backgroundColor: "#2563EB", borderRadius: 6 }]
}, options: { plugins: { legend: { display:false } }, animation: { duration: 900 } } });
</script>';
include __DIR__ . '/../includes/layout_end.php';
