<?php
require __DIR__ . '/../config/config.php';
require_shop_owner(); // only the owner manages staff
$shopId = active_shop_id();
$shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$shopId]);
$employeeLimit = $shop['employee_limit_override'] ?? (int)get_setting('employee_limit', 3);
$currentCount = db_fetch_one("SELECT COUNT(*) c FROM shop_staff WHERE shop_id=?", 'i', [$shopId])['c'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'];

    if ($action === 'add') {
        if ($currentCount >= $employeeLimit) {
            flash('error', 'You have reached your employee limit. Please contact the administrator.');
            redirect(shop_url('employees.php'));
        }
        $name = trim($_POST['name']); $email = trim($_POST['email']); $password = $_POST['password'];
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            flash('error', 'Please fill all fields correctly.'); redirect(shop_url('employees.php'));
        }
        if (db_fetch_one("SELECT id FROM shop_staff WHERE email=?", 's', [$email])) {
            flash('error', 'A staff account with this email already exists.'); redirect(shop_url('employees.php'));
        }
        $staffId = db_insert("INSERT INTO shop_staff (shop_id, name, email, password_hash) VALUES (?,?,?,?)",
            'isss', [$shopId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        foreach (ALL_STAFF_PERMISSIONS as $key => $label) {
            $allowed = isset($_POST['perm'][$key]) ? 1 : 0;
            db_insert("INSERT INTO staff_permissions (staff_id, permission_key, allowed) VALUES (?,?,?)", 'isi', [$staffId, $key, $allowed]);
        }
        flash('success', 'Staff member added.');
    } elseif ($action === 'update_permissions') {
        $staffId = (int)$_POST['staff_id'];
        $staff = db_fetch_one("SELECT id FROM shop_staff WHERE id=? AND shop_id=?", 'ii', [$staffId, $shopId]);
        if ($staff) {
            foreach (ALL_STAFF_PERMISSIONS as $key => $label) {
                $allowed = isset($_POST['perm'][$key]) ? 1 : 0;
                db_exec("INSERT INTO staff_permissions (staff_id, permission_key, allowed) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE allowed=?", 'isii', [$staffId, $key, $allowed, $allowed]);
            }
            flash('success', 'Permissions updated.');
        }
    } elseif ($action === 'toggle_status') {
        db_exec("UPDATE shop_staff SET status = IF(status='active','disabled','active') WHERE id=? AND shop_id=?", 'ii', [(int)$_POST['staff_id'], $shopId]);
        flash('success', 'Staff status updated.');
    } elseif ($action === 'delete') {
        db_exec("DELETE FROM shop_staff WHERE id=? AND shop_id=?", 'ii', [(int)$_POST['staff_id'], $shopId]);
        flash('success', 'Staff member removed.');
    }
    redirect(shop_url('employees.php'));
}

$staffList = db_fetch_all("SELECT * FROM shop_staff WHERE shop_id=? ORDER BY created_at DESC", 'i', [$shopId]);
foreach ($staffList as &$st) {
    $perms = db_fetch_all("SELECT permission_key, allowed FROM staff_permissions WHERE staff_id=?", 'i', [$st['id']]);
    $st['permissions'] = array_column($perms, 'allowed', 'permission_key');
}
unset($st);

$dashRole = 'shop'; $pageTitle = 'Staff Management'; $dashUserName = current_shop_owner()['name']; $dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Staff Management</h1>
  <span class="badge badge-<?= $currentCount >= $employeeLimit ? 'danger' : 'success' ?>"><?= $currentCount ?> / <?= $employeeLimit ?> Staff Accounts Used</span>
</div>

<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Created</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($staffList): foreach ($staffList as $st): ?>
      <tr>
        <td><?= clean($st['name']) ?></td><td><?= clean($st['email']) ?></td>
        <td><span class="badge badge-<?= $st['status']==='active'?'success':'danger' ?>"><?= clean($st['status']) ?></span></td>
        <td><?= date('M d, Y', strtotime($st['created_at'])) ?></td>
        <td><?= $st['last_login'] ? date('M d, Y g:ia', strtotime($st['last_login'])) : 'Never' ?></td>
        <td class="action-cell">
          <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('perm-modal-<?= $st['id'] ?>').style.display='flex'">Permissions</button>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
            <button class="btn btn-sm btn-outline"><?= $st['status']==='active'?'Disable':'Enable' ?></button></form>
          <form method="post" class="inline-form" onsubmit="return confirm('Remove this staff member?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button></form>
        </td>
      </tr>
      <div class="modal" id="perm-modal-<?= $st['id'] ?>">
        <div class="modal-box">
          <h3>Permissions — <?= clean($st['name']) ?></h3>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_permissions"><input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
            <div class="permission-matrix">
              <?php foreach (ALL_STAFF_PERMISSIONS as $key => $label): ?>
                <label><input type="checkbox" name="perm[<?= $key ?>]" <?= !empty($st['permissions'][$key]) ? 'checked' : '' ?>> <?= clean($label) ?></label>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Save Permissions</button>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('perm-modal-<?= $st['id'] ?>').style.display='none'">Cancel</button>
          </form>
        </div>
      </div>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No staff members yet</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="dash-form-card">
  <h3>Add Staff Member</h3>
  <?php if ($currentCount >= $employeeLimit): ?>
    <div class="alert alert-error">You have reached your employee limit. Please contact the administrator.</div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="form-row">
        <div><label>Name</label><input type="text" name="name" required></div>
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Password</label><input type="password" name="password" required minlength="6"></div>
      </div>
      <label>Permissions</label>
      <div class="permission-matrix">
        <?php foreach (ALL_STAFF_PERMISSIONS as $key => $label): ?>
          <label><input type="checkbox" name="perm[<?= $key ?>]"> <?= clean($label) ?></label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary">Add Staff</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
