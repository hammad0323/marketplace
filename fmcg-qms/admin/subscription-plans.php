<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$plans = db_all("SELECT sp.*, (SELECT COUNT(*) FROM companies c WHERE c.subscription_plan_id=sp.id) AS companies_count FROM subscription_plans sp ORDER BY price_monthly", []);

$pageTitle = 'Subscription Plans';
$activeMenu = 'plans';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Subscription Plans</h4><p class="text-muted mb-0 small">Control what each plan tier allows companies to use</p></div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openPlanModal()"><i class="bi bi-plus-lg"></i> New Plan</button>
</div>

<div class="row g-3">
  <?php foreach ($plans as $p): ?>
  <div class="col-lg-4">
    <div class="qc-card h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div><h3 class="mb-0"><?= out($p['name']) ?></h3><div class="text-muted small"><?= (int)$p['companies_count'] ?> companies</div></div>
        <?= status_badge($p['status']) ?>
      </div>
      <div class="fs-3 fw-bold mb-3">$<?= fmt_number($p['price_monthly'],0) ?> <span class="fs-6 text-muted fw-normal">/month</span></div>
      <ul class="list-unstyled small d-flex flex-column gap-2 mb-3">
        <li><i class="bi bi-people text-primary me-2"></i><?= $p['employee_limit'] ?: 'Unlimited' ?> employees</li>
        <li><i class="bi bi-diagram-3 text-primary me-2"></i><?= $p['department_limit'] ?: 'Unlimited' ?> departments</li>
        <li><i class="bi bi-tools text-primary me-2"></i><?= $p['tool_limit'] ?: 'Unlimited' ?> quality tools</li>
        <li><i class="bi bi-hdd text-primary me-2"></i><?= $p['storage_limit_mb'] ?> MB storage</li>
        <li><i class="bi bi-robot text-primary me-2"></i><?= $p['ai_usage_limit'] ?: 'Unlimited' ?> AI requests/mo</li>
        <li><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i><?= $p['report_limit'] ?: 'Unlimited' ?> reports/mo</li>
      </ul>
      <p class="small text-muted"><?= out($p['features']) ?></p>
      <button class="btn btn-soft-primary btn-sm w-100" onclick='openPlanModal(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i> Edit</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal fade" id="planModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="planForm">
        <div class="modal-header"><h5 class="modal-title">Subscription Plan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="id" id="p_id">
          <div class="mb-2"><label class="form-label small fw-semibold">Name</label><input class="form-control" name="name" id="p_name" required></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Price / month ($)</label><input type="number" step="0.01" class="form-control" name="price_monthly" id="p_price"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Status</label>
              <select class="form-select" name="status" id="p_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4"><label class="form-label small fw-semibold">Employees (0=∞)</label><input type="number" class="form-control" name="employee_limit" id="p_employees"></div>
            <div class="col-4"><label class="form-label small fw-semibold">Departments</label><input type="number" class="form-control" name="department_limit" id="p_departments"></div>
            <div class="col-4"><label class="form-label small fw-semibold">Tools</label><input type="number" class="form-control" name="tool_limit" id="p_tools"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4"><label class="form-label small fw-semibold">Storage (MB)</label><input type="number" class="form-control" name="storage_limit_mb" id="p_storage"></div>
            <div class="col-4"><label class="form-label small fw-semibold">AI Usage/mo</label><input type="number" class="form-control" name="ai_usage_limit" id="p_ai"></div>
            <div class="col-4"><label class="form-label small fw-semibold">Reports/mo</label><input type="number" class="form-control" name="report_limit" id="p_reports"></div>
          </div>
          <div class="mb-1"><label class="form-label small fw-semibold">Features (short description)</label><textarea class="form-control" name="features" id="p_features" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Plan</button></div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
function openPlanModal(p){
  p = p || {};
  document.getElementById("p_id").value = p.id || "";
  document.getElementById("p_name").value = p.name || "";
  document.getElementById("p_price").value = p.price_monthly || 0;
  document.getElementById("p_status").value = p.status || "active";
  document.getElementById("p_employees").value = p.employee_limit ?? 15;
  document.getElementById("p_departments").value = p.department_limit ?? 5;
  document.getElementById("p_tools").value = p.tool_limit ?? 15;
  document.getElementById("p_storage").value = p.storage_limit_mb ?? 500;
  document.getElementById("p_ai").value = p.ai_usage_limit ?? 100;
  document.getElementById("p_reports").value = p.report_limit ?? 50;
  document.getElementById("p_features").value = p.features || "";
  new bootstrap.Modal(document.getElementById("planModal")).show();
}
$("#planForm").on("submit", function(e){
  e.preventDefault();
  $.post(QMS.baseUrl + "/ajax/admin/plan-actions", $(this).serialize() + "&csrf_token=" + QMS.csrfToken)
    .done(function(res){ if(res.success){ QMS.toast("success","Plan saved"); location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
