<?php
require __DIR__ . '/includes/admin.php';

$me = admin();
if (is_post()) {
    require_csrf();
    $do = post('do');
    if ($do === 'me') {
        if (!password_verify((string)post('current'), $me['password'])) {
            flash('error', 'Current password is incorrect.');
        } else {
            $email = post('email');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || val('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, $me['id']])) {
                flash('error', 'Enter a valid email that is not used by another admin.');
            } else {
                q('UPDATE admins SET name = ?, email = ? WHERE id = ?', [post('name') ?: $me['name'], $email, $me['id']]);
                if (post('new') !== '') {
                    if (strlen((string)post('new')) < 8) flash('error', 'New password must be at least 8 characters.');
                    else { q('UPDATE admins SET password = ? WHERE id = ?', [password_hash((string)post('new'), PASSWORD_DEFAULT), $me['id']]); flash('success', 'Password changed.'); }
                }
                flash('success', 'Your profile has been updated.');
            }
        }
        redirect('admin/users');
    }
    require_section('users');
    if ($do === 'add') {
        $email = post('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string)post('password')) < 8 || post('name') === '') {
            flash('error', 'Enter a name, valid email and a password of at least 8 characters.');
        } elseif (val('SELECT COUNT(*) FROM admins WHERE email = ?', [$email])) {
            flash('error', 'An admin with that email already exists.');
        } else {
            q('INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)', [post('name'), $email, password_hash((string)post('password'), PASSWORD_DEFAULT), in_array(post('role'), ['super', 'manager', 'staff'], true) ? post('role') : 'staff']);
            flash('success', 'Admin user added.');
        }
    }
    if (in_array($do, ['toggle', 'delete', 'role'], true) && (int)post('id') !== (int)$me['id']) {
        if ($do === 'toggle') q('UPDATE admins SET status = 1 - status WHERE id = ?', [(int)post('id')]);
        if ($do === 'delete') q('DELETE FROM admins WHERE id = ?', [(int)post('id')]);
        if ($do === 'role' && in_array(post('role'), ['super', 'manager', 'staff'], true)) q('UPDATE admins SET role = ? WHERE id = ?', [post('role'), (int)post('id')]);
        flash('success', 'Admin updated.');
    }
    redirect('admin/users');
}
$admins = rows('SELECT * FROM admins ORDER BY id');
admin_header('Admin Users', 'users');
?>
<div class="grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="me">
    <div class="card__head"><h3>My profile & password</h3></div>
    <?= f_text('name', 'Name', $me['name']) ?>
    <?= f_text('email', 'Login email', $me['email'], ['type' => 'email']) ?>
    <?= f_text('new', 'New password', '', ['type' => 'password', 'attrs' => 'autocomplete="new-password" minlength="8"', 'help' => 'Leave blank to keep your current password']) ?>
    <?= f_text('current', 'Current password (required to save)', '', ['type' => 'password', 'attrs' => 'required autocomplete="current-password"']) ?>
    <button class="btn btn-primary">Update Profile</button>
  </form>
  <?php if (admin_can('users')): ?>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="card__head"><h3>Add admin user</h3></div>
    <?= f_text('name', 'Name', '', ['attrs' => 'required']) ?>
    <?= f_text('email', 'Email', '', ['type' => 'email', 'attrs' => 'required']) ?>
    <?= f_text('password', 'Password', '', ['type' => 'text', 'attrs' => 'required minlength="8"']) ?>
    <?= f_select('role', 'Role', 'staff', ['super' => 'Super admin — everything', 'manager' => 'Manager — everything except admin users', 'staff' => 'Staff — orders, products, customers, reviews']) ?>
    <button class="btn btn-primary">Add Admin</button>
  </form>
  <?php endif; ?>
</div>
<?php if (admin_can('users')): ?>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($admins as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
      <tr><td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="pill">You</span>' : '' ?></td><td><?= e($u['email']) ?></td>
        <td><?php if ($self): ?><?= e(ucfirst($u['role'])) ?><?php else: ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>"><select name="role" onchange="this.form.submit()"><?php foreach (['super', 'manager', 'staff'] as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
        <td class="muted"><?= $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never' ?></td>
        <td><?= $u['status'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge badge-cancelled">Disabled</span>' ?></td>
        <td class="actions"><?php if (!$self): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm" name="do" value="toggle"><?= $u['status'] ? 'Disable' : 'Enable' ?></button><button class="icon danger" name="do" value="delete" data-confirm="Delete this admin?"><?= aicon('trash') ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php admin_footer();
