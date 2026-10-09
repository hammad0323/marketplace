<?php
/** Order detail: fulfilment status, payment recording, courier, notes, refunds, history. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('orders.view');
$id = (int)get('id');
$o = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('danger', 'Order not found.'); redirect(admin_url('orders')); }

if (is_post()) {
    csrf_check();
    $act = post('action');
    try {
        if ($act !== 'note' && !can('orders.manage')) throw new InvalidArgumentException('You do not have permission to change orders.');
        switch ($act) {
            case 'status':
                $new = post('status');
                if (!isset(order_statuses()[$new])) throw new InvalidArgumentException('Invalid status.');
                order_set_status($id, $new, mb_substr(post('note'), 0, 500), (bool)post('notify'), (bool)post('restock'));
                audit('order_status', 'order', $id, ['from' => $o['status'], 'to' => $new]);
                flash('success', 'Order status updated.');
                break;
            case 'tracking':
                $url = clean_url(post('tracking_url'));
                db_exec('UPDATE orders SET courier_name = ?, tracking_number = ?, tracking_url = ? WHERE id = ?', [mb_substr(post('courier_name'), 0, 120) ?: null, mb_substr(post('tracking_number'), 0, 120) ?: null, $url ?: null, $id]);
                order_history_add($id, null, null, 'Courier details updated: ' . post('courier_name') . ' ' . post('tracking_number'), (bool)post('visible'));
                audit('order_tracking', 'order', $id, ['courier' => post('courier_name'), 'tracking' => post('tracking_number')]);
                flash('success', 'Courier details saved.');
                break;
            case 'cod_collected':
                if ($o['payment_method'] !== 'cod') throw new InvalidArgumentException('Not a COD order.');
                $p = db_one("SELECT id FROM payments WHERE order_id = ? AND method = 'cod' ORDER BY id DESC LIMIT 1", [$id]);
                if (!$p) $p = ['id' => db_insert("INSERT INTO payments (order_id, method, amount, status) VALUES (?, 'cod', ?, 'pending')", [$id, $o['grand_total']])];
                db_exec('UPDATE orders SET cod_collected = 1, cod_collected_at = NOW() WHERE id = ?', [$id]);
                payment_log('cod', 'cod_collect', 'collected', (int)$p['id'], $id, ['note' => post('note')], null, null, (float)$o['grand_total'], 'COD cash collected');
                order_mark_paid((int)$p['id'], null, 'Cash on delivery collected');
                audit('order_cod_collected', 'order', $id);
                flash('success', 'COD marked as collected and the order marked paid.');
                break;
            case 'payment_status':
                // Manual payment recording (e.g. bank transfer confirmed, or correcting a status). Logged.
                $ps = post('payment_status');
                if (!in_array($ps, ['unpaid', 'pending', 'paid', 'failed', 'cancelled'], true)) throw new InvalidArgumentException('Use the refund form for refunds.');
                $ref = mb_substr(post('reference'), 0, 120);
                if ($ps === 'paid') {
                    if ($ref === '') throw new InvalidArgumentException('Enter a payment reference (transaction ID or receipt number) to mark as paid manually.');
                    $p = db_one('SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]) ?: ['id' => db_insert("INSERT INTO payments (order_id, method, amount, status) VALUES (?, ?, ?, 'pending')", [$id, $o['payment_method'], $o['grand_total']])];
                    db_exec("UPDATE payments SET status = 'pending' WHERE id = ? AND status IN ('failed','cancelled')", [(int)$p['id']]);
                    payment_log($o['payment_method'], 'manual', 'paid', (int)$p['id'], $id, ['reference' => $ref], $ref, null, (float)$o['grand_total'], 'Marked paid manually by admin');
                    order_mark_paid((int)$p['id'], $ref, 'recorded manually, ref ' . $ref);
                } else {
                    db_exec('UPDATE orders SET payment_status = ? WHERE id = ?', [$ps, $id]);
                    order_history_add($id, null, $ps, 'Payment status set to ' . $ps . ($ref ? ' (' . $ref . ')' : ''), false);
                    payment_log($o['payment_method'], 'manual', $ps, null, $id, ['reference' => $ref], $ref ?: null, null, null, 'Payment status changed manually');
                }
                audit('order_payment_status', 'order', $id, ['from' => $o['payment_status'], 'to' => $ps, 'ref' => $ref]);
                flash('success', 'Payment status recorded.');
                break;
            case 'refund':
                if (!can('orders.refund')) throw new InvalidArgumentException('You do not have permission to record refunds.');
                $msg = payment_refund($id, (float)to_money(post('amount')), mb_substr(post('reason'), 0, 255));
                audit('order_refund', 'order', $id, ['amount' => post('amount'), 'reason' => post('reason')]);
                flash('success', $msg);
                break;
            case 'note':
                $note = trim(mb_substr(post('note'), 0, 2000));
                if ($note === '') throw new InvalidArgumentException('Note is empty.');
                db_insert('INSERT INTO order_notes (order_id, admin_id, note, is_customer_visible) VALUES (?, ?, ?, ?)', [$id, admin_id(), $note, post('visible') ? 1 : 0]);
                audit('order_note', 'order', $id, ['visible' => (bool)post('visible')]);
                flash('success', 'Note added.');
                break;
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect(admin_url('order-view?id=' . $id));
}

$items = order_items($id);
$addr = order_address($id);
$history = db_all('SELECT h.*, a.name admin FROM order_status_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE order_id = ? ORDER BY h.id DESC', [$id]);
$notes = db_all('SELECT n.*, a.name admin FROM order_notes n LEFT JOIN admins a ON a.id = n.admin_id WHERE order_id = ? ORDER BY n.id DESC', [$id]);
$txns = db_all('SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 50', [$id]);
$pays = db_all('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$id]);
$customer = $o['customer_id'] ? db_one('SELECT id, name, email FROM customers WHERE id = ?', [(int)$o['customer_id']]) : null;
$next = order_allowed_transitions($o['status']);

$admin_title = 'Order ' . $o['order_number'];
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
  <a href="<?= e(admin_url('orders')) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Orders</a>
  <span class="align-self-center"><?= status_badge($o['status']) ?> <?= status_badge($o['payment_status']) ?> <span class="badge text-bg-light text-uppercase"><?= e($o['payment_method']) ?></span></span>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(admin_url('invoice?id=' . $id)) ?>"><i class="bi bi-receipt"></i> Invoice</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(admin_url('invoice?id=' . $id . '&type=packing')) ?>"><i class="bi bi-box"></i> Packing slip</a>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header">Items</div>
      <div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th></th><th>Item</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr></thead>
        <tbody><?php foreach ($items as $it): ?>
          <tr><td><img class="thumb" src="<?= e(img_url($it['image'])) ?>" alt=""></td>
            <td><strong><?= e($it['product_name']) ?></strong><div class="small text-muted"><?= e($it['sku']) ?> · <?= e($it['variant_label']) ?></div>
              <?php if ($it['fulfillment_type'] === 'made_to_order'): ?><div class="small text-warning"><i class="bi bi-scissors"></i> Made to order · <?= (int)$it['lead_days'] ?> days<?= $it['custom_length'] ? ' · Length ' . (int)$it['custom_length'] . '"' : '' ?><?= $it['custom_sleeve'] ? ' · Sleeve: ' . e($it['custom_sleeve']) : '' ?></div><?php endif; ?>
              <?php if ($it['custom_notes']): ?><div class="small border-start ps-2 mt-1">“<?= e($it['custom_notes']) ?>”</div><?php endif; ?></td>
            <td class="text-end"><?= money($it['unit_price']) ?><?= $it['customization_fee'] > 0 ? '<div class="small text-muted">+' . money($it['customization_fee']) . ' custom</div>' : '' ?></td>
            <td class="text-center"><?= (int)$it['quantity'] ?></td><td class="text-end"><?= money($it['line_total']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <div class="card-body"><dl class="row mb-0 small" style="max-width:360px;margin-left:auto">
        <dt class="col-7">Subtotal</dt><dd class="col-5 text-end"><?= money($o['subtotal']) ?></dd>
        <?php if ($o['customization_total'] > 0): ?><dt class="col-7">Customisation</dt><dd class="col-5 text-end"><?= money($o['customization_total']) ?></dd><?php endif; ?>
        <?php if ($o['discount_total'] > 0): ?><dt class="col-7">Discount <?= e($o['coupon_code']) ?></dt><dd class="col-5 text-end">−<?= money($o['discount_total']) ?></dd><?php endif; ?>
        <dt class="col-7">Delivery (<?= e($o['shipping_label']) ?>)</dt><dd class="col-5 text-end"><?= money($o['shipping_total']) ?></dd>
        <?php if ($o['cod_fee'] > 0): ?><dt class="col-7">COD fee</dt><dd class="col-5 text-end"><?= money($o['cod_fee']) ?></dd><?php endif; ?>
        <dt class="col-7 fs-6">Total</dt><dd class="col-5 text-end fs-6 fw-bold"><?= money($o['grand_total']) ?></dd>
        <?php if ($o['refunded_total'] > 0): ?><dt class="col-7 text-danger">Refunded</dt><dd class="col-5 text-end text-danger">−<?= money($o['refunded_total']) ?></dd><?php endif; ?>
        <?php if ($o['payment_method'] === 'cod'): ?><dt class="col-7">Amount due on delivery</dt><dd class="col-5 text-end"><?= $o['cod_collected'] ? 'Collected ' . e(date('j M', strtotime($o['cod_collected_at']))) : money($o['grand_total']) ?></dd><?php endif; ?>
      </dl></div>
    </div>

    <div class="card mb-3"><div class="card-header">Status history</div><div class="card-body"><ul class="timeline">
      <?php foreach ($history as $h): ?><li><div class="small text-muted"><?= e($h['created_at']) ?> · <?= e($h['admin'] ?? 'System') ?><?= $h['is_customer_visible'] ? ' · <span class="text-success">visible to customer</span>' : '' ?></div>
        <?= $h['status'] ? status_badge($h['status']) . ' ' : '' ?><?= $h['payment_status'] ? status_badge($h['payment_status']) . ' ' : '' ?><?= e($h['note']) ?></li><?php endforeach; ?>
    </ul></div></div>

    <div class="card mb-3"><div class="card-header">Notes</div><div class="card-body">
      <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="note" class="form-control mb-2" rows="2" placeholder="Add a note…" required></textarea>
        <label class="form-check small"><input type="checkbox" class="form-check-input" name="visible" value="1"> Visible to customer (shown on their order page)</label>
        <button class="btn btn-sm btn-primary mt-2">Add note</button></form>
      <?php foreach ($notes as $n): ?><div class="border-start ps-2 mb-2 <?= $n['is_customer_visible'] ? 'border-success' : 'border-secondary' ?>"><div class="small text-muted"><?= e($n['created_at']) ?> · <?= e($n['admin'] ?? '') ?> · <?= $n['is_customer_visible'] ? 'Customer-visible' : 'Internal' ?></div><?= nl2br(e($n['note'])) ?></div><?php endforeach; ?>
    </div></div>

    <div class="card"><div class="card-header">Payments & transaction log</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Attempt</th><th>Method</th><th>Status</th><th>Reference</th><th>Provider ID</th><th class="text-end">Amount</th><th>Verified</th></tr></thead>
        <tbody><?php foreach ($pays as $p): ?><tr><td>#<?= (int)$p['id'] ?></td><td><?= e($p['method']) ?> <?= $p['mode'] ? '<span class="badge text-bg-light">' . e($p['mode']) . '</span>' : '' ?></td><td><?= status_badge($p['status']) ?><?= $p['failure_reason'] ? '<div class="small text-muted">' . e($p['failure_reason']) . '</div>' : '' ?></td><td class="small"><?= e($p['gateway_reference']) ?></td><td class="small"><?= e($p['provider_txn_id']) ?></td><td class="text-end"><?= money($p['amount']) ?></td><td class="small"><?= e($p['verified_at']) ?></td></tr><?php endforeach; ?></tbody>
      </table>
      <table class="table table-sm mb-0 border-top">
        <thead><tr><th>Time</th><th>Event</th><th>Status</th><th>Signature</th><th>Message</th></tr></thead>
        <tbody><?php foreach ($txns as $t): ?><tr><td class="small"><?= e($t['created_at']) ?></td><td><?= e($t['gateway'] . ' / ' . $t['event_type']) ?></td><td><?= e($t['status']) ?></td><td><?= $t['signature_valid'] === null ? '—' : ($t['signature_valid'] ? '<span class="text-success">valid</span>' : '<span class="text-danger">invalid</span>') ?></td><td class="small"><?= e($t['message']) ?></td></tr><?php endforeach; ?>
        <?php if (!$txns): ?><tr><td colspan="5" class="text-muted small">No gateway events.</td></tr><?php endif; ?></tbody>
      </table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header">Customer</div><div class="card-body small">
      <strong><?= e($o['customer_name']) ?></strong><?= $customer ? ' · <a href="' . e(admin_url('customer-view?id=' . $customer['id'])) . '">account</a>' : ' · guest' ?><br>
      <a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><br><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a>
      <?php if ($addr): ?><hr><strong>Delivery address</strong><br><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city']) ?><?= $addr['province'] ? ', ' . e($addr['province']) : '' ?> <?= e($addr['postal_code']) ?><br><?= e($addr['phone']) ?><?php endif; ?>
      <?php if ($o['customer_note']): ?><hr><strong>Customer note</strong><br><?= nl2br(e($o['customer_note'])) ?><?php endif; ?>
      <hr><span class="text-muted">Placed <?= e($o['created_at']) ?> · Est. delivery <?= e($o['estimated_delivery']) ?><?= $o['has_custom_items'] ? '<br>Custom-order terms accepted: ' . ($o['custom_terms_accepted'] ? 'yes' : 'no') : '' ?></span>
    </div></div>

    <?php if (can('orders.manage')): ?>
    <div class="card mb-3"><div class="card-header">Update status</div><div class="card-body">
      <?php if ($next): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
        <select name="status" class="form-select form-select-sm mb-2"><?php foreach ($next as $s): ?><option value="<?= $s ?>"><?= order_statuses()[$s] ?></option><?php endforeach; ?></select>
        <input name="note" class="form-control form-control-sm mb-2" placeholder="Message to customer (optional)">
        <label class="form-check small"><input type="checkbox" class="form-check-input" name="notify" value="1" checked> Email the customer</label>
        <label class="form-check small"><input type="checkbox" class="form-check-input" name="restock" value="1" checked> Return items to stock (for cancellations/returns)</label>
        <button class="btn btn-sm btn-primary mt-2">Update</button>
      </form>
      <?php else: ?><p class="small text-muted mb-0">This order is <?= e(order_statuses()[$o['status']]) ?> — no further status changes.</p><?php endif; ?>
    </div></div>

    <div class="card mb-3"><div class="card-header">Courier & tracking</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="tracking">
        <input name="courier_name" class="form-control form-control-sm mb-2" placeholder="Courier (e.g. TCS, Leopards, M&P)" value="<?= e($o['courier_name']) ?>">
        <input name="tracking_number" class="form-control form-control-sm mb-2" placeholder="Tracking number" value="<?= e($o['tracking_number']) ?>">
        <input name="tracking_url" class="form-control form-control-sm mb-2" placeholder="Tracking link (optional)" value="<?= e($o['tracking_url']) ?>">
        <label class="form-check small"><input type="checkbox" class="form-check-input" name="visible" value="1" checked> Add update to customer timeline</label>
        <button class="btn btn-sm btn-primary mt-2">Save</button></form>
    </div></div>

    <div class="card mb-3"><div class="card-header">Payment</div><div class="card-body">
      <?php if ($o['payment_method'] === 'cod' && !$o['cod_collected'] && !in_array($o['status'], ['cancelled', 'returned'], true)): ?>
        <form method="post" class="mb-3" data-confirm="Confirm the courier has remitted <?= e(money($o['grand_total'])) ?> for this order?"><?= csrf_field() ?><input type="hidden" name="action" value="cod_collected"><button class="btn btn-sm btn-success w-100"><i class="bi bi-cash-coin"></i> Mark COD collected (<?= money($o['grand_total']) ?>)</button></form>
      <?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment_status">
        <label class="form-label small">Record payment status manually</label>
        <select name="payment_status" class="form-select form-select-sm mb-2"><?php foreach (['unpaid', 'pending', 'paid', 'failed', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= $o['payment_status'] === $s ? ' selected' : '' ?>><?= payment_statuses()[$s] ?></option><?php endforeach; ?></select>
        <input name="reference" class="form-control form-control-sm mb-2" placeholder="Reference / transaction ID (required for paid)">
        <button class="btn btn-sm btn-outline-secondary">Record</button>
        <div class="form-text">Online payments update automatically after server-side verification. Use this only for confirmed manual reconciliations.</div>
      </form>
      <?php if (can('orders.refund') && in_array($o['payment_status'], ['paid', 'partially_refunded'], true)): ?>
        <hr><form method="post" data-confirm="Record this refund?"><?= csrf_field() ?><input type="hidden" name="action" value="refund">
          <label class="form-label small">Refund (max <?= money($o['grand_total'] - $o['refunded_total']) ?>)</label>
          <input name="amount" type="number" step="0.01" min="1" max="<?= e($o['grand_total'] - $o['refunded_total']) ?>" class="form-control form-control-sm mb-2" required>
          <input name="reason" class="form-control form-control-sm mb-2" placeholder="Reason">
          <button class="btn btn-sm btn-outline-danger">Record refund</button>
          <div class="form-text"><?= $o['payment_method'] === 'card' ? 'Card refunds are sent to the card gateway automatically.' : 'Wallet/COD refunds are recorded here — send the money via the gateway portal or bank transfer.' ?></div>
        </form>
      <?php endif; ?>
    </div></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
