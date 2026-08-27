<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $id = db_exec("INSERT INTO haccp_plans (company_id,product_id,title,team,scope,status,created_at) VALUES (?,?,?,?,?, 'active',NOW())",
        [$cid, post_int('product_id') ?: null, post('title'), post('team'), post('scope')]);
    log_activity($cid, current_user_id(), 'create', 'haccp_plan', $id, 'Created HACCP plan ' . post('title'));
    redirect(base_url('manager/haccp-view.php?id=' . $id));
}

$plans = db_all(
    "SELECT h.*, p.name AS product_name, (SELECT COUNT(*) FROM haccp_hazards hh WHERE hh.haccp_plan_id=h.id AND hh.is_ccp=1) AS ccp_count
     FROM haccp_plans h LEFT JOIN products p ON p.id=h.product_id WHERE h.company_id=? ORDER BY h.created_at DESC", [$cid]
);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'HACCP';
$activeMenu = 'haccp';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">HACCP Plans</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#haccpModal"><i class="bi bi-plus-lg"></i> New Plan</button>
</div>
<div class="row g-3">
  <?php foreach ($plans as $p): ?>
  <div class="col-md-4">
    <a href="<?= base_url('manager/haccp-view.php?id=' . $p['id']) ?>" class="text-decoration-none text-dark">
      <div class="qc-card h-100">
        <h3 class="mb-1"><?= out($p['title']) ?></h3>
        <div class="small text-muted mb-2"><?= out($p['product_name'] ?: 'General') ?></div>
        <span class="badge bg-primary-subtle text-primary"><?= (int)$p['ccp_count'] ?> Critical Control Points</span>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if (!$plans): ?><p class="text-muted">No HACCP plans yet.</p><?php endif; ?>
</div>

<div class="modal fade" id="haccpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New HACCP Plan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label small">Title *</label><input class="form-control" name="title" required></div>
      <div class="mb-2"><label class="form-label small">Product</label><select class="form-select" name="product_id"><option value="">--</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label small">HACCP Team</label><input class="form-control" name="team" placeholder="Names of team members"></div>
      <div class="mb-1"><label class="form-label small">Scope</label><textarea class="form-control" name="scope" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Create Plan</button></div>
  </form>
</div></div></div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
