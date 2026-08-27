<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$filters = ['status' => get_param('status') ?: null, 'severity' => get_param('severity') ?: null];
$ncrList = get_ncr_list($cid, $filters, 100);

$pageTitle = 'NCR';
$activeMenu = 'ncr';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Non-Conformance Reports</h4></div>
  <a href="<?= base_url('manager/ncr-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New NCR</a>
</div>
<div class="qc-card">
  <table id="ncrTable" class="table table-hover align-middle">
    <thead><tr><th>NCR #</th><th>Product/Batch</th><th>Department</th><th>Process</th><th>Severity</th><th>Status</th><th>Due</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($ncrList as $n): ?>
      <tr>
        <td class="fw-semibold small"><?= out($n['ncr_number']) ?></td>
        <td class="small"><?= out($n['product_name']) ?> <?= $n['batch_number'] ? '/ ' . out($n['batch_number']) : '' ?></td>
        <td class="small"><?= out($n['department_name']) ?></td>
        <td class="small"><?= out($n['process']) ?></td>
        <td><?= severity_badge($n['severity']) ?></td>
        <td><?= status_badge($n['status']) ?></td>
        <td class="small <?= (strtotime($n['due_date']) < time() && $n['status'] !== 'closed') ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= fmt_date($n['due_date']) ?></td>
        <td class="text-end"><a href="<?= base_url('manager/ncr-view.php?id=' . $n['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-right"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$ncrList): ?><tr><td colspan="8" class="text-center text-muted py-4">No NCRs recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>$(function(){ $("#ncrTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
