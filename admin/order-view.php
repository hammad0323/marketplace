<?php
require __DIR__ . '/includes/init.php';
admin_require('orders');
$id = input_int('id');
$o = db_row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('danger', 'Order not found.'); redirect('admin/orders.php'); }
if (is_post()) {
    csrf_check();
    $act = input('act');
    if ($act === 'status') {
        $err = order_update_status($id, input('status'), input('note'), input('notify') === '1');
        flash($err ? 'danger' : 'success', $err ?: 'Order status updated to ' . status_label(input('status')) . '.');
    } elseif ($act === 'note' && input('note') !== '') {
        order_add_note($id, input('note'), input('internal') !== '0');
        flash('success', 'Note added.');
    } elseif ($act === 'payment') {
        $ps = input('payment_status');
        if (in_array($ps, ['unpaid', 'partial', 'paid', 'refunded'], true)) {
            db_exec('UPDATE orders SET payment_status = ?, payment_reference = COALESCE(NULLIF(?, ""), payment_reference) WHERE id = ?', [$ps, input('payment_reference'), $id]);
            db_exec('UPDATE payments SET status = ?, reference = COALESCE(NULLIF(?, ""), reference), received_by = ? WHERE order_id = ?', [$ps === 'paid' ? 'received' : ($ps === 'refunded' ? 'refunded' : 'pending'), input('payment_reference'), admin_id(), $id]);
            order_add_note($id, 'Payment status set to ' . $ps . (input('payment_reference') ? ' (ref ' . input('payment_reference') . ')' : ''));
            flash('success', 'Payment updated.');
        }
    } elseif ($act === 'picked') {
        $picked = array_map('intval', input_arr('picked'));
        db_exec('UPDATE order_items SET is_picked = 0 WHERE order_id = ?', [$id]);
        if ($picked) db_exec('UPDATE order_items SET is_picked = 1 WHERE order_id = ? AND id IN (' . in_placeholders($picked) . ')', array_merge([$id], $picked));
        flash('success', 'Picking progress saved.');
    } elseif ($act === 'admin_notes') {
        db_exec('UPDATE orders SET admin_notes = ? WHERE id = ?', [input('admin_notes') ?: null, $id]);
        flash('success', 'Internal notes saved.');
    }
    redirect('admin/order-view.php?id=' . $id);
}
$items = order_items($id);
$hist = db_all('SELECT h.*, a.name AS admin FROM order_status_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE h.order_id = ? ORDER BY h.id DESC', [$id]);
$customer = $o['customer_id'] ? db_row('SELECT c.*, (SELECT COUNT(*) FROM orders WHERE customer_id = c.id AND status <> "draft") AS orders FROM customers c WHERE c.id = ?', [$o['customer_id']]) : null;
$pos = db_row('SELECT ps.*, a.name AS cashier FROM pos_sales ps LEFT JOIN admins a ON a.id = ps.admin_id WHERE ps.order_id = ?', [$id]);
db_exec('UPDATE notifications SET is_read = 1 WHERE reference_id = ? AND type IN ("new_order","rashan_order")', [$id]);
$flow = $o['fulfillment'] === 'pickup' ? ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'completed'] : ($o['fulfillment'] === 'walkin' ? ['pending', 'processing', 'completed'] : ['pending', 'confirmed', 'processing', 'out_for_delivery', 'completed']);
$waText = "Assalam-o-Alaikum {$o['customer_name']}, this is Fahim Store regarding your order {$o['order_number']} (" . money($o['total']) . ').';
admin_header('Order ' . $o['order_number'], $o['order_type'] === 'rashan' ? 'rashan-orders' : 'orders');
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?= status_badge($o['status']) ?>
  <?= $o['order_type'] === 'rashan' ? '<span class="badge bg-success"><i class="bi bi-basket2"></i> Monthly Rashan</span>' : '' ?>
  <span class="badge bg-light text-dark border"><?= $o['channel'] === 'pos' ? 'POS / Shop' : 'Website' ?></span>
  <span class="text-muted small">Placed <?= fmt_datetime($o['created_at']) ?></span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="<?= aurl('print-invoice.php?id=' . $id) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Invoice</a>
    <a href="<?= aurl('print-picking.php?id=' . $id) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-check me-1"></i>Picking list</a>
    <?php if ($o['channel'] === 'pos'): ?><a href="<?= aurl('print-receipt.php?id=' . $id) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i>Receipt</a><?php endif; ?>
    <?php if ($o['status'] === 'draft' && admin_can('pos')): ?><a href="<?= aurl('pos.php?load=' . $id) ?>" class="btn btn-sm btn-brand">Open draft in POS</a><?php endif; ?>
    <a href="tel:<?= e($o['customer_phone']) ?>" class="btn btn-sm btn-outline-brand"><i class="bi bi-telephone me-1"></i>Call</a>
    <a href="<?= e(whatsapp_link($o['customer_phone'], $waText)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
    <?php if ($o['customer_email']): ?><a href="mailto:<?= e($o['customer_email']) ?>?subject=<?= rawurlencode('Your order ' . $o['order_number']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope"></i></a><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header">Update status</div><div class="card-body">
      <div class="d-flex flex-wrap gap-1 mb-3 status-flow">
        <?php foreach ($flow as $st): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="status"><input type="hidden" name="status" value="<?= $st ?>"><input type="hidden" name="notify" value="1">
          <button class="btn btn-sm <?= $o['status'] === $st ? 'btn-brand' : 'btn-outline-secondary' ?>" <?= $o['status'] === $st ? 'disabled' : '' ?>><?= e(status_label($st)) ?></button></form><i class="bi bi-chevron-right align-self-center text-muted small"></i>
        <?php endforeach; ?>
        <form method="post" data-confirm="Cancel this order? Stock will be returned to inventory."><?= csrf_field() ?><input type="hidden" name="act" value="status"><input type="hidden" name="status" value="cancelled"><input type="hidden" name="notify" value="1"><button class="btn btn-sm btn-outline-danger ms-2" <?= $o['status'] === 'cancelled' ? 'disabled' : '' ?>>Cancel order</button></form>
      </div>
      <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="act" value="status">
        <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="Status"><?php foreach (order_statuses() as $k => [$label]) if ($k !== 'draft' || $o['status'] === 'draft') echo '<option value="' . $k . '"' . ($o['status'] === $k ? ' selected' : '') . '>' . e($label) . '</option>'; ?></select></div>
        <div class="col-md-5"><input name="note" class="form-control form-control-sm" placeholder="Note shown to customer (optional)"></div>
        <div class="col-md-2"><div class="form-check small"><input type="checkbox" class="form-check-input" name="notify" value="1" id="nt" checked><label class="form-check-label" for="nt">Notify</label></div></div>
        <div class="col-md-2"><button class="btn btn-sm btn-brand w-100">Update</button></div>
      </form>
      <p class="small text-muted mt-2 mb-0"><i class="bi bi-box-seam me-1"></i>Stock: <?= $o['stock_deducted'] ? '<span class="text-success">deducted from inventory</span>' : '<span class="text-warning">not yet deducted</span> (policy: ' . e(setting('stock_policy', 'placed')) . ')' ?></p>
    </div></div>

    <form method="post" class="card mb-3"><?= csrf_field() ?><input type="hidden" name="act" value="picked">
      <div class="card-header d-flex justify-content-between">Items (<?= count($items) ?>) <small class="fw-normal text-muted">Tick items as you pack them</small></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th style="width:40px">✓</th><th>Product</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody>
        <?php foreach ($items as $it): ?>
        <tr class="<?= $it['is_picked'] ? 'table-success' : '' ?>"><td><input type="checkbox" class="form-check-input" name="picked[]" value="<?= (int) $it['id'] ?>" <?= $it['is_picked'] ? 'checked' : '' ?> aria-label="Picked"></td>
          <td><div class="d-flex gap-2 align-items-center"><img src="<?= e(upload_url($it['thumb'])) ?>" class="a-thumb" alt=""><div><?= e($it['product_name']) ?><?= $it['variation_name'] ? ' <span class="text-muted">(' . e($it['variation_name']) . ')</span>' : '' ?><br><small class="text-muted">SKU <?= e($it['sku']) ?> · <?= e($it['category_name']) ?></small></div></div></td>
          <td class="text-end"><?= money($it['unit_price']) ?></td><td class="text-end fw-bold"><?= (int) $it['quantity'] ?></td><td class="text-end"><?= money($it['line_total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= money($o['subtotal']) ?></td></tr>
          <?php if ($o['discount_amount'] > 0): ?><tr><td colspan="4" class="text-end text-success">Discount <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></td><td class="text-end text-success">- <?= money($o['discount_amount']) ?></td></tr><?php endif; ?>
          <tr><td colspan="4" class="text-end">Delivery</td><td class="text-end"><?= money($o['delivery_fee']) ?></td></tr>
          <tr><th colspan="4" class="text-end fs-5">Total</th><th class="text-end fs-5"><?= money($o['total']) ?></th></tr>
        </tfoot></table></div>
      <div class="card-footer bg-white"><button class="btn btn-sm btn-outline-brand">Save picking progress</button></div>
    </form>

    <div class="card mb-3"><div class="card-header">History &amp; notes</div><div class="card-body">
      <form method="post" class="d-flex gap-2 mb-3"><?= csrf_field() ?><input type="hidden" name="act" value="note"><input name="note" class="form-control form-control-sm" placeholder="Add a note…" required maxlength="500">
        <select name="internal" class="form-select form-select-sm w-auto"><option value="1">Internal</option><option value="0">Visible to customer</option></select><button class="btn btn-sm btn-dark">Add</button></form>
      <ul class="list-unstyled small mb-0">
        <?php foreach ($hist as $h): ?>
        <li class="border-bottom py-2"><strong><?= $h['from_status'] !== $h['to_status'] ? e(status_label($h['to_status'])) : 'Note' ?></strong><?= $h['is_internal'] ? ' <span class="badge bg-light text-dark border">internal</span>' : '' ?>
          <?= $h['note'] ? ' — ' . e($h['note']) : '' ?><br><span class="text-muted"><?= fmt_datetime($h['created_at']) ?> · <?= e($h['admin'] ?: 'Customer / system') ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div></div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header">Customer</div><div class="card-body small">
      <div class="fs-6 fw-bold"><?= e($o['customer_name']) ?></div>
      <div><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($o['customer_phone']) ?>"><?= e($o['customer_phone']) ?></a></div>
      <?php if ($o['customer_email']): ?><div><i class="bi bi-envelope me-1"></i><?= e($o['customer_email']) ?></div><?php endif; ?>
      <?php if ($customer): ?><div class="mt-2"><a href="<?= aurl('customer-view.php?id=' . $customer['id']) ?>"><?= (int) $customer['orders'] ?> orders · view customer</a></div><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header"><?= $o['fulfillment'] === 'pickup' ? 'Store pickup' : ($o['fulfillment'] === 'walkin' ? 'Walk-in sale' : 'Home delivery') ?></div><div class="card-body small">
      <?php if ($o['fulfillment'] === 'pickup'): ?>
        <div class="fs-6"><i class="bi bi-calendar-event me-1"></i><?= e(fmt_datetime($o['pickup_at'])) ?></div>
      <?php elseif ($o['fulfillment'] === 'delivery'): ?>
        <div><?= e($o['address_line']) ?></div><div><?= e($o['area']) ?>, <?= e($o['city']) ?></div>
        <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($o['address_line'] . ', ' . $o['area'] . ', ' . $o['city']) ?>" target="_blank" rel="noopener">Open in maps</a>
      <?php else: ?>Sold at the counter.<?php endif; ?>
      <?php if ($o['delivery_instructions']): ?><div class="mt-2 alert alert-light p-2 mb-0"><strong>Instructions:</strong> <?= e($o['delivery_instructions']) ?></div><?php endif; ?>
      <?php if ($o['customer_notes']): ?><div class="mt-2 alert alert-warning p-2 mb-0"><strong>Customer notes:</strong> <?= nl2br(e($o['customer_notes'])) ?></div><?php endif; ?>
    </div></div>
    <form method="post" class="card mb-3"><?= csrf_field() ?><input type="hidden" name="act" value="payment">
      <div class="card-header">Payment</div><div class="card-body small">
      <p class="mb-2"><?= e(payment_method_name($o['payment_method'])) ?><?= $o['payment_reference'] ? '<br>Ref: <strong>' . e($o['payment_reference']) . '</strong>' : '' ?></p>
      <?php if ($pos): ?><p class="mb-2">Cash received <?= money($pos['amount_tendered']) ?> · change <?= money($pos['change_given']) ?> · cashier <?= e($pos['cashier']) ?></p><?php endif; ?>
      <div class="d-flex gap-2"><select name="payment_status" class="form-select form-select-sm" aria-label="Payment status"><?php foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps) echo '<option value="' . $ps . '"' . ($o['payment_status'] === $ps ? ' selected' : '') . '>' . ucfirst($ps) . '</option>'; ?></select>
        <input name="payment_reference" class="form-control form-control-sm" placeholder="Reference"><button class="btn btn-sm btn-dark">Save</button></div>
    </div></form>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="act" value="admin_notes">
      <div class="card-header">Internal notes</div><div class="card-body"><textarea name="admin_notes" class="form-control form-control-sm" rows="3" aria-label="Internal notes"><?= e($o['admin_notes']) ?></textarea></div>
      <div class="card-footer bg-white"><button class="btn btn-sm btn-outline-secondary">Save notes</button></div></form>
  </div>
</div>
<?php admin_footer();
