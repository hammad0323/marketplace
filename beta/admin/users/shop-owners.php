<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id']; $status = $_POST['status'];
    db_exec("UPDATE shop_owners SET status=? WHERE id=?", 'si', [$status, $id]);
    flash('success', 'Shop owner status updated.');
    redirect(admin_url('users/shop-owners.php'));
}

$owners = db_fetch_all("SELECT o.*, s.shop_name, s.status as shop_status, s.id as shop_id FROM shop_owners o
    LEFT JOIN shops s ON s.owner_id = o.id ORDER BY o.created_at DESC");

$dashRole = 'admin'; $pageTitle = 'Shop Owners'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shop Owners</h1></div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Name</th><th>Email</th><th>Shop</th><th>Shop Status</th><th>Account Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($owners as $o): ?>
      <tr>
        <td><?= clean($o['name']) ?></td>
        <td><?= clean($o['email']) ?></td>
        <td><?php if ($o['shop_id']): ?><a href="<?= admin_url('shops/view.php?id=' . $o['shop_id']) ?>"><?= clean($o['shop_name']) ?></a><?php endif; ?></td>
        <td><span class="badge badge-<?= $o['shop_status'] === 'active' ? 'success' : 'warn' ?>"><?= clean($o['shop_status'] ?? '-') ?></span></td>
        <td><span class="badge badge-<?= $o['status'] === 'active' ? 'success' : 'danger' ?>"><?= clean($o['status']) ?></span></td>
        <td>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>">
            <select name="status" onchange="this.form.submit()">
              <option value="active" <?= $o['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $o['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
