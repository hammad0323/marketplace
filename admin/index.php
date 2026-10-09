<?php
require __DIR__ . '/_inc/bootstrap.php';
$admin = require_admin('dashboard.view');

$counts = [];
foreach (db_all('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$total = array_sum($counts);
$today = (int) db_val('SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE()');
$valid = "status NOT IN ('cancelled','refunded')";
$rev = db_one("SELECT
    COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN grand_total END), 0) today,
    COALESCE(SUM(CASE WHEN created_at >= CURDATE() - INTERVAL 6 DAY THEN grand_total END), 0) week,
    COALESCE(SUM(CASE WHEN created_at >= CURDATE() - INTERVAL 29 DAY THEN grand_total END), 0) month,
    COALESCE(SUM(grand_total - refunded_total), 0) all_time
    FROM orders WHERE $valid");
$pay = db_one("SELECT
    SUM(payment_status = 'paid') paid, SUM(payment_status IN ('pending','unpaid') AND $valid) pending,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total - refunded_total END), 0) paid_amount,
    COALESCE(SUM(CASE WHEN payment_status IN ('pending','unpaid') AND $valid THEN grand_total END), 0) pending_amount
    FROM orders");
$days = [];
foreach (db_all("SELECT DATE(created_at) d, SUM(grand_total) t, COUNT(*) n FROM orders WHERE $valid AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)") as $r) {
    $days[$r['d']] = $r;
}
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $series[] = ['d' => $d, 't' => (float) ($days[$d]['t'] ?? 0), 'n' => (int) ($days[$d]['n'] ?? 0)];
}
$maxT = max(1, max(array_column($series, 't')));
$top = db_all("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) qty, SUM(oi.line_total) revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.$valid GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 6");
$low = db_all('SELECT p.id, p.name, p.sku, p.low_stock_threshold, i.quantity, v.label FROM product_inventory i JOIN products p ON p.id = i.product_id
    LEFT JOIN product_variants v ON v.id = i.variant_id WHERE p.track_stock = 1 AND p.status = \'published\' AND (v.id IS NULL OR v.is_active = 1) AND i.quantity <= p.low_stock_threshold ORDER BY i.quantity ASC LIMIT 8');
$recent = db_all('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8');

$warnings = [];
if (setting_bool('show_sample_content')) {
    $warnings[] = ['Sample content (demo testimonials) is visible on the storefront.', 'settings'];
}
foreach (['easypaisa', 'jazzcash', 'card'] as $g) {
    $gw = payment_gateway($g);
    if ($gw && (int) $gw['is_enabled'] && !gateway_is_configured($g)) {
        $warnings[] = [$gw['display_name'] . ' is enabled but not configured — it is hidden from checkout.', 'payments'];
    }
    if ($gw && (int) $gw['is_enabled'] && $gw['mode'] === 'sandbox') {
        $warnings[] = [$gw['display_name'] . ' is in sandbox/test mode.', 'payments'];
    }
}
if (!setting_bool('seo_indexing_enabled', true)) {
    $warnings[] = ['Search engine indexing is switched OFF.', 'seo'];
}
if (setting_bool('maintenance_mode')) {
    $warnings[] = ['Maintenance mode is ON — visitors see the maintenance page.', 'settings'];
}
if (str_ends_with((string) setting('support_email'), '@example.com')) {
    $warnings[] = ['Contact details still use placeholder values.', 'settings'];
}

