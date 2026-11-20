<?php
/** Admin users (Super Admin only). */
require __DIR__ . '/_init.php';
require_super_admin();
$me = current_admin();
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'create') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $pw = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') flash('error', 'Enter a name and a valid email.');
        elseif (db_val('SELECT 1 FROM admins WHERE email = ?', [$email])) flash('error', 'An admin with that email exists.');
        elseif ($err = password_ok($pw)) flash('error', $err);
        else {
            $id = db_insert('admins', ['name' => $name, 'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => ($_POST['role'] ?? '') === 'super_admin' ? 'super_admin' : 'admin', 'must_change_password' => 1]);
            activity('admin', (int) $me['id'], 'admin_created', null, 'admin', $id);
            flash('success', 'Admin created. They must change the password at first sign-in.');
        }
    }
    if ($a === 'toggle' && (int) $_POST['id'] !== (int) $me['id']) {
        db_exec('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [(int) $_POST['id']]);
        flash('success', 'Admin updated.');
    }
    redirect('admin/admins.php');
}
$rows = db_all('SELECT * FROM admins ORDER BY id');
admin_header('Admin users');
?>
<div class="row g-3"><div class="col-xl-8"><div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e(str_replace('_', ' ', $r['role'])) ?></td><td class="small text-muted"><?= e(format_datetime($r['last_login_at'])) ?></td>
<td><span class="badge rounded-pill text-bg-<?= (int) $r['is_active'] ? 'success' : 'secondary' ?>"><?= (int) $r['is_active'] ? 'Active' : 'Disabled' ?></span></td>
<td><?php if ((int) $r['id'] !== (int) $me['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light"><?= (int) $r['is_active'] ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<div class="col-xl-4"><div class="p-card"><div class="p-card-head">Add admin</div><div class="p-card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<?= f_input('name', 'Name', '', 'text', ['required' => true]) ?><?= f_input('email', 'Email', '', 'email', ['required' => true]) ?><?= f_input('password', 'Temporary password', '', 'text', ['required' => true, 'minlength' => 8]) ?>
<?= f_select('role', 'Role', ['admin' => 'Admin (manage shops & payments)', 'super_admin' => 'Super Admin (also settings & admins)'], 'admin') ?>
<button class="btn btn-primary w-100">Create admin</button></form></div></div></div></div>
<?php admin_footer();
