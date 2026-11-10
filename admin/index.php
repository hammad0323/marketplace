<?php
require __DIR__ . '/includes/init.php';
admin_require('dashboard');
$valid = "status NOT IN ('cancelled','draft')";
$k = db_row("SELECT
    COALESCE(SUM(CASE WHEN $valid THEN total END),0) AS total_sales,
    COALESCE(SUM(CASE WHEN $valid AND DATE(created_at) = CURDATE() THEN total END),0) AS today_sales,
    COALESCE(SUM(CASE WHEN $valid AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN total END),0) AS month_sales,
    SUM(status <> 'draft') AS total_orders,
    SUM(status = 'pending') AS pending, SUM(status IN ('confirmed','processing','ready_for_pickup','out_for_delivery')) AS processing,
    SUM(status = 'completed') AS completed, SUM(status = 'cancelled') AS cancelled,
    SUM(DATE(created_at) = CURDATE() AND status <> 'draft') AS today_orders
    FROM orders");
$customers = (int) db_val('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
$products = (int) db_val("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status <> 'archived'");
$low = db_all("SELECT p.id, p.name, p.low_stock_threshold, i.variation_id, i.quantity, v.name AS vname FROM inventory i JOIN products p ON p.id = i.product_id
    LEFT JOIN product_variations v ON v.id = i.variation_id
    WHERE p.deleted_at IS NULL AND p.status = 'active' AND p.track_stock = 1 AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0 AND v.is_active = 1))
    AND i.quantity <= p.low_stock_threshold ORDER BY i.quantity ASC LIMIT 10");
$lowCount = (int) db_val("SELECT COUNT(*) FROM inventory i JOIN products p ON p.id = i.product_id WHERE p.deleted_at IS NULL AND p.status = 'active' AND p.track_stock = 1
    AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0)) AND i.quantity <= p.low_stock_threshold");
$best = db_all("SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.$valid AND o.created_at >= ? GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 8", [date('Y-m-d', strtotime('-30 days'))]);
$recent = db_all("SELECT * FROM orders WHERE status <> 'draft' ORDER BY id DESC LIMIT 8");
$pendingRashan = db_all("SELECT * FROM orders WHERE order_type = 'rashan' AND status IN ('pending','confirmed','processing') ORDER BY id DESC LIMIT 5");
// 30-day chart
$daily = [];
foreach (db_all("SELECT DATE(created_at) d, SUM(total) t, COUNT(*) c FROM orders WHERE $valid AND created_at >= ? GROUP BY DATE(created_at)", [date('Y-m-d', strtotime('-29 days'))]) as $r) $daily[$r['d']] = $r;
$labels = $sales = $counts = [];
for ($i = 29; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $labels[] = date('d M', strtotime($d)); $sales[] = (float) ($daily[$d]['t'] ?? 0); $counts[] = (int) ($daily[$d]['c'] ?? 0); }
$channel = db_all("SELECT channel, COUNT(*) c, SUM(total) t FROM orders WHERE $valid AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY channel");

admin_header('Dashboard', 'dashboard');
$kpis = [
    ['Total sales', money($k['total_sales']), 'bi-cash-stack'], ["Today's sales", money($k['today_sales']), 'bi-calendar-day'], ['This month', money($k['month_sales']), 'bi-calendar3'],
    ['Total orders', (int) $k['total_orders'], 'bi-bag'], ['Pending', (int) $k['pending'], 'bi-hourglass-split'], ['Processing', (int) $k['processing'], 'bi-gear'],
    ['Completed', (int) $k['completed'], 'bi-check2-circle'], ['Cancelled', (int) $k['cancelled'], 'bi-x-circle'], ['Customers', $customers, 'bi-people'],
    ['Products', $products, 'bi-box-seam'], ['Low stock', $lowCount, 'bi-exclamation-triangle'], ["Today's orders", (int) $k['today_orders'], 'bi-receipt'],
];
?>
<?php if ($pendingRashan): ?>
<div class="card order-alert mb-3"><div class="card-body py-2">
  <strong><i class="bi bi-basket2 text-success me-1"></i>Monthly Rashan orders waiting</strong>
  <?php foreach ($pendingRashan as $o): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center small border-top py-2 mt-1">
    <a href="<?= aurl('order-view.php?id=' . $o['id']) ?>" class="fw-bold"><?= e($o['order_number']) ?></a> <?= status_badge($o['status']) ?>
    <span><?= e($o['customer_name']) ?> · <a href="tel:<?= e($o['customer_phone']) ?>"><?= e($o['customer_phone']) ?></a></span>
    <span class="text-muted"><?= $o['fulfillment'] === 'pickup' ? '<i class="bi bi-shop"></i> Pickup ' . e(fmt_datetime($o['pickup_at'])) : '<i class="bi bi-truck"></i> ' . e($o['address_line'] . ', ' . $o['area']) ?></span>
    <strong class="ms-auto"><?= money($o['total']) ?></strong>
    <a href="<?= aurl('print-picking.php?id=' . $o['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Picking list</a>
  </div>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>
