<?php
require __DIR__ . '/../config/config.php';
require_admin();
$admin = current_admin();

$stats = [
    'total_sales' => db_fetch_one("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE payment_status='paid'")['t'],
    'today_sales' => db_fetch_one("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")['t'],
    'month_sales' => db_fetch_one("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['t'],
    'total_orders' => db_fetch_one("SELECT COUNT(*) c FROM orders")['c'],
    'pending_orders' => db_fetch_one("SELECT COUNT(*) c FROM shop_orders WHERE status='placed'")['c'],
    'completed_orders' => db_fetch_one("SELECT COUNT(*) c FROM shop_orders WHERE status='delivered'")['c'],
    'total_customers' => db_fetch_one("SELECT COUNT(*) c FROM customers")['c'],
    'total_shops' => db_fetch_one("SELECT COUNT(*) c FROM shops")['c'],
    'active_shops' => db_fetch_one("SELECT COUNT(*) c FROM shops WHERE status='active'")['c'],
    'banned_shops' => db_fetch_one("SELECT COUNT(*) c FROM shops WHERE status IN ('banned','suspended')")['c'],
    'total_products' => db_fetch_one("SELECT COUNT(*) c FROM products")['c'],
    'total_commission' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions")['t'],
    'pending_commission' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE payment_status='pending'")['t'],
    'overdue_commission' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE payment_status='overdue'")['t'],
];

$salesTrend = db_fetch_all("SELECT DATE(created_at) d, SUM(grand_total) t FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY d");
$orderStatusData = db_fetch_all("SELECT status, COUNT(*) c FROM shop_orders GROUP BY status");
$commissionData = ['paid' => 0, 'pending' => 0, 'overdue' => 0];
foreach (db_fetch_all("SELECT payment_status, SUM(commission_amount) t FROM commissions GROUP BY payment_status") as $r) $commissionData[$r['payment_status']] = (float)$r['t'];
$topShops = db_fetch_all("SELECT s.shop_name, SUM(so.subtotal) revenue, SUM(so.commission_total) commission, COUNT(*) orders, s.status
    FROM shop_orders so JOIN shops s ON s.id=so.shop_id GROUP BY so.shop_id ORDER BY revenue DESC LIMIT 5");

$pendingShops = db_fetch_one("SELECT COUNT(*) c FROM shops WHERE status='pending'")['c'];
$pendingProducts = db_fetch_one("SELECT COUNT(*) c FROM products WHERE status='pending'")['c'];
$pendingPayments = db_fetch_one("SELECT COUNT(*) c FROM commission_payments WHERE status='submitted'")['c'];

$dashRole = 'admin'; $pageTitle = 'Dashboard'; $dashUserName = $admin['name']; $dashLogoutUrl = admin_url('logout.php');
$dashNotifType = 'admin'; $dashNotifId = 1;
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row">
  <h1>Welcome back, <?= clean($admin['name']) ?></h1>
</div>

<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Total Sales</span><span class="stat-value"><?= format_price($stats['total_sales']) ?></span></div>
  <div class="stat-card"><span class="stat-label">Today's Sales</span><span class="stat-value"><?= format_price($stats['today_sales']) ?></span></div>
  <div class="stat-card"><span class="stat-label">Monthly Sales</span><span class="stat-value"><?= format_price($stats['month_sales']) ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Orders</span><span class="stat-value"><?= $stats['total_orders'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Pending Orders</span><span class="stat-value"><?= $stats['pending_orders'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Completed Orders</span><span class="stat-value"><?= $stats['completed_orders'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Customers</span><span class="stat-value"><?= $stats['total_customers'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Shops</span><span class="stat-value"><?= $stats['total_shops'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Active Shops</span><span class="stat-value"><?= $stats['active_shops'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Banned/Suspended</span><span class="stat-value"><?= $stats['banned_shops'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Products</span><span class="stat-value"><?= $stats['total_products'] ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Commission</span><span class="stat-value"><?= format_price($stats['total_commission']) ?></span></div>
  <div class="stat-card warn"><span class="stat-label">Pending Commission</span><span class="stat-value"><?= format_price($stats['pending_commission']) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Overdue Commission</span><span class="stat-value"><?= format_price($stats['overdue_commission']) ?></span></div>
</div>

<div class="quick-actions">
  <a href="<?= admin_url('products/index.php') ?>" class="qa-btn"><i class="fa-solid fa-box"></i> Products <?php if ($pendingProducts): ?><span class="badge badge-warn"><?= $pendingProducts ?> pending</span><?php endif; ?></a>
  <a href="<?= admin_url('categories/index.php') ?>" class="qa-btn"><i class="fa-solid fa-tags"></i> Categories</a>
  <a href="<?= admin_url('shops/index.php?status=pending') ?>" class="qa-btn"><i class="fa-solid fa-shop"></i> Shops <?php if ($pendingShops): ?><span class="badge badge-warn"><?= $pendingShops ?> pending</span><?php endif; ?></a>
  <a href="<?= admin_url('orders/index.php') ?>" class="qa-btn"><i class="fa-solid fa-receipt"></i> Orders</a>
  <a href="<?= admin_url('payments/index.php') ?>" class="qa-btn"><i class="fa-solid fa-money-bill"></i> Payments <?php if ($pendingPayments): ?><span class="badge badge-warn"><?= $pendingPayments ?> new</span><?php endif; ?></a>
  <a href="<?= admin_url('commissions/index.php') ?>" class="qa-btn"><i class="fa-solid fa-percent"></i> Overdue Commissions</a>
  <a href="<?= admin_url('banners/index.php') ?>" class="qa-btn"><i class="fa-solid fa-image"></i> Banners</a>
  <a href="<?= admin_url('settings/index.php') ?>" class="qa-btn"><i class="fa-solid fa-gear"></i> Settings</a>
</div>

<div class="chart-grid">
  <div class="chart-card">
    <h3>Sales — Last 14 Days</h3>
    <canvas id="salesChart"></canvas>
  </div>
  <div class="chart-card">
    <h3>Orders by Status</h3>
    <canvas id="ordersChart"></canvas>
  </div>
  <div class="chart-card">
    <h3>Commission: Paid vs Pending vs Overdue</h3>
    <canvas id="commissionChart"></canvas>
  </div>
</div>

<div class="dash-table-card">
  <h3>Top Shops</h3>
  <table class="dash-table">
    <thead><tr><th>Shop</th><th>Revenue</th><th>Commission</th><th>Orders</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($topShops as $s): ?>
        <tr>
          <td><?= clean($s['shop_name']) ?></td>
          <td><?= format_price($s['revenue']) ?></td>
          <td><?= format_price($s['commission']) ?></td>
          <td><?= $s['orders'] ?></td>
          <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'warn' ?>"><?= clean($s['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: { labels: <?= json_encode(array_column($salesTrend, 'd')) ?>, datasets: [{ label: 'Sales', data: <?= json_encode(array_map('floatval', array_column($salesTrend, 't'))) ?>, borderColor: '#2f6fed', tension: 0.3, fill: false }] }
});
new Chart(document.getElementById('ordersChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode(array_column($orderStatusData, 'status')) ?>, datasets: [{ data: <?= json_encode(array_map('intval', array_column($orderStatusData, 'c'))) ?>, backgroundColor: ['#2f6fed','#ff7a1a','#0b1f3a','#3ccf6c','#e0442f','#999'] }] }
});
new Chart(document.getElementById('commissionChart'), {
  type: 'bar',
  data: { labels: ['Paid','Pending','Overdue'], datasets: [{ label: 'Commission', data: [<?= $commissionData['paid'] ?>, <?= $commissionData['pending'] ?>, <?= $commissionData['overdue'] ?>], backgroundColor: ['#3ccf6c','#ff7a1a','#e0442f'] }] }
});
</script>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
