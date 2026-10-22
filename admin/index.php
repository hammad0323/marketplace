<?php
require __DIR__ . '/includes/init.php';
admin_require('dashboard');
$expired = orders_expire_unpaid();
$rev = sql_revenue_condition('o');
$net = 'o.grand_total - o.amount_refunded';

$stats = db_row("SELECT
    COALESCE(SUM(CASE WHEN $rev THEN $net END), 0) AS total_sales,
    COALESCE(SUM(CASE WHEN $rev AND DATE(o.created_at) = CURDATE() THEN $net END), 0) AS today_sales,
    COALESCE(SUM(CASE WHEN $rev AND o.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN $net END), 0) AS month_sales,
    COUNT(*) AS total_orders,
    SUM(DATE(o.created_at) = CURDATE()) AS today_orders,
    COALESCE(SUM(CASE WHEN o.payment_method = 'cod' AND o.payment_status <> 'paid' AND o.status IN ('confirmed','processing','shipped') THEN o.grand_total END), 0) AS cod_due,
    COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled','refunded') AND o.payment_status NOT IN ('paid','refunded','partially_refunded') THEN o.grand_total END), 0) AS pipeline
    FROM orders o");
$byStatus = array_column(db_all('SELECT status, COUNT(*) n FROM orders GROUP BY status'), 'n', 'status');
$payments = db_all("SELECT o.payment_method, COUNT(*) n, SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) paid_n,
    COALESCE(SUM(CASE WHEN $rev THEN $net END),0) paid_total, COALESCE(SUM(CASE WHEN o.payment_status IN ('pending','unpaid') AND o.status NOT IN ('cancelled','refunded') THEN o.grand_total END),0) pending_total,
    SUM(o.payment_status = 'failed') failed_n FROM orders o GROUP BY o.payment_method");
$best = db_all("SELECT oi.product_name, oi.product_id, SUM(oi.qty) units, SUM(oi.line_total) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status NOT IN ('cancelled','refunded') GROUP BY oi.product_id, oi.product_name ORDER BY units DESC LIMIT 6");
$topCats = db_all("SELECT c.name, SUM(oi.qty) units, SUM(oi.line_total) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id JOIN categories c ON c.id = p.category_id WHERE o.status NOT IN ('cancelled','refunded') GROUP BY c.id ORDER BY revenue DESC LIMIT 6");
$lowStock = db_all("SELECT p.id, p.name, v.sku, v.stock_qty, COALESCE(v.low_stock_threshold, p.low_stock_threshold) th FROM product_variants v JOIN products p ON p.id = v.product_id
    WHERE p.track_stock = 1 AND p.allow_backorder = 0 AND v.is_active = 1 AND p.status = 'published' AND v.stock_qty <= COALESCE(v.low_stock_threshold, p.low_stock_threshold)
    UNION ALL SELECT p.id, p.name, p.sku, p.stock_qty, p.low_stock_threshold FROM products p WHERE p.has_variants = 0 AND p.track_stock = 1 AND p.allow_backorder = 0 AND p.status = 'published' AND p.stock_qty <= p.low_stock_threshold
    ORDER BY stock_qty ASC LIMIT 8");
$lowCount = (int) db_val("SELECT (SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.track_stock = 1 AND p.allow_backorder = 0 AND v.is_active = 1 AND p.status='published' AND v.stock_qty <= COALESCE(v.low_stock_threshold, p.low_stock_threshold))
    + (SELECT COUNT(*) FROM products p WHERE p.has_variants = 0 AND p.track_stock = 1 AND p.allow_backorder = 0 AND p.status='published' AND p.stock_qty <= p.low_stock_threshold)");
$recentOrders = db_all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$recentCustomers = db_all('SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders FROM customers c ORDER BY c.id DESC LIMIT 6');
$daily = array_column(db_all("SELECT DATE(o.created_at) d, SUM($net) t FROM orders o WHERE $rev AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(o.created_at)"), 't', 'd');
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = (float) ($daily[$d] ?? 0);
}
$maxDay = max(1, max($days));
$unread = admin_can('newsletter') ? (int) db_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0') : 0;
$pendingReviews = admin_can('reviews') ? (int) db_val("SELECT COUNT(*) FROM reviews WHERE status = 'pending'") : 0;
$gatewayIssues = array_filter(payment_methods_all(), fn($m) => in_array(payment_method_state($m), ['unconfigured', 'unverified', 'testing'], true));

admin_header('Dashboard');
?>
<?php if ($expired): ?><div class="alert alert-info"><?= $expired ?> unpaid online order(s) were cancelled automatically and their stock released.</div><?php endif; ?>
<?php if ($gatewayIssues && admin_can('payments')): ?>
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> Online payment methods awaiting setup or testing:
        <?= e(implode(', ', array_map(fn($m) => $m['name'] . ' (' . payment_method_state($m) . ')', $gatewayIssues))) ?>. They are hidden from customers until configured and verified. <a href="<?= e(url('admin/payments/')) ?>">Payment settings</a></div>
<?php endif; ?>

<div class="a-kpis">
    <div class="a-kpi"><span>Total sales</span><strong><?= e(money($stats['total_sales'])) ?></strong><small>Paid, net of refunds</small></div>
    <div class="a-kpi"><span>Today's sales</span><strong><?= e(money($stats['today_sales'])) ?></strong><small><?= (int) $stats['today_orders'] ?> orders placed today</small></div>
    <div class="a-kpi"><span>This month</span><strong><?= e(money($stats['month_sales'])) ?></strong><small>Paid revenue</small></div>
    <div class="a-kpi"><span>Awaiting payment</span><strong><?= e(money($stats['pipeline'])) ?></strong><small>COD due on delivery: <?= e(money($stats['cod_due'])) ?></small></div>
</div>

<div class="a-status-row">
    <?php foreach (['pending' => 'New / pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $l): ?>
        <a href="<?= e(url('admin/orders/?status=' . $k)) ?>" class="a-status-tile s-<?= $k ?>"><strong><?= (int) ($byStatus[$k] ?? 0) ?></strong><span><?= $l ?></span></a>
    <?php endforeach; ?>
    <a href="<?= e(url('admin/orders/')) ?>" class="a-status-tile"><strong><?= (int) $stats['total_orders'] ?></strong><span>All orders</span></a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card a-card"><div class="card-header">Paid sales — last 30 days</div><div class="card-body">
            <div class="a-chart" role="img" aria-label="Daily paid sales for the last 30 days">
                <?php foreach ($days as $d => $v): ?>
                    <div class="a-bar" style="--h:<?= round($v / $maxDay * 100, 1) ?>%" title="<?= e(date('D j M', strtotime($d)) . ': ' . money($v)) ?>"><span></span></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-2"><span><?= e(date('j M', strtotime('-29 days'))) ?></span><span>Today</span></div>
        </div></div>

        <div class="card a-card"><div class="card-header d-flex justify-content-between">Recent orders <a href="<?= e(url('admin/orders/')) ?>">View all</a></div>
            <div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Order</th><th>Customer</th><th>City</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead><tbody>
            <?php foreach ($recentOrders as $o): $city = db_val("SELECT city FROM order_addresses WHERE order_id = ? LIMIT 1", [(int) $o['id']]); ?>
                <tr><td><a href="<?= e(url('admin/orders/view.php?id=' . $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><div class="small text-muted"><?= e(format_datetime($o['created_at'])) ?></div></td>
                <td><?= e($o['customer_name']) ?></td><td><?= e($city) ?></td><td><?= status_badge($o['status']) ?></td><td><?= status_badge($o['payment_status']) ?> <small class="text-muted"><?= e(strtoupper($o['payment_method'])) ?></small></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?><tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>

        <div class="card a-card"><div class="card-header">Payment summary</div>
            <div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Method</th><th>Orders</th><th>Paid</th><th>Failed</th><th class="text-end">Paid revenue</th><th class="text-end">Outstanding</th></tr></thead><tbody>
            <?php foreach ($payments as $p): ?>
                <tr><td><?= e(payment_method_get($p['payment_method'])['name'] ?? $p['payment_method']) ?></td><td><?= (int) $p['n'] ?></td><td><?= (int) $p['paid_n'] ?></td><td><?= (int) $p['failed_n'] ?></td><td class="text-end"><?= e(money($p['paid_total'])) ?></td><td class="text-end"><?= e(money($p['pending_total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted py-3">No payments yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>
    </div>

    <div class="col-xl-4">
        <?php if ($unread || $pendingReviews): ?>
            <div class="card a-card"><div class="card-body d-grid gap-2">
                <?php if ($unread): ?><a href="<?= e(url('admin/newsletter/messages.php')) ?>"><i class="bi bi-envelope"></i> <?= $unread ?> unread contact message(s)</a><?php endif; ?>
                <?php if ($pendingReviews): ?><a href="<?= e(url('admin/reviews/?status=pending')) ?>"><i class="bi bi-star"></i> <?= $pendingReviews ?> review(s) awaiting approval</a><?php endif; ?>
            </div></div>
        <?php endif; ?>
        <div class="card a-card"><div class="card-header d-flex justify-content-between">Low stock <span class="badge text-bg-<?= $lowCount ? 'danger' : 'success' ?>"><?= $lowCount ?></span></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($lowStock as $l): ?>
                    <li class="list-group-item d-flex justify-content-between small"><a href="<?= e(url('admin/inventory/?q=' . urlencode($l['sku']))) ?>"><?= e($l['name']) ?><br><span class="text-muted"><?= e($l['sku']) ?></span></a><span class="fw-bold <?= $l['stock_qty'] <= 0 ? 'text-danger' : 'text-warning' ?>"><?= (int) $l['stock_qty'] ?></span></li>
                <?php endforeach; ?>
                <?php if (!$lowStock): ?><li class="list-group-item text-muted small">All stock levels are healthy.</li><?php endif; ?>
            </ul></div>
        <div class="card a-card"><div class="card-header">Best-selling products</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($best as $b): ?><li class="list-group-item d-flex justify-content-between small"><span><?= e($b['product_name']) ?></span><span><?= (int) $b['units'] ?> sold</span></li><?php endforeach; ?>
                <?php if (!$best): ?><li class="list-group-item text-muted small">No sales yet.</li><?php endif; ?>
            </ul></div>
        <div class="card a-card"><div class="card-header">Top categories</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($topCats as $c): ?><li class="list-group-item d-flex justify-content-between small"><span><?= e($c['name']) ?></span><span><?= e(money($c['revenue'])) ?></span></li><?php endforeach; ?>
                <?php if (!$topCats): ?><li class="list-group-item text-muted small">No sales yet.</li><?php endif; ?>
            </ul></div>
        <div class="card a-card"><div class="card-header">Recent customers</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentCustomers as $c): ?><li class="list-group-item small"><a href="<?= e(url('admin/customers/view.php?id=' . $c['id'])) ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a> <span class="text-muted">· <?= (int) $c['orders'] ?> orders · <?= e(format_date($c['created_at'])) ?></span></li><?php endforeach; ?>
                <?php if (!$recentCustomers): ?><li class="list-group-item text-muted small">No registered customers yet.</li><?php endif; ?>
            </ul></div>
    </div>
</div>
<?php admin_footer();
