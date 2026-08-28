<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$companies = db_all(
    "SELECT c.*, sp.name AS plan_name,
     (SELECT COUNT(*) FROM users u WHERE u.company_id=c.id AND u.role='employee') AS employee_count,
     (SELECT COUNT(*) FROM departments d WHERE d.company_id=c.id) AS department_count
     FROM companies c LEFT JOIN subscription_plans sp ON sp.id = c.subscription_plan_id ORDER BY c.created_at DESC", []
);

$pageTitle = 'Companies';
$activeMenu = 'companies';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Companies</h4><p class="text-muted mb-0 small">Manage tenant companies on the platform</p></div>
  <a href="<?= base_url('admin/company-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Company</a>
</div>

<div class="qc-card">
  <div class="table-responsive">
    <table id="companiesTable" class="table table-hover align-middle">
      <thead><tr><th>Company</th><th>Code</th><th>Industry</th><th>Plan</th><th>Employees</th><th>Departments</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($companies as $c): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= out($c['name']) ?></div>
            <div class="small text-muted"><?= out($c['email']) ?></div>
          </td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= out($c['code']) ?></span></td>
          <td class="small"><?= out($c['industry']) ?></td>
          <td class="small"><?= out($c['plan_name'] ?? '-') ?></td>
          <td><?= (int)$c['employee_count'] ?> / <?= (int)$c['employee_limit'] ?: '∞' ?></td>
          <td><?= (int)$c['department_count'] ?> / <?= (int)$c['department_limit'] ?: '∞' ?></td>
          <td class="small"><?= fmt_date($c['expiry_date']) ?></td>
          <td><?= status_badge($c['status']) ?></td>
          <td class="text-end">
            <div class="dropdown">
              <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= base_url('admin/company-view.php?id=' . $c['id']) ?>"><i class="bi bi-eye me-2"></i>View Dashboard</a></li>
                <li><a class="dropdown-item" href="<?= base_url('admin/company-form.php?id=' . $c['id']) ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                <li><a class="dropdown-item toggle-status" href="#" data-id="<?= $c['id'] ?>" data-status="<?= $c['status']==='active'?'inactive':'active' ?>">
                  <i class="bi bi-power me-2"></i><?= $c['status']==='active' ? 'Deactivate' : 'Activate' ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-company" href="#" data-id="<?= $c['id'] ?>"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraScripts = '<script>
$(function(){
  $("#companiesTable").DataTable({ pageLength: 15, order: [] });
  $(document).on("click", ".toggle-status", function(e){
    e.preventDefault();
    var id = $(this).data("id"), status = $(this).data("status");
    QMS.confirmAction({ title: "Change company status?", text: "This will set the company to " + status + "." }, function(){
      $.post(QMS.baseUrl + "/ajax/admin/company-actions", { action:"toggle_status", id:id, status:status, csrf_token: QMS.csrfToken })
        .done(function(res){ if(res.success){ QMS.toast("success","Status updated"); location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
    });
  });
  $(document).on("click", ".delete-company", function(e){
    e.preventDefault();
    var id = $(this).data("id");
    QMS.confirmAction({ title:"Delete this company?", text:"This permanently removes ALL company data. This cannot be undone.", icon:"error", confirmText:"Delete permanently" }, function(){
      $.post(QMS.baseUrl + "/ajax/admin/company-actions", { action:"delete", id:id, csrf_token: QMS.csrfToken })
        .done(function(res){ if(res.success){ QMS.toast("success","Company deleted"); location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
    });
  });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
