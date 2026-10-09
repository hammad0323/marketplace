<?php
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/order-filters.php';
require_admin('orders.view');
[$where, $params, $f] = order_filters();
$total = (int) db_val("SELECT COUNT(*) FROM orders o WHERE $where", $params);
$pg = paginate($total, 30, input_int('page', 1, 'get'));
$rows = db_all("SELECT o.*, (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) items, a.city FROM orders o LEFT JOIN order_addresses a ON a.order_id = o.id AND a.address_type = 'shipping' WHERE $where ORDER BY o.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$sum = db_one("SELECT COALESCE(SUM(grand_total), 0) t FROM orders o WHERE $where AND o.status NOT IN ('cancelled','refunded')", $params);
admin_header('Orders', 'orders');
?>
<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input class="form-control form-control-sm" name="q" value="<?= e($f['q']) ?>" placeholder="Order #, name, email, phone, tracking"></div>
    <div class="col-6 col-md-2"><label class="form-label">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Payment status</label><select class="form-select form-select-sm" name="payment_status"><option value="">All</option><?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>"<?= $f['payment_status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Method</label><select class="form-select form-select-sm" name="payment_method"><option value="">All</option><?php foreach (['cod', 'easypaisa', 'jazzcash', 'card'] as $m): ?><option value="<?= $m ?>"<?= $f['payment_method'] === $m ? ' selected' : '' ?>><?= e(payment_method_label($m)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-1"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="col-6 col-md-1"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="col-6 col-md-1 d-flex gap-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
  </div>
</form>
<div class="d-flex justify-content-between align-items-center mb-2">
  <small class="text-muted"><?= $total ?> orders · value (excl. cancelled/refunded): <?= e(money($sum['t'])) ?></small>
  <?php if (can('orders.export')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('orders-export') . query_with([], ['page'])) ?>"><i class="bi bi-download"></i> Export CSV</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Items</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td><a href="<?= e(admin_url('order-view', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number']) ?></strong></a><?= $o['customer_id'] ? '' : ' <span class="badge text-bg-light">Guest</span>' ?></td>
      <td><small><?= e(format_date($o['created_at'], true)) ?></small></td>
      <td><?= e($o['customer_name']) ?><br><small class="text-muted"><?= e($o['city']) ?> · <?= e($o['phone']) ?></small></td>
      <td><?= (int) $o['items'] ?></td>
      <td><?= badge($o['status']) ?></td>
      <td><small><?= e(payment_method_label($o['payment_method'])) ?></small><br><?= badge($o['payment_status']) ?></td>
      <td class="text-end"><?= e(money($o['grand_total'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No orders match.</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="card-body border-top"><?= admin_pager($pg) ?></div>
</div>
<?php admin_footer();
