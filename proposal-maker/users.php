<?php
/**
 * USERS — add, edit and delete team members (admins only).
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = post('act');
    try {
        if ($act === 'save') {
            $uid = (int) post('uid');
            $data = ['name' => post('name'), 'email' => strtolower(post('email')), 'role' => post('role') === 'editor' ? 'editor' : 'admin', 'status' => post('status') === '0' ? 0 : 1];
            if ($data['name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Name and a valid email are required.');
            if (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $uid])) throw new RuntimeException('That email is already used.');
            if ($uid === (int) $me['id']) { $data['role'] = 'admin'; $data['status'] = 1; }
            $pw = (string) ($_POST['password'] ?? '');
            if ($pw !== '') {
                if (strlen($pw) < 6) throw new RuntimeException('Password must be at least 6 characters.');
                $data['password'] = password_hash($pw, PASSWORD_DEFAULT);
            }
            if ($uid) {
                db_update('users', $data, 'id = ?', [$uid]);
                flash('success', 'User updated.');
            } else {
                if ($pw === '') throw new RuntimeException('Password is required for new users.');
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('users', $data);
                flash('success', 'User created.');
            }
        } elseif ($act === 'delete') {
            $uid = (int) post('uid');
            if ($uid === (int) $me['id']) throw new RuntimeException('You cannot delete your own account.');
            db_query('DELETE FROM users WHERE id = ?', [$uid]);
            flash('success', 'User deleted.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('users.php');
}
$users = db_all('SELECT * FROM users ORDER BY id');
$pageTitle = 'Users';
$activeNav = 'users';
include __DIR__ . '/layouts/header.php';
?>
<div class="page-head"><div><h1>Users</h1><p>Team members who can log in and manage proposals.</p></div>
  <div class="actions"><button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#uModal" data-user='{}'><i class="bi bi-person-plus"></i> Add user</button></div></div>
<div class="card-x"><div class="table-responsive"><table class="table table-x">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr><td class="fw-semibold text-dark"><span class="avatar me-2" style="width:30px;height:30px;font-size:12px"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td><td><span class="chip"><?= e(ucfirst($u['role'])) ?></span></td>
      <td><?= $u['status'] ? '<span class="badge-status bs-accepted">Active</span>' : '<span class="badge-status bs-draft">Disabled</span>' ?></td>
      <td class="small text-muted"><?= e($u['last_login'] ? time_ago($u['last_login']) : 'Never') ?></td>
      <td><div class="act-btns">
        <button class="btn" data-bs-toggle="modal" data-bs-target="#uModal" data-user='<?= e(jencode(['uid' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'status' => $u['status']])) ?>'><i class="bi bi-pencil"></i></button>
        <?php if ((int) $u['id'] !== (int) $me['id']): ?><form method="post" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="uid" value="<?= (int) $u['id'] ?>"><button class="btn text-danger"><i class="bi bi-trash3"></i></button></form><?php endif; ?>
      </div></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<div class="modal fade" id="uModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" style="border-radius:16px">
  <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="uid">
  <div class="modal-header border-0"><h5 class="modal-title">User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
    <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
    <div class="col-6"><label class="form-label">Role</label><select class="form-select" name="role"><option value="admin">Admin</option><option value="editor">Editor</option></select></div>
    <div class="col-6"><label class="form-label">Status</label><select class="form-select" name="status"><option value="1">Active</option><option value="0">Disabled</option></select></div>
    <div class="col-12"><label class="form-label">Password <span class="text-muted small">(leave empty to keep)</span></label><input class="form-control" type="password" name="password" autocomplete="new-password"></div>
  </div></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save</button></div>
</form></div></div>
<?php
$extraScripts = "<script>$('#uModal').on('show.bs.modal', function(e){ var u = $(e.relatedTarget).data('user') || {}; var f = $(this).find('form')[0]; f.reset(); $(f).find('[name=uid]').val(u.uid||''); $(f).find('[name=name]').val(u.name||''); $(f).find('[name=email]').val(u.email||''); $(f).find('[name=role]').val(u.role||'admin'); $(f).find('[name=status]').val(u.status === undefined ? 1 : u.status); });</script>";
include __DIR__ . '/layouts/footer.php';
