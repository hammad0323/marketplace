<?php
require __DIR__ . '/includes/init.php';
admin_require('orders');
if (is_post()) {
    csrf_check();
    $ids = array_values(array_filter(array_map('intval', input_arr('ids'))));
    $st = input('bulk_status');
    $ok = 0; $errs = [];
    if ($ids && isset(order_statuses()[$st]) && $st !== 'draft') {
        foreach ($ids as $oid) { $e = order_update_status($oid, $st, 'Bulk update'); if ($e) $errs[] = '#' . $oid . ': ' . $e; else $ok++; }
        flash($errs ? 'warning' : 'success', $ok . ' orders updated.' . ($errs ? ' ' . implode(' ', $errs) : ''));
    }
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/orders.php');
}
$f = ['q' => input('q'), 'status' => input('status'), 'type' => input('type'), 'channel' => input('channel'), 'fulfillment' => input('fulfillment'), 'from' => input('from'), 'to' => input('to'), 'payment' => input('payment')];
$where = ['1=1']; $params = [];
if ($f['status'] === '') $where[] = "o.status <> 'draft'";
elseif (isset(order_statuses()[$f['status']])) { $where[] = 'o.status = ?'; $params[] = $f['status']; }
elseif ($f['status'] === 'open') $where[] = "o.status IN ('pending','confirmed','processing','ready_for_pickup','out_for_delivery')";
if ($f['q'] !== '') { $like = '%' . db_like($f['q']) . '%'; $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)'; array_push($params, $like, $like, $like, $like); }
if (in_array($f['type'], ['regular', 'rashan'], true)) { $where[] = 'o.order_type = ?'; $params[] = $f['type']; }
if (in_array($f['channel'], ['web', 'pos'], true)) { $where[] = 'o.channel = ?'; $params[] = $f['channel']; }
if (in_array($f['fulfillment'], ['delivery', 'pickup', 'walkin'], true)) { $where[] = 'o.fulfillment = ?'; $params[] = $f['fulfillment']; }
if (in_array($f['payment'], ['unpaid', 'paid', 'partial', 'refunded'], true)) { $where[] = 'o.payment_status = ?'; $params[] = $f['payment']; }
if ($f['from']) { $where[] = 'o.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to']) { $where[] = 'o.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$w = implode(' AND ', $where);
if (input('export') === '1') {
    $rows = db_all("SELECT o.order_number, o.created_at, o.customer_name, o.customer_phone, o.order_type, o.channel, o.fulfillment, o.area, o.city, o.subtotal, o.discount_amount, o.delivery_fee, o.total, o.payment_method, o.payment_status, o.status FROM orders o WHERE $w ORDER BY o.id DESC LIMIT 50000", $params);
    csv_download('orders-' . date('Y-m-d') . '.csv', ['Order', 'Date', 'Customer', 'Phone', 'Type', 'Channel', 'Fulfillment', 'Area', 'City', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Payment', 'Payment status', 'Status'], array_map('array_values', $rows));
}
$pg = paginate((int) db_val("SELECT COUNT(*) FROM orders o WHERE $w", $params), 30);
$rows = db_all("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items FROM orders o WHERE $w ORDER BY o.id DESC LIMIT 30 OFFSET {$pg['offset']}", $params);
$counts = [];
foreach (db_all("SELECT status, COUNT(*) c FROM orders" . ($f['type'] === 'rashan' ? " WHERE order_type = 'rashan'" : '') . " GROUP BY status") as $c) $counts[$c['status']] = (int) $c['c'];
$isRashan = $f['type'] === 'rashan';
admin_header($isRashan ? 'Monthly Rashan Orders' : 'Orders', $isRashan ? 'rashan-orders' : 'orders');
?>
<ul class="nav nav-pills mb-3 small flex-nowrap overflow-auto">
  <li class="nav-item"><a class="nav-link <?= $f['status'] === '' ? 'active' : '' ?>" href="<?= e(url_with(['status' => null, 'page' => null])) ?>">All</a></li>
  <?php foreach (order_statuses() as $k => [$label]): ?><li class="nav-item"><a class="nav-link text-nowrap <?= $f['status'] === $k ? 'active' : '' ?>" href="<?= e(url_with(['status' => $k, 'page' => null])) ?>"><?= e($label) ?> <span class="badge bg-light text-dark"><?= $counts[$k] ?? 0 ?></span></a></li><?php endforeach; ?>
</ul>
<form class="a-filter">
  <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
  <input type="search" name="q" class="form-control form-control-sm" placeholder="Order #, name, phone" value="<?= e($f['q']) ?>">
  <?= a_sel('type', ['regular' => 'Regular', 'rashan' => 'Monthly Rashan'], $f['type'], 'All types') ?>
  <?= a_sel('channel', ['web' => 'Website', 'pos' => 'POS / Shop'], $f['channel'], 'All channels') ?>
  <?= a_sel('fulfillment', ['delivery' => 'Delivery', 'pickup' => 'Pickup', 'walkin' => 'Walk-in'], $f['fulfillment'], 'Delivery & pickup') ?>
  <?= a_sel('payment', ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'partial' => 'Partial'], $f['payment'], 'Any payment') ?>
  <input type="date" name="from" class="form-control form-control-sm" value="<?= e($f['from']) ?>" aria-label="From date"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($f['to']) ?>" aria-label="To date">
  <button class="btn btn-sm btn-light">Filter</button><a class="btn btn-sm btn-outline-secondary" href="<?= e(url_with(['export' => 1])) ?>">Export CSV</a>
</form>
<form method="post">
  <?= csrf_field() ?>
  <div class="d-flex gap-2 align-items-center mb-2">
    <select name="bulk_status" class="form-select form-select-sm w-auto"><option value="">Change status of selected…</option><?php foreach (order_statuses() as $k => [$label]) if ($k !== 'draft') echo '<option value="' . $k . '">' . e($label) . '</option>'; ?></select>
    <button class="btn btn-sm btn-dark" data-confirm="Update the selected orders?">Apply</button>
  </div>
  <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th><input type="checkbox" class="form-check-input js-check-all" data-target=".js-oc" aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Delivery / pickup</th><th>Items</th><th class="text-end">Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($rows as $o): ?>
    <tr>
      <td><input type="checkbox" class="form-check-input js-oc" name="ids[]" value="<?= (int) $o['id'] ?>" aria-label="Select"></td>
      <td><a href="<?= aurl('order-view.php?id=' . $o['id']) ?>" class="fw-bold"><?= e($o['order_number']) ?></a><br>
        <?= $o['order_type'] === 'rashan' ? '<span class="badge bg-success">Rashan</span> ' : '' ?><?= $o['channel'] === 'pos' ? '<span class="badge bg-secondary">POS</span>' : '<span class="badge bg-light text-dark border">Web</span>' ?></td>
      <td><?= e($o['customer_name']) ?><br><a href="tel:<?= e($o['customer_phone']) ?>" class="small"><?= e($o['customer_phone']) ?></a> <a href="<?= e(whatsapp_link($o['customer_phone'])) ?>" target="_blank" rel="noopener" class="small text-success"><i class="bi bi-whatsapp"></i></a></td>
      <td class="small"><?= $o['fulfillment'] === 'pickup' ? '<i class="bi bi-shop"></i> Pickup<br>' . e(fmt_datetime($o['pickup_at'])) : ($o['fulfillment'] === 'walkin' ? '<i class="bi bi-person-walking"></i> Walk-in' : '<i class="bi bi-truck"></i> ' . e($o['area'] . ', ' . $o['city'])) ?></td>
      <td><?= (int) $o['items'] ?></td><td class="text-end fw-semibold"><?= money($o['total']) ?></td>
      <td class="small"><?= e(payment_method_name($o['payment_method'])) ?><br><span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-light text-dark border' ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
      <td><?= status_badge($o['status']) ?></td><td class="small text-muted text-nowrap"><?= fmt_datetime($o['created_at']) ?></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted p-4">No orders found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</form>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
