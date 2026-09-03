<?php
require __DIR__ . '/../config/config.php';
require_permission('view_dashboard');
$shopId = active_shop_id();
$shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$shopId]);

$stats = [
    'revenue' => db_fetch_one("SELECT COALESCE(SUM(vendor_earning),0) t FROM shop_orders WHERE shop_id=? AND status='delivered'", 'i', [$shopId])['t'],
    'orders' => db_fetch_one("SELECT COUNT(*) c FROM shop_orders WHERE shop_id=?", 'i', [$shopId])['c'],
    'pending_orders' => db_fetch_one("SELECT COUNT(*) c FROM shop_orders WHERE shop_id=? AND status='placed'", 'i', [$shopId])['c'],
    'products' => db_fetch_one("SELECT COUNT(*) c FROM products WHERE shop_id=?", 'i', [$shopId])['c'],
    'customers' => db_fetch_one("SELECT COUNT(DISTINCT o.customer_id) c FROM shop_orders so JOIN orders o ON o.id=so.order_id WHERE so.shop_id=?", 'i', [$shopId])['c'],
    'pending_commission' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status IN ('pending','overdue')", 'i', [$shopId])['t'],
    'overdue_commission' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status='overdue'", 'i', [$shopId])['t'],
    'staff_count' => db_fetch_one("SELECT COUNT(*) c FROM shop_staff WHERE shop_id=?", 'i', [$shopId])['c'],
];
$salesTrend = db_fetch_all("SELECT DATE(so.created_at) d, SUM(so.subtotal) t FROM shop_orders so WHERE so.shop_id=? AND so.created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(so.created_at) ORDER BY d", 'i', [$shopId]);
$recentOrders = db_fetch_all("SELECT so.*, o.order_number, c.first_name, c.last_name FROM shop_orders so JOIN orders o ON o.id=so.order_id JOIN customers c ON c.id=o.customer_id WHERE so.shop_id=? ORDER BY so.created_at DESC LIMIT 8", 'i', [$shopId]);

$employeeLimit = $shop['employee_limit_override'] ?? (int)get_setting('employee_limit', 3);

$dashRole = current_shop_owner() ? 'shop' : 'employee'; $pageTitle = 'Dashboard';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1><?= clean($shop['shop_name']) ?> <span class="badge badge-<?= $shop['status'] === 'active' ? 'success' : 'warn' ?>"><?= clean(str_replace('_',' ',$shop['status'])) ?></span></h1></div>
<?php if ($shop['status'] === 'payment_overdue'): ?>
  <div class="alert alert-error">Your commission payment is overdue. Your shop has been restricted until the outstanding amount is paid. <a href="<?= shop_url('payments.php') ?>">Pay Now</a></div>
<?php elseif (in_array($shop['status'], ['suspended','banned'], true)): ?>
  <div class="alert alert-error">Your shop is <?= clean($shop['status']) ?>. <?= clean($shop['status_reason']) ?></div>
<?php endif; ?>

<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Revenue</span><span class="stat-value"><?= format_price($stats['revenue']) ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Orders</span><span class="stat-value"><?= $stats['orders'] ?></span></div>
  <div class="stat-card warn"><span class="stat-label">Pending Orders</span><span class="stat-value"><?= $stats['pending_orders'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Products</span><span class="stat-value"><?= $stats['products'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Customers</span><span class="stat-value"><?= $stats['customers'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Staff</span><span class="stat-value"><?= $stats['staff_count'] ?> / <?= $employeeLimit ?></span></div>
  <div class="stat-card warn"><span class="stat-label">Pending Commission</span><span class="stat-value"><?= format_price($stats['pending_commission']) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Overdue Commission</span><span class="stat-value"><?= format_price($stats['overdue_commission']) ?></span></div>
</div>

<div class="chart-grid">
  <div class="chart-card"><h3>Sales — Last 14 Days</h3><canvas id="salesChart"></canvas></div>
</div>

<div class="dash-table-card">
  <h3>Recent Orders</h3>
  <table class="dash-table">
    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if ($recentOrders): foreach ($recentOrders as $o): ?>
        <tr><td><?= clean($o['shop_order_number']) ?></td><td><?= clean($o['first_name'] . ' ' . $o['last_name']) ?></td><td><?= format_price($o['subtotal']) ?></td>
            <td><span class="badge badge-<?= $o['status']==='delivered'?'success':($o['status']==='cancelled'?'danger':'warn') ?>"><?= clean($o['status']) ?></span></td>
            <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td><td><a href="<?= shop_url('orders.php?id=' . $o['id']) ?>" class="btn btn-sm btn-outline">View</a></td></tr>
      <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No orders yet</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: { labels: <?= json_encode(array_column($salesTrend, 'd')) ?>, datasets: [{ label: 'Sales', data: <?= json_encode(array_map('floatval', array_column($salesTrend, 't'))) ?>, borderColor: '#2f6fed', tension: 0.3 }] }
});
</script>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
