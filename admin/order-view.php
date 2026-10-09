<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('orders.view');
$id = input_int('id', 0, 'get');
$order = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('error', 'Order not found.');
    redirect(admin_url('orders'));
}

if (is_post()) {
    require_csrf();
    $action = input('action');
    $note = mb_substr(input('note'), 0, 500);
    switch ($action) {
        case 'status':
            require_admin('orders.edit');
            $new = input('status');
            $err = order_set_status($id, $new, $note ?: null, (bool) input_bool('notify'));
            if ($err) {
                flash('error', $err);
            } else {
                audit_log('order_status', 'order', $id, ['from' => $order['status'], 'to' => $new, 'note' => $note]);
                flash('success', 'Order status updated to ' . status_label($new) . '.');
            }
            break;
        case 'tracking':
            require_admin('orders.edit');
            $url = input('tracking_url');
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                flash('error', 'Tracking URL must start with http:// or https://');
                break;
            }
            db_exec('UPDATE orders SET courier_name = ?, tracking_number = ?, tracking_url = ? WHERE id = ?', [mb_substr(input('courier_name'), 0, 100) ?: null, mb_substr(input('tracking_number'), 0, 100) ?: null, $url ?: null, $id]);
            audit_log('order_tracking', 'order', $id, ['courier' => input('courier_name'), 'tracking' => input('tracking_number')]);
            flash('success', 'Courier details saved.');
            break;
        case 'note':
            require_admin('orders.edit');
            if ($note !== '') {
                db_insert('order_notes', ['order_id' => $id, 'admin_id' => admin_id(), 'note' => $note, 'is_customer_visible' => input_bool('visible')]);
                audit_log('order_note', 'order', $id, ['visible' => input_bool('visible')]);
                flash('success', 'Note added.');
            }
            break;
        case 'cod_collected':
            require_admin('orders.refund');
            $err = order_mark_cod_collected($id, $note ?: null);
            $err ? flash('error', $err) : flash('success', 'COD payment recorded as collected.');
            if (!$err) {
                audit_log('cod_collected', 'order', $id, ['amount' => $order['grand_total']]);
            }
            break;
        case 'refund':
            require_admin('orders.refund');
            $amount = (float) input_float('amount', 0);
            $err = order_record_refund($id, $amount, $note ?: 'Refund recorded', (bool) input_bool('restock'));
            $err ? flash('error', $err) : flash('success', 'Refund of ' . money($amount) . ' recorded.');
            if (!$err) {
                audit_log('order_refund', 'order', $id, ['amount' => $amount, 'restock' => input_bool('restock'), 'reason' => $note]);
            }
            break;
        case 'verify_payment':
            require_admin('orders.refund');
            $pay = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]);
            if ($pay && $pay['method'] !== 'cod') {
                flash('info', payment_verify_with_provider($pay));
                audit_log('payment_verify', 'order', $id, ['payment' => $pay['reference']]);
            }
            break;
        case 'manual_paid':
            require_admin('payments.manage');
            $pay = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]);
            if (!$pay || $pay['method'] === 'cod' || $pay['status'] === 'paid') {
                flash('error', 'This payment cannot be marked paid manually.');
                break;
            }
            if (mb_strlen($note) < 5) {
                flash('error', 'Enter the provider transaction ID / reason (min 5 characters) so this can be reconciled.');
                break;
            }
            payment_log($pay, 'manual', 'paid', ['amount' => (float) $pay['amount'], 'message' => $note]);
            payment_mark_paid((int) $pay['id'], (float) $pay['amount'], mb_substr(input('provider_ref'), 0, 100) ?: null, 'Marked paid manually after verification in merchant portal: ' . $note);
            audit_log('payment_manual_paid', 'order', $id, ['reference' => $pay['reference'], 'note' => $note]);
            flash('success', 'Payment marked as paid.');
            break;
    }
    redirect(admin_url('order-view', ['id' => $id]));
}

