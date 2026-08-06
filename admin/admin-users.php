<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
$isSuperAdmin = $admin['role'] === 'super_admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isSuperAdmin) {
        mp_flash('error', 'Only a super admin can add new admin accounts.');
        mp_redirect('admin-users.php');
    }

    mp_verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['admin', 'super_admin'], true) ? $_POST['role'] : 'admin';

    if ($name === '' || $email === '' || strlen($password) < 8) {
        mp_flash('error', 'Please fill in all fields (password must be at least 8 characters).');
        mp_redirect('admin-users.php');
    }

    if (mp_find_admin_by_email($email)) {
        mp_flash('error', 'An admin with that email already exists.');
        mp_redirect('admin-users.php');
    }

    $newAdminId = mp_insert_admin([
        'name'          => $name,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role'          => $role,
    ]);

    mp_log_activity('admin', $admin['id'], 'admin.created', 'admin', $newAdminId, "{$name} <{$email}> ({$role})");
    mp_flash('success', 'Admin account created.');
    mp_redirect('admin-users.php');
}

$admins = mp_all_admins();

$pageTitle = 'Admin Users';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Admin Users</h1>

<?php if ($isSuperAdmin): ?>
<div class="admin-panel">
    <h2 style="margin-top:0;">Add Admin</h2>
    <form method="post" action="admin-users.php" class="checkout-address-grid" style="align-items:end;">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="admin">Admin</option>
                <option value="super_admin">Super Admin</option>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn">Add Admin</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-panel">
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><?php if ($isSuperAdmin): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($admins as $adminRow): ?>
            <tr>
                <td><?= mp_e($adminRow['name']) ?></td>
                <td><?= mp_e($adminRow['email']) ?></td>
                <td><span class="badge"><?= $adminRow['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?></span></td>
                <td><span class="status-chip status-<?= $adminRow['is_active'] ? 'completed' : 'cancelled' ?>"><?= $adminRow['is_active'] ? 'Active' : 'Disabled' ?></span></td>
                <td><?= $adminRow['last_login_at'] ? mp_e(date('M j, Y g:i A', strtotime($adminRow['last_login_at']))) : 'Never' ?></td>
                <?php if ($isSuperAdmin): ?>
                <td>
                    <?php if ((int) $adminRow['id'] !== (int) $admin['id']): ?>
                    <form method="post" action="admin-user-toggle-active.php" class="inline-form">
                        <?= mp_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $adminRow['id'] ?>">
                        <button type="submit" class="link-button"><?= $adminRow['is_active'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
