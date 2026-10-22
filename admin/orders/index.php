<?php
require __DIR__ . '/../includes/init.php';
admin_require('orders');

$f = [
    'q' => input('q', '', 'get'), 'status' => input('status', '', 'get'), 'payment_status' => input('payment_status', '', 'get'),
    'method' => input('method', '', 'get'), 'city' => input('city', '', 'get'), 'from' => input('from', '', 'get'), 'to' => input('to', '', 'get'),
];
$where = ['1'];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ? OR o.tracking_number LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if (isset(order_statuses()[$f['status']])) { $where[] = 'o.status = ?'; $params[] = $f['status']; }
if (isset(payment_statuses()[$f['payment_status']])) { $where[] = 'o.payment_status = ?'; $params[] = $f['payment_status']; }
if ($f['method'] !== '') { $where[] = 'o.payment_method = ?'; $params[] = $f['method']; }
if ($f['city'] !== '') { $where[] = 'a.city LIKE ?'; $params[] = '%' . $f['city'] . '%'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'o.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'o.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$w = implode(' AND ', $where);
$from = "FROM orders o LEFT JOIN order_addresses a ON a.order_id = o.id AND a.address_type = 'shipping' WHERE $w";

if (input('export', '', 'get') === 'csv') {
    if (!admin_can('orders.export')) {
        http_response_code(403);
        exit('Not allowed');
    }
    audit_log('orders_exported', 'order', null, $f);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Order', 'Date', 'Customer', 'Email', 'Phone', 'Address', 'City', 'Region', 'Status', 'Payment status', 'Payment method', 'Items', 'Subtotal', 'Discount', 'Delivery', 'Payment fee', 'Total', 'Paid', 'Refunded', 'Coupon', 'Courier', 'Tracking']);
    $cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v; // CSV injection guard
    foreach (db_all("SELECT o.*, a.address_line1, a.address_line2, a.city, a.region, (SELECT GROUP_CONCAT(CONCAT(oi.qty, 'x ', oi.product_name, IFNULL(CONCAT(' [', oi.variant_label, ']'), '')) SEPARATOR '; ') FROM order_items oi WHERE oi.order_id = o.id) items $from ORDER BY o.id DESC", $params) as $o) {
        fputcsv($out, array_map($cell, [$o['order_number'], $o['created_at'], $o['customer_name'], $o['email'], $o['phone'], trim($o['address_line1'] . ' ' . $o['address_line2']), $o['city'], $o['region'],
            $o['status'], $o['payment_status'], $o['payment_method'], $o['items'], $o['subtotal'] + $o['customization_total'], $o['discount_total'], $o['shipping_total'], $o['payment_fee'], $o['grand_total'], $o['amount_paid'], $o['amount_refunded'], $o['coupon_code'], $o['courier_name'], $o['tracking_number']]));
    }
    fclose($out);
    exit;
}

$total = (int) db_val("SELECT COUNT(*) $from", $params);
$sum = (float) db_val("SELECT COALESCE(SUM(o.grand_total),0) $from", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT o.*, a.city, (SELECT SUM(qty) FROM order_items oi WHERE oi.order_id = o.id) items $from ORDER BY o.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$methods = array_column(payment_methods_all(), 'name', 'code');
admin_header('Orders');
?>
<form class="a-filters" method="get">
    <input class="form-control" name="q" value="<?= e($f['q']) ?>" placeholder="Order no., name, email, phone, tracking">
    <select class="form-select" name="status"><option value="">Any status</option><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="form-select" name="payment_status"><option value="">Any payment</option><?php foreach (payment_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= $f['payment_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="form-select" name="method"><option value="">Any method</option><?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['method'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <input class="form-control" name="city" value="<?= e($f['city']) ?>" placeholder="City" style="max-width:140px">
    <input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>" title="From">
    <input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>" title="To">
    <button class="btn btn-dark">Filter</button>
    <a class="btn btn-link" href="<?= e(url('admin/orders/')) ?>">Reset</a>
    <?php if (admin_can('orders.export')): ?><a class="btn btn-outline-dark ms-auto" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-download"></i> Export CSV</a><?php endif; ?>
</form>
<div class="card a-card">
    <div class="card-header"><?= number_format($total) ?> orders · <?= e(money($sum)) ?> total value</div>
    <div class="table-responsive"><table class="table a-table mb-0">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>City</th><th>Items</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead><tbody>
    <?php foreach ($rows as $o): ?>
        <tr>
            <td><a href="<?= e(url('admin/orders/view.php?id=' . $o['id'])) ?>"><strong><?= e($o['order_number']) ?></strong></a><?= (int) $o['has_custom_items'] ? ' <i class="bi bi-scissors text-warning" title="Custom / made-to-order items"></i>' : '' ?></td>
            <td class="small text-nowrap"><?= e(format_datetime($o['created_at'])) ?></td>
            <td><?= e($o['customer_name']) ?><div class="small text-muted"><?= e($o['phone']) ?></div></td>
            <td><?= e($o['city']) ?></td>
            <td><?= (int) $o['items'] ?></td>
            <td><?= status_badge($o['status']) ?></td>
            <td><?= status_badge($o['payment_status']) ?><div class="small text-muted"><?= e($methods[$o['payment_method']] ?? $o['payment_method']) ?></div></td>
            <td class="text-end fw-semibold"><?= e(money($o['grand_total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="a-empty"><i class="bi bi-receipt"></i><p>No orders match these filters.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?= pagination_links($pg, url('admin/orders/')) ?>
<?php admin_footer();
