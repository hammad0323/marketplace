<?php
require __DIR__ . '/includes/init.php';
admin_require('admins');
$perms = admin_permission_list();
if (is_post()) {
    csrf_check();
    $act = input('act'); $id = input_int('id');
    if ($act === 'admin') {
        $email = mb_strtolower(input('email'));
        $role = input_int('role_id');
        if (input('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !db_val('SELECT id FROM admin_roles WHERE id = ?', [$role])) { flash('danger', 'Name, valid email and role are required.'); redirect('admin/admins.php'); }
        if (db_val('SELECT id FROM admins WHERE email = ? AND id <> ? AND deleted_at IS NULL', [$email, $id])) { flash('danger', 'Email already used by another admin.'); redirect('admin/admins.php'); }
        $pw = (string) ($_POST['password'] ?? '');
        if (!$id && strlen($pw) < 8) { flash('danger', 'Password must be at least 8 characters.'); redirect('admin/admins.php'); }
        // never lock out the last super admin
        if ($id && (int) db_val('SELECT is_super FROM admin_roles r JOIN admins a ON a.role_id = r.id WHERE a.id = ?', [$id]) && (!db_val('SELECT is_super FROM admin_roles WHERE id = ?', [$role]) || !input_int('is_active'))
            && (int) db_val('SELECT COUNT(*) FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE r.is_super = 1 AND a.is_active = 1 AND a.deleted_at IS NULL') <= 1) {
            flash('danger', 'You cannot remove the last active Super Admin.'); redirect('admin/admins.php');
        }
        $d = ['name' => input('name'), 'email' => $email, 'phone' => input('phone') ?: null, 'role_id' => $role, 'is_active' => input_int('is_active')];
        if ($pw !== '') { if (strlen($pw) < 8) { flash('danger', 'Password must be at least 8 characters.'); redirect('admin/admins.php'); } $d['password_hash'] = password_hash($pw, PASSWORD_DEFAULT); }
        $id ? db_update_row('admins', $d, 'id = ?', [$id]) : db_insert_row('admins', $d);
        flash('success', 'Admin user saved.');
    } elseif ($act === 'delete_admin') {
        if ($id === admin_id()) flash('danger', 'You cannot delete yourself.');
        else { db_exec('UPDATE admins SET deleted_at = NOW(), is_active = 0, email = CONCAT(email, ".deleted.", id) WHERE id = ?', [$id]); flash('success', 'Admin removed.'); }
    } elseif ($act === 'role') {
        $name = input('name');
        if ($name === '') { flash('danger', 'Role name required.'); redirect('admin/admins.php'); }
        if ($id && db_val('SELECT is_super FROM admin_roles WHERE id = ?', [$id])) { db_exec('UPDATE admin_roles SET name = ?, description = ? WHERE id = ?', [$name, input('description'), $id]); }
        else {
            if ($id) db_exec('UPDATE admin_roles SET name = ?, description = ? WHERE id = ?', [$name, input('description'), $id]);
            else $id = db_insert('INSERT INTO admin_roles (name, description) VALUES (?, ?)', [$name, input('description')]);
            db_exec('DELETE FROM admin_permissions WHERE role_id = ?', [$id]);
            foreach (input_arr('perms') as $p) if (isset($perms[$p])) db_exec('INSERT INTO admin_permissions (role_id, permission) VALUES (?, ?)', [$id, $p]);
        }
        flash('success', 'Role saved.');
    } elseif ($act === 'delete_role') {
        if (db_val('SELECT COUNT(*) FROM admins WHERE role_id = ? AND deleted_at IS NULL', [$id]) || db_val('SELECT is_super FROM admin_roles WHERE id = ?', [$id])) flash('danger', 'Role is in use or protected.');
        else { db_exec('DELETE FROM admin_roles WHERE id = ?', [$id]); flash('success', 'Role deleted.'); }
    }
    redirect('admin/admins.php');
}
$admins = db_all('SELECT a.*, r.name AS role FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.deleted_at IS NULL ORDER BY a.id');
$roles = db_all('SELECT r.*, (SELECT COUNT(*) FROM admins WHERE role_id = r.id AND deleted_at IS NULL) AS users FROM admin_roles r ORDER BY r.is_super DESC, r.name');
$roleOpts = []; foreach ($roles as $r) $roleOpts[$r['id']] = $r['name'];
$ea = input_int('edit') ? db_row('SELECT * FROM admins WHERE id = ?', [input_int('edit')]) : ['id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'role_id' => '', 'is_active' => 1];
$er = input_int('role') ? db_row('SELECT * FROM admin_roles WHERE id = ?', [input_int('role')]) : ['id' => 0, 'name' => '', 'description' => '', 'is_super' => 0];
$erPerms = $er['id'] ? db_col('SELECT permission FROM admin_permissions WHERE role_id = ?', [$er['id']]) : [];
admin_header('Admin Users & Roles', 'admins');
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="card-header">Admin users</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($admins as $a): ?><tr><td><strong><?= e($a['name']) ?></strong><br><small class="text-muted"><?= e($a['email']) ?></small></td><td><?= e($a['role']) ?></td><td class="small"><?= $a['last_login_at'] ? fmt_datetime($a['last_login_at']) . '<br><span class="text-muted">' . e($a['last_login_ip']) . '</span>' : 'Never' ?></td>
      <td><?= $a['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></td>
      <td class="text-end text-nowrap"><a href="?edit=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a><?php if ($a['id'] != admin_id()): ?> <form method="post" class="d-inline" data-confirm="Remove this admin?"><?= csrf_field() ?><input type="hidden" name="act" value="delete_admin"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
    <div class="card"><div class="card-header">Roles &amp; permissions</div><div class="table-responsive"><table class="table align-middle mb-0"><tbody>
    <?php foreach ($roles as $r): $rp = $r['is_super'] ? ['All permissions'] : array_map(function ($p) use ($perms) { return $perms[$p] ?? $p; }, db_col('SELECT permission FROM admin_permissions WHERE role_id = ?', [$r['id']])); ?>
      <tr><td><strong><?= e($r['name']) ?></strong> <small class="text-muted">(<?= (int) $r['users'] ?> users)</small><br><small class="text-muted"><?= e(implode(' · ', $rp)) ?></small></td>
        <td class="text-end text-nowrap"><a href="?role=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a><?php if (!$r['is_super'] && !$r['users']): ?> <form method="post" class="d-inline" data-confirm="Delete role?"><?= csrf_field() ?><input type="hidden" name="act" value="delete_role"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
  </div>
  <div class="col-xl-5">
    <form method="post" class="card mb-3" autocomplete="off"><div class="card-header"><?= $ea['id'] ? 'Edit admin' : 'Add admin user' ?></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="admin"><input type="hidden" name="id" value="<?= (int) $ea['id'] ?>">
      <?= af_text('name', 'Name', $ea['name'], ['required' => true]) ?><?= af_text('email', 'Email (login)', $ea['email'], ['type' => 'email', 'required' => true]) ?><?= af_text('phone', 'Phone', $ea['phone']) ?>
      <?= af_select('role_id', 'Role', $roleOpts, $ea['role_id']) ?><?= af_text('password', $ea['id'] ? 'New password (leave empty to keep)' : 'Password (min 8)', '', ['type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?><?= af_check('is_active', 'Active', $ea['is_active']) ?>
    </div><div class="card-footer bg-white"><button class="btn btn-brand">Save admin</button></div></form>
    <form method="post" class="card"><div class="card-header"><?= $er['id'] ? 'Edit role' : 'New role' ?></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="role"><input type="hidden" name="id" value="<?= (int) $er['id'] ?>">
      <?= af_text('name', 'Role name', $er['name'], ['required' => true]) ?><?= af_text('description', 'Description', $er['description']) ?>
      <?php if ($er['is_super']): ?><p class="small text-muted">Super Admin always has every permission.</p><?php else: ?>
      <label class="form-label">Permissions</label><div class="row"><?php foreach ($perms as $k => $l): ?><div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="perms[]" value="<?= $k ?>" id="p_<?= $k ?>" <?= in_array($k, $erPerms, true) ? 'checked' : '' ?>><label class="form-check-label small" for="p_<?= $k ?>"><?= e($l) ?></label></div></div><?php endforeach; ?></div><?php endif; ?>
    </div><div class="card-footer bg-white"><button class="btn btn-brand">Save role</button></div></form>
  </div>
</div>
<?php admin_footer();
