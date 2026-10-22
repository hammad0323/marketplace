<?php
/** Admin users. Only roles with the "admins" permission (Super Admin by default). */
require __DIR__ . '/../includes/init.php';
$me = admin_require('admins');
$errors = [];
$editId = input_int('edit');
if (is_post()) {
    $a = input('action', 'save', 'post');
    $id = input_int('id');
    if ($a === 'toggle' && $id !== (int) $me['id']) {
        db_exec('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [$id]);
        audit_log('admin_toggled', 'admin', $id);
        redirect('admin/users/');
    }
    if ($a === 'delete' && $id !== (int) $me['id']) {
        db_exec('DELETE FROM admins WHERE id = ?', [$id]);
        audit_log('admin_deleted', 'admin', $id);
        flash('success', 'Admin removed.');
        redirect('admin/users/');
    }
    if ($a === 'save') {
        $d = ['name' => mb_substr(input('name', '', 'post'), 0, 120), 'email' => strtolower(input('email', '', 'post')), 'role_id' => input_int('role_id')];
        $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($d['name']) < 2) $errors[] = 'Name is required.';
        if (!valid_email($d['email'])) $errors[] = 'Valid email is required.';
        if (db_val('SELECT id FROM admins WHERE email = ? AND id <> ?', [$d['email'], $id])) $errors[] = 'Email already in use.';
        if (!db_val('SELECT id FROM admin_roles WHERE id = ?', [$d['role_id']])) $errors[] = 'Choose a role.';
        if (!$id && $pw === '') $errors[] = 'Set an initial password.';
        if ($pw !== '' && (strlen($pw) < 10 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw))) $errors[] = 'Password needs 10+ characters with letters and numbers.';
        if ($id === (int) $me['id'] && $d['role_id'] !== (int) $me['role_id']) $errors[] = 'You cannot change your own role.';
        if (!$errors) {
            if ($pw !== '') {
                $d['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
                $d['must_change_password'] = $id === (int) $me['id'] ? 0 : 1;
            }
            $id ? db_update('admins', $d, 'id = ?', [$id]) : ($id = db_insert('admins', $d + ['is_active' => 1]));
            audit_log('admin_saved', 'admin', $id, ['email' => $d['email'], 'role_id' => $d['role_id'], 'password_set' => $pw !== '']);
            flash('success', 'Admin saved.' . ($pw !== '' ? ' They will be asked to change the password at first sign-in.' : ''));
            redirect('admin/users/');
        }
        $editId = $id ?: -1;
    }
}
$admins = db_all('SELECT a.*, r.name role FROM admins a JOIN admin_roles r ON r.id = a.role_id ORDER BY a.id');
$roles = array_column(db_all('SELECT id, name FROM admin_roles ORDER BY id'), 'name', 'id');
$e = $editId > 0 ? db_row('SELECT * FROM admins WHERE id = ?', [$editId]) : ($errors ? $_POST : []);
admin_header('Admin users');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link active" href="#">Users</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/users/roles.php')) ?>">Roles &amp; permissions</a></li></ul>
<div class="row g-4"><div class="col-lg-8"><div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($admins as $a): ?><tr><td><strong><?= e($a['name']) ?></strong><div class="small text-muted"><?= e($a['email']) ?></div></td><td><?= e($a['role']) ?></td>
<td class="small"><?= e(format_datetime($a['last_login_at']) ?: 'never') ?><div class="text-muted"><?= e($a['last_login_ip']) ?></div></td><td><?= a_status_toggle((bool) $a['is_active']) ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="?edit=<?= (int) $a['id'] ?>"><i class="bi bi-pencil"></i></a>
<?php if ((int) $a['id'] !== (int) $me['id']): ?><?= a_post_button(url('admin/users/'), ['action' => 'toggle', 'id' => $a['id']], '<i class="bi bi-power"></i>', 'btn btn-sm btn-light', 'Enable/disable this admin?') ?>
<?= a_post_button(url('admin/users/'), ['action' => 'delete', 'id' => $a['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete admin ' . $a['email'] . '?') ?><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<div class="col-lg-4"><div class="card a-card"><div class="card-header"><?= $editId > 0 ? 'Edit admin' : 'Add admin' ?></div><div class="card-body">
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="id" value="<?= max(0, $editId) ?>"><input type="hidden" name="action" value="save">
    <?= a_input('name', 'Name', $e['name'] ?? '') ?>
    <?= a_input('email', 'Email', $e['email'] ?? '', 'email') ?>
    <?= a_select('role_id', 'Role', $roles, $e['role_id'] ?? 2) ?>
    <?= a_input('password', $editId > 0 ? 'New password (leave blank to keep)' : 'Initial password', '', 'password', ['autocomplete' => 'new-password']) ?>
    <button class="btn btn-dark w-100">Save admin</button>
</form></div></div></div></div>
<?php admin_footer();
