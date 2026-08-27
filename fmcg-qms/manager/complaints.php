<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action', 'create');
    if ($action === 'create') {
        $number = generate_sequenced_number($cid, 'customer_complaints', 'complaint_number', 'CMP');
        $complaintId = db_exec("INSERT INTO customer_complaints (company_id,complaint_number,customer_name,product_id,batch_id,complaint_type,severity,description,status,created_at)
                 VALUES (?,?,?,?,?,?,?,?,'open',NOW())",
            [$cid, $number, post('customer_name'), post_int('product_id') ?: null, post_int('batch_id') ?: null, post('complaint_type'), post('severity','medium'), post('description')]);
        notify_company_managers($cid, 'complaint', 'New Complaint: ' . $number, post('description'), base_url('manager/complaints.php'), post('severity')==='critical' ? 'danger' : 'warning');
        log_activity($cid, current_user_id(), 'create', 'complaint', $complaintId, "Logged complaint $number");
        flash_set('success', 'Complaint logged.');
    } elseif ($action === 'update') {
        $id = post_int('id');
        $status = post('status');
        $sql = "UPDATE customer_complaints SET investigation=?, root_cause=?, action_taken=?, status=?" . ($status === 'closed' ? ', closed_at=NOW()' : '') . " WHERE id=? AND company_id=?";
        db_exec($sql, [post('investigation'), post('root_cause'), post('action_taken'), $status, $id, $cid]);
        flash_set('success', 'Complaint updated.');
    }
    redirect(base_url('manager/complaints.php'));
}

$complaints = db_all("SELECT cc.*, p.name AS product_name FROM customer_complaints cc LEFT JOIN products p ON p.id=cc.product_id WHERE cc.company_id=? ORDER BY cc.created_at DESC", [$cid]);
$products = db_all("SELECT id, name FROM products WHERE company_id=? ORDER BY name", [$cid]);
$batches = db_all("SELECT id, batch_number FROM batches WHERE company_id=? ORDER BY created_at DESC LIMIT 100", [$cid]);

$pageTitle = 'Complaints';
$activeMenu = 'complaints';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Customer Complaints</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cmpModal"><i class="bi bi-plus-lg"></i> Log Complaint</button>
</div>
<div class="qc-card">
  <table id="cmpTable" class="table table-hover align-middle">
    <thead><tr><th>Complaint #</th><th>Customer</th><th>Product</th><th>Type</th><th>Severity</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($complaints as $c): ?>
      <tr>
        <td class="fw-semibold small"><?= out($c['complaint_number']) ?></td>
        <td class="small"><?= out($c['customer_name']) ?></td>
        <td class="small"><?= out($c['product_name']) ?></td>
        <td class="small"><?= out($c['complaint_type']) ?></td>
        <td><?= severity_badge($c['severity']) ?></td>
        <td><?= status_badge($c['status']) ?></td>
        <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#viewCmp<?= $c['id'] ?>"><i class="bi bi-eye"></i></button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$complaints): ?><tr><td colspan="7" class="text-center text-muted py-4">No complaints logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foreach ($complaints as $c): ?>
<div class="modal fade" id="viewCmp<?= $c['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title"><?= out($c['complaint_number']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="update"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <p class="small"><?= out($c['description']) ?></p>
      <div class="mb-2"><label class="form-label small">Investigation</label><textarea class="form-control form-control-sm" name="investigation" rows="2"><?= out($c['investigation']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small">Root Cause</label><textarea class="form-control form-control-sm" name="root_cause" rows="2"><?= out($c['root_cause']) ?></textarea></div>
      <div class="mb-2"><label class="form-label small">Action Taken</label><textarea class="form-control form-control-sm" name="action_taken" rows="2"><?= out($c['action_taken']) ?></textarea></div>
      <div class="mb-1"><label class="form-label small">Status</label>
        <select class="form-select form-select-sm" name="status">
          <?php foreach (['open','investigation','closed'] as $s): ?><option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Save</button></div>
  </form>
</div></div></div>
<?php endforeach; ?>

<div class="modal fade" id="cmpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">Log Complaint</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="create">
      <div class="mb-2"><label class="form-label small">Customer Name</label><input class="form-control form-control-sm" name="customer_name"></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small">Product</label><select class="form-select form-select-sm" name="product_id"><option value="">--</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= out($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small">Batch</label><select class="form-select form-select-sm" name="batch_id"><option value="">--</option><?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>"><?= out($b['batch_number']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small">Type</label><input class="form-control form-control-sm" name="complaint_type" placeholder="Packaging, Quality, Foreign Material..."></div>
        <div class="col-6"><label class="form-label small">Severity</label><select class="form-select form-select-sm" name="severity"><?php foreach (['critical','high','medium','low'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-1"><label class="form-label small">Description *</label><textarea class="form-control form-control-sm" name="description" rows="3" required></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Save Complaint</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = '<script>$(function(){ $("#cmpTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
