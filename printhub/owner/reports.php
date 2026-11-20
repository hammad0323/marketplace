<?php
require __DIR__ . '/_init.php';
$period = in_array($_GET['period'] ?? 'month', ['day', 'week', 'month', 'quarter'], true) ? ($_GET['period'] ?? 'month') : 'month';
$r = shop_report(owner_shop_id(), $period);
owner_header('Reports');
?>
<ul class="nav nav-pills gap-1 mb-3"><?php foreach (['day' => 'Today', 'week' => '7 days', 'month' => '30 days', 'quarter' => '90 days'] as $k => $l): ?><li class="nav-item"><a class="nav-link py-1 px-3<?= $period === $k ? ' active' : '' ?>" href="?period=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><?= stat_card('Orders', (string) $r['orders'], 'receipt', 'violet', 'since ' . format_date($r['from'])) ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Revenue', money($r['revenue']), 'cash-stack', 'teal', 'Completed or paid') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Average order', money($r['average_order_value']), 'graph-up', 'blue') ?></div>
    <div class="col-6 col-lg-3"><?= stat_card('Completed / cancelled', $r['completed'] . ' / ' . $r['cancelled'], 'check2-all', 'green') ?></div>
</div>
<div class="row g-3"><div class="col-xl-8"><div class="p-card"><div class="p-card-head">Daily orders &amp; revenue</div><div class="p-card-body"><div class="chart-box"><canvas id="ch"></canvas></div></div></div></div>
<div class="col-xl-4"><div class="p-card"><div class="p-card-head">Most popular services</div><div class="p-card-body">
<?php $max = max(1, ...array_column($r['top_categories'] ?: [['count' => 1]], 'count')); foreach ($r['top_categories'] as $c): ?>
    <div class="mb-2"><div class="d-flex small"><span><?= e($c['name']) ?></span><strong class="ms-auto"><?= (int) $c['count'] ?></strong></div><div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= round($c['count'] / $max * 100) ?>%;background:#0F766E"></div></div></div>
<?php endforeach; ?><?php if (!$r['top_categories']): ?><p class="text-muted small mb-0">No orders in this period.</p><?php endif; ?>
</div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var d = <?= json_encode($r['daily']) ?>;
    new Chart(document.getElementById('ch'), { data: { labels: d.map(function (x) { return x.date.slice(5); }), datasets: [
        { type: 'bar', label: 'Orders', data: d.map(function (x) { return x.orders; }), backgroundColor: '#99F6E4', borderRadius: 4, yAxisID: 'y' },
        { type: 'line', label: 'Revenue (Rs.)', data: d.map(function (x) { return x.revenue; }), borderColor: '#0F766E', tension: .35, pointRadius: 2, yAxisID: 'y1' }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { beginAtZero: true, position: 'right', grid: { display: false } }, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } } });
});
</script>
<?php owner_footer(['vendor/chartjs/chart.umd.js']);
