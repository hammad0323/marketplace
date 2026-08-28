<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$employees = db_all("SELECT id, name, department_id FROM users WHERE company_id=? AND role='employee' AND status='active' ORDER BY name", [$cid]);
$selectedUserId = get_int('user_id') ?: (int)($employees[0]['id'] ?? 0);
$tools = get_tools_for_company($cid);
$assignedToolIds = array_column(db_all("SELECT tool_id FROM tool_assignments WHERE company_id=? AND user_id=? AND status='active'", [$cid, $selectedUserId]), 'tool_id');
$categories = get_tool_categories();

$pageTitle = 'Tool Assignment';
$activeMenu = 'tools';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Tool Assignment</h4><p class="text-muted mb-0 small">Select an employee, then toggle which quality tools appear on their dashboard.</p></div>

<div class="row g-3">
  <div class="col-lg-3">
    <div class="qc-card p-2">
      <?php foreach ($employees as $e): ?>
        <a href="<?= base_url('manager/tool-assignment.php?user_id=' . $e['id']) ?>"
           class="d-block px-3 py-2 rounded-3 text-decoration-none mb-1 <?= $e['id']==$selectedUserId ? 'bg-primary text-white' : 'text-dark' ?>">
          <?= out($e['name']) ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$employees): ?><p class="text-muted small p-2">No employees yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-9">
    <?php foreach ($categories as $cat):
      $catTools = array_filter($tools, fn($t) => $t['category_id'] == $cat['id']);
      if (!$catTools) continue; ?>
      <div class="qc-card mb-3">
        <div class="qc-card-header"><h3><?= out($cat['name']) ?></h3></div>
        <div class="row g-2">
          <?php foreach ($catTools as $t): $isAssigned = in_array($t['id'], $assignedToolIds); ?>
            <div class="col-md-6">
              <label class="d-flex align-items-center gap-2 border rounded-3 p-2 tool-assign-row" style="cursor:pointer;">
                <input type="checkbox" class="form-check-input assign-toggle" data-tool-id="<?= $t['id'] ?>" <?= $isAssigned ? 'checked' : '' ?> <?= !$selectedUserId ? 'disabled' : '' ?>>
                <i class="bi <?= out($t['icon'] ?: 'bi-clipboard-check') ?> text-primary"></i>
                <span class="small fw-semibold"><?= out($t['name']) ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php
$extraScripts = '<script>
$(document).on("change",".assign-toggle", function(){
  var toolId = $(this).data("tool-id"), checked = $(this).is(":checked");
  $.post(QMS.baseUrl + "/ajax/manager/tool-assignment-actions", {
    action: checked ? "assign" : "unassign", user_id: ' . (int)$selectedUserId . ', tool_id: toolId, csrf_token: QMS.csrfToken
  }).done(function(res){ if(res.success){ QMS.toast("success", checked ? "Tool assigned" : "Tool removed"); } else { QMS.toast("error", res.message||"Failed"); } });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
