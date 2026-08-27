<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$categories = db_all("SELECT tc.*, (SELECT COUNT(*) FROM tools t WHERE t.category_id=tc.id) AS tool_count FROM tool_categories tc ORDER BY sort_order", []);

$pageTitle = 'Tool Categories';
$activeMenu = 'tool-categories';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Quality Tool Categories</h4><p class="text-muted mb-0 small">Group tools in the Dynamic Tool Engine</p></div>
  <button class="btn btn-primary btn-sm" onclick="openCatModal()"><i class="bi bi-plus-lg"></i> New Category</button>
</div>

<div class="qc-card">
  <table id="catTable" class="table table-hover align-middle">
    <thead><tr><th>Icon</th><th>Name</th><th>Slug</th><th>Tools</th><th>Order</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td><i class="bi <?= out($c['icon']) ?> fs-5 text-primary"></i></td>
        <td class="fw-semibold"><?= out($c['name']) ?></td>
        <td class="text-muted small"><?= out($c['slug']) ?></td>
        <td><span class="badge bg-secondary-subtle text-secondary"><?= (int)$c['tool_count'] ?> tools</span></td>
        <td><?= (int)$c['sort_order'] ?></td>
        <td class="text-end"><button class="btn btn-sm btn-light border" onclick='openCatModal(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="catForm">
    <div class="modal-header"><h5 class="modal-title">Tool Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="c_id">
      <div class="mb-2"><label class="form-label small fw-semibold">Name</label><input class="form-control" name="name" id="c_name" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Icon (Bootstrap Icons class)</label><input class="form-control" name="icon" id="c_icon" placeholder="bi-tools"></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Sort Order</label><input type="number" class="form-control" name="sort_order" id="c_order" value="0"></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<?php
$extraScripts = '<script>
$(function(){ $("#catTable").DataTable({ order: [], pageLength: 25 }); });
function openCatModal(c){
  c = c || {};
  document.getElementById("c_id").value = c.id || "";
  document.getElementById("c_name").value = c.name || "";
  document.getElementById("c_icon").value = c.icon || "bi-tools";
  document.getElementById("c_order").value = c.sort_order || 0;
  new bootstrap.Modal(document.getElementById("catModal")).show();
}
$("#catForm").on("submit", function(e){
  e.preventDefault();
  $.post(QMS.baseUrl + "/ajax/admin/category-actions.php", $(this).serialize() + "&csrf_token=" + QMS.csrfToken)
    .done(function(res){ if(res.success){ QMS.toast("success","Category saved"); location.reload(); } else { QMS.toast("error", res.message||"Failed"); } });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
