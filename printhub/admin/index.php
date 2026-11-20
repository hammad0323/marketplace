<?php
require __DIR__ . '/_init.php';
$s = admin_stats();
$charts = admin_charts();
$recentShops = db_all('SELECT * FROM shops WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 6');
$recentOrders = db_all('SELECT o.order_number, o.status, o.created_at, o.customer_name, sh.name shop_name, COALESCE(o.final_amount, o.quoted_amount, o.estimated_amount) amount FROM orders o JOIN shops sh ON sh.id = o.shop_id ORDER BY o.id DESC LIMIT 8');
admin_header('Dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?= stat_card('Registered shops', (string) $s['total'], 'shop', 'teal', $s['new_shops_month'] . ' new this month', 'admin/shops.php') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Active shops', (string) $s['active'], 'check-circle', 'green', $s['grace'] ? $s['grace'] . ' in grace period' : 'Subscriptions running', 'admin/shops.php?state=active') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Expired subscriptions', (string) ($s['expired'] + $s['none']), 'calendar-x', 'rose', 'Ordering closed', 'admin/shops.php?state=expired') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Suspended shops', (string) $s['suspended'], 'pause-circle', 'slate', 'By admin', 'admin/shops.php?state=suspended') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Monthly recurring revenue', money($s['mrr']), 'graph-up-arrow', 'teal', 'Active + grace shops') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Collected payments', money($s['collected']), 'cash-stack', 'blue', money($s['collected_month']) . ' this month', 'admin/payments.php') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Pending payments', (string) $s['pending_payments'], 'hourglass-split', 'amber', money($s['pending_payments_amount']) . ' to verify', 'admin/payments.php?status=pending') ?></div>
    <div class="col-6 col-xl-3"><?= stat_card('Customer print orders', number_format($s['orders_total']), 'receipt', 'violet', number_format($s['orders_completed']) . ' completed · ' . number_format($s['orders_pending']) . ' pending', 'admin/orders.php') ?></div>
</div>

<div class="row g-3">
    <div class="col-xl-8"><div class="p-card"><div class="p-card-head"><i class="bi bi-bar-chart"></i> Monthly subscription revenue</div><div class="p-card-body"><div class="chart-box"><canvas id="chRevenue"></canvas></div></div></div></div>
    <div class="col-xl-4"><div class="p-card"><div class="p-card-head"><i class="bi bi-pie-chart"></i> Active vs expired shops</div><div class="p-card-body"><div class="chart-box"><canvas id="chStatus"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-shop"></i> New shop registrations</div><div class="p-card-body"><div class="chart-box"><canvas id="chShops"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-activity"></i> Monthly order activity</div><div class="p-card-body"><div class="chart-box"><canvas id="chOrders"></canvas></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-bell"></i> Subscription expiry notifications<a class="ms-auto small" href="<?= e(path('admin/subscriptions.php')) ?>">All subscriptions</a></div>
        <div class="table-responsive"><table class="table p-table"><thead><tr><th>Shop</th><th>Ends</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($s['expiring'] as $x): $st = $x['state']; ?>
            <tr><td><a href="<?= e(path('admin/shop.php?id=' . $x['shop']['id'])) ?>"><?= e($x['shop']['name']) ?></a><div class="small text-muted"><?= e($x['shop']['city']) ?></div></td>
            <td class="small"><?= e(format_date($st['end_date'])) ?><div class="text-muted"><?= $st['days_left'] >= 0 ? 'in ' . $st['days_left'] . ' day' . ($st['days_left'] === 1 ? '' : 's') : abs($st['days_left']) . ' days ago' ?></div></td>
            <td><?= sub_badge($st['state']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(path('admin/payments.php?record=' . $x['shop']['id'])) ?>">Renew</a></td></tr>
        <?php endforeach; ?>
        <?php if (!$s['expiring']): ?><tr><td colspan="4"><div class="p-empty py-4"><i class="bi bi-check2-circle"></i>No subscriptions expiring soon.</div></td></tr><?php endif; ?>
        </tbody></table></div></div></div>
    <div class="col-xl-6"><div class="p-card"><div class="p-card-head"><i class="bi bi-receipt"></i> Latest orders<a class="ms-auto small" href="<?= e(path('admin/orders.php')) ?>">All orders</a></div>
        <div class="table-responsive"><table class="table p-table"><thead><tr><th>Order</th><th>Shop</th><th>Status</th><th class="text-end">Amount</th></tr></thead><tbody>
        <?php foreach ($recentOrders as $o): ?>
            <tr><td><strong><?= e($o['order_number']) ?></strong><div class="small text-muted"><?= e(time_ago($o['created_at'])) ?></div></td><td class="small"><?= e($o['shop_name']) ?></td>
            <td><?= order_badge($o['status']) ?></td><td class="text-end"><?= $o['amount'] !== null ? e(money($o['amount'])) : '<span class="text-muted small">Quote</span>' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentOrders): ?><tr><td colspan="4"><div class="p-empty py-4"><i class="bi bi-inbox"></i>No orders yet.</div></td></tr><?php endif; ?>
        </tbody></table></div></div></div>
</div>

<div class="p-card"><div class="p-card-head"><i class="bi bi-stars"></i> New registrations<a class="ms-auto small" href="<?= e(path('admin/shop-form.php')) ?>"><i class="bi bi-plus"></i> Add shop</a></div>
    <div class="table-responsive"><table class="table p-table"><thead><tr><th>Shop</th><th>Shop ID</th><th>Owner</th><th>City</th><th>Subscription</th><th>Created</th></tr></thead><tbody>
    <?php foreach ($recentShops as $sh): $st = subscription_state((int) $sh['id'], $sh); ?>
        <tr><td><a href="<?= e(path('admin/shop.php?id=' . $sh['id'])) ?>"><strong><?= e($sh['name']) ?></strong></a></td><td><code><?= e($sh['shop_code']) ?></code></td><td><?= e($sh['owner_name']) ?></td><td><?= e($sh['city']) ?></td><td><?= sub_badge($st['state']) ?></td><td class="small text-muted"><?= e(format_date($sh['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recentShops): ?><tr><td colspan="6"><div class="p-empty"><i class="bi bi-shop"></i>No shops yet. <a href="<?= e(path('admin/shop-form.php')) ?>">Create the first shop</a>.</div></td></tr><?php endif; ?>
    </tbody></table></div></div>

<script>window.PH_CHARTS = <?= json_encode($charts) ?>;</script>
<?php admin_footer(['vendor/chartjs/chart.umd.js', 'js/admin-charts.js']);
