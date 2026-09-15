<?php
$pageTitle = 'Reviews';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int)$_POST['id'];
    if ($action === 'approve') mysqli_query($mysqli, "UPDATE reviews SET status='approved' WHERE id=$id");
    elseif ($action === 'reject') mysqli_query($mysqli, "UPDATE reviews SET status='rejected' WHERE id=$id");
    elseif ($action === 'delete') mysqli_query($mysqli, "DELETE FROM reviews WHERE id=$id");
    flash_set('success', 'Review updated.');
    redirect('reviews.php');
}

$filter = $_GET['status'] ?? '';
$where = $filter ? "WHERE r.status = '" . mysqli_real_escape_string($mysqli, $filter) . "'" : '';
$reviews = mysqli_query($mysqli, "SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id $where ORDER BY r.created_at DESC");
?>
<h1 class="page-title mb-4">Reviews</h1>
<ul class="nav nav-pills mb-4">
  <li class="nav-item"><a class="nav-link <?= $filter===''?'active':'' ?>" href="?">All</a></li>
  <li class="nav-item"><a class="nav-link <?= $filter==='pending'?'active':'' ?>" href="?status=pending">Pending</a></li>
  <li class="nav-item"><a class="nav-link <?= $filter==='approved'?'active':'' ?>" href="?status=approved">Approved</a></li>
  <li class="nav-item"><a class="nav-link <?= $filter==='rejected'?'active':'' ?>" href="?status=rejected">Rejected</a></li>
</ul>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Comment</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($r = mysqli_fetch_assoc($reviews)): ?>
      <tr>
        <td><?= e($r['product_name']) ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
        <td class="small"><?= e($r['comment']) ?></td>
        <td><span class="badge <?= $r['status']==='approved'?'text-bg-success':($r['status']==='rejected'?'text-bg-danger':'text-bg-warning') ?>"><?= e($r['status']) ?></span></td>
        <td class="text-end text-nowrap">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></button></form>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-lg"></i></button></form>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
