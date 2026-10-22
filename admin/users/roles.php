<?php
require __DIR__ . '/../includes/init.php';
admin_require('admins');
$roles = db_all('SELECT * FROM admin_roles ORDER BY id');
$perms = db_all('SELECT * FROM admin_permissions ORDER BY group_name, id');
if (is_post()) {
    if (input('action', '', 'post') === 'add') {
        $name = mb_substr(input('name', '', 'post'), 0, 80);
        if ($name !== '') db_insert('admin_roles', ['name' => $name, 'slug' => unique_slug('admin_roles', $name), 'description' => mb_substr(input('description', '', 'post'), 0, 255)]);
    } else {
        foreach ($roles as $r) {
            if ($r['slug'] === 'super-admin') continue; // always has everything
            db_exec('DELETE FROM admin_role_permissions WHERE role_id = ?', [(int) $r['id']]);
            foreach ((array) ($_POST['perm'][$r['id']] ?? []) as $pid) {
                db_exec('INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)', [(int) $r['id'], (int) $pid]);
            }
        }
    }
    audit_log('roles_saved', 'admin_role');
    flash('success', 'Roles saved.');
    redirect('admin/users/roles.php');
}
$has = [];
foreach (db_all('SELECT * FROM admin_role_permissions') as $rp) $has[$rp['role_id']][$rp['permission_id']] = true;
admin_header('Roles & permissions');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/users/')) ?>">Users</a></li><li class="nav-item"><a class="nav-link active" href="#">Roles &amp; permissions</a></li></ul>
<form method="post"><?= csrf_field() ?>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Permission</th><?php foreach ($roles as $r): ?><th class="text-center"><?= e($r['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $g = null; foreach ($perms as $p): if ($g !== $p['group_name']): $g = $p['group_name']; ?><tr><td colspan="<?= count($roles) + 1 ?>" class="small fw-bold text-uppercase text-muted bg-light"><?= e($g) ?></td></tr><?php endif; ?>
<tr><td><?= e($p['label']) ?> <code class="small"><?= e($p['perm_key']) ?></code></td>
<?php foreach ($roles as $r): ?><td class="text-center"><input class="form-check-input" type="checkbox" name="perm[<?= (int) $r['id'] ?>][]" value="<?= (int) $p['id'] ?>"<?= $r['slug'] === 'super-admin' || isset($has[$r['id']][$p['id']]) ? ' checked' : '' ?><?= $r['slug'] === 'super-admin' ? ' disabled' : '' ?>></td><?php endforeach; ?></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<button class="btn btn-dark">Save permissions</button></form>
<div class="card a-card mt-4"><div class="card-header">Add role</div><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
<div class="col-md-4"><input class="form-control" name="name" placeholder="Role name" required></div><div class="col-md-6"><input class="form-control" name="description" placeholder="Description"></div><div class="col-md-2"><button class="btn btn-dark w-100">Add</button></div></form></div></div>
<?php admin_footer();
