<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('admins.manage');
if (is_post()) {
    require_csrf();
    if (input('action') === 'create') {
        $name = mb_substr(input('name'), 0, 80);
        $slug = str_replace('-', '_', slugify($name, 60));
        if ($name === '' || db_val('SELECT COUNT(*) FROM admin_roles WHERE slug = ?', [$slug])) {
            flash('error', 'Enter a unique role name.');
        } else {
            $id = db_insert('admin_roles', ['name' => $name, 'slug' => $slug, 'description' => mb_substr(input('description'), 0, 255) ?: null]);
            audit_log('role_created', 'role', $id, ['name' => $name]);
            flash('success', 'Role created — now choose its permissions.');
        }
    } elseif (input('action') === 'delete') {
        $role = db_one('SELECT * FROM admin_roles WHERE id = ?', [input_int('role_id')]);
        if (!$role || $role['is_system']) {
            flash('error', 'Built-in roles cannot be deleted.');
        } elseif (db_val('SELECT COUNT(*) FROM admins WHERE role_id = ?', [$role['id']])) {
            flash('error', 'Reassign the administrators using this role first.');
        } else {
            db_exec('DELETE FROM admin_roles WHERE id = ?', [$role['id']]);
            audit_log('role_deleted', 'role', (int) $role['id'], ['name' => $role['name']]);
            flash('success', 'Role deleted.');
        }
    } else {
        $valid = array_map('intval', db_col('SELECT id FROM admin_permissions'));
        db_tx(function () use ($valid) {
            foreach (db_all("SELECT * FROM admin_roles WHERE slug <> 'super_admin'") as $role) {
                $chosen = array_values(array_intersect($valid, array_map('intval', (array) ($_POST['perm'][$role['id']] ?? []))));
                db_exec('DELETE FROM admin_role_permissions WHERE role_id = ?', [$role['id']]);
                foreach ($chosen as $pid) {
                    db_insert('admin_role_permissions', ['role_id' => $role['id'], 'permission_id' => $pid]);
                }
                audit_log('role_permissions_updated', 'role', (int) $role['id'], ['permissions' => count($chosen)]);
            }
        });
        flash('success', 'Permissions saved.');
    }
    redirect(admin_url('roles'));
}
$roles = db_all('SELECT r.*, (SELECT COUNT(*) FROM admins a WHERE a.role_id = r.id) users FROM admin_roles r ORDER BY r.id');
$perms = db_all('SELECT * FROM admin_permissions ORDER BY group_name, id');
$granted = [];
foreach (db_all('SELECT role_id, permission_id FROM admin_role_permissions') as $g) {
    $granted[(int) $g['role_id']][(int) $g['permission_id']] = true;
}
admin_header('Roles & permissions', 'roles');
?>
<p class="text-muted">Permissions are enforced on the server for every admin page and action. Super Admin always has every permission.</p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>Permission</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e($r['name']) ?><br><small class="text-muted fw-normal"><?= (int) $r['users'] ?> users</small></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php $group = null; foreach ($perms as $p): if ($p['group_name'] !== $group): $group = $p['group_name']; ?>
    <tr class="table-light"><td colspan="<?= count($roles) + 1 ?>"><strong><?= e($group) ?></strong></td></tr>
  <?php endif; ?>
    <tr><td><?= e($p['label']) ?> <small class="text-muted"><?= e($p['perm_key']) ?></small></td>
      <?php foreach ($roles as $r): ?><td class="text-center"><?php if ($r['slug'] === 'super_admin'): ?><i class="bi bi-check-lg text-success"></i><?php else: ?><input class="form-check-input" type="checkbox" name="perm[<?= (int) $r['id'] ?>][]" value="<?= (int) $p['id'] ?>" <?= isset($granted[(int) $r['id']][(int) $p['id']]) ? 'checked' : '' ?>><?php endif; ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="card-body border-top"><?= f_submit('Save permissions') ?></div></div>
</form>
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="card"><div class="card-header">Add a custom role</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><?= f_text('name', 'Role name', '', ['required' => true]) ?><?= f_text('description', 'Description', '') ?><?= f_submit('Create role') ?></form>
  </div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Custom roles</div><ul class="list-group list-group-flush">
    <?php foreach ($roles as $r): if ($r['is_system']) continue; ?><li class="list-group-item d-flex justify-content-between align-items-center"><?= e($r['name']) ?>
      <form method="post" data-confirm="Delete role <?= e($r['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="role_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></li><?php endforeach; ?>
    <li class="list-group-item text-muted small">Built-in roles: Super Admin, Store Manager, Order Manager, Content Manager.</li>
  </ul></div></div>
</div>
<?php admin_footer();
