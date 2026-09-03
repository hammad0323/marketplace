<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    db_exec("UPDATE shop_staff SET status = IF(status='active','disabled','active') WHERE id=?", 'i', [(int)$_POST['id']]);
    flash('success', 'Staff status updated.');
    redirect(admin_url('employees/index.php'));
}

$staff = db_fetch_all("SELECT st.*, s.shop_name FROM shop_staff st JOIN shops s ON s.id = st.shop_id ORDER BY st.created_at DESC");
$employeeLimit = get_setting('employee_limit', 3);

$dashRole = 'admin'; $pageTitle = 'Shop Staff'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shop Staff (All Shops)</h1></div>
<p class="text-muted">Global employee limit per shop is currently <strong><?= clean($employeeLimit) ?></strong>. Change it in <a href="<?= admin_url('settings/index.php') ?>">Settings</a>.</p>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Name</th><th>Email</th><th>Shop</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($staff): foreach ($staff as $st): ?>
      <tr>
        <td><?= clean($st['name']) ?></td><td><?= clean($st['email']) ?></td><td><?= clean($st['shop_name']) ?></td>
        <td><span class="badge badge-<?= $st['status']==='active'?'success':'danger' ?>"><?= clean($st['status']) ?></span></td>
        <td><?= $st['last_login'] ? date('M d, Y', strtotime($st['last_login'])) : 'Never' ?></td>
        <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $st['id'] ?>"><button class="btn btn-sm btn-outline"><?= $st['status']==='active'?'Disable':'Enable' ?></button></form></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No staff accounts yet</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
