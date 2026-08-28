<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$departments = db_all(
    "SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id) AS employee_count FROM departments d WHERE d.company_id=? ORDER BY d.name",
    [$cid]
);
$limit = check_company_limit($cid, 'department_limit', 'departments');
$suggested = ['Production','Quality Assurance','Quality Control','Food Safety','Laboratory','Warehouse','Supply Chain','Procurement','Maintenance','Engineering','Packaging','R&D','Production Planning','Dispatch','Customer Service'];

$pageTitle = 'Departments';
$activeMenu = 'departments';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Departments</h4><p class="text-muted mb-0 small"><?= $limit['current'] ?> of <?= $limit['limit'] ?: '∞' ?> used</p></div>
  <button class="btn btn-primary btn-sm" onclick="openDeptModal()" <?= !$limit['allowed'] ? 'disabled' : '' ?>><i class="bi bi-plus-lg"></i> New Department</button>
</div>

<div class="row g-3">
  <?php foreach ($departments as $d): ?>
  <div class="col-md-4">
    <div class="qc-card h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div><h3 class="mb-1"><?= out($d['name']) ?></h3><p class="small text-muted mb-2"><?= out($d['description']) ?></p></div>
        <?= status_badge($d['status']) ?>
      </div>
      <div class="small text-muted"><i class="bi bi-people"></i> <?= (int)$d['employee_count'] ?> employees</div>
      <button class="btn btn-sm btn-soft-primary mt-3" onclick='openDeptModal(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i> Edit</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php if (!$departments): ?><p class="text-muted mt-3">No departments yet. Common departments: <?= out(implode(', ', $suggested)) ?></p><?php endif; ?>

<div class="modal fade" id="deptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="deptForm">
    <div class="modal-header"><h5 class="modal-title">Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="d_id">
      <div class="mb-2"><label class="form-label small fw-semibold">Name</label>
        <input class="form-control" name="name" id="d_name" list="deptSuggestions" required>
        <datalist id="deptSuggestions"><?php foreach ($suggested as $s): ?><option value="<?= out($s) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="mb-2"><label class="form-label small fw-semibold">Description</label><textarea class="form-control" name="description" id="d_desc" rows="2"></textarea></div>
      <div class="mb-1"><label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status" id="d_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<?php
$extraScripts = '<script>
function openDeptModal(d){
  d = d || {};
  document.getElementById("d_id").value = d.id || "";
  document.getElementById("d_name").value = d.name || "";
  document.getElementById("d_desc").value = d.description || "";
  document.getElementById("d_status").value = d.status || "active";
  new bootstrap.Modal(document.getElementById("deptModal")).show();
}
$("#deptForm").on("submit", function(e){
  e.preventDefault();
  $.post(QMS.baseUrl + "/ajax/manager/department-actions", $(this).serialize() + "&csrf_token=" + QMS.csrfToken)
    .done(function(res){ if(res.success){ QMS.toast("success","Department saved"); location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
