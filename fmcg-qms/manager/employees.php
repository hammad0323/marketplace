<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$employees = db_all(
    "SELECT u.*, d.name AS department_name, s.name AS shift_name,
     (SELECT COUNT(*) FROM tool_assignments ta WHERE ta.user_id=u.id AND ta.status='active') AS tool_count
     FROM users u LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN shifts s ON s.id=u.shift_id
     WHERE u.company_id=? AND u.role='employee' ORDER BY u.created_at DESC", [$cid]
);
$limit = check_company_limit($cid, 'employee_limit', 'users', "role='employee'");

$pageTitle = 'Employees';
$activeMenu = 'employees';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Employees</h4><p class="text-muted mb-0 small"><?= $limit['current'] ?> of <?= $limit['limit'] ?: '∞' ?> used</p></div>
  <a href="<?= base_url('manager/employee-form.php') ?>" class="btn btn-primary btn-sm <?= !$limit['allowed'] ? 'disabled' : '' ?>"><i class="bi bi-plus-lg"></i> New Employee</a>
</div>

<div class="qc-card">
  <table id="empTable" class="table table-hover align-middle">
    <thead><tr><th>Employee</th><th>Code</th><th>Department</th><th>Designation</th><th>Shift</th><th>Tools</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($employees as $e): ?>
      <tr>
        <td><div class="fw-semibold"><?= out($e['name']) ?></div><div class="small text-muted"><?= out($e['email']) ?></div></td>
        <td class="small"><?= out($e['employee_code']) ?></td>
        <td class="small"><?= out($e['department_name']) ?></td>
        <td class="small"><?= out($e['designation']) ?></td>
        <td class="small"><?= out($e['shift_name']) ?></td>
        <td><span class="badge bg-secondary-subtle text-secondary"><?= (int)$e['tool_count'] ?></span></td>
        <td><?= status_badge($e['status']) ?></td>
        <td class="text-end">
          <a href="<?= base_url('manager/employee-form.php?id=' . $e['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
          <a href="<?= base_url('manager/tool-assignment.php?user_id=' . $e['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-tools"></i></a>
          <button class="btn btn-sm btn-light border toggle-emp" data-id="<?= $e['id'] ?>" data-status="<?= $e['status']==='active'?'inactive':'active' ?>"><i class="bi bi-power"></i></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$extraScripts = '<script>
$(function(){ $("#empTable").DataTable({ order: [], pageLength: 20 }); });
$(document).on("click",".toggle-emp", function(){
  var id=$(this).data("id"), status=$(this).data("status");
  QMS.confirmAction({ title: status==="inactive" ? "Disable this employee?" : "Enable this employee?", text:"" }, function(){
    $.post(QMS.baseUrl + "/ajax/manager/employee-actions", { action:"toggle_status", id:id, status:status, csrf_token: QMS.csrfToken })
      .done(function(res){ if(res.success){ location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
  });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
