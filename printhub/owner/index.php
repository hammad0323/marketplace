<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
$c = shop_dashboard($sid);
$st = subscription_state($sid, $OWNER['shop']);
$new = owner_order_list($sid, 'new', '', 1, 10)['items'];
$active = owner_order_list($sid, 'active', '', 1, 10)['items'];
owner_header('Dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?= stat_card("Today's orders", (string) ($c['today'] ?? 0), 'calendar-day', 'teal', '', 'owner/orders.php?status=today') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Waiting for you', (string) ($c['pending'] ?? 0), 'hourglass-split', 'amber', 'Accept or send a price', 'owner/orders.php?status=new') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Accepted', (string) ($c['accepted'] ?? 0), 'check2-circle', 'blue', 'Incl. quotes & payments', 'owner/orders.php?status=active') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Printing', (string) ($c['printing'] ?? 0), 'printer', 'violet', ($c['ready'] ?? 0) . ' ready for collection', 'owner/orders.php?status=printing') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Completed', (string) ($c['completed'] ?? 0), 'bag-check', 'green', '', 'owner/orders.php?status=completed') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Cancelled', (string) ($c['cancelled'] ?? 0), 'x-circle', 'rose', 'Rejected or cancelled', 'owner/orders.php?status=closed') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card("Today's revenue", money($c['revenue_today']), 'cash-coin', 'teal', money($c['revenue_month']) . ' this month', 'owner/reports.php') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Subscription', $st['end_date'] ? format_date($st['end_date']) : '—', 'calendar-check', in_array($st['state'], ['active'], true) ? 'slate' : 'rose', e($st['label']), 'owner/subscription.php') ?></div>
</div>
<div class="row g-3">
<div class="col-xl-7"><div class="p-card"><div class="p-card-head"><i class="bi bi-inbox"></i> New orders<a class="ms-auto small" href="<?= e(path('owner/orders.php?status=new')) ?>">All</a></div>
<div class="list-group list-group-flush" data-new-orders>
<?php foreach ($new as $o): ?><a class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" href="<?= e(path('owner/order.php?id=' . $o['id'])) ?>">
    <span class="p-stat-icon" style="background:#FEF3C7;color:#B45309;width:40px;height:40px"><i class="bi bi-file-earmark-arrow-down"></i></span>
    <span class="flex-grow-1"><strong><?= e($o['order_number']) ?></strong> · <?= e($o['customer_name']) ?><br><small class="text-muted"><?= e($o['categories'] ?: 'Print order') ?> · <?= (int) $o['items'] ?> item<?= $o['items'] > 1 ? 's' : '' ?> · <?= e(time_ago($o['created_at'])) ?></small></span>
    <span class="text-end"><?= $o['amount'] !== null ? '<strong>' . e(money($o['amount'])) . '</strong>' : '<span class="badge text-bg-primary">Needs price</span>' ?></span></a><?php endforeach; ?>
<?php if (!$new): ?><div class="p-empty"><i class="bi bi-check2-circle"></i>No new orders. New orders appear here automatically.</div><?php endif; ?>
</div></div></div>
<div class="col-xl-5"><div class="p-card"><div class="p-card-head"><i class="bi bi-printer"></i> In progress</div>
<div class="list-group list-group-flush">
<?php foreach ($active as $o): ?><a class="list-group-item list-group-item-action py-3" href="<?= e(path('owner/order.php?id=' . $o['id'])) ?>"><div class="d-flex"><strong><?= e($o['order_number']) ?></strong><span class="ms-auto"><?= order_badge($o['status']) ?></span></div><small class="text-muted"><?= e($o['customer_name']) ?> · <?= $o['amount'] !== null ? e(money($o['amount'])) : 'quote pending' ?></small></a><?php endforeach; ?>
<?php if (!$active): ?><div class="p-empty py-4"><i class="bi bi-cup-hot"></i>Nothing in progress.</div><?php endif; ?>
</div></div>
<div class="p-card"><div class="p-card-body d-flex gap-3 align-items-center"><img src="<?= e(path('api/v1/owner/qr.png?scale=6')) ?>" alt="Shop QR" width="96" height="96" style="border-radius:10px;border:1px solid #E2E8F0">
<div><strong>Your shop QR code</strong><p class="small text-muted mb-2">Customers scan it to send files. Link: <a href="<?= e(shop_url($OWNER['shop'])) ?>" target="_blank"><?= e($OWNER['shop']['shop_code']) ?></a></p><a class="btn btn-sm btn-primary" href="<?= e(path('owner/qr.php')) ?>">Print / share QR</a></div></div></div>
</div></div>
<script>document.addEventListener('DOMContentLoaded', function () { jQuery(document).on('ph:notifications', function (e, items) { if (items.some(function (n) { return n.type === 'new_order'; })) { location.reload(); } }); });</script>
<?php owner_footer();
