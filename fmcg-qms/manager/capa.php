<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$filters = ['status' => get_param('status') ?: null, 'overdue' => get_param('overdue') ?: null];
$capaList = get_capa_list($cid, $filters, 100);

$pageTitle = 'CAPA';
$activeMenu = 'capa';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">CAPA - Corrective &amp; Preventive Actions</h4></div>
  <a href="<?= base_url('manager/capa-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New CAPA</a>
</div>
<div class="mb-3"><a href="?" class="badge rounded-pill text-decoration-none px-3 py-2 <?= !$filters['overdue']?'bg-primary':'bg-secondary-subtle text-secondary' ?>">All</a>
  <a href="?overdue=1" class="badge rounded-pill text-decoration-none px-3 py-2 <?= $filters['overdue']?'bg-danger':'bg-secondary-subtle text-secondary' ?>">Overdue</a></div>
<div class="qc-card">
  <table id="capaTable" class="table table-hover align-middle">
    <thead><tr><th>CAPA #</th><th>Problem</th><th>Responsible</th><th>Status</th><th>Due</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($capaList as $c): ?>
      <tr>
        <td class="fw-semibold small"><?= out($c['capa_number']) ?></td>
        <td class="small text-truncate" style="max-width:320px;"><?= out($c['problem_statement']) ?></td>
        <td class="small"><?= out($c['responsible_name'] ?: '-') ?></td>
        <td><?= status_badge($c['status']) ?></td>
        <td class="small <?= (strtotime($c['due_date']) < time() && !in_array($c['status'],['closed','effective','rejected'])) ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= fmt_date($c['due_date']) ?></td>
        <td class="text-end"><a href="<?= base_url('manager/capa-view.php?id=' . $c['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-right"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$capaList): ?><tr><td colspan="6" class="text-center text-muted py-4">No CAPA records yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>$(function(){ $("#capaTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
