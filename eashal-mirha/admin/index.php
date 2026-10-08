<?php
require __DIR__ . '/includes/admin.php';

$valid = "status NOT IN ('cancelled','returned')";
$stats = [
    'today_sales'  => (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND DATE(created_at) = CURDATE()"),
    'today_orders' => (int)val('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()'),
    'month_sales'  => (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'total_sales'  => (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid"),
    'orders'       => (int)val('SELECT COUNT(*) FROM orders'),
    'pending'      => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'verify'       => (int)val("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending_verification'"),
    'customers'    => (int)val('SELECT COUNT(*) FROM customers'),
    'products'     => (int)val('SELECT COUNT(*) FROM products'),
    'low_stock'    => (int)val('SELECT COUNT(*) FROM products WHERE status = 1 AND stock <= ?', [(int)setting('low_stock', 3)]),
];

// Sales for the last 14 days
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (rows("SELECT DATE(created_at) d, SUM(total) t FROM orders WHERE $valid AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)") as $r) {
    $days[$r['d']] = (float)$r['t'];
}
$maxDay = max(1, max($days));
$byStatus = [];
foreach (rows('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $byStatus[$r['status']] = (int)$r['n'];
$byMethod = rows("SELECT payment_method m, COUNT(*) n, SUM(total) t FROM orders WHERE $valid GROUP BY payment_method ORDER BY t DESC");
$recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$top = attach_images(rows('SELECT * FROM products ORDER BY sales_count DESC LIMIT 5'));
$low = rows('SELECT id, name, stock, sku FROM products WHERE status = 1 AND stock <= ? ORDER BY stock LIMIT 6', [(int)setting('low_stock', 3)]);

admin_header('Dashboard', 'index');
?>
<div class="welcome">
  <div><h2>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(strtok(admin()['name'], ' ')) ?></h2><p class="muted">Here is what is happening in your store today.</p></div>
  <div class="welcome__actions"><a class="btn btn-primary" href="<?= url('admin/product-edit') ?>"><?= aicon('plus') ?> Add Product</a><a class="btn" href="<?= url('admin/orders?status=pending') ?>">Pending Orders</a></div>
</div>

<div class="stats">
  <div class="stat stat--gold"><span>Today's Sales</span><strong><?= money($stats['today_sales']) ?></strong><small><?= $stats['today_orders'] ?> orders today</small></div>
  <div class="stat"><span>This Month</span><strong><?= money($stats['month_sales']) ?></strong><small>Lifetime <?= money($stats['total_sales']) ?></small></div>
  <a class="stat" href="<?= url('admin/orders?status=pending') ?>"><span>Pending Orders</span><strong><?= $stats['pending'] ?></strong><small><?= $stats['verify'] ?> payments to verify</small></a>
  <a class="stat" href="<?= url('admin/customers') ?>"><span>Customers</span><strong><?= $stats['customers'] ?></strong><small><?= $stats['orders'] ?> orders total</small></a>
  <a class="stat" href="<?= url('admin/products') ?>"><span>Products</span><strong><?= $stats['products'] ?></strong><small class="<?= $stats['low_stock'] ? 'text-danger' : '' ?>"><?= $stats['low_stock'] ?> low on stock</small></a>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h3>Sales — last 14 days</h3><span class="muted"><?= money(array_sum($days)) ?></span></div>
    <div class="bars">
      <?php foreach ($days as $d => $t): ?>
        <div class="bar" title="<?= date('d M', strtotime($d)) ?>: <?= money($t) ?>"><i style="height:<?= max(2, round($t / $maxDay * 100)) ?>%"></i><span><?= date('d', strtotime($d)) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h3>Orders by status</h3></div>
    <div class="status-list">
      <?php foreach (order_statuses() as $k => $lbl): $n = $byStatus[$k] ?? 0; ?>
        <a href="<?= url('admin/orders?status=' . $k) ?>"><?= status_badge($k) ?><span class="meter"><i style="width:<?= $stats['orders'] ? round($n / $stats['orders'] * 100) : 0 ?>%"></i></span><b><?= $n ?></b></a>
      <?php endforeach; ?>
    </div>
    <?php if ($byMethod): ?>
      <h4 class="mt">Revenue by payment method</h4>
      <?php foreach ($byMethod as $m): ?><div class="kv"><span><?= e(payment_label($m['m'])) ?> (<?= (int)$m['n'] ?>)</span><b><?= money($m['t']) ?></b></div><?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2 wide-left">
  <div class="card">
    <div class="card__head"><h3>Recent orders</h3><a href="<?= url('admin/orders') ?>" class="link">View all</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr class="clickable" data-href="<?= url('admin/order-view?id=' . $o['id']) ?>">
          <td><a href="<?= url('admin/order-view?id=' . $o['id']) ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
          <td><?= e($o['name']) ?><small class="block muted"><?= e($o['city']) ?></small></td>
          <td><?= money($o['total']) ?></td>
          <td><?= e(payment_label($o['payment_method'])) ?><br><?= status_badge($o['payment_status']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td class="muted"><?= date('d M, h:i A', strtotime($o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="6" class="empty">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div>
    <div class="card">
      <div class="card__head"><h3>Best sellers</h3></div>
      <?php foreach ($top as $p): ?>
        <a class="mini-prod" href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><img src="<?= e(img($p['image'])) ?>" alt=""><span><?= e($p['name']) ?><small><?= (int)$p['sales_count'] ?> sold · <?= (int)$p['stock'] ?> in stock</small></span></a>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card__head"><h3>Low stock</h3></div>
      <?php foreach ($low as $p): ?>
        <div class="kv"><a href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><b class="<?= $p['stock'] <= 0 ? 'text-danger' : '' ?>"><?= (int)$p['stock'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$low): ?><p class="muted">All products are well stocked.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer();
