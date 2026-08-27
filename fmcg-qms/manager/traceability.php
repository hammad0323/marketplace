<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$batchNumber = get_param('batch', '');
$batch = null;
if ($batchNumber !== '') {
    $batch = db_one(
        "SELECT b.*, p.name AS product_name, pl.name AS line_name, s.name AS shift_name, sup.name AS supplier_name
         FROM batches b LEFT JOIN products p ON p.id=b.product_id LEFT JOIN production_lines pl ON pl.id=b.production_line_id
         LEFT JOIN shifts s ON s.id=b.shift_id LEFT JOIN suppliers sup ON sup.id=b.supplier_id
         WHERE b.company_id=? AND b.batch_number=?", [$cid, $batchNumber]
    );
}

$issues = $ncrList = $capaList = $complaints = $ccpRecords = $submissions = [];
if ($batch) {
    $issues = db_all("SELECT * FROM quality_issues WHERE company_id=? AND batch_id=? ORDER BY created_at DESC", [$cid, $batch['id']]);
    $ncrList = db_all("SELECT * FROM ncr WHERE company_id=? AND batch_id=? ORDER BY created_at DESC", [$cid, $batch['id']]);
    $complaints = db_all("SELECT * FROM customer_complaints WHERE company_id=? AND batch_id=? ORDER BY created_at DESC", [$cid, $batch['id']]);
    $ccpRecords = db_all("SELECT * FROM ccp_monitoring WHERE company_id=? AND batch_id=? ORDER BY created_at DESC", [$cid, $batch['id']]);
    $submissions = db_all(
        "SELECT ts.*, t.name AS tool_name, u.name AS user_name FROM tool_submissions ts JOIN tools t ON t.id=ts.tool_id JOIN users u ON u.id=ts.user_id
         WHERE ts.company_id=? AND ts.batch_id=? ORDER BY ts.created_at DESC", [$cid, $batch['id']]
    );
    if ($issues) {
        $issueIds = array_column($issues, 'id');
        $placeholders = implode(',', array_fill(0, count($issueIds), '?'));
        $capaList = db_all("SELECT * FROM capa WHERE company_id=? AND source_type='issue' AND source_id IN ($placeholders)", [$cid, ...$issueIds]);
    }
}

$pageTitle = 'Traceability';
$activeMenu = 'traceability';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Batch Traceability</h4><p class="text-muted mb-0 small">Search any batch to see raw material, production, quality and customer history end-to-end</p></div>

<form method="GET" class="qc-card mb-3 d-flex gap-2">
  <input type="text" name="batch" class="form-control" placeholder="Enter batch number, e.g. B-YOG-0912" value="<?= out($batchNumber) ?>">
  <button class="btn btn-primary"><i class="bi bi-search"></i> Trace</button>
</form>

<?php if ($batchNumber !== '' && !$batch): ?>
  <div class="alert alert-warning">No batch found matching "<?= out($batchNumber) ?>".</div>
<?php elseif ($batch): ?>
  <div class="qc-card mb-3">
    <div class="row g-3">
      <div class="col-md-3"><div class="small text-muted">Batch</div><div class="fw-bold"><?= out($batch['batch_number']) ?></div></div>
      <div class="col-md-3"><div class="small text-muted">Product</div><div class="fw-semibold"><?= out($batch['product_name']) ?></div></div>
      <div class="col-md-2"><div class="small text-muted">Production Date</div><div><?= fmt_date($batch['production_date']) ?></div></div>
      <div class="col-md-2"><div class="small text-muted">Expiry</div><div><?= fmt_date($batch['expiry_date']) ?></div></div>
      <div class="col-md-2"><div class="small text-muted">Status</div><div><?= status_badge($batch['status']) ?></div></div>
      <div class="col-md-3"><div class="small text-muted">Production Line / Shift</div><div><?= out($batch['line_name']) ?> / <?= out($batch['shift_name']) ?></div></div>
      <div class="col-md-3"><div class="small text-muted">Raw Material Supplier</div><div><?= out($batch['supplier_name'] ?: '-') ?></div></div>
      <div class="col-md-6"><div class="small text-muted">Quantity Produced</div><div><?= fmt_number($batch['quantity_produced'],0) ?></div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3>Quality Submissions (<?= count($submissions) ?>)</h3></div>
        <?php foreach ($submissions as $s): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= out($s['tool_name']) ?> - <?= out($s['user_name']) ?></span><?= status_badge($s['status']) ?></div><?php endforeach; ?>
        <?php if (!$submissions): ?><p class="small text-muted mb-0">None recorded.</p><?php endif; ?>
      </div>
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3>CCP Monitoring (<?= count($ccpRecords) ?>)</h3></div>
        <?php foreach ($ccpRecords as $c): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= out($c['ccp_name']) ?>: <?= out($c['actual_reading']) ?></span><?= status_badge($c['result']) ?></div><?php endforeach; ?>
        <?php if (!$ccpRecords): ?><p class="small text-muted mb-0">None recorded.</p><?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3>Quality Issues / NCR / CAPA</h3></div>
        <?php foreach ($issues as $i): ?><a href="<?= base_url('manager/issue-view.php?id=' . $i['id']) ?>" class="d-flex justify-content-between small border-bottom py-1 text-decoration-none text-dark"><span><?= out($i['issue_number']) ?></span><?= severity_badge($i['severity']) ?></a><?php endforeach; ?>
        <?php foreach ($ncrList as $n): ?><a href="<?= base_url('manager/ncr-view.php?id=' . $n['id']) ?>" class="d-flex justify-content-between small border-bottom py-1 text-decoration-none text-dark"><span><?= out($n['ncr_number']) ?></span><?= status_badge($n['status']) ?></a><?php endforeach; ?>
        <?php foreach ($capaList as $c): ?><a href="<?= base_url('manager/capa-view.php?id=' . $c['id']) ?>" class="d-flex justify-content-between small border-bottom py-1 text-decoration-none text-dark"><span><?= out($c['capa_number']) ?></span><?= status_badge($c['status']) ?></a><?php endforeach; ?>
        <?php if (!$issues && !$ncrList && !$capaList): ?><p class="small text-muted mb-0">No quality problems recorded for this batch.</p><?php endif; ?>
      </div>
      <div class="qc-card">
        <div class="qc-card-header"><h3>Customer Complaints (<?= count($complaints) ?>)</h3></div>
        <?php foreach ($complaints as $c): ?><div class="d-flex justify-content-between small border-bottom py-1"><span><?= out($c['complaint_number']) ?> - <?= out($c['customer_name']) ?></span><?= status_badge($c['status']) ?></div><?php endforeach; ?>
        <?php if (!$complaints): ?><p class="small text-muted mb-0">None recorded.</p><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
