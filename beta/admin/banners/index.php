<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    db_exec("DELETE FROM banners WHERE id=?", 'i', [(int)$_POST['id']]);
    flash('success', 'Banner deleted.');
    redirect(admin_url('banners/index.php'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_verify();
    db_exec("UPDATE banners SET status = IF(status='active','inactive','active') WHERE id=?", 'i', [(int)$_POST['id']]);
    redirect(admin_url('banners/index.php'));
}

$banners = db_fetch_all("SELECT * FROM banners ORDER BY sort_order");
$dashRole = 'admin'; $pageTitle = 'Banners'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Banners</h1><a href="<?= admin_url('banners/form.php') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Banner</a></div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Order</th><th>Heading</th><th>Button</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($banners as $b): ?>
      <tr>
        <td><?= $b['sort_order'] ?></td>
        <td><?= clean($b['heading']) ?><br><small class="text-muted"><?= clean($b['sub_heading']) ?></small></td>
        <td><?= clean($b['button_text']) ?></td>
        <td>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="badge badge-<?= $b['status'] === 'active' ? 'success' : 'danger' ?>"><?= clean($b['status']) ?></button></form>
        </td>
        <td class="action-cell">
          <a href="<?= admin_url('banners/form.php?id=' . $b['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete banner?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$banners): ?><tr><td colspan="5"><div class="empty-state"><h3>No banners yet</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
