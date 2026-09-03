<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();

$count = db_fetch_one("SELECT COUNT(*) c FROM orders WHERE customer_id=?", 'i', [$customer['id']])['c'];
$pagination = paginate((int)$count, 10);
$orders = db_fetch_all("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?", 'iii', [$customer['id'], $pagination['perPage'], $pagination['offset']]);

$dashRole = 'customer'; $pageTitle = 'My Orders'; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>My Orders</h1></div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Order #</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php if ($orders): foreach ($orders as $o): ?>
      <tr>
        <td><?= clean($o['order_number']) ?></td>
        <td><?= format_price($o['grand_total']) ?></td>
        <td><?= clean(str_replace('_',' ',$o['payment_method'])) ?></td>
        <td><span class="badge badge-warn"><?= clean(str_replace('_',' ',$o['order_status'])) ?></span></td>
        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
        <td><a href="<?= customer_url('order-details.php?id=' . $o['id']) ?>" class="btn btn-sm btn-outline">View</a></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-receipt"></i><h3>No orders yet</h3><a class="btn btn-primary" href="<?= base_url() ?>">Start Shopping</a></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, customer_url('orders.php')) ?>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
