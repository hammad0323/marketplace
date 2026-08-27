<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$tools = db_all(
    "SELECT t.*, tc.name AS category_name, (SELECT COUNT(*) FROM tool_fields f WHERE f.tool_id=t.id) AS field_count,
     (SELECT COUNT(*) FROM tool_assignments ta WHERE ta.tool_id=t.id AND ta.status='active') AS assignment_count
     FROM tools t LEFT JOIN tool_categories tc ON tc.id=t.category_id
     WHERE t.company_id IS NULL ORDER BY tc.sort_order, t.name", []
);

$pageTitle = 'Dynamic Tool Builder';
$activeMenu = 'tools';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Quality Tool Library</h4><p class="text-muted mb-0 small">Global tools available to every company - build new tools without writing code</p></div>
  <a href="<?= base_url('admin/tool-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Tool</a>
</div>

<div class="qc-card">
  <table id="toolsTable" class="table table-hover align-middle">
    <thead><tr><th>Tool</th><th>Category</th><th>Type</th><th>Frequency</th><th>Fields</th><th>Assignments</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tools as $t): ?>
      <tr>
        <td><i class="bi <?= out($t['icon'] ?: 'bi-clipboard-check') ?> text-primary me-2"></i><span class="fw-semibold"><?= out($t['name']) ?></span></td>
        <td class="small"><?= out($t['category_name']) ?></td>
        <td class="small text-capitalize"><?= out($t['tool_type']) ?></td>
        <td class="small text-capitalize"><?= out(str_replace('_',' ',$t['frequency'])) ?></td>
        <td><?= (int)$t['field_count'] ?></td>
        <td><?= (int)$t['assignment_count'] ?></td>
        <td><?= status_badge($t['status']) ?></td>
        <td class="text-end">
          <a href="<?= base_url('admin/tool-form.php?id=' . $t['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
          <button class="btn btn-sm btn-light border toggle-tool" data-id="<?= $t['id'] ?>" data-status="<?= $t['status']==='active'?'inactive':'active' ?>">
            <i class="bi bi-power"></i></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$extraScripts = '<script>
$(function(){ $("#toolsTable").DataTable({ order: [], pageLength: 25 }); });
$(document).on("click",".toggle-tool", function(){
  var id=$(this).data("id"), status=$(this).data("status");
  $.post(QMS.baseUrl + "/ajax/admin/tool-actions.php", { action:"toggle_status", id:id, status:status, csrf_token: QMS.csrfToken })
    .done(function(res){ if(res.success){ location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
