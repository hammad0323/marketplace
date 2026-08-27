<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$totalCompanies = db_count('companies');
$activeCompanies = db_count('companies', "status='active'");
$totalUsers = db_count('users');
$totalManagers = db_count('users', "role='manager'");
$totalEmployees = db_count('users', "role='employee'");
$totalTools = db_count('tools');
$totalIssues = db_count('quality_issues');
$openIssues = db_count('quality_issues', "status<>'closed'");
$criticalIssues = db_count('quality_issues', "severity='critical' AND status<>'closed'");
$totalCapa = db_count('capa');
$overdueCapa = db_count('capa', "due_date < CURDATE() AND status NOT IN ('closed','rejected','effective')");
$totalAudits = db_count('audits');
$aiRequests30d = db_count('ai_logs', "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

$companyGrowth = db_all(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM companies
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym ORDER BY ym", []
);

$topCompaniesByIssues = db_all(
    "SELECT c.name, COUNT(qi.id) issues FROM companies c LEFT JOIN quality_issues qi ON qi.company_id = c.id
     GROUP BY c.id ORDER BY issues DESC LIMIT 8", []
);

$recentActivity = get_activity_logs(null, [], 10);

$pageTitle = 'Platform Dashboard';
$activeMenu = 'dashboard';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Platform Dashboard</h4>
    <p class="text-muted mb-0 small">Cross-company overview of the QualityCore platform</p>
  </div>
  <a href="<?= base_url('admin/companies.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Company</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-buildings"></i></div>
    <div class="stat-value"><?= $totalCompanies ?></div><div class="stat-label">Companies (<?= $activeCompanies ?> active)</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-people"></i></div>
    <div class="stat-value"><?= $totalUsers ?></div><div class="stat-label"><?= $totalManagers ?> managers, <?= $totalEmployees ?> employees</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-tools"></i></div>
    <div class="stat-value"><?= $totalTools ?></div><div class="stat-label">Quality tools in library</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-cpu"></i></div>
    <div class="stat-value"><?= $aiRequests30d ?></div><div class="stat-label">AI requests (30 days)</div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon text-danger" style="background:#FEF2F2;"><i class="bi bi-exclamation-triangle"></i></div>
    <div class="stat-value"><?= $openIssues ?></div><div class="stat-label"><?= $criticalIssues ?> critical, of <?= $totalIssues ?> total issues</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon text-warning" style="background:#FFFBEB;"><i class="bi bi-clipboard-data"></i></div>
    <div class="stat-value"><?= $totalCapa ?></div><div class="stat-label"><?= $overdueCapa ?> overdue CAPA</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clipboard2-check"></i></div>
    <div class="stat-value"><?= $totalAudits ?></div><div class="stat-label">Audits recorded</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-shield-check"></i></div>
    <div class="stat-value"><?= count(get_tool_categories()) ?></div><div class="stat-label">Tool categories</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Company Growth</h3></div>
      <canvas id="growthChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Issues by Company</h3></div>
      <canvas id="issuesChart" height="130"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Recent Platform Activity</h3><a href="<?= base_url('admin/system-logs.php') ?>" class="small">View all</a></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Time</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead>
          <tbody>
          <?php foreach ($recentActivity as $log): ?>
            <tr><td class="text-muted small"><?= time_ago($log['created_at']) ?></td>
              <td><span class="badge bg-secondary-subtle text-secondary text-capitalize"><?= out($log['action']) ?></span></td>
              <td class="small text-capitalize"><?= out(str_replace('_',' ',$log['module'])) ?></td>
              <td class="small"><?= out($log['description']) ?></td>
              <td class="small text-muted"><?= out($log['ip_address']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$recentActivity): ?><tr><td colspan="5" class="text-center text-muted py-3">No activity yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
new Chart(document.getElementById("growthChart"), { type: "line", data: {
  labels: ' . json_encode(array_column($companyGrowth, 'ym')) . ',
  datasets: [{ label: "New Companies", data: ' . json_encode(array_map('intval', array_column($companyGrowth, 'c'))) . ',
    borderColor: "#2563EB", backgroundColor: "rgba(37,99,235,.1)", fill: true, tension: .35 }]
}, options: { plugins: { legend: { display: false } }, animation: { duration: 900 } } });

new Chart(document.getElementById("issuesChart"), { type: "bar", data: {
  labels: ' . json_encode(array_column($topCompaniesByIssues, 'name')) . ',
  datasets: [{ label: "Issues", data: ' . json_encode(array_map('intval', array_column($topCompaniesByIssues, 'issues'))) . ',
    backgroundColor: "#60A5FA", borderRadius: 6 }]
}, options: { indexAxis: "y", plugins: { legend: { display: false } }, animation: { duration: 900 } } });
</script>';
include __DIR__ . '/../includes/layout_end.php';
