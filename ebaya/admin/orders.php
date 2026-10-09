<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('orders.view');

/** Build WHERE from filters — shared with the CSV export. */
function order_filters(): array
{
    $w = ['1=1'];
    $p = [];
    if (($q = mb_substr(get('q'), 0, 100)) !== '') { $w[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
    if (($s = get('status')) && isset(order_statuses()[$s])) { $w[] = 'o.status = ?'; $p[] = $s; }
    if (($s = get('payment_status')) && isset(payment_statuses()[$s])) { $w[] = 'o.payment_status = ?'; $p[] = $s; }
    if (($s = get('method')) && preg_match('/^[a-z]+$/', $s)) { $w[] = 'o.payment_method = ?'; $p[] = $s; }
    if (($s = mb_substr(get('city'), 0, 100)) !== '') { $w[] = 'a.city = ?'; $p[] = $s; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from'))) { $w[] = 'o.created_at >= ?'; $p[] = get('from') . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to'))) { $w[] = 'o.created_at <= ?'; $p[] = get('to') . ' 23:59:59'; }
    return [implode(' AND ', $w), $p];
}

[$w, $params] = order_filters();
$from = "FROM orders o LEFT JOIN order_addresses a ON a.order_id = o.id AND a.type = 'shipping' WHERE $w";

if (get('export') === 'csv') {
    if (!can('reports.export')) { http_response_code(403); exit('Forbidden'); }
    audit('orders_export', 'order', null, $_GET);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ebaya-orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Order', 'Date', 'Customer', 'Email', 'Phone', 'City', 'Province', 'Status', 'Payment status', 'Method', 'Subtotal', 'Customisation', 'Discount', 'Delivery', 'COD fee', 'Total', 'Refunded', 'Coupon', 'Courier', 'Tracking', 'Items']);
    $res = db_all("SELECT o.*, a.city, a.province, (SELECT GROUP_CONCAT(CONCAT(oi.quantity, ' x ', oi.product_name, ' [', oi.sku, ']') SEPARATOR '; ') FROM order_items oi WHERE oi.order_id = o.id) items $from ORDER BY o.id DESC LIMIT 20000", $params);
    // Neutralise spreadsheet formula injection in text fields.
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    foreach ($res as $o) {
        fputcsv($out, array_map($safe, [$o['order_number'], $o['created_at'], $o['customer_name'], $o['email'], $o['phone'], $o['city'], $o['province'], $o['status'], $o['payment_status'], $o['payment_method'],
            $o['subtotal'], $o['customization_total'], $o['discount_total'], $o['shipping_total'], $o['cod_fee'], $o['grand_total'], $o['refunded_total'], $o['coupon_code'], $o['courier_name'], $o['tracking_number'], $o['items']]));
    }
    exit;
}

$pg = paginate((int)db_val("SELECT COUNT(*) $from", $params), 30, (int)get('page', 1));
$rows = db_all("SELECT o.*, a.city, (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) qty $from ORDER BY o.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$methods = db_col('SELECT code FROM payment_gateways ORDER BY sort_order');
$cities = db_col("SELECT DISTINCT city FROM order_addresses ORDER BY city LIMIT 300");

$admin_title = 'Orders';
require __DIR__ . '/partials/header.php';
?>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="q" value="<?= e(get('q')) ?>" placeholder="Order #, name, email, phone"></div>
    <div class="col-md-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">Any</option><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= get('status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">Payment</label><select class="form-select form-select-sm" name="payment_status"><option value="">Any</option><?php foreach (payment_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= get('payment_status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><label class="form-label small">Method</label><select class="form-select form-select-sm" name="method"><option value="">Any</option><?php foreach ($methods as $m): ?><option<?= get('method') === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><label class="form-label small">City</label><select class="form-select form-select-sm" name="city"><option value="">Any</option><?php foreach ($cities as $c): ?><option<?= get('city') === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e(get('from')) ?>"></div>
    <div class="col-md-1"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e(get('to')) ?>"></div>
    <div class="col-md-1 d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a class="btn btn-sm btn-light" href="<?= e(admin_url('orders')) ?>">×</a></div>
  </div>
</form>
<div class="card">
  <div class="card-header d-flex align-items-center"><span class="small text-muted"><?= $pg['total'] ?> orders</span>
    <?php if (can('reports.export')): ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(query_with(['export' => 'csv', 'page' => null])) ?>"><i class="bi bi-download"></i> Export CSV</a><?php endif; ?></div>
  <div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>City</th><th>Items</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><a class="fw-semibold" href="<?= e(admin_url('order-view?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a><?= $o['has_custom_items'] ? ' <i class="bi bi-scissors text-warning" title="Custom / made to order"></i>' : '' ?></td>
        <td class="small"><?= e(date('j M Y, g:ia', strtotime($o['created_at']))) ?></td>
        <td><?= e($o['customer_name']) ?><div class="small text-muted"><?= e($o['phone']) ?></div></td>
        <td class="small"><?= e($o['city']) ?></td>
        <td><?= (int)$o['qty'] ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td><span class="small text-uppercase"><?= e($o['payment_method']) ?></span><br><?= status_badge($o['payment_status']) ?></td>
        <td class="text-end"><?= money($o['grand_total']) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No orders match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<div class="mt-3"><?= admin_pager($pg) ?></div>
<?php require __DIR__ . '/partials/footer.php';