<div class="row g-3 mb-3">
  <?php foreach ($kpis as [$label, $val, $icon]): ?>
  <div class="col-6 col-md-4 col-xl-2"><div class="kpi"><i class="bi <?= $icon ?>"></i><div><small><?= e($label) ?></small><strong><?= e($val) ?></strong></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Sales - last 30 days</span><a href="<?= aurl('reports.php') ?>" class="small">Reports</a></div><div class="card-body"><canvas id="salesChart" height="110"></canvas></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header">Best sellers (30 days)</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead><tbody>
    <?php foreach ($best as $b): ?><tr><td class="small"><?= e($b['product_name']) ?></td><td class="text-end"><?= (int) $b['qty'] ?></td><td class="text-end small"><?= money($b['revenue']) ?></td></tr><?php endforeach; ?>
    <?php if (!$best): ?><tr><td colspan="3" class="text-muted small p-3">No sales yet.</td></tr><?php endif; ?>
    </tbody></table>
    <?php if ($channel): ?><div class="p-3 small border-top"><strong>This month by channel:</strong> <?php foreach ($channel as $c): ?><span class="badge bg-light text-dark border me-1"><?= $c['channel'] === 'pos' ? 'POS / Shop' : 'Website' ?>: <?= (int) $c['c'] ?> · <?= money($c['t']) ?></span><?php endforeach; ?></div><?php endif; ?>
  </div></div></div>
  <div class="col-xl-8"><div class="card"><div class="card-header d-flex justify-content-between"><span>Recent orders</span><a href="<?= aurl('orders.php') ?>" class="small">All orders</a></div><div class="table-responsive">
    <table class="table table-hover align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($recent as $o): ?>
    <tr onclick="location='<?= aurl('order-view.php?id=' . $o['id']) ?>'" style="cursor:pointer">
      <td class="fw-semibold"><?= e($o['order_number']) ?><?= $o['channel'] === 'pos' ? ' <span class="badge bg-secondary">POS</span>' : '' ?></td>
      <td><?= e($o['customer_name']) ?><br><small class="text-muted"><?= e($o['customer_phone']) ?></small></td>
      <td><?= $o['order_type'] === 'rashan' ? '<span class="badge bg-success">Rashan</span> ' : '' ?><small><?= ucfirst($o['fulfillment']) ?></small></td>
      <td><?= money($o['total']) ?></td><td><?= status_badge($o['status']) ?></td><td class="small text-muted"><?= time_ago($o['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header d-flex justify-content-between"><span>Low stock alerts</span><a href="<?= aurl('inventory.php?filter=low') ?>" class="small">View all (<?= $lowCount ?>)</a></div>
    <ul class="list-group list-group-flush">
    <?php foreach ($low as $l): ?><li class="list-group-item d-flex justify-content-between small"><a href="<?= aurl('inventory.php?q=' . urlencode($l['name'])) ?>"><?= e($l['name'] . ($l['vname'] ? ' - ' . $l['vname'] : '')) ?></a><span class="badge <?= $l['quantity'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= (int) $l['quantity'] ?> left</span></li><?php endforeach; ?>
    <?php if (!$low): ?><li class="list-group-item small text-muted">All products are well stocked.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php admin_footer([asset('vendor/chartjs/chart.umd.min.js')]); ?>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: { labels: <?= json_encode($labels) ?>, datasets: [
    { type: 'line', label: 'Orders', data: <?= json_encode($counts) ?>, borderColor: '#f59e0b', backgroundColor: '#f59e0b', yAxisID: 'y1', tension: .3 },
    { label: 'Sales (Rs.)', data: <?= json_encode($sales) ?>, backgroundColor: 'rgba(15,118,110,.75)', borderRadius: 4 }
  ]},
  options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: 'right', grid: { display: false } } } }
});
</script>