$items = order_items($id);
$address = order_address($id);
$history = db_all('SELECT h.*, a.name admin_name FROM order_status_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE order_id = ? ORDER BY h.created_at, h.id', [$id]);
$notes = db_all('SELECT n.*, a.name admin_name FROM order_notes n LEFT JOIN admins a ON a.id = n.admin_id WHERE order_id = ? ORDER BY n.created_at DESC', [$id]);
$payments = db_all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$id]);
$txns = db_all('SELECT t.*, a.name admin_name FROM payment_transactions t LEFT JOIN admins a ON a.id = t.admin_id WHERE t.order_id = ? ORDER BY t.id', [$id]);
$customer = $order['customer_id'] ? db_one('SELECT * FROM customers WHERE id = ?', [$order['customer_id']]) : null;
$otherOrders = (int) db_val('SELECT COUNT(*) FROM orders WHERE email = ? AND id <> ?', [$order['email'], $id]);
$next = order_transitions()[$order['status']] ?? [];
$lastPay = end($payments) ?: null;
$remaining = round((float) $order['grand_total'] - (float) $order['refunded_total'], 2);

admin_header('Order ' . $order['order_number'], 'orders');
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <a href="<?= e(admin_url('orders')) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Orders</a>
  <span><?= badge($order['status']) ?> <?= badge($order['payment_status']) ?></span>
  <small class="text-muted">Placed <?= e(format_date($order['created_at'], true)) ?></small>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(admin_url('order-print', ['id' => $id, 'type' => 'invoice'])) ?>"><i class="bi bi-receipt"></i> Invoice</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(admin_url('order-print', ['id' => $id, 'type' => 'packing'])) ?>"><i class="bi bi-box"></i> Packing slip</a>
  </div>
