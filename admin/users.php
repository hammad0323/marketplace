<?php
require __DIR__ . '/../config.php';
wh_require_admin();
if (!in_array(wh_admin_role(), ['admin', 'super_admin'], true)) {
    http_response_code(403);
    require __DIR__ . '/header.php';
    echo '<div class="admin-card"><h3>Access denied</h3><p>Only business admins can manage admin users.</p></div>';
    require __DIR__ . '/footer.php';
    exit;
}
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $name = wh_input_post('name');
    $email = wh_input_post('email');
    $role = in_array(wh_input_post('role'), ['admin', 'manager', 'staff'], true) ? wh_input_post('role') : 'staff';
    $status = wh_input_post('status') === 'inactive' ? 'inactive' : 'active';
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '') {
        wh_flash_set('error', 'Name and email are required.');
    } else {
        if ($id) {
            $data = ['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status];
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $data['must_change_password'] = 0;
            }
            wh_update('admin_users', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
            wh_flash_set('success', 'User updated.');
        } else {
            $existing = wh_fetch_one('SELECT id FROM admin_users WHERE email=?', 's', [$email]);
            if ($existing) {
                wh_flash_set('error', 'A user with this email already exists.');
            } else {
                wh_insert('admin_users', [
                    'business_id' => $businessId, 'name' => $name, 'email' => $email,
                    'password_hash' => password_hash($password ?: bin2hex(random_bytes(6)), PASSWORD_DEFAULT),
                    'role' => $role, 'status' => $status, 'must_change_password' => 1,
                ]);
                wh_flash_set('success', 'User created.' . ($password === '' ? ' A random password was set — use "Forgot password" to issue a reset link.' : ''));
            }
        }
    }
    wh_redirect(BASE_URL . '/admin/users.php');
}

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    if ($id === (int) $_SESSION['admin_id']) {
        wh_flash_set('error', 'You cannot delete your own account.');
    } else {
        wh_execute('DELETE FROM admin_users WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        wh_flash_set('success', 'User deleted.');
    }
    wh_redirect(BASE_URL . '/admin/users.php');
}

$users = wh_fetch_all('SELECT * FROM admin_users WHERE business_id=? ORDER BY created_at', 'i', [$businessId]);

$pageTitle = 'Admin Users';
$activePage = 'users';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Admin Users</h3>
    <button type="button" class="btn btn-primary btn-sm" onclick="openUserModal()"><i class="fa-solid fa-plus"></i> Add User</button>
  </div>
  <p class="hint">Roles: <strong>Admin</strong> (full access), <strong>Manager</strong> (bookings, halls, payments, customers, gallery, reports), <strong>Staff</strong> (bookings, customers, calendar only).</p>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge badge-confirmed"><?= e(ucfirst($u['role'])) ?></span></td>
        <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td><?= $u['last_login'] ? wh_format_date($u['last_login'], 'd M Y, g:i A') : 'Never' ?></td>
        <td style="white-space:nowrap;">
          <button type="button" class="btn btn-light btn-sm" onclick='openUserModal(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i></button>
          <?php if ((int) $u['id'] !== (int) $_SESSION['admin_id']): ?>
          <a href="<?= e(BASE_URL) ?>/admin/users.php?delete=<?= (int) $u['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this user?"><i class="fa-solid fa-trash"></i></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="userModal">
  <div class="modal">
    <div class="modal-head"><h3 id="userModalTitle">Add User</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="id" id="userId">
      <div class="form-group"><label>Name</label><input type="text" name="name" id="userName" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" id="userEmail" required></div>
      <div class="form-grid">
        <div class="form-group"><label>Role</label>
          <select name="role" id="userRole"><option value="admin">Admin</option><option value="manager">Manager</option><option value="staff">Staff</option></select>
        </div>
        <div class="form-group"><label>Status</label>
          <select name="status" id="userStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <div class="form-group"><label>Password <span id="userPwHint" class="hint"></span></label><input type="password" name="password" id="userPassword" minlength="8"></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save User</button>
    </form>
  </div>
</div>
<script>
function openUserModal(u) {
  document.getElementById('userModalTitle').textContent = u ? 'Edit User' : 'Add User';
  document.getElementById('userId').value = u ? u.id : '';
  document.getElementById('userName').value = u ? u.name : '';
  document.getElementById('userEmail').value = u ? u.email : '';
  document.getElementById('userRole').value = u ? u.role : 'staff';
  document.getElementById('userStatus').value = u ? u.status : 'active';
  document.getElementById('userPassword').value = '';
  document.getElementById('userPwHint').textContent = u ? '(leave blank to keep current password)' : '(leave blank to auto-generate; use Forgot Password to issue reset link)';
  document.getElementById('userModal').classList.add('open');
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
