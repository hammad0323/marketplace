<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();
$orderCount = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE customer_id = {$customer['id']}"))['c'];
$recentOrders = mysqli_query($mysqli, "SELECT * FROM orders WHERE customer_id = {$customer['id']} ORDER BY created_at DESC LIMIT 5");

$pageTitle = 'My Account | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <h1 class="h4 font-serif mb-1">Welcome back, <?= e($customer['name']) ?></h1>
      <p class="text-muted mb-4">You have placed <?= (int)$orderCount ?> order(s) so far.</p>
      <div class="summary-box">
        <h2 class="h6 mb-3">Recent Orders</h2>
        <?php if (mysqli_num_rows($recentOrders) === 0): ?>
          <p class="text-muted mb-0">You haven't placed any orders yet. <a href="<?= BASE_URL ?>/shop.php">Start shopping</a>.</p>
        <?php else: ?>
        <table class="table align-middle">
          <thead><tr><th>Order #</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php while ($o = mysqli_fetch_assoc($recentOrders)): ?>
            <tr>
              <td><?= e($o['order_number']) ?></td>
              <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
              <td><?= format_price($o['total']) ?></td>
              <td><span class="badge status-badge status-<?= e($o['order_status']) ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
              <td><a href="order_detail.php?id=<?= (int)$o['id'] ?>" class="small">View</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
