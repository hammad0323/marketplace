<?php
/**
 * Dashboard. Revenue rule: an order counts as a sale once its payment is
 * recorded as paid (online gateways after verification; COD once the
 * courier's cash is marked collected), minus any refunds. Cancelled and
 * unpaid orders never count as revenue.
 */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('dashboard.view');

const PAID_SQL = "payment_status IN ('paid','partially_refunded')";
$sales = db_one('SELECT COALESCE(SUM(grand_total - refunded_total),0) total, COUNT(*) n FROM orders WHERE ' . PAID_SQL);
$today = db_one('SELECT COALESCE(SUM(grand_total - refunded_total),0) total, COUNT(*) n FROM orders WHERE ' . PAID_SQL . ' AND DATE(COALESCE(paid_at, created_at)) = CURDATE()');
$month = db_val('SELECT COALESCE(SUM(grand_total - refunded_total),0) FROM orders WHERE ' . PAID_SQL . " AND COALESCE(paid_at, created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$pendingValue = db_val("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE payment_status IN ('unpaid','pending') AND status NOT IN ('cancelled','returned')");
$byStatus = [];
foreach (db_all('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $byStatus[$r['status']] = (int)$r['n'];
$totalOrders = array_sum($byStatus);
$newToday = (int)db_val('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()');
$payments = db_all("SELECT payment_method, COUNT(*) n,
    SUM(CASE WHEN payment_status IN ('paid','partially_refunded') THEN grand_total - refunded_total ELSE 0 END) paid,
    SUM(CASE WHEN payment_status IN ('unpaid','pending') AND status NOT IN ('cancelled','returned') THEN grand_total ELSE 0 END) outstanding,
    SUM(payment_status = 'failed') failed
    FROM orders GROUP BY payment_method ORDER BY paid DESC");
$best = db_all("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) units, SUM(oi.line_total) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE " . COMPLETED_ORDER_SQL . " GROUP BY oi.product_id, oi.product_name ORDER BY units DESC LIMIT 6");
$topCats = db_all("SELECT c.name, SUM(oi.line_total) revenue, SUM(oi.quantity) units FROM order_items oi JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id JOIN categories c ON c.id = p.category_id WHERE " . COMPLETED_ORDER_SQL . " GROUP BY c.id, c.name ORDER BY revenue DESC LIMIT 6");
$lowStock = db_all("SELECT v.id, v.sku, p.name, p.id pid, i.quantity, COALESCE(i.low_stock_threshold, p.low_stock_threshold) th FROM product_variants v
    JOIN products p ON p.id = v.product_id JOIN product_inventory i ON i.variant_id = v.id
    WHERE p.track_inventory = 1 AND p.status = 'published' AND v.status = 'active' AND i.quantity <= COALESCE(i.low_stock_threshold, p.low_stock_threshold)
    ORDER BY i.quantity ASC LIMIT 10");
$recentOrders = db_all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$recentCustomers = db_all('SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders FROM customers c ORDER BY c.id DESC LIMIT 6');
$chart = [];
foreach (db_all('SELECT DATE(COALESCE(paid_at, created_at)) d, SUM(grand_total - refunded_total) t FROM orders WHERE ' . PAID_SQL . ' AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d') as $r) $chart[$r['d']] = (float)$r['t'];
$max = max([1] + $chart);
$maxBest = max([1] + array_map(fn($b) => (int)$b['units'], $best));

$admin_title = 'Dashboard';
require __DIR__ . '/partials/header.php';
$stat = fn($label, $value, $sub = '') => '<div class="col-6 col-xl-3"><div class="card stat"><div class="label">' . e($label) . '</div><div class="value">' . $value . '</div><div class="sub">' . $sub . '</div></div></div>';
?>
<div class="row g-3 mb-3">
  <?= $stat('Total sales', money($sales['total']), (int)$sales['n'] . ' paid orders') ?>
  <?= $stat("Today's sales", money($today['total']), (int)$today['n'] . ' paid today · ' . $newToday . ' new orders') ?>
  <?= $stat('This month', money($month), 'Paid, net of refunds') ?>
  <?= $stat('Awaiting payment', money($pendingValue), 'Unpaid/pending, not cancelled') ?>
</div>
<div class="row g-3 mb-3">
  <?php foreach (['pending' => 'New / pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'in_production' => 'In production', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'] as $k => $l): ?>
    <div class="col-6 col-md-3 col-xl"><a class="card stat text-decoration-none text-reset" href="<?= e(admin_url('orders?status=' . $k)) ?>"><div class="label"><?= e($l) ?></div><div class="value"><?= (int)($byStatus[$k] ?? 0) ?></div></a></div>
  <?php endforeach; ?>
  <div class="col-6 col-md-3 col-xl"><div class="card stat"><div class="label">Total orders</div><div class="value"><?= $totalOrders ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header">Paid sales — last 14 days</div><div class="card-body">
      <div class="chart-bars">
        <?php for ($i = 13; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i day")); $v = $chart[$d] ?? 0; ?>
          <div style="height:<?= max(2, round($v / $max * 100)) ?>%" title="<?= e(date('j M', strtotime($d)) . ': ' . money($v)) ?>"></div>
        <?php endfor; ?>
      </div>
      <div class="d-flex justify-content-between small text-muted mt-1"><span><?= date('j M', strtotime('-13 day')) ?></span><span>Today</span></div>
    </div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between">Recent orders <a href="<?= e(admin_url('orders')) ?>" class="small">View all</a></div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
        <tbody><?php foreach ($recentOrders as $o): ?>
          <tr><td><a href="<?= e(admin_url('order-view?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a><div class="small text-muted"><?= e(date('j M, g:ia', strtotime($o['created_at']))) ?></div></td>
            <td><?= e($o['customer_name']) ?></td><td><?= status_badge($o['status']) ?></td><td><?= e(strtoupper($o['payment_method'])) ?> <?= status_badge($o['payment_status']) ?></td><td class="text-end"><?= money($o['grand_total']) ?></td></tr>
        <?php endforeach; if (!$recentOrders): ?><tr><td colspan="5" class="text-muted">No orders yet.</td></tr><?php endif; ?></tbody>
      </table></div>
    </div>

    <div class="card"><div class="card-header">Payment summary</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Method</th><th>Orders</th><th class="text-end">Paid (net)</th><th class="text-end">Outstanding</th><th class="text-end">Failed</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr><td><?= e(strtoupper($p['payment_method'])) ?></td><td><?= (int)$p['n'] ?></td><td class="text-end"><?= money($p['paid']) ?></td><td class="text-end"><?= money($p['outstanding']) ?></td><td class="text-end"><?= (int)$p['failed'] ?></td></tr>
        <?php endforeach; if (!$payments): ?><tr><td colspan="5" class="text-muted">No payments yet.</td></tr><?php endif; ?></tbody>
      </table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header">Best-selling abayas</div><div class="card-body">
      <?php foreach ($best as $b): ?>
        <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($b['product_name']) ?></span><span><?= (int)$b['units'] ?> sold</span></div><div class="bar"><span style="width:<?= round($b['units'] / $maxBest * 100) ?>%"></span></div></div>
      <?php endforeach; if (!$best): ?><p class="text-muted small mb-0">Appears once orders are paid or delivered.</p><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">Top categories</div><ul class="list-group list-group-flush">
      <?php foreach ($topCats as $c): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($c['name']) ?></span><span><?= money($c['revenue']) ?></span></li><?php endforeach; ?>
      <?php if (!$topCats): ?><li class="list-group-item text-muted small">No completed sales yet.</li><?php endif; ?>
    </ul></div>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between">Low stock <a class="small" href="<?= e(admin_url('inventory?low=1')) ?>">Inventory</a></div><ul class="list-group list-group-flush">
      <?php foreach ($lowStock as $l): ?><li class="list-group-item d-flex justify-content-between small"><span><a href="<?= e(admin_url('product-edit?id=' . $l['pid'])) ?>"><?= e($l['name']) ?></a><br><span class="text-muted"><?= e($l['sku']) ?></span></span><span class="badge <?= $l['quantity'] <= 0 ? 'text-bg-danger' : 'text-bg-warning' ?> align-self-center"><?= (int)$l['quantity'] ?></span></li><?php endforeach; ?>
      <?php if (!$lowStock): ?><li class="list-group-item text-muted small">All stock levels are healthy.</li><?php endif; ?>
    </ul></div>
    <div class="card"><div class="card-header">Recent customers</div><ul class="list-group list-group-flush">
      <?php foreach ($recentCustomers as $c): ?><li class="list-group-item small d-flex justify-content-between"><a href="<?= e(admin_url('customer-view?id=' . $c['id'])) ?>"><?= e($c['name']) ?></a><span class="text-muted"><?= (int)$c['orders'] ?> orders</span></li><?php endforeach; ?>
      <?php if (!$recentCustomers): ?><li class="list-group-item text-muted small">No registered customers yet.</li><?php endif; ?>
    </ul></div>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
