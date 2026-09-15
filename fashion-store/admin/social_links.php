<?php
$pageTitle = 'Social Links';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $platform = trim($_POST['platform'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon_class'] ?? '');
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        if ($id) {
            $stmt = mysqli_prepare($mysqli, "UPDATE social_links SET platform=?, url=?, icon_class=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $platform, $url, $icon, $status, $id);
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO social_links (platform, url, icon_class, status) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $platform, $url, $icon, $status);
        }
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Saved.');
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM social_links WHERE id = " . (int)$_POST['id']);
    }
    redirect('social_links.php');
}

$links = mysqli_query($mysqli, "SELECT * FROM social_links ORDER BY sort_order");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Social Links</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#slModal" onclick="resetSl()"><i class="bi bi-plus-lg"></i> Add</button>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Platform</th><th>URL</th><th>Icon Class</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($l = mysqli_fetch_assoc($links)): ?>
      <tr>
        <td><i class="bi <?= e($l['icon_class']) ?>"></i> <?= e($l['platform']) ?></td>
        <td class="small"><?= e($l['url']) ?></td>
        <td class="small"><?= e($l['icon_class']) ?></td>
        <td><span class="badge <?= $l['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($l['status']) ?></span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editSl(<?= json_encode($l) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>
<div class="modal fade" id="slModal">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="sl_id">
      <div class="modal-header"><h5 class="modal-title">Social Link</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Platform</label><input type="text" name="platform" id="sl_platform" class="form-control mb-3" required>
        <label class="form-label">URL</label><input type="text" name="url" id="sl_url" class="form-control mb-3" required>
        <label class="form-label">Bootstrap Icon Class (e.g. bi-facebook)</label><input type="text" name="icon_class" id="sl_icon" class="form-control mb-3">
        <label class="form-label">Status</label><select name="status" id="sl_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
      </div>
      <div class="modal-footer"><button class="btn btn-primary text-white">Save</button></div>
    </form>
  </div></div>
</div>
<script>
function resetSl(){document.getElementById('sl_id').value='';document.getElementById('sl_platform').value='';document.getElementById('sl_url').value='';document.getElementById('sl_icon').value='';document.getElementById('sl_status').value='active';}
function editSl(l){document.getElementById('sl_id').value=l.id;document.getElementById('sl_platform').value=l.platform;document.getElementById('sl_url').value=l.url;document.getElementById('sl_icon').value=l.icon_class||'';document.getElementById('sl_status').value=l.status;new bootstrap.Modal(document.getElementById('slModal')).show();}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
