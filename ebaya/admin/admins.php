<?php
/** Admin users and role permissions (Super Admin only by default). */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('admins.manage');
$errors = [];
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'save_admin') {
        $aid = (int)post('id');
        $name = mb_substr(post('name'), 0, 120);
        $email = strtolower(post('email'));
        $role = (int)post('role_id');
        $pw = (string)($_POST['password'] ?? '');
        if (!v_len($name, 2, 120) || !v_email($email)) $errors[] = 'Enter a name and valid email.';
        if (!db_val('SELECT id FROM admin_roles WHERE id = ?', [$role])) $errors[] = 'Choose a role.';
        if (db_val('SELECT id FROM admins WHERE email = ? AND id <> ?', [$email, $aid])) $errors[] = 'Email already in use.';
        if (!$aid && strlen($pw) < 10) $errors[] = 'Set an initial password of at least 10 characters.';
        if ($pw !== '' && ($e = v_password($pw))) $errors[] = $e;
        $status = post('status') === 'disabled' ? 'disabled' : 'active';
        if ($aid === (int)$me['id'] && ($status === 'disabled' || $role !== (int)$me['role_id'])) $errors[] = 'You cannot disable yourself or change your own role.';
        // Never leave the store without an active Super Admin.
        $superRole = (int)db_val('SELECT id FROM admin_roles WHERE is_super = 1 LIMIT 1');
        if ($aid && (int)db_val('SELECT role_id FROM admins WHERE id = ?', [$aid]) === $superRole && ($role !== $superRole || $status === 'disabled')
            && (int)db_val("SELECT COUNT(*) FROM admins WHERE role_id = ? AND status = 'active'", [$superRole]) <= 1) $errors[] = 'At least one active Super Admin is required.';
        if (!$errors) {
            if ($aid) {
                db_exec('UPDATE admins SET name = ?, email = ?, role_id = ?, status = ? WHERE id = ?', [$name, $email, $role, $status, $aid]);
                if ($pw !== '') db_exec('UPDATE admins SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $aid]);
            } else {
                $aid = db_insert('INSERT INTO admins (role_id, name, email, password_hash, status, must_change_password) VALUES (?, ?, ?, ?, ?, 1)', [$role, $name, $email, password_hash($pw, PASSWORD_DEFAULT), $status]);
            }
            audit('admin_save', 'admin', $aid, ['email' => $email, 'role' => $role, 'status' => $status, 'password_reset' => $pw !== '']);
            flash('success', 'Admin user saved.' . ($pw !== '' ? ' They will be asked to choose a new password at next sign-in.' : ''));
            redirect(admin_url('admins'));
        }
    } elseif ($act === 'save_perms') {
        foreach (db_all('SELECT * FROM admin_roles WHERE is_super = 0') as $r) {
            db_exec('DELETE FROM admin_role_permissions WHERE role_id = ?', [(int)$r['id']]);
            foreach ((array)($_POST['perm'][$r['id']] ?? []) as $pid) {
                if (db_val('SELECT id FROM admin_permissions WHERE id = ?', [(int)$pid])) db_exec('INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)', [(int)$r['id'], (int)$pid]);
            }
        }
        audit('role_permissions_update', 'role');
        flash('success', 'Role permissions updated.');
        redirect(admin_url('admins'));
    }
}
$admins = db_all('SELECT a.*, r.name role FROM admins a JOIN admin_roles r ON r.id = a.role_id ORDER BY a.id');
$roles = db_all('SELECT * FROM admin_roles ORDER BY id');
$perms = db_all('SELECT * FROM admin_permissions ORDER BY perm_group, id');
$rp = [];
foreach (db_all('SELECT * FROM admin_role_permissions') as $x) $rp[(int)$x['role_id']][(int)$x['permission_id']] = true;
$edit = get('edit') !== '' ? (db_one('SELECT * FROM admins WHERE id = ?', [(int)get('edit')]) ?: ['id' => 0]) : null;
$admin_title = 'Admin users & roles';
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header d-flex">Admin users<a class="btn btn-sm btn-primary ms-auto" href="?edit=0">Add admin</a></div>
    <table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
      <?php foreach ($admins as $a): ?><tr><td><?= e($a['name']) ?></td><td><?= e($a['email']) ?></td><td><?= e($a['role']) ?></td><td><?= status_badge($a['status']) ?></td><td class="small"><?= e($a['last_login_at'] ?? '—') ?></td><td><a class="btn btn-sm btn-light" href="?edit=<?= (int)$a['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="col-xl-5"><?php if ($edit !== null): $av = fn($k, $d = '') => $edit[$k] ?? $d; ?>
    <div class="card"><div class="card-header"><?= $av('id') ? 'Edit admin' : 'New admin' ?></div><div class="card-body"><form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="save_admin"><input type="hidden" name="id" value="<?= (int)$av('id', 0) ?>">
      <?= f_text('name', 'Name', $av('name'), ['required' => true]) ?><?= f_text('email', 'Email', $av('email'), ['type' => 'email', 'required' => true]) ?>
      <?= f_select('role_id', 'Role', array_column($roles, 'name', 'id'), $av('role_id')) ?>
      <?= f_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $av('status', 'active')) ?>
      <?= f_text('password', $av('id') ? 'Set a new temporary password (optional)' : 'Initial password', '', ['type' => 'password', 'help' => 'At least 10 characters with letters and numbers. They must change it at first sign-in.']) ?>
      <button class="btn btn-primary">Save</button></form></div></div>
  <?php endif; ?></div>
</div>
<div class="card mt-3"><div class="card-header">Role permissions (enforced on the server for every page and action)</div><div class="card-body">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_perms">
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Permission</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e($r['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php $grp = null; foreach ($perms as $p): if ($p['perm_group'] !== $grp): $grp = $p['perm_group']; ?><tr><td colspan="<?= count($roles) + 1 ?>" class="small text-uppercase text-muted pt-3"><?= e($grp) ?></td></tr><?php endif; ?>
      <tr><td><?= e($p['label']) ?> <code class="small"><?= e($p['perm_key']) ?></code></td>
      <?php foreach ($roles as $r): ?><td class="text-center"><?php if ($r['is_super']): ?><i class="bi bi-check2 text-success" title="Super Admin has every permission"></i><?php else: ?><input type="checkbox" class="form-check-input" name="perm[<?= (int)$r['id'] ?>][]" value="<?= (int)$p['id'] ?>"<?= isset($rp[(int)$r['id']][(int)$p['id']]) ? ' checked' : '' ?>><?php endif; ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </tbody></table></div><button class="btn btn-primary">Save permissions</button></form>
</div></div>
<?php require __DIR__ . '/partials/footer.php';
