<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

$filters = ['user_id' => $uid, 'tool_id' => get_int('tool_id') ?: null, 'status' => get_param('status') ?: null];
$submissions = get_tool_submissions($cid, $filters, 100);
$myTools = db_all(
    "SELECT DISTINCT t.id, t.name FROM tool_assignments ta JOIN tools t ON t.id = ta.tool_id WHERE ta.user_id = ? ORDER BY t.name",
    [$uid]
);

$pageTitle = 'My Submissions';
$activeMenu = 'my-submissions';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">My Submissions</h4><p class="text-muted mb-0 small">Everything you've submitted through your assigned quality tools</p></div>

<form method="GET" class="qc-card mb-3 row g-2 align-items-end">
  <div class="col-md-4"><label class="form-label small">Tool</label>
    <select class="form-select form-select-sm" name="tool_id"><option value="">All Tools</option>
      <?php foreach ($myTools as $t): ?><option value="<?= $t['id'] ?>" <?= $filters['tool_id']==$t['id']?'selected':'' ?>><?= out($t['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-3"><label class="form-label small">Status</label>
    <select class="form-select form-select-sm" name="status"><option value="">All</option>
      <?php foreach (['submitted','missed'] as $s): ?><option value="<?= $s ?>" <?= $filters['status']==$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>

<div class="qc-card">
  <table id="mySubTable" class="table table-hover align-middle">
    <thead><tr><th>Date</th><th>Tool</th><th>Status</th><th>Deviation</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($submissions as $s): ?>
      <tr>
        <td class="small"><?= fmt_datetime($s['created_at']) ?></td>
        <td class="fw-semibold small"><?= out($s['tool_name']) ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td><?= $s['has_deviation'] ? severity_badge($s['deviation_severity']) : '<span class="text-muted small">-</span>' ?></td>
        <td class="text-end"><a href="<?= base_url('employee/my-submission-view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$submissions): ?><tr><td colspan="5" class="text-center text-muted py-4">No submissions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>$(function(){ $("#mySubTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
