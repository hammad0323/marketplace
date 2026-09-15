<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        mysqli_query($mysqli, "UPDATE customers SET status = IF(status='active','disabled','active') WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Customer status updated.');
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM customers WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Customer deleted.');
    } elseif ($action === 'reset_password') {
        $newPass = bin2hex(random_bytes(4));
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($mysqli, "UPDATE customers SET password_hash = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $hash, (int)$_POST['id']);
        mysqli_stmt_execute($stmt);
        flash_set('success', "Password reset. Temporary password: $newPass");
    }
    redirect('customers.php');
}

$search = trim($_GET['q'] ?? '');
$where = '';
$types = '';
$params = [];
if ($search !== '') { $where = "WHERE name LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%')"; $types = 'ss'; $params = [$search, $search]; }
$stmt = mysqli_prepare($mysqli, "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count FROM customers c $where ORDER BY c.created_at DESC");
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$customers = mysqli_stmt_get_result($stmt);
?>
<h1 class="page-title mb-4">Customers</h1>
<div class="admin-card mb-3">
  <form class="row g-2"><div class="col-md-6"><input type="text" name="q" class="form-control" placeholder="Search name or email" value="<?= e($search) ?>"></div><div class="col-md-2"><button class="btn btn-outline-dark w-100">Search</button></div></form>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php while ($c = mysqli_fetch_assoc($customers)): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone']) ?></td>
        <td><?= (int)$c['order_count'] ?></td>
        <td><span class="badge <?= $c['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($c['status']) ?></span></td>
        <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
        <td class="text-end text-nowrap">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Reset password"><i class="bi bi-key"></i></button></form>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle2-on"></i></button></form>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
