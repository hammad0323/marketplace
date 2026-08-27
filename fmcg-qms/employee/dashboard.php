<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

$toolStatus = get_employee_tool_status($uid);
$completed = count(array_filter($toolStatus, fn($t) => in_array($t['status'], ['submitted', 'issue_detected'], true)));
$pending = count(array_filter($toolStatus, fn($t) => $t['status'] === 'pending'));
$missed = count(array_filter($toolStatus, fn($t) => $t['status'] === 'missed'));

$myIssues = db_all("SELECT * FROM quality_issues WHERE company_id=? AND assigned_to=? AND status<>'closed' ORDER BY created_at DESC LIMIT 5", [$cid, $uid]);
$myActions = db_all("SELECT ca.*, c.capa_number FROM capa_actions ca JOIN capa c ON c.id=ca.capa_id WHERE ca.company_id=? AND ca.responsible_user=? AND ca.status<>'done' ORDER BY ca.due_date LIMIT 5", [$cid, $uid]);

$pageTitle = 'My Dashboard';
$activeMenu = 'dashboard';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Hi, <?= out(explode(' ', current_user_name())[0]) ?></h4><p class="text-muted mb-0 small">Here's what's on your plate today</p></div>

<div class="row g-3 mb-4">
  <div class="col-md-4 col-6"><div class="stat-card"><div class="stat-icon" style="background:#F0FDF4;color:#16A34A;"><i class="bi bi-check-circle"></i></div><div class="stat-value"><?= $completed ?></div><div class="stat-label">Completed Today</div></div></div>
  <div class="col-md-4 col-6"><div class="stat-card"><div class="stat-icon" style="background:#FFFBEB;color:#D97706;"><i class="bi bi-hourglass-split"></i></div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending</div></div></div>
  <div class="col-md-4 col-6"><div class="stat-card"><div class="stat-icon" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-x-circle"></i></div><div class="stat-value"><?= $missed ?></div><div class="stat-label">Missed</div></div></div>
</div>

<div class="qc-card mb-4">
  <div class="qc-card-header"><h3>My Quality Tools</h3><a href="<?= base_url('employee/floor-mode.php') ?>" class="small"><i class="bi bi-tablet"></i> Floor Mode</a></div>
  <div class="row g-3">
    <?php foreach ($toolStatus as $t): $a = $t['assignment']; $status = $t['status'];
      $statusClass = ['submitted' => 'status-completed', 'issue_detected' => 'status-issue', 'missed' => 'status-missed'][$status] ?? '';
      $statusLabel = ['submitted' => 'Completed', 'pending' => 'Pending', 'missed' => 'Missed', 'issue_detected' => 'Issue Detected'][$status] ?? $status; ?>
      <div class="col-md-4 col-6">
        <a href="<?= base_url('employee/tool-submit.php?tool_id=' . $a['tool_id']) ?>" class="tool-tile <?= $statusClass ?>">
          <div class="tool-icon"><i class="bi <?= out($a['icon'] ?: 'bi-clipboard-check') ?>"></i></div>
          <h6 class="fw-bold mb-1"><?= out($a['name']) ?></h6>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="badge bg-secondary-subtle text-secondary text-capitalize small"><?= out(str_replace('_',' ',$a['frequency'])) ?></span>
            <?= status_badge($status === 'issue_detected' ? 'issue_detected' : $status) ?>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$toolStatus): ?><p class="text-muted">No tools assigned yet. Contact your manager.</p><?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>My Open Issues</h3><a href="<?= base_url('employee/my-issues.php') ?>" class="small">View all</a></div>
      <?php foreach ($myIssues as $i): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div><div class="small fw-semibold"><?= out($i['issue_number']) ?></div><div class="small text-muted text-truncate" style="max-width:220px;"><?= out($i['description']) ?></div></div>
          <?= severity_badge($i['severity']) ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$myIssues): ?><p class="small text-muted mb-0 mt-2">No open issues assigned to you.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="qc-card">
      <div class="qc-card-header"><h3>My Actions</h3><a href="<?= base_url('employee/my-actions.php') ?>" class="small">View all</a></div>
      <?php foreach ($myActions as $a): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div><div class="small fw-semibold"><?= out($a['capa_number']) ?></div><div class="small text-muted text-truncate" style="max-width:220px;"><?= out($a['action_text']) ?></div></div>
          <span class="small <?= (strtotime($a['due_date']) < time()) ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= fmt_date($a['due_date']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$myActions): ?><p class="small text-muted mb-0 mt-2">No actions assigned to you.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
