<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    db_exec("INSERT INTO suppliers (company_id,name,code,contact_person,email,phone,material_category,status) VALUES (?,?,?,?,?,?,?,'active')",
        [$cid, post('name'), post('code') ?: generate_code('SUP',3), post('contact_person'), post('email'), post('phone'), post('material_category')]);
    log_activity($cid, current_user_id(), 'create', 'supplier', null, 'Added supplier ' . post('name'));
    flash_set('success', 'Supplier added.');
    redirect(base_url('manager/suppliers.php'));
}

$suppliers = db_all(
    "SELECT s.*, (SELECT AVG(overall_score) FROM supplier_scorecards ss WHERE ss.supplier_id=s.id) AS avg_score
     FROM suppliers s WHERE s.company_id=? ORDER BY s.name", [$cid]
);

$pageTitle = 'Suppliers';
$activeMenu = 'suppliers';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Suppliers</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supModal"><i class="bi bi-plus-lg"></i> New Supplier</button>
</div>
<div class="row g-3">
  <?php foreach ($suppliers as $s): $score = round((float)($s['avg_score'] ?? 0),1); $rag = $score>=90?'green':($score>=75?'amber':'red'); ?>
  <div class="col-md-4">
    <a href="<?= base_url('manager/supplier-view.php?id=' . $s['id']) ?>" class="text-decoration-none text-dark">
      <div class="qc-card h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div><h3 class="mb-0"><?= out($s['name']) ?></h3><div class="small text-muted"><?= out($s['material_category']) ?></div></div>
          <?= rag_badge($rag) ?>
        </div>
        <div class="fs-4 fw-bold mt-2"><?= $s['avg_score'] !== null ? $score . '%' : 'N/A' ?></div>
        <div class="small text-muted">Avg. Quality Score</div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if (!$suppliers): ?><p class="text-muted">No suppliers yet.</p><?php endif; ?>
</div>

<div class="modal fade" id="supModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label small fw-semibold">Name *</label><input class="form-control" name="name" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Material Category</label><input class="form-control" name="material_category"></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Contact Person</label><input class="form-control" name="contact_person"></div>
      <div class="row g-2"><div class="col-6"><label class="form-label small fw-semibold">Email</label><input type="email" class="form-control" name="email"></div>
      <div class="col-6"><label class="form-label small fw-semibold">Phone</label><input class="form-control" name="phone"></div></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