admin_header('Dashboard', 'index');
?>
<?php if ($warnings && can('settings.general')): ?>
  <div class="alert alert-warning"><strong><i class="bi bi-list-check"></i> Pre-launch checks</strong>
    <ul class="mb-0 mt-1"><?php foreach ($warnings as [$msg, $link]): ?><li><?= e($msg) ?> <a href="<?= e(admin_url($link)) ?>">Fix</a></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <?php
  $cards = [
      ['Total orders', $total, 'bag', 'orders'], ["Today's orders", $today, 'calendar-day', 'orders?date_from=' . date('Y-m-d')],
      ['Pending', $counts['pending'] ?? 0, 'hourglass-split', 'orders?status=pending'], ['Processing', ($counts['processing'] ?? 0) + ($counts['confirmed'] ?? 0), 'gear', 'orders?status=processing'],
      ['Shipped', $counts['shipped'] ?? 0, 'truck', 'orders?status=shipped'], ['Delivered', $counts['delivered'] ?? 0, 'check2-circle', 'orders?status=delivered'],
      ['Cancelled', $counts['cancelled'] ?? 0, 'x-circle', 'orders?status=cancelled'], ['On hold', $counts['on_hold'] ?? 0, 'pause-circle', 'orders?status=on_hold'],
  ];
  foreach ($cards as [$label, $val, $icon, $link]): ?>
    <div class="col-6 col-md-4 col-xl-3"><a class="stat-card" href="<?= e(admin_url($link)) ?>"><span class="stat-card__icon"><i class="bi bi-<?= $icon ?>"></i></span><div class="stat-card__label"><?= e($label) ?></div><div class="stat-card__value"><?= number_format((int) $val) ?></div></a></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-card__label">Revenue today</div><div class="stat-card__value"><?= e(money($rev['today'])) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-card__label">Last 7 days</div><div class="stat-card__value"><?= e(money($rev['week'])) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-card__label">Payments completed</div><div class="stat-card__value"><?= e(money($pay['paid_amount'])) ?></div><small class="text-muted"><?= (int) $pay['paid'] ?> orders</small></div></div>
  <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-card__label">Payments pending</div><div class="stat-card__value"><?= e(money($pay['pending_amount'])) ?></div><small class="text-muted"><?= (int) $pay['pending'] ?> orders (incl. COD)</small></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Revenue — last 14 days</span><small class="text-muted">30 days: <?= e(money($rev['month'])) ?> · All time (net): <?= e(money($rev['all_time'])) ?></small></div>
      <div class="card-body pb-4">
        <div class="chart-bars">
          <?php foreach ($series as $s): ?><div class="chart-bars__bar" style="height: <?= max(1, round($s['t'] / $maxT * 100)) ?>%" data-label="<?= e(date('d M', strtotime($s['d']))) ?>: <?= e(money($s['t'])) ?> (<?= $s['n'] ?>)"><span><?= e(date('d', strtotime($s['d']))) ?></span></div><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-exclamation-triangle text-warning"></i> Low stock</span><?php if (can('inventory.manage')): ?><a href="<?= e(admin_url('inventory', ['filter' => 'low'])) ?>" class="small">Inventory</a><?php endif; ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($low as $l): ?>
          <li class="list-group-item d-flex justify-content-between"><span><?= e($l['name']) ?><?= $l['label'] ? ' <small class="text-muted">— ' . e($l['label']) . '</small>' : '' ?></span><span class="badge text-bg-<?= (int) $l['quantity'] <= 0 ? 'danger' : 'warning' ?>"><?= (int) $l['quantity'] ?></span></li>
        <?php endforeach; ?>
        <?php if (!$low): ?><li class="list-group-item text-muted">All stock levels are healthy.</li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="col-xl-8">
    <div class="card"><div class="card-header d-flex justify-content-between"><span>Recent orders</span><a class="small" href="<?= e(admin_url('orders')) ?>">All orders</a></div>
      <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $o): ?>
          <tr><td><a href="<?= e(admin_url('order-view', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number']) ?></strong></a><br><small class="text-muted"><?= e(format_date($o['created_at'], true)) ?></small></td>
            <td><?= e($o['customer_name']) ?></td><td><?= badge($o['status']) ?></td><td><?= e(payment_method_label($o['payment_method'])) ?><br><?= badge($o['payment_status']) ?></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="text-muted text-center py-4">No orders yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card"><div class="card-header">Top-selling products</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($top as $t): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($t['product_name']) ?><br><small class="text-muted"><?= e(money($t['revenue'])) ?></small></span><strong><?= (int) $t['qty'] ?></strong></li><?php endforeach; ?>
        <?php if (!$top): ?><li class="list-group-item text-muted">Sales data will appear once orders are placed.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php admin_footer();
