<?php
require __DIR__ . '/includes/init.php';
admin_require('reports');
$tab = input('tab', 'sales');
$from = input('from') ?: date('Y-m-01');
$to = input('to') ?: date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
$valid = "o.status NOT IN ('cancelled','draft')";
$data = []; $head = [];
switch ($tab) {
    case 'sales':
        $summary = db_row("SELECT COUNT(*) orders, COALESCE(SUM(o.total),0) revenue, COALESCE(SUM(o.discount_amount),0) discounts, COALESCE(SUM(o.delivery_fee),0) delivery, COALESCE(AVG(o.total),0) avg_order FROM orders o WHERE $valid AND o.created_at BETWEEN ? AND ?", $range);
        $profit = db_row("SELECT COALESCE(SUM(oi.line_total),0) sales, COALESCE(SUM(oi.cost_price * oi.quantity),0) cost FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $valid AND o.created_at BETWEEN ? AND ?", $range);
        $head = ['Date', 'Orders', 'Web', 'POS', 'Rashan', 'Revenue'];
        $data = db_all("SELECT DATE(o.created_at) d, COUNT(*) n, SUM(o.channel = 'web') web, SUM(o.channel = 'pos') pos, SUM(o.order_type = 'rashan') rashan, SUM(o.total) t FROM orders o WHERE $valid AND o.created_at BETWEEN ? AND ? GROUP BY DATE(o.created_at) ORDER BY d", $range);
        $byPay = db_all("SELECT o.payment_method m, COUNT(*) n, SUM(o.total) t FROM orders o WHERE $valid AND o.created_at BETWEEN ? AND ? GROUP BY o.payment_method ORDER BY t DESC", $range);
        break;
    case 'products':
        $head = ['Product', 'SKU', 'Qty sold', 'Revenue', 'Cost', 'Profit'];
        $data = db_all("SELECT oi.product_name, oi.sku, SUM(oi.quantity) q, SUM(oi.line_total) rev, SUM(oi.cost_price * oi.quantity) cost, SUM(oi.line_total - oi.cost_price * oi.quantity) profit FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $valid AND o.created_at BETWEEN ? AND ? GROUP BY oi.product_id, oi.variation_id, oi.product_name, oi.sku ORDER BY q DESC LIMIT 500", $range);
        break;
    case 'categories':
        $head = ['Category', 'Qty sold', 'Revenue'];
        $data = db_all("SELECT COALESCE(c.name, 'Uncategorised') name, SUM(oi.quantity) q, SUM(oi.line_total) rev FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.primary_category_id WHERE $valid AND o.created_at BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY rev DESC", $range);
        break;
    case 'inventory':
        $head = ['Product', 'Variation', 'SKU', 'Stock', 'Unit cost', 'Stock value (cost)', 'Retail value'];
        $data = db_all("SELECT p.name, COALESCE(v.name, '') vname, COALESCE(v.sku, p.sku) sku, i.quantity, COALESCE(v.cost_price, p.cost_price) cost,
            GREATEST(i.quantity,0) * COALESCE(v.cost_price, p.cost_price) value, GREATEST(i.quantity,0) * COALESCE(NULLIF(v.sale_price,0), v.price, NULLIF(p.sale_price,0), p.price) retail
            FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variations v ON v.id = i.variation_id
            WHERE p.deleted_at IS NULL AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0)) ORDER BY value DESC");
        break;
    case 'movement':
        $head = ['Product', 'Stock in', 'Stock out', 'Net', 'Current stock'];
        $data = db_all("SELECT p.name, SUM(CASE WHEN m.qty_change > 0 THEN m.qty_change ELSE 0 END) sin, SUM(CASE WHEN m.qty_change < 0 THEN -m.qty_change ELSE 0 END) sout, SUM(m.qty_change) net,
            (SELECT SUM(quantity) FROM inventory WHERE product_id = p.id) cur FROM inventory_movements m JOIN products p ON p.id = m.product_id WHERE m.created_at BETWEEN ? AND ? GROUP BY p.id, p.name ORDER BY sout DESC LIMIT 500", $range);
        break;
    case 'customers':
        $head = ['Customer', 'Phone', 'Orders', 'Total spent'];
        $data = db_all("SELECT o.customer_name, o.customer_phone, COUNT(*) n, SUM(o.total) t FROM orders o WHERE $valid AND o.created_at BETWEEN ? AND ? AND o.customer_phone <> '00000000000' GROUP BY o.customer_phone, o.customer_name ORDER BY t DESC LIMIT 200", $range);
        break;
    case 'cashiers':
        $head = ['Session', 'Cashier', 'Opened', 'Closed', 'Opening cash', 'Sales', 'Expected cash', 'Counted cash', 'Difference'];
        $data = db_all("SELECT s.id, a.name, s.opened_at, s.closed_at, s.opening_cash, (SELECT COALESCE(SUM(o.total),0) FROM pos_sales ps JOIN orders o ON o.id = ps.order_id WHERE ps.pos_session_id = s.id AND o.status <> 'cancelled') sales,
            s.expected_cash, s.closing_cash, (s.closing_cash - s.expected_cash) diff FROM pos_sessions s JOIN admins a ON a.id = s.admin_id WHERE s.opened_at BETWEEN ? AND ? ORDER BY s.id DESC", $range);
        break;
}
if (input('export') === '1') csv_download('report-' . $tab . '-' . $from . '-to-' . $to . '.csv', $head, array_map('array_values', $data));
admin_header('Reports', 'reports');
$tabs = ['sales' => 'Sales', 'products' => 'Product sales', 'categories' => 'Category sales', 'inventory' => 'Inventory valuation', 'movement' => 'Product movement', 'customers' => 'Top customers', 'cashiers' => 'POS sessions'];
?>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto"><?php foreach ($tabs as $k => $l): ?><li class="nav-item"><a class="nav-link text-nowrap <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<form class="a-filter"><input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php if ($tab !== 'inventory'): ?><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" aria-label="From"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" aria-label="To">
  <?php foreach (['Today' => [date('Y-m-d'), date('Y-m-d')], 'This month' => [date('Y-m-01'), date('Y-m-d')], 'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')], 'This year' => [date('Y-01-01'), date('Y-m-d')]] as $l => [$a, $b]): ?><a class="btn btn-sm btn-light" href="?tab=<?= e($tab) ?>&from=<?= $a ?>&to=<?= $b ?>"><?= $l ?></a><?php endforeach; ?>
  <button class="btn btn-sm btn-brand">Apply</button><?php endif; ?>
  <a href="<?= e(url_with(['export' => 1])) ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download me-1"></i>Export CSV</a></form>
