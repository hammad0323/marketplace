<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/includes/admin_header.php';

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($status !== '') { $where[] = 'order_status = ?'; $types .= 's'; $params[] = $status; }
if ($search !== '') { $where[] = "(order_number LIKE CONCAT('%',?,'%') OR guest_name LIKE CONCAT('%',?,'%') OR guest_email LIKE CONCAT('%',?,'%'))"; $types .= 'sss'; $params[] = $search; $params[] = $search; $params[] = $search; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = mysqli_prepare($mysqli, "SELECT COUNT(*) c FROM orders $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, ceil($total / $perPage));

$sql = "SELECT * FROM orders $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = mysqli_prepare($mysqli, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);

$statuses = ['pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','refunded'];
?>
<h1 class="page-title mb-4">Orders</h1>

<div class="admin-card mb-3">
  <form class="row g-2">
    <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Search order # / customer" value="<?= e($search) ?>"></div>
    <div class="col-md-4">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-dark w-100">Filter</button></div>
  </form>
</div>

<div class="admin-card">
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Payment Status</th><th>Order Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php while ($o = mysqli_fetch_assoc($orders)): ?>
      <tr onclick="location.href='order_view.php?id=<?= (int)$o['id'] ?>'" style="cursor:pointer">
        <td><?= e($o['order_number']) ?></td>
        <td><?= e($o['guest_name'] ?: 'Customer #' . $o['customer_id']) ?></td>
        <td><?= format_price($o['total']) ?></td>
        <td><?= e(strtoupper($o['payment_method'])) ?></td>
        <td><span class="badge text-bg-light border"><?= e(ucfirst($o['payment_status'])) ?></span></td>
        <td><span class="badge status-badge status-<?= e($o['order_status']) ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
        <td><?= e(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <nav><ul class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= e($status) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
