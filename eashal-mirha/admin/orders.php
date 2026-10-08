<?php
require __DIR__ . '/includes/admin.php';

if (is_post()) {
    require_csrf();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $act = post('bulk');
    if ($ids && $act) {
        $in = implode(',', $ids);
        if (isset(order_statuses()[$act])) {
            foreach ($ids as $oid) {
                q('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?', [$act, $oid]);
                order_add_history($oid, $act, 'Status changed to ' . order_statuses()[$act] . ' (bulk).');
            }
            flash('success', count($ids) . ' order(s) updated.');
        } elseif ($act === 'paid') {
            q("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id IN ($in)");
            foreach ($ids as $oid) order_add_history($oid, 'paid', 'Payment marked as received (bulk).');
            flash('success', 'Marked as paid.');
        } elseif ($act === 'delete' && admin_can('settings')) {
            q("DELETE FROM order_items WHERE order_id IN ($in)");
            q("DELETE FROM order_history WHERE order_id IN ($in)");
            q("DELETE FROM orders WHERE id IN ($in)");
            flash('success', 'Orders deleted.');
        }
    }
    back('admin/orders');
}

$where = ['1'];
$params = [];
if (get('q') !== '') { $where[] = '(order_no LIKE ? OR name LIKE ? OR phone LIKE ? OR email LIKE ? OR txn_id LIKE ?)'; $params = array_merge($params, array_fill(0, 5, '%' . get('q') . '%')); }
if (get('status') !== '') { $where[] = 'status = ?'; $params[] = get('status'); }
if (get('pay') !== '') { $where[] = 'payment_status = ?'; $params[] = get('pay'); }
if (get('method') !== '') { $where[] = 'payment_method = ?'; $params[] = get('method'); }
if (get('from') !== '') { $where[] = 'DATE(created_at) >= ?'; $params[] = get('from'); }
if (get('to') !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = get('to'); }
$w = implode(' AND ', $where);

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Name', 'Phone', 'Email', 'Address', 'City', 'Items', 'Subtotal', 'Delivery', 'Discount', 'Total', 'Payment', 'Payment Status', 'TID', 'Status', 'Courier', 'Tracking']);
    foreach (rows("SELECT o.*, (SELECT GROUP_CONCAT(CONCAT(name, IF(size<>'', CONCAT(' (', size, ')'), ''), ' x', qty) SEPARATOR '; ') FROM order_items WHERE order_id = o.id) items FROM orders o WHERE $w ORDER BY id DESC", $params) as $o) {
        fputcsv($out, [$o['order_no'], $o['created_at'], $o['name'], $o['phone'], $o['email'], $o['address'], $o['city'], $o['items'], $o['subtotal'], $o['shipping'], $o['discount'], $o['total'], payment_label($o['payment_method']), $o['payment_status'], $o['txn_id'], $o['status'], $o['courier'], $o['tracking_no']]);
    }
    exit;
}

$total = (int)val("SELECT COUNT(*) FROM orders WHERE $w", $params);
$sum = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $w", $params);
$pg = paginate($total, 30, (int)get('page', 1));
$orders = rows("SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) items FROM orders o WHERE $w ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = $r['n'];

admin_header('Orders', 'orders');
?>
<div class="status-tabs">
  <a href="<?= url('admin/orders') ?>" class="<?= get('status') === '' ? 'active' : '' ?>">All <b><?= array_sum($counts) ?></b></a>
  <?php foreach (order_statuses() as $k => $l): ?><a href="?status=<?= $k ?>" class="<?= get('status') === $k ? 'active' : '' ?>"><?= $l ?> <b><?= (int)($counts[$k] ?? 0) ?></b></a><?php endforeach; ?>
</div>
<div class="toolbar">
  <form class="filters" method="get">
    <?php if (get('status') !== ''): ?><input type="hidden" name="status" value="<?= e(get('status')) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Order #, name, phone, TID">
    <select name="pay"><option value="">Any payment status</option><?php foreach (payment_statuses() as $k => $l): ?><option value="<?= $k ?>" <?= get('pay') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="method"><option value="">Any method</option><?php foreach (['cod', 'card', 'jazzcash', 'easypaisa'] as $m): ?><option value="<?= $m ?>" <?= get('method') === $m ? 'selected' : '' ?>><?= e(payment_label($m)) ?></option><?php endforeach; ?></select>
    <input type="date" name="from" value="<?= e(get('from')) ?>" title="From">
    <input type="date" name="to" value="<?= e(get('to')) ?>" title="To">
    <button class="btn">Filter</button>
  </form>
  <a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <select name="bulk"><option value="">Bulk actions</option>
      <optgroup label="Change status"><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></optgroup>
      <option value="paid">Mark as paid</option>
      <?php if (admin_can('settings')): ?><option value="delete">Delete</option><?php endif; ?>
    </select>
    <button class="btn btn-sm" data-confirm-bulk>Apply</button>
    <span class="muted"><?= $total ?> orders · <?= money($sum) ?></span>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th class="w-check"><input type="checkbox" data-check-all></th><th>Order</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $o['id'] ?>"></td>
        <td><a href="<?= url('admin/order-view?id=' . $o['id']) ?>"><strong><?= e($o['order_no']) ?></strong></a><?= $o['customer_id'] ? '' : '<small class="block muted">Guest</small>' ?></td>
        <td class="muted"><?= date('d M Y', strtotime($o['created_at'])) ?><small class="block"><?= date('h:i A', strtotime($o['created_at'])) ?></small></td>
        <td><?= e($o['name']) ?><small class="block muted"><?= e($o['phone']) ?> · <?= e($o['city']) ?></small></td>
        <td><?= (int)$o['items'] ?></td>
        <td><strong><?= money($o['total']) ?></strong></td>
        <td><?= e(payment_label($o['payment_method'])) ?><br><?= status_badge($o['payment_status']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td class="actions"><a class="icon" href="<?= url('admin/order-view?id=' . $o['id']) ?>" title="Open"><?= aicon('eye') ?></a><a class="icon" href="<?= url('admin/invoice?id=' . $o['id']) ?>" target="_blank" title="Invoice"><?= aicon('print') ?></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="9" class="empty">No orders match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
<?= page_links($pg) ?>
<?php admin_footer();
