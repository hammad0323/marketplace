<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

$issues = db_all(
    "SELECT qi.*, d.name AS department_name FROM quality_issues qi LEFT JOIN departments d ON d.id=qi.department_id
     WHERE qi.company_id=? AND (qi.assigned_to=? OR qi.detected_by=?) ORDER BY qi.created_at DESC", [$cid, $uid, $uid]
);

$pageTitle = 'My Issues';
$activeMenu = 'my-issues';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">My Issues</h4></div>
<div class="qc-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Issue #</th><th>Department</th><th>Defect</th><th>Severity</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($issues as $i): ?>
      <tr><td class="fw-semibold small"><?= out($i['issue_number']) ?></td><td class="small"><?= out($i['department_name']) ?></td>
        <td class="small"><?= out($i['defect_type']) ?></td><td><?= severity_badge($i['severity']) ?></td>
        <td><?= status_badge($i['status']) ?></td><td class="small text-muted"><?= time_ago($i['created_at']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$issues): ?><tr><td colspan="6" class="text-center text-muted py-4">No issues linked to you.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
