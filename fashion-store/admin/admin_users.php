<?php
$pageTitle = 'Admin Users';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 1);
        $status = $_POST['status'] === 'active' ? 'active' : 'disabled';
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '') {
            flash_set('danger', 'Name and email are required.');
        } elseif ($id) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($mysqli, "UPDATE admins SET name=?, email=?, role_id=?, status=?, password_hash=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssissi', $name, $email, $roleId, $status, $hash, $id);
            } else {
                $stmt = mysqli_prepare($mysqli, "UPDATE admins SET name=?, email=?, role_id=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssisi', $name, $email, $roleId, $status, $id);
            }
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Admin user updated.');
        } else {
            if ($password === '') $password = bin2hex(random_bytes(4));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($mysqli, "INSERT INTO admins (role_id, name, email, password_hash, status) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'issss', $roleId, $name, $email, $hash, $status);
            if (mysqli_stmt_execute($stmt)) {
                flash_set('success', "Admin created. Temporary password: $password");
            } else {
                flash_set('danger', 'Could not create admin (email may already exist).');
            }
        }
    } elseif ($action === 'delete') {
        if ((int)$_POST['id'] !== (int)$_SESSION['admin_id']) {
            mysqli_query($mysqli, "DELETE FROM admins WHERE id = " . (int)$_POST['id']);
            flash_set('success', 'Admin removed.');
        }
    }
    redirect('admin_users.php');
}

$admins = mysqli_query($mysqli, "SELECT a.*, r.name AS role_name FROM admins a JOIN admin_roles r ON r.id = a.role_id ORDER BY a.name");
$roles = mysqli_query($mysqli, "SELECT * FROM admin_roles ORDER BY id");
$roleList = [];
while ($r = mysqli_fetch_assoc($roles)) $roleList[] = $r;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Admin Users</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#adminModal" onclick="resetAdminForm()"><i class="bi bi-plus-lg"></i> Add Admin</button>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
    <tbody>
    <?php while ($a = mysqli_fetch_assoc($admins)): ?>
      <tr>
        <td><?= e($a['name']) ?></td>
        <td><?= e($a['email']) ?></td>
        <td><?= e($a['role_name']) ?></td>
        <td><span class="badge <?= $a['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($a['status']) ?></span></td>
        <td class="small text-muted"><?= $a['last_login'] ? e(date('d M Y H:i', strtotime($a['last_login']))) : '—' ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='editAdmin(<?= json_encode($a) ?>)'><i class="bi bi-pencil"></i></button>
          <?php if ((int)$a['id'] !== (int)$_SESSION['admin_id']): ?>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="adminModal">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="a_id">
      <div class="modal-header"><h5 class="modal-title" id="adminModalTitle">Add Admin</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Name</label><input type="text" name="name" id="a_name" class="form-control mb-3" required>
        <label class="form-label">Email</label><input type="email" name="email" id="a_email" class="form-control mb-3" required>
        <label class="form-label">Role</label>
        <select name="role_id" id="a_role" class="form-select mb-3">
          <?php foreach ($roleList as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Password (leave blank to keep / auto-generate)</label>
        <input type="password" name="password" class="form-control mb-3">
        <label class="form-label">Status</label>
        <select name="status" id="a_status" class="form-select"><option value="active">Active</option><option value="disabled">Disabled</option></select>
      </div>
      <div class="modal-footer"><button class="btn btn-primary text-white">Save</button></div>
    </form>
  </div></div>
</div>
<script>
function resetAdminForm(){document.getElementById('adminModalTitle').textContent='Add Admin';document.getElementById('a_id').value='';document.getElementById('a_name').value='';document.getElementById('a_email').value='';document.getElementById('a_status').value='active';}
function editAdmin(a){document.getElementById('adminModalTitle').textContent='Edit Admin';document.getElementById('a_id').value=a.id;document.getElementById('a_name').value=a.name;document.getElementById('a_email').value=a.email;document.getElementById('a_role').value=a.role_id;document.getElementById('a_status').value=a.status;new bootstrap.Modal(document.getElementById('adminModal')).show();}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
