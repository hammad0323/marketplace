<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$id = get_int('id');
$company = db_one("SELECT c.*, sp.name AS plan_name FROM companies c LEFT JOIN subscription_plans sp ON sp.id=c.subscription_plan_id WHERE c.id=?", [$id]);
if (!$company) { flash_set('danger', 'Company not found.'); redirect(base_url('admin/companies.php')); }

$quality = get_company_quality_score($id);
$heatmap = get_department_heatmap($id);
$employeeCount = db_count('users', 'company_id=? AND role=?', 'is', [$id, 'employee']);
$openIssues = db_count('quality_issues', "company_id=? AND status<>'closed'", 'i', [$id]);
$openCapa = db_count('capa', "company_id=? AND status NOT IN ('closed','rejected','effective')", 'i', [$id]);
$toolsAssigned = db_count('tool_assignments', "company_id=? AND status='active'", 'i', [$id]);

$pageTitle = $company['name'];
$activeMenu = 'companies';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="<?= base_url('admin/companies.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Companies</a>
    <h4 class="fw-bold mb-0 mt-1"><?= out($company['name']) ?> <?= status_badge($company['status']) ?></h4>
    <p class="text-muted mb-0 small"><?= out($company['code']) ?> - <?= out($company['plan_name'] ?? 'No plan') ?> - <?= out($company['industry']) ?></p>
  </div>
  <a href="<?= base_url('admin/company-form.php?id=' . $id) ?>" class="btn btn-soft-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-award"></i></div><div class="stat-value"><?= $quality['score'] ?>%</div><div class="stat-label">Quality Score <?= rag_badge($quality['rag']) ?></div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-people"></i></div><div class="stat-value"><?= $employeeCount ?></div><div class="stat-label">Employees</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value"><?= $openIssues ?></div><div class="stat-label">Open Issues</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-clipboard-data"></i></div><div class="stat-value"><?= $openCapa ?></div><div class="stat-label">Open CAPA</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Department Quality Heatmap</h3></div>
      <div class="row g-2">
        <?php foreach ($heatmap as $d): ?>
          <div class="col-md-4 col-6">
            <div class="heatmap-cell <?= $d['rag'] ?>">
              <div class="small"><?= out($d['department']) ?></div>
              <div class="fs-5"><?= $d['quality_score'] ?>%</div>
              <div class="small opacity-75"><?= $d['open_issues'] ?> open issues</div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$heatmap): ?><p class="text-muted small">No departments yet.</p><?php endif; ?>
      </div>
    </div>
    <div class="qc-card">
      <div class="qc-card-header"><h3>KPI Snapshot (30 days)</h3></div>
      <div class="row g-3">
        <?php foreach ($quality['components'] as $key => $c): ?>
          <div class="col-md-4 col-6">
            <div class="border rounded-3 p-2">
              <div class="small text-muted"><?= out($c['name']) ?></div>
              <div class="fw-bold"><?= fmt_number($c['value'],1) ?>% <?= rag_badge($c['rag']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Company Info</h3></div>
      <table class="table table-sm mb-0">
        <tr><td class="text-muted small">Contact</td><td><?= out($company['contact_person']) ?></td></tr>
        <tr><td class="text-muted small">Email</td><td><?= out($company['email']) ?></td></tr>
        <tr><td class="text-muted small">Phone</td><td><?= out($company['phone']) ?></td></tr>
        <tr><td class="text-muted small">Location</td><td><?= out($company['city']) ?>, <?= out($company['country']) ?></td></tr>
        <tr><td class="text-muted small">Tools Assigned</td><td><?= $toolsAssigned ?></td></tr>
        <tr><td class="text-muted small">Subscription</td><td><?= fmt_date($company['start_date']) ?> - <?= fmt_date($company['expiry_date']) ?></td></tr>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