<?php if ($tab === 'sales'): ?>
<div class="row g-3 mb-3">
  <?php foreach ([['Orders', (int) $summary['orders'], 'bi-bag'], ['Revenue', money($summary['revenue']), 'bi-cash-stack'], ['Avg. order', money($summary['avg_order']), 'bi-graph-up'], ['Gross profit', money($profit['sales'] - $profit['cost']), 'bi-piggy-bank'], ['Discounts given', money($summary['discounts']), 'bi-ticket'], ['Delivery charges', money($summary['delivery']), 'bi-truck']] as [$l, $v, $i]): ?>
  <div class="col-6 col-md-4 col-xl-2"><div class="kpi"><i class="bi <?= $i ?>"></i><div><small><?= $l ?></small><strong><?= e($v) ?></strong></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-3"><div class="col-lg-8"><div class="card"><div class="card-body"><canvas id="rc" height="110"></canvas></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">By payment method</div><ul class="list-group list-group-flush small"><?php foreach ($byPay as $p): ?><li class="list-group-item d-flex justify-content-between"><span><?= e(payment_method_name($p['m'])) ?> (<?= (int) $p['n'] ?>)</span><strong><?= money($p['t']) ?></strong></li><?php endforeach; ?></ul></div></div></div>
<?php endif; ?>
<div class="card"><div class="table-responsive" style="max-height:65vh"><table class="table table-sm table-hover mb-0"><thead class="sticky-top"><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($data as $r): ?><tr><?php foreach (array_values($r) as $i => $v): ?><td class="<?= is_numeric($v) && $i ? 'text-end' : '' ?>"><?= is_float($v) || (is_numeric($v) && strpos((string) $v, '.') !== false) ? e(number_format((float) $v, 2)) : e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$data): ?><tr><td colspan="<?= count($head) ?>" class="text-muted p-4 text-center">No data for this period.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_footer($tab === 'sales' ? [asset('vendor/chartjs/chart.umd.min.js')] : []); ?>
<?php if ($tab === 'sales'): ?><script>new Chart(document.getElementById('rc'), { type: 'line', data: { labels: <?= json_encode(array_map(function ($r) { return date('d M', strtotime($r['d'])); }, $data)) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode(array_map(function ($r) { return (float) $r['t']; }, $data)) ?>, borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,.15)', fill: true, tension: .3 }] }, options: { scales: { y: { beginAtZero: true } } } });</script><?php endif; ?>
