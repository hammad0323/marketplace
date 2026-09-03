<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id']; $status = $_POST['status'];
    db_exec("UPDATE customers SET status=? WHERE id=?", 'si', [$status, $id]);
    flash('success', 'Customer status updated.');
    redirect(admin_url('users/customers.php'));
}

$search = trim($_GET['search'] ?? '');
$where = $search ? "WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];
$types = $search ? 'sss' : '';

$count = db_fetch_one("SELECT COUNT(*) c FROM customers $where", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$customers = db_fetch_all("SELECT * FROM customers $where ORDER BY created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = 'admin'; $pageTitle = 'Customers'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Customers</h1>
  <form class="table-search"><input type="text" name="search" placeholder="Search customers..." value="<?= clean($search) ?>"><button><i class="fa-solid fa-search"></i></button></form>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($customers): foreach ($customers as $c): ?>
      <tr>
        <td><?= clean($c['first_name'] . ' ' . $c['last_name']) ?></td>
        <td><?= clean($c['email']) ?></td>
        <td><?= clean($c['phone']) ?></td>
        <td><span class="badge badge-<?= $c['status'] === 'active' ? 'success' : 'danger' ?>"><?= clean($c['status']) ?></span></td>
        <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
        <td>
          <form method="post" class="inline-form" onsubmit="return confirm('Change status?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
            <select name="status" onchange="this.form.submit()">
              <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              <option value="banned" <?= $c['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No customers found</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, admin_url('users/customers.php?search=' . urlencode($search))) ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
