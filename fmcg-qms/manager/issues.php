<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$filters = ['severity' => get_param('severity') ?: null, 'status' => get_param('status') ?: null, 'search' => get_param('search') ?: null, 'department_id' => get_int('department_id') ?: null];
$filterDepartment = $filters['department_id'] ? db_one("SELECT name FROM departments WHERE id=? AND company_id=?", [$filters['department_id'], $cid]) : null;
$issues = get_issues($cid, $filters, 100);

$pageTitle = 'Quality Issues';
$activeMenu = 'issues';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Quality Issues<?php if ($filterDepartment): ?> <span class="badge bg-primary-subtle text-primary fs-6">in <?= out($filterDepartment['name']) ?> <a href="<?= base_url('manager/issues.php') ?>" class="text-primary ms-1" title="Clear filter"><i class="bi bi-x-circle"></i></a></span><?php endif; ?></h4><p class="text-muted mb-0 small">Detected &rarr; Assigned &rarr; Investigation &rarr; Containment &rarr; Root Cause &rarr; Corrective/Preventive Action &rarr; Verification &rarr; Closed</p></div>
  <a href="<?= base_url('manager/issue-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Log Issue</a>
</div>

<form method="GET" class="qc-card mb-3 row g-2 align-items-end">
  <div class="col-md-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= out($filters['search']) ?>" placeholder="Issue number or description"></div>
  <div class="col-md-3"><label class="form-label small">Severity</label>
    <select class="form-select form-select-sm" name="severity"><option value="">All</option>
      <?php foreach (['critical','high','medium','low','observation'] as $s): ?><option value="<?= $s ?>" <?= $filters['severity']==$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-3"><label class="form-label small">Status</label>
    <select class="form-select form-select-sm" name="status"><option value="">All</option>
      <?php foreach (ISSUE_WORKFLOW_STEPS as $s): ?><option value="<?= $s ?>" <?= $filters['status']==$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>

<div class="qc-card">
  <table id="issuesTable" class="table table-hover align-middle">
    <thead><tr><th>Issue #</th><th>Department</th><th>Product/Batch</th><th>Defect</th><th>Severity</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($issues as $i): ?>
      <tr>
        <td class="fw-semibold small"><?= out($i['issue_number']) ?></td>
        <td class="small"><?= out($i['department_name']) ?></td>
        <td class="small"><?= out($i['product_name']) ?> <?= $i['batch_number'] ? '/ ' . out($i['batch_number']) : '' ?></td>
        <td class="small"><?= out($i['defect_type']) ?></td>
        <td><?= severity_badge($i['severity']) ?></td>
        <td><?= status_badge($i['status']) ?></td>
        <td class="small text-muted"><?= time_ago($i['created_at']) ?></td>
        <td class="text-end"><a href="<?= base_url('manager/issue-view.php?id=' . $i['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-right"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$issues): ?><tr><td colspan="8" class="text-center text-muted py-4">No issues match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>$(function(){ $("#issuesTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