</div>
<?php if ($order['status'] === 'on_hold' && $order['payment_status'] === 'paid'): ?><div class="alert alert-warning">Payment was received after this order was cancelled. Check stock, then confirm the order or record a refund.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header">Items</div>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th></th><th>Product</th><th>SKU</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr><td><img class="thumb" src="<?= e(media_url($it['image_path'])) ?>" alt=""></td>
            <td><?= $it['product_id'] ? '<a href="' . e(admin_url('product-edit', ['id' => $it['product_id']])) . '">' . e($it['product_name']) . '</a>' : e($it['product_name']) ?><?= $it['variant_label'] ? '<br><small class="text-muted">' . e($it['variant_label']) . '</small>' : '' ?><?= $it['gift_wrap'] ? '<br><small class="text-warning"><i class="bi bi-gift"></i> Gift packaging (' . e(money($it['gift_wrap_price'])) . ' each)</small>' : '' ?></td>
            <td><small><?= e($it['sku']) ?></small></td>
            <td class="text-end"><?= e(money($it['unit_price'])) ?><?= $it['regular_price'] > $it['unit_price'] ? '<br><del class="small text-muted">' . e(money($it['regular_price'])) . '</del>' : '' ?></td>
            <td class="text-center"><?= (int) $it['quantity'] ?></td><td class="text-end"><?= e(money($it['line_total'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-body border-top"><dl class="kv ms-auto" style="max-width:360px">
        <dt>Subtotal</dt><dd class="text-end"><?= e(money($order['subtotal'])) ?></dd>
        <?php if ((float) $order['gift_wrap_total']): ?><dt>Gift packaging</dt><dd class="text-end"><?= e(money($order['gift_wrap_total'])) ?></dd><?php endif; ?>
        <?php if ((float) $order['discount_total']): ?><dt>Discount <?= e($order['coupon_code']) ?></dt><dd class="text-end">− <?= e(money($order['discount_total'])) ?></dd><?php endif; ?>
        <dt>Delivery</dt><dd class="text-end"><?= e(money($order['shipping_total'])) ?><br><small class="text-muted"><?= e($order['shipping_label']) ?> · <?= e($order['delivery_estimate']) ?></small></dd>
        <?php if ((float) $order['cod_fee']): ?><dt>COD fee</dt><dd class="text-end"><?= e(money($order['cod_fee'])) ?></dd><?php endif; ?>
        <dt class="fs-6 text-dark">Total</dt><dd class="text-end fs-6 fw-semibold"><?= e(money($order['grand_total'])) ?></dd>
        <?php if ((float) $order['refunded_total']): ?><dt>Refunded</dt><dd class="text-end text-danger">− <?= e(money($order['refunded_total'])) ?></dd><?php endif; ?>
      </dl></div>
    </div>

    <div class="card mb-3"><div class="card-header">Payment & transactions</div><div class="card-body">
      <?php foreach ($payments as $pay): ?>
        <p class="mb-2"><strong><?= e(payment_method_label($pay['method'])) ?></strong> via <?= e($pay['provider']) ?> · Ref <code><?= e($pay['reference']) ?></code> <?= badge($pay['status']) ?>
          <?= $pay['provider_reference'] ? '· Provider ref <code>' . e($pay['provider_reference']) . '</code>' : '' ?> · <?= e(money($pay['amount'])) ?><?= $pay['paid_at'] ? ' · paid ' . e(format_date($pay['paid_at'], true)) : '' ?></p>
      <?php endforeach; ?>
      <?php if ($txns): ?>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>When</th><th>Type</th><th>Status</th><th>Amount</th><th>Provider</th><th>Signature</th><th>By</th></tr></thead><tbody>
        <?php foreach ($txns as $t): ?><tr><td><small><?= e(format_date($t['created_at'], true)) ?></small></td><td><?= e(status_label($t['txn_type'])) ?></td><td><?= e($t['status']) ?></td><td><?= $t['amount'] !== null ? e(money($t['amount'])) : '' ?></td><td><small><?= e(trim($t['provider_code'] . ' ' . $t['provider_message'] . ' ' . $t['provider_txn_id'])) ?></small></td><td><?= $t['signature_valid'] === null ? '—' : ($t['signature_valid'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>') ?></td><td><small><?= e($t['admin_name'] ?: 'System') ?></small></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <?php if (can('orders.refund') && $order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' && !in_array($order['status'], ['cancelled', 'refunded'], true)): ?>
          <form method="post" class="d-flex gap-2" data-confirm="Confirm that <?= e(money($order['grand_total'])) ?> cash has been collected?"><?= csrf_field() ?><input type="hidden" name="action" value="cod_collected"><input class="form-control form-control-sm" name="note" placeholder="Courier / remittance ref (optional)"><button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-cash-coin"></i> Mark COD collected</button></form>
        <?php endif; ?>
        <?php if (can('orders.refund') && $lastPay && $lastPay['method'] !== 'cod' && in_array($lastPay['status'], ['pending', 'processing', 'failed'], true)): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify_payment"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Verify with provider</button></form>
        <?php endif; ?>
      </div>
      <?php if (can('payments.manage') && $lastPay && $lastPay['method'] !== 'cod' && $lastPay['status'] !== 'paid' && !in_array($lastPay['status'], ['refunded', 'partially_refunded'], true)): ?>
        <details class="mt-3"><summary class="small">Mark paid manually (only after confirming in the provider's merchant portal)</summary>
          <form method="post" class="row g-2 mt-1" data-confirm="Mark this payment as paid? This is recorded in the audit log."><?= csrf_field() ?><input type="hidden" name="action" value="manual_paid">
            <div class="col-md-4"><input class="form-control form-control-sm" name="provider_ref" placeholder="Provider transaction ID" required></div>
            <div class="col-md-6"><input class="form-control form-control-sm" name="note" placeholder="How was this verified?" required minlength="5"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-warning w-100">Mark paid</button></div></form>
        </details>
      <?php endif; ?>
      <?php if (can('orders.refund') && in_array($order['payment_status'], ['paid', 'partially_refunded'], true) && $remaining > 0): ?>
        <details class="mt-3"><summary class="small">Record a refund</summary>
          <p class="small text-muted mt-2 mb-2">Execute the refund in the payment provider's merchant portal (or by bank transfer for COD), then record it here for reconciliation.</p>
          <form method="post" class="row g-2" data-confirm="Record this refund?"><?= csrf_field() ?><input type="hidden" name="action" value="refund">
            <div class="col-md-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" max="<?= e($remaining) ?>" name="amount" value="<?= e($remaining) ?>" required></div>
            <div class="col-md-5"><input class="form-control form-control-sm" name="note" placeholder="Reason / provider refund reference" required></div>
            <div class="col-md-2"><label class="form-check small"><input class="form-check-input" type="checkbox" name="restock" value="1"> Restock items</label></div>
            <div class="col-md-2"><button class="btn btn-sm btn-danger w-100">Record refund</button></div></form>
        </details>
      <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header">Status history</div><div class="card-body">
      <ul class="timeline-admin">
        <?php foreach ($history as $h): ?><li><time><?= e(format_date($h['created_at'], true)) ?> · <?= e($h['admin_name'] ?: 'System') ?><?= $h['is_customer_visible'] ? '' : ' · internal' ?></time><strong><?= e(status_label($h['status'])) ?></strong><?= $h['payment_status'] ? ' / ' . e(status_label($h['payment_status'])) : '' ?><?= $h['note'] ? ' — ' . e($h['note']) : '' ?></li><?php endforeach; ?>
      </ul>
    </div></div>
  </div>

  <div class="col-xl-4">
    <?php if (can('orders.edit')): ?>
    <div class="card mb-3"><div class="card-header">Update status</div><div class="card-body">
      <?php if ($next): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select class="form-select mb-2" name="status"><?php foreach ($next as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?></select>
          <textarea class="form-control mb-2" name="note" rows="2" placeholder="Note (shown to customer in order history)" maxlength="500"></textarea>
          <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" checked> Email the customer</label>
          <button class="btn btn-primary w-100">Update</button>
        </form>
        <?php if (order_can_cancel($order)): ?><p class="small text-muted mt-2 mb-0">Cancelling restores stock and coupon usage automatically.</p><?php endif; ?>
      <?php else: ?><p class="text-muted mb-0">No further status changes are available.</p><?php endif; ?>
    </div></div>
    <div class="card mb-3"><div class="card-header">Courier & tracking</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="tracking">
        <?= f_text('courier_name', 'Courier', $order['courier_name'], ['list' => 'couriers']) ?>
        <datalist id="couriers"><option value="TCS"><option value="Leopards Courier"><option value="M&P"><option value="Trax"><option value="PostEx"><option value="Call Courier"></datalist>
        <?= f_text('tracking_number', 'Tracking number', $order['tracking_number']) ?>
        <?= f_text('tracking_url', 'Tracking URL', $order['tracking_url']) ?>
        <button class="btn btn-outline-primary w-100">Save tracking</button>
      </form>
    </div></div>
    <?php endif; ?>
    <div class="card mb-3"><div class="card-header">Customer</div><div class="card-body">
      <p class="mb-1"><strong><?= e($order['customer_name']) ?></strong> <?= $customer ? '<a class="small" href="' . e(admin_url('customer-view', ['id' => $customer['id']])) . '">profile</a>' : '<span class="badge text-bg-light">Guest</span>' ?></p>
      <p class="mb-1"><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a>
        <?php $wa = preg_replace('/^0/', '92', preg_replace('/[^0-9]/', '', $order['phone'])); ?> · <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></p>
      <?php if ($otherOrders): ?><p class="small text-muted mb-1"><?= $otherOrders ?> other order(s) with this email.</p><?php endif; ?>
      <?php if ($address): ?><hr><p class="mb-0 small"><?= e($address['full_name']) ?><br><?= e($address['address_line1']) ?><?= $address['address_line2'] ? '<br>' . e($address['address_line2']) : '' ?><br><?= e($address['city']) ?>, <?= e($address['region']) ?> <?= e($address['postal_code']) ?><br><?= e($address['country']) ?></p><?php endif; ?>
      <?php if ($order['customer_note']): ?><hr><p class="small mb-0"><strong>Customer note:</strong><br><?= nl2br(e($order['customer_note'])) ?></p><?php endif; ?>
      <hr><p class="small text-muted mb-0">IP <?= e($order['ip_address']) ?></p>
    </div></div>
    <div class="card"><div class="card-header">Notes</div><div class="card-body">
      <?php if (can('orders.edit')): ?>
      <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea class="form-control mb-2" name="note" rows="2" maxlength="500" required placeholder="Add a note"></textarea>
        <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="visible" value="1"> Visible to customer</label>
        <button class="btn btn-sm btn-outline-primary">Add note</button></form>
      <?php endif; ?>
      <?php foreach ($notes as $n): ?><div class="border-start border-3 ps-2 mb-2 <?= $n['is_customer_visible'] ? 'border-info' : 'border-secondary' ?>"><small class="text-muted"><?= e(format_date($n['created_at'], true)) ?> · <?= e($n['admin_name']) ?> · <?= $n['is_customer_visible'] ? 'customer-visible' : 'internal' ?></small><br><?= nl2br(e($n['note'])) ?></div><?php endforeach; ?>
    </div></div>
  </div>
</div>
<?php admin_footer();
