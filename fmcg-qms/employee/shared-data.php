<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$myDeptId = $_SESSION['department_id'] ?? null;

$sharedDepartments = $myDeptId ? get_departments_shared_with($cid, (int)$myDeptId) : [];
$viewDeptId = get_int('department_id') ?: (int)($sharedDepartments[0]['id'] ?? 0);

$allowed = $myDeptId && department_can_view($cid, (int)$myDeptId, $viewDeptId);
$viewDept = $allowed ? db_one("SELECT * FROM departments WHERE id=? AND company_id=?", [$viewDeptId, $cid]) : null;

$issues = $submissions = [];
$quality = null;
if ($viewDept) {
    $quality = get_department_heatmap($cid);
    $quality = array_values(array_filter($quality, fn($d) => $d['department'] === $viewDept['name']))[0] ?? null;
    $issues = get_issues($cid, ['department_id' => $viewDeptId], 15);
    $submissions = get_tool_submissions($cid, ['department_id' => $viewDeptId], 15);
}

$pageTitle = 'Department Data';
$activeMenu = 'shared-data';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Department Data</h4><p class="text-muted mb-0 small">Read-only visibility into other departments' quality data, as granted by your manager. You cannot edit anything here.</p></div>

<?php if (!$myDeptId): ?>
  <div class="alert alert-warning">You are not assigned to a department yet, so no shared data can be shown. Contact your manager.</div>
<?php elseif (!$sharedDepartments): ?>
  <div class="alert alert-info">No departments have been shared with you yet. Your manager can grant this from Settings &rarr; Department Data Sharing.</div>
<?php else: ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php foreach ($sharedDepartments as $d): ?>
    <a href="<?= base_url('employee/shared-data.php?department_id=' . $d['id']) ?>" class="badge rounded-pill text-decoration-none px-3 py-2 <?= $viewDeptId==$d['id'] ? 'bg-primary' : 'bg-secondary-subtle text-secondary' ?>"><?= out($d['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($viewDept && $quality): ?>
<div class="row g-3 mb-3">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-award"></i></div><div class="stat-value"><?= $quality['quality_score'] ?>% <?= rag_badge($quality['rag']) ?></div><div class="stat-label">Quality Score</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-percent"></i></div><div class="stat-value"><?= $quality['defect_rate'] ?>%</div><div class="stat-label">Defect Rate</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value"><?= $quality['open_issues'] ?></div><div class="stat-label">Open Issues</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-person-check"></i></div><div class="stat-value"><?= $quality['compliance'] ?>%</div><div class="stat-label">Inspection Compliance</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Recent Quality Issues - <?= out($viewDept['name']) ?></h3></div>
      <?php foreach ($issues as $i): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div><div class="small fw-semibold"><?= out($i['issue_number']) ?></div><div class="small text-muted text-truncate" style="max-width:220px;"><?= out($i['description']) ?></div></div>
          <?= severity_badge($i['severity']) ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$issues): ?><p class="small text-muted mb-0 mt-2">No issues recorded for this department.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Recent Submissions - <?= out($viewDept['name']) ?></h3></div>
      <?php foreach ($submissions as $s): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div><div class="small fw-semibold"><?= out($s['tool_name']) ?></div><div class="small text-muted"><?= out($s['user_name']) ?> - <?= time_ago($s['created_at']) ?></div></div>
          <?= status_badge($s['status']) ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$submissions): ?><p class="small text-muted mb-0 mt-2">No submissions recorded for this department.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php elseif ($viewDept): ?>
  <p class="text-muted">No department data available yet.</p>
<?php elseif ($viewDeptId): ?>
  <div class="alert alert-warning">That department hasn't been shared with you.</div>
<?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
