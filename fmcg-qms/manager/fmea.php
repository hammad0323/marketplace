<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $id = db_exec("INSERT INTO fmea (company_id,title,fmea_type,product_id,department_id,created_by,status,created_at) VALUES (?,?,?,?,?,?, 'active',NOW())",
        [$cid, post('title'), post('fmea_type','process'), post_int('product_id') ?: null, post_int('department_id') ?: null, current_user_id()]);
    log_activity($cid, current_user_id(), 'create', 'fmea', $id, 'Created FMEA ' . post('title'));
    redirect(base_url('manager/fmea-view.php?id=' . $id));
}

$studies = db_all(
    "SELECT f.*, p.name AS product_name, (SELECT COUNT(*) FROM fmea_items fi WHERE fi.fmea_id=f.id) AS item_count,
     (SELECT MAX(rpn) FROM fmea_items fi WHERE fi.fmea_id=f.id) AS max_rpn
     FROM fmea f LEFT JOIN products p ON p.id=f.product_id WHERE f.company_id=? ORDER BY f.created_at DESC", [$cid]
);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'FMEA';
$activeMenu = 'fmea';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">FMEA - Failure Mode & Effects Analysis</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fmeaModal"><i class="bi bi-plus-lg"></i> New FMEA</button>
</div>
<div class="row g-3">
  <?php foreach ($studies as $s): $risk = $s['max_rpn'] ? rpn_risk_level($s['max_rpn']) : null; ?>
  <div class="col-md-4">
    <a href="<?= base_url('manager/fmea-view.php?id=' . $s['id']) ?>" class="text-decoration-none text-dark">
      <div class="qc-card h-100">
        <div class="d-flex justify-content-between"><h3 class="mb-1"><?= out($s['title']) ?></h3><span class="badge bg-secondary-subtle text-secondary text-uppercase"><?= out($s['fmea_type']) ?></span></div>
        <div class="small text-muted mb-2"><?= out($s['product_name'] ?: 'General') ?></div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-muted"><?= (int)$s['item_count'] ?> failure modes</span>
          <?php if ($s['max_rpn']): ?><span class="badge <?= $risk==='critical'||$risk==='high' ? 'bg-danger' : 'bg-warning' ?>">Max RPN <?= $s['max_rpn'] ?></span><?php endif; ?>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if (!$studies): ?><p class="text-muted">No FMEA studies yet.</p><?php endif; ?>
</div>

<div class="modal fade" id="fmeaModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New FMEA</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label small">Title *</label><input class="form-control" name="title" required></div>
      <div class="mb-2"><label class="form-label small">Type</label><select class="form-select" name="fmea_type"><option value="process">Process FMEA</option><option value="design">Design FMEA</option></select></div>
      <div class="mb-2"><label class="form-label small">Product</label><select class="form-select" name="product_id"><option value="">--</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-1"><label class="form-label small">Department</label><select class="form-select" name="department_id"><option value="">--</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Create FMEA</button></div>
  </form>
</div></div></div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
