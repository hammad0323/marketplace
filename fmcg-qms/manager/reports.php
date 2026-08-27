<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$reportTypes = report_type_labels();
$type = get_param('type', 'quality_issues');
if (!isset($reportTypes[$type])) $type = 'quality_issues';
$dateFrom = get_param('date_from', date('Y-m-d', strtotime('-30 days')));
$dateTo = get_param('date_to', date('Y-m-d'));

$rows = report_rows($type, $cid, $dateFrom, $dateTo);
$columns = $rows ? array_keys($rows[0]) : [];

$pageTitle = 'Reports';
$activeMenu = 'reports';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Quality Reports</h4></div>

<form method="GET" class="qc-card mb-3 row g-2 align-items-end">
  <div class="col-md-4"><label class="form-label small">Report Type</label>
    <select class="form-select form-select-sm" name="type">
      <?php foreach ($reportTypes as $k => $v): ?><option value="<?= $k ?>" <?= $type==$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-3"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= out($dateFrom) ?>"></div>
  <div class="col-md-3"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= out($dateTo) ?>"></div>
  <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">Run</button></div>
  <div class="col-md-1"><a class="btn btn-soft-primary btn-sm w-100" href="<?= base_url('ajax/manager/report-export.php?' . http_build_query(['type'=>$type,'date_from'=>$dateFrom,'date_to'=>$dateTo])) ?>"><i class="bi bi-download"></i></a></div>
</form>

<div class="qc-card">
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><?php foreach ($columns as $c): ?><th class="text-capitalize"><?= out(str_replace('_',' ',$c)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><?php foreach ($r as $v): ?><td class="small"><?= out((string)$v) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No data for this report/date range.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="small text-muted mb-0"><?= count($rows) ?> record(s). Use the download icon above to export as CSV; use your browser's Print &rarr; Save as PDF for a PDF copy.</p>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
