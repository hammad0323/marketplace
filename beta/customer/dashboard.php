<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();

$orderCount = db_fetch_one("SELECT COUNT(*) c FROM orders WHERE customer_id=?", 'i', [$customer['id']])['c'];
$wishlistCount = db_fetch_one("SELECT COUNT(*) c FROM wishlists WHERE customer_id=?", 'i', [$customer['id']])['c'];
$addressCount = db_fetch_one("SELECT COUNT(*) c FROM addresses WHERE customer_id=?", 'i', [$customer['id']])['c'];
$recentOrders = db_fetch_all("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC LIMIT 5", 'i', [$customer['id']]);

$dashRole = 'customer'; $pageTitle = 'My Dashboard'; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
$dashNotifType = 'customer'; $dashNotifId = $customer['id'];
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Welcome, <?= clean($customer['first_name']) ?></h1></div>
<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Orders</span><span class="stat-value"><?= $orderCount ?></span></div>
  <div class="stat-card"><span class="stat-label">Wishlist Items</span><span class="stat-value"><?= $wishlistCount ?></span></div>
  <div class="stat-card"><span class="stat-label">Saved Addresses</span><span class="stat-value"><?= $addressCount ?></span></div>
</div>
<div class="dash-table-card">
  <h3>Recent Orders</h3>
  <table class="dash-table">
    <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if ($recentOrders): foreach ($recentOrders as $o): ?>
        <tr><td><?= clean($o['order_number']) ?></td><td><?= format_price($o['grand_total']) ?></td>
            <td><span class="badge badge-warn"><?= clean($o['order_status']) ?></span></td><td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
            <td><a href="<?= customer_url('order-details.php?id=' . $o['id']) ?>" class="btn btn-sm btn-outline">View</a></td></tr>
      <?php endforeach; else: ?><tr><td colspan="5"><div class="empty-state"><h3>No orders yet</h3><a class="btn btn-primary" href="<?= base_url() ?>">Start Shopping</a></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
