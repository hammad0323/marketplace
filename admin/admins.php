<?php
require __DIR__ . '/_inc/bootstrap.php';
$me = require_admin('admins.manage');
$editId = input_int('edit', 0, 'get');
$errors = [];
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    $target = $id ? db_one('SELECT a.*, r.slug role_slug FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ?', [$id]) : null;
    $superRole = (int) db_val("SELECT id FROM admin_roles WHERE slug = 'super_admin'");
    $activeSupers = (int) db_val("SELECT COUNT(*) FROM admins WHERE role_id = ? AND status = 'active'", [$superRole]);
    $d = [
        'name' => mb_substr(input('name'), 0, 120),
        'email' => mb_strtolower(input('email')),
        'role_id' => input_int('role_id'),
        'status' => input('status') === 'disabled' ? 'disabled' : 'active',
    ];
    if ($d['name'] === '') { $errors[] = 'Name is required.'; }
    if (!valid_email($d['email'])) { $errors[] = 'Valid email required.'; }
    if (db_val('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$d['email'], $id])) { $errors[] = 'Email already used by another administrator.'; }
    if (!db_val('SELECT COUNT(*) FROM admin_roles WHERE id = ?', [$d['role_id']])) { $errors[] = 'Choose a role.'; }
    // Never lock the store out of its last Super Admin.
    if ($target && $target['role_slug'] === 'super_admin' && $target['status'] === 'active' && $activeSupers <= 1 && ($d['role_id'] !== $superRole || $d['status'] !== 'active')) {
        $errors[] = 'This is the only active Super Admin and cannot be demoted or disabled.';
    }
    if ($target && (int) $target['id'] === (int) $me['id'] && $d['status'] === 'disabled') {
        $errors[] = 'You cannot disable your own account.';
    }
    $pass = (string) ($_POST['password'] ?? '');
    if (!$target || $pass !== '') {
        if (strlen($pass) < 10 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $errors[] = 'Password needs at least 10 characters with letters and numbers.';
        }
    }
    if (!$errors) {
        if ($pass !== '') {
            $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            $d['must_change_password'] = 1;
        }
        if ($target) {
            db_update('admins', $d, 'id = ?', [$id]);
        } else {
            $id = db_insert('admins', $d);
        }
        audit_log($target ? 'admin_updated' : 'admin_created', 'admin', $id, ['email' => $d['email'], 'role_id' => $d['role_id'], 'status' => $d['status'], 'password_set' => $pass !== '']);
        flash('success', 'Administrator saved.' . ($pass !== '' ? ' They must change the temporary password at next sign-in.' : ''));
        redirect(admin_url('admins'));
    }
    $editId = $id;
}
$rows = db_all('SELECT a.*, r.name role_name FROM admins a JOIN admin_roles r ON r.id = a.role_id ORDER BY a.name');
$roles = array_column(db_all('SELECT id, name FROM admin_roles ORDER BY id'), 'name', 'id');
$edit = $editId ? db_one('SELECT * FROM admins WHERE id = ?', [$editId]) : null;
if ($errors) {
    $edit = array_merge($edit ?? [], ['name' => input('name'), 'email' => input('email'), 'role_id' => input_int('role_id'), 'status' => input('status'), 'id' => $editId]);
}
admin_header('Administrators', 'admins');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $a): ?>
      <tr><td><strong><?= e($a['name']) ?></strong><?= (int) $a['id'] === (int) $me['id'] ? ' <span class="badge text-bg-light">you</span>' : '' ?><br><small class="text-muted"><?= e($a['email']) ?></small></td>
        <td><?= e($a['role_name']) ?></td><td><?= badge($a['status']) ?><?= $a['must_change_password'] ? ' <span class="badge text-bg-warning">temp password</span>' : '' ?></td>
        <td><small><?= e(format_date($a['last_login_at'], true)) ?> <?= e($a['last_login_ip']) ?></small></td>
        <td class="text-end"><a class="btn btn-sm btn-light" href="?edit=<?= (int) $a['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr>
    <?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header"><?= $edit ? 'Edit administrator' : 'Add administrator' ?></div><div class="card-body">
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <?= f_text('name', 'Name', $edit['name'] ?? '', ['required' => true]) ?>
      <?= f_text('email', 'Email', $edit['email'] ?? '', ['required' => true], null, 'email') ?>
      <?= f_select('role_id', 'Role', $roles, $edit['role_id'] ?? 2) ?>
      <?= f_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $edit['status'] ?? 'active') ?>
      <?= f_text('password', $edit ? 'New temporary password (blank = unchanged)' : 'Temporary password', '', ['autocomplete' => 'new-password'], 'Min 10 characters incl. letters and numbers. The user must change it at first sign-in.', 'password') ?>
      <?= f_submit() ?> <?php if ($edit): ?><a class="btn btn-light" href="<?= e(admin_url('admins')) ?>">Cancel</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php admin_footer();
