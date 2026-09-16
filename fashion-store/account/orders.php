<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();
$orders = mysqli_query($mysqli, "SELECT * FROM orders WHERE customer_id = {$customer['id']} ORDER BY created_at DESC");

$pageTitle = 'My Orders | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <h1 class="h4 font-serif mb-4">My Orders</h1>
      <div class="summary-box">
        <?php if (mysqli_num_rows($orders) === 0): ?>
          <p class="text-muted mb-0">You haven't placed any orders yet. <a href="<?= url('shop') ?>">Start shopping</a>.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Order #</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php while ($o = mysqli_fetch_assoc($orders)): ?>
            <tr>
              <td><?= e($o['order_number']) ?></td>
              <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
              <td><?= format_price($o['total']) ?></td>
              <td><?= e(strtoupper($o['payment_method'])) ?></td>
              <td><span class="badge status-badge status-<?= e($o['order_status']) ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
              <td><a href="<?= e(url('account/order/' . $o['id'])) ?>" class="small">View</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
