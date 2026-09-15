<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$stats = [];
$stats['total_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders"))['c'];
$stats['today_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()"))['c'];
$stats['pending_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE order_status = 'pending'"))['c'];
$stats['processing_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE order_status IN ('confirmed','processing','packed','shipped','out_for_delivery')"))['c'];
$stats['delivered_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE order_status = 'delivered'"))['c'];
$stats['cancelled_orders'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM orders WHERE order_status IN ('cancelled','returned','refunded')"))['c'];
$stats['total_sales'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(total),0) s FROM orders WHERE payment_status = 'paid' OR payment_method = 'cod'"))['s'];
$stats['today_sales'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(total),0) s FROM orders WHERE DATE(created_at) = CURDATE()"))['s'];
$stats['month_sales'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(total),0) s FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())"))['s'];
$stats['customers'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM customers"))['c'];
$stats['products'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM products"))['c'];
$stats['low_stock'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM products WHERE stock_qty <= 5"))['c'];

$recentOrders = mysqli_query($mysqli, "SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");
$lowStockProducts = mysqli_query($mysqli, "SELECT * FROM products WHERE stock_qty <= 5 ORDER BY stock_qty ASC LIMIT 8");

$chartRes = mysqli_query($mysqli, "SELECT DATE(created_at) d, SUM(total) s FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
$chartData = [];
while ($r = mysqli_fetch_assoc($chartRes)) $chartData[$r['d']] = (float)$r['s'];
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('D', strtotime($d));
    $chartValues[] = $chartData[$d] ?? 0;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Dashboard</h1>
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-receipt"></i></div><div><div class="stat-value"><?= (int)$stats['total_orders'] ?></div><div class="stat-label">Total Orders</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-calendar-check"></i></div><div><div class="stat-value"><?= (int)$stats['today_orders'] ?></div><div class="stat-label">Today's Orders</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value"><?= (int)$stats['pending_orders'] ?></div><div class="stat-label">Pending Orders</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-info-subtle text-info"><i class="bi bi-arrow-repeat"></i></div><div><div class="stat-value"><?= (int)$stats['processing_orders'] ?></div><div class="stat-label">Processing</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div><div><div class="stat-value"><?= (int)$stats['delivered_orders'] ?></div><div class="stat-label">Delivered</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-x-circle"></i></div><div><div class="stat-value"><?= (int)$stats['cancelled_orders'] ?></div><div class="stat-label">Cancelled/Returned</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value"><?= format_price($stats['total_sales']) ?></div><div class="stat-label">Total Sales</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-graph-up"></i></div><div><div class="stat-value"><?= format_price($stats['today_sales']) ?></div><div class="stat-label">Today's Sales</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-info-subtle text-info"><i class="bi bi-calendar3"></i></div><div><div class="stat-value"><?= format_price($stats['month_sales']) ?></div><div class="stat-label">Monthly Sales</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-people"></i></div><div><div class="stat-value"><?= (int)$stats['customers'] ?></div><div class="stat-label">Customers</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-hanger"></i></div><div><div class="stat-value"><?= (int)$stats['products'] ?></div><div class="stat-label">Products</div></div></div>
  <div class="stat-card"><div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-value"><?= (int)$stats['low_stock'] ?></div><div class="stat-label">Low Stock Products</div></div></div>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-7">
    <div class="admin-card">
      <h2 class="h6 mb-3">Sales — Last 7 Days</h2>
      <canvas id="salesChart" height="120"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="admin-card">
      <h2 class="h6 mb-3">Low Stock Products</h2>
      <table class="table table-sm">
        <thead><tr><th>Product</th><th>Stock</th></tr></thead>
        <tbody>
        <?php while ($p = mysqli_fetch_assoc($lowStockProducts)): ?>
          <tr><td><?= e($p['name']) ?></td><td><span class="badge text-bg-danger"><?= (int)$p['stock_qty'] ?></span></td></tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="admin-card mt-4">
  <h2 class="h6 mb-3">Recent Orders</h2>
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php while ($o = mysqli_fetch_assoc($recentOrders)): ?>
      <tr onclick="location.href='order_view.php?id=<?= (int)$o['id'] ?>'" style="cursor:pointer">
        <td><?= e($o['order_number']) ?></td>
        <td><?= e($o['guest_name'] ?: 'Registered Customer') ?></td>
        <td><?= format_price($o['total']) ?></td>
        <td><?= e(strtoupper($o['payment_method'])) ?></td>
        <td><span class="badge status-badge status-<?= e($o['order_status']) ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
        <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{ label: 'Sales', data: <?= json_encode($chartValues) ?>, borderColor: '#a5763f', backgroundColor: 'rgba(165,118,63,.15)', tension: .35, fill: true }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
