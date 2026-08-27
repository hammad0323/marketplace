<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$filters = [
    'tool_id' => get_int('tool_id') ?: null, 'status' => get_param('status') ?: null,
    'department_id' => get_int('department_id') ?: null, 'date_from' => get_param('date_from') ?: null, 'date_to' => get_param('date_to') ?: null,
];
$page = max(1, get_int('page', 1));
$perPage = 25;
$submissions = get_tool_submissions($cid, $filters, $perPage, ($page - 1) * $perPage);
$tools = db_all("SELECT id, name FROM tools WHERE company_id IS NULL OR company_id=? ORDER BY name", [$cid]);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'Inspections & Submissions';
$activeMenu = 'submissions';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Inspections &amp; Submissions</h4><p class="text-muted mb-0 small">Every quality tool submission across your company</p></div>

<form method="GET" class="qc-card mb-3 row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label small">Tool</label>
    <select class="form-select form-select-sm" name="tool_id"><option value="">All Tools</option>
      <?php foreach ($tools as $t): ?><option value="<?= $t['id'] ?>" <?= $filters['tool_id']==$t['id']?'selected':'' ?>><?= out($t['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><label class="form-label small">Department</label>
    <select class="form-select form-select-sm" name="department_id"><option value="">All</option>
      <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $filters['department_id']==$d['id']?'selected':'' ?>><?= out($d['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><label class="form-label small">Status</label>
    <select class="form-select form-select-sm" name="status"><option value="">All</option>
      <?php foreach (['submitted','missed','pending'] as $s): ?><option value="<?= $s ?>" <?= $filters['status']==$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= out($filters['date_from']) ?>"></div>
  <div class="col-md-2"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= out($filters['date_to']) ?>"></div>
  <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>

<div class="qc-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Date</th><th>Tool</th><th>Employee</th><th>Department</th><th>Status</th><th>Deviation</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td class="small"><?= fmt_datetime($s['created_at']) ?></td>
          <td class="fw-semibold small"><?= out($s['tool_name']) ?></td>
          <td class="small"><?= out($s['user_name']) ?></td>
          <td class="small"><?= out($s['department_name']) ?></td>
          <td><?= status_badge($s['status']) ?></td>
          <td><?= $s['has_deviation'] ? severity_badge($s['deviation_severity']) : '<span class="text-muted small">-</span>' ?></td>
          <td class="text-end"><a href="<?= base_url('manager/submission-view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$submissions): ?><tr><td colspan="7" class="text-center text-muted py-4">No submissions match these filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
