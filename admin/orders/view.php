<?php
/** Order detail: fulfilment, payment, courier, notes, cancellation and refunds. */
require __DIR__ . '/../includes/init.php';
admin_require('orders');
$id = input_int('id');
$order = db_row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders/');
}
$aid = admin_id();

if (is_post()) {
    $action = input('action', '', 'post');
    $note = mb_substr(input('note', '', 'post'), 0, 2000);
    $visible = input_bool('visible');
    try {
        switch ($action) {
            case 'status':
                $status = input('status', '', 'post');
                if (!isset(order_statuses()[$status]) || in_array($status, ['refunded'], true)) {
                    throw new RuntimeException('Choose a valid status. Use the Refund panel to refund.');
                }
                if (in_array($order['status'], ['cancelled', 'refunded'], true)) {
                    throw new RuntimeException('This order is ' . $order['status'] . ' and can no longer change status.');
                }
                if ($status === 'cancelled') {
                    if (in_array($order['status'], ['shipped', 'delivered'], true)) {
                        throw new RuntimeException('Shipped or delivered orders cannot be cancelled — use a refund / return instead.');
                    }
                    order_cancel($id, $note ?: 'Order cancelled by store.', $aid, $visible);
                } elseif ($status !== $order['status']) {
                    $extra = '';
                    if ($status === 'shipped') $extra = ', shipped_at = COALESCE(shipped_at, NOW())';
                    if ($status === 'delivered') $extra = ', delivered_at = COALESCE(delivered_at, NOW())';
                    db_exec("UPDATE orders SET status = ? $extra WHERE id = ?", [$status, $id]);
                    order_history_add($id, ['status' => $status, 'note' => $note ?: null, 'visible' => $visible, 'admin_id' => $aid]);
                }
                if ($status === 'delivered' && $order['payment_method'] === 'cod' && input_bool('cod_collected') && $order['payment_status'] !== 'paid') {
                    cod_mark_collected($order, $aid);
                }
                audit_log('order_status', 'order', $id, ['from' => $order['status'], 'to' => $status]);
                if (input_bool('notify')) {
                    mail_order_status(db_row('SELECT * FROM orders WHERE id = ?', [$id]), $visible ? $note : '');
                }
                flash('success', 'Order status updated.');
                break;
            case 'cod_collected':
                cod_mark_collected($order, $aid);
                flash('success', 'Cash collected — order marked as paid.');
                break;
            case 'payment':
                $ps = input('payment_status', '', 'post');
                if (!in_array($ps, ['unpaid', 'pending', 'paid', 'failed', 'cancelled'], true)) {
                    throw new RuntimeException('Invalid payment status.');
                }
                if ($ps === 'paid' && $order['payment_method'] !== 'cod' && !input_bool('confirm_manual')) {
                    throw new RuntimeException('Online payments are confirmed automatically by the gateway. Tick the confirmation box to record a manual payment you have verified in the merchant portal.');
                }
                db_transaction(function () use ($id, $ps, $order, $note, $aid) {
                    $paid = $ps === 'paid' ? (float) $order['grand_total'] : (float) $order['amount_paid'];
                    db_exec('UPDATE orders SET payment_status = ?, amount_paid = ? WHERE id = ?', [$ps, $paid, $id]);
                    if ($ps === 'paid') {
                        db_exec("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$id]);
                        if ($order['status'] === 'pending') {
                            db_exec("UPDATE orders SET status = 'confirmed' WHERE id = ?", [$id]);
                        }
                    }
                    payment_log($order['payment_method'], 'manual_status', ['order_id' => $id, 'status' => $ps, 'amount' => $paid, 'message' => 'Set by admin #' . $aid . ($note ? ': ' . $note : '')]);
                    order_history_add($id, ['payment_status' => $ps, 'note' => $note ?: 'Payment status recorded manually.', 'type' => 'payment', 'admin_id' => $aid]);
                });
                audit_log('order_payment_status', 'order', $id, ['from' => $order['payment_status'], 'to' => $ps]);
                flash('success', 'Payment status recorded.');
                break;
            case 'verify':
                $p = db_row("SELECT * FROM payments WHERE order_id = ? AND status IN ('pending','failed') ORDER BY id DESC LIMIT 1", [$id]);
                if (!$p) throw new RuntimeException('No pending payment attempt to verify.');
                $res = payment_verify($p);
                audit_log('order_payment_verify', 'order', $id, $res);
                flash(!empty($res['paid']) ? 'success' : 'info', $res['message'] ?? 'Checked.');
                break;
            case 'courier':
                $url = input('tracking_url', '', 'post');
                db_update('orders', [
                    'courier_name' => nullable(mb_substr(input('courier_name', '', 'post'), 0, 80)),
                    'tracking_number' => nullable(mb_substr(input('tracking_number', '', 'post'), 0, 80)),
                    'tracking_url' => $url !== '' && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $url) ? $url : null,
                ], 'id = ?', [$id]);
                order_history_add($id, ['note' => 'Courier: ' . input('courier_name', '', 'post') . ' · Tracking: ' . input('tracking_number', '', 'post'), 'visible' => true, 'type' => 'note', 'admin_id' => $aid]);
                audit_log('order_courier', 'order', $id);
                if (input_bool('mark_shipped') && in_array($order['status'], ['pending', 'confirmed', 'processing'], true)) {
                    db_exec("UPDATE orders SET status = 'shipped', shipped_at = NOW() WHERE id = ?", [$id]);
                    order_history_add($id, ['status' => 'shipped', 'note' => 'Handed to courier.', 'visible' => true, 'admin_id' => $aid]);
                }
                if (input_bool('notify')) {
                    mail_order_status(db_row('SELECT * FROM orders WHERE id = ?', [$id]));
                }
                flash('success', 'Courier details saved.');
                break;
            case 'note':
                if ($note === '') throw new RuntimeException('Write a note first.');
                order_history_add($id, ['note' => $note, 'visible' => $visible, 'type' => 'note', 'admin_id' => $aid]);
                flash('success', $visible ? 'Note added (visible to customer).' : 'Internal note added.');
                break;
            case 'refund':
                if (!admin_can('orders.refund')) throw new RuntimeException('Your role cannot issue refunds.');
                $amount = round((float) input_float('amount', 0), 2);
                $refundable = (float) $order['amount_paid'] - (float) $order['amount_refunded'];
                if ($amount <= 0 || $amount > $refundable + 0.001) throw new RuntimeException('Refund amount must be between 1 and ' . money($refundable) . ' (amount paid minus earlier refunds).');
                db_transaction(function () use ($id, $amount, $order, $note, $aid) {
                    $o = db_row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
                    $newRefunded = (float) $o['amount_refunded'] + $amount;
                    $full = $newRefunded >= (float) $o['amount_paid'] - 0.001;
                    db_exec('UPDATE orders SET amount_refunded = ?, payment_status = ?, status = ? WHERE id = ?', [$newRefunded, $full ? 'refunded' : 'partially_refunded', $full ? 'refunded' : $o['status'], $id]);
                    $p = db_row("SELECT * FROM payments WHERE order_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 1", [$id]);
                    if ($p) {
                        db_exec('UPDATE payments SET refunded_amount = refunded_amount + ?, status = IF(refunded_amount >= amount, \'refunded\', status) WHERE id = ?', [$amount, (int) $p['id']]);
                    }
                    if (input_bool('restock')) {
                        order_restore_stock($o, 'refund', $aid);
                    }
                    payment_log($o['payment_method'], 'refund_recorded', ['payment_id' => $p['id'] ?? null, 'order_id' => $id, 'amount' => $amount, 'status' => 'refunded', 'message' => 'Recorded by admin #' . $aid . ($note ? ': ' . $note : '')]);
                    order_history_add($id, ['status' => $full ? 'refunded' : null, 'payment_status' => $full ? 'refunded' : 'partially_refunded', 'note' => 'Refund of ' . money($amount) . ' recorded.' . ($note ? ' ' . $note : ''), 'visible' => true, 'type' => 'payment', 'admin_id' => $aid]);
                });
                audit_log('order_refund', 'order', $id, ['amount' => $amount, 'restock' => input_bool('restock')]);
                flash('success', 'Refund of ' . money($amount) . ' recorded. Remember to send the money back via the payment provider / bank.');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/orders/view.php?id=' . $id);
}

function cod_mark_collected(array $order, ?int $aid): void
{
    db_transaction(function () use ($order, $aid) {
        db_exec("UPDATE orders SET payment_status = 'paid', amount_paid = grand_total, cod_collected = 1 WHERE id = ?", [(int) $order['id']]);
        db_exec("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND method = 'cod' AND status = 'pending'", [(int) $order['id']]);
        payment_log('cod', 'cod_collected', ['order_id' => (int) $order['id'], 'amount' => (float) $order['grand_total'], 'status' => 'paid', 'message' => 'Cash collected, recorded by admin #' . $aid]);
        order_history_add((int) $order['id'], ['payment_status' => 'paid', 'note' => 'Cash on Delivery collected.', 'type' => 'payment', 'admin_id' => $aid]);
    });
    audit_log('order_cod_collected', 'order', (int) $order['id']);
}

$items = order_items($id);
$addr = order_address($id);
$history = db_all('SELECT h.*, a.name admin_name FROM order_status_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE h.order_id = ? ORDER BY h.id DESC', [$id]);
$txns = db_all('SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 50', [$id]);
$pm = payment_method_get($order['payment_method']);
$customer = $order['customer_id'] ? db_row('SELECT * FROM customers WHERE id = ?', [(int) $order['customer_id']]) : null;
$prevOrders = (int) db_val('SELECT COUNT(*) FROM orders WHERE (email = ? OR phone = ?) AND id <> ?', [$order['email'], $order['phone'], $id]);
$final = in_array($order['status'], ['cancelled', 'refunded'], true);
admin_header('Order ' . $order['order_number']);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?= status_badge($order['status']) ?> <?= status_badge($order['payment_status']) ?>
    <span class="text-muted small">Placed <?= e(format_datetime($order['created_at'])) ?> · <?= e($pm['name'] ?? $order['payment_method']) ?></span>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/orders/invoice.php?id=' . $id)) ?>" target="_blank"><i class="bi bi-printer"></i> Invoice</a>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/orders/invoice.php?id=' . $id . '&type=packing')) ?>" target="_blank"><i class="bi bi-box"></i> Packing slip</a>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('order/' . $order['order_number'] . '?t=' . $order['access_token'])) ?>" target="_blank"><i class="bi bi-eye"></i> Customer view</a>
    </div>
</div>
<div class="row g-4">
<div class="col-xl-8">
    <div class="card a-card"><div class="card-header">Items</div><div class="table-responsive"><table class="table a-table mb-0">
        <thead><tr><th></th><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th class="text-end">Total</th></tr></thead><tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><div class="a-thumb a-thumb-sm"><?php if ($it['image']): ?><img src="<?= e(img_sized($it['image'], 120)) ?>" alt=""><?php endif; ?></div></td>
            <td><?= $it['product_id'] ? '<a href="' . e(url('admin/products/edit.php?id=' . $it['product_id'])) . '">' . e($it['product_name']) . '</a>' : e($it['product_name']) ?>
                <?php if ($it['variant_label']): ?><div class="small text-muted"><?= e($it['variant_label']) ?></div><?php endif; ?>
                <?php if ($it['made_to_order']): ?><div class="small text-warning"><i class="bi bi-hourglass-split"></i> Made to order — <?= (int) $it['lead_time_days'] ?> days</div><?php endif; ?>
                <?php if ($it['custom_measurements'] || $it['custom_color'] || $it['personalization'] || $it['custom_instructions']): ?>
                    <div class="a-help mt-2 small"><strong><i class="bi bi-scissors"></i> Customisation (+<?= e(money($it['customization_fee'])) ?> each)</strong>
                        <?php if ($it['custom_measurements']): ?><div>Measurements: <?= e($it['custom_measurements']) ?></div><?php endif; ?>
                        <?php if ($it['custom_color']): ?><div>Colour: <?= e($it['custom_color']) ?></div><?php endif; ?>
                        <?php if ($it['personalization']): ?><div>Personalisation: <?= e($it['personalization']) ?></div><?php endif; ?>
                        <?php if ($it['custom_instructions']): ?><div>Instructions: <?= e($it['custom_instructions']) ?></div><?php endif; ?>
                        <div class="text-muted mt-1">Confirm the custom details with the customer before production.</div></div>
                <?php endif; ?></td>
            <td class="small"><?= e($it['sku']) ?></td><td class="text-nowrap"><?= e(money($it['unit_price'])) ?><?= $it['regular_price'] > $it['unit_price'] ? '<div class="small text-muted"><s>' . e(money($it['regular_price'])) . '</s></div>' : '' ?></td><td><?= (int) $it['qty'] ?></td><td class="text-end"><?= e(money($it['line_total'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="card-body"><dl class="row mb-0 small" style="max-width:380px;margin-left:auto">
            <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end"><?= e(money($order['subtotal'])) ?></dd>
            <?php if ((float) $order['customization_total']): ?><dt class="col-7 fw-normal">Customisation</dt><dd class="col-5 text-end"><?= e(money($order['customization_total'])) ?></dd><?php endif; ?>
            <?php if ((float) $order['discount_total']): ?><dt class="col-7 fw-normal">Discount (<?= e($order['coupon_code']) ?>)</dt><dd class="col-5 text-end text-success">−<?= e(money($order['discount_total'])) ?></dd><?php endif; ?>
            <dt class="col-7 fw-normal">Delivery (<?= e($order['shipping_zone']) ?>, <?= e($order['shipping_method']) ?>)</dt><dd class="col-5 text-end"><?= e(money($order['shipping_total'])) ?></dd>
            <?php if ((float) $order['payment_fee']): ?><dt class="col-7 fw-normal">Payment fee</dt><dd class="col-5 text-end"><?= e(money($order['payment_fee'])) ?></dd><?php endif; ?>
            <dt class="col-7 fs-6">Total</dt><dd class="col-5 text-end fs-6 fw-bold"><?= e(money($order['grand_total'])) ?></dd>
            <dt class="col-7 fw-normal">Paid</dt><dd class="col-5 text-end"><?= e(money($order['amount_paid'])) ?></dd>
            <?php if ((float) $order['amount_refunded']): ?><dt class="col-7 fw-normal">Refunded</dt><dd class="col-5 text-end text-danger"><?= e(money($order['amount_refunded'])) ?></dd><?php endif; ?>
            <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' && !$final): ?><dt class="col-7">Amount due (COD)</dt><dd class="col-5 text-end fw-bold"><?= e(money($order['grand_total'])) ?></dd><?php endif; ?>
        </dl></div>
    </div>

    <div class="card a-card"><div class="card-header">Timeline &amp; notes</div><div class="card-body">
        <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="note">
            <textarea class="form-control mb-2" name="note" rows="2" placeholder="Add a note…" required></textarea>
            <div class="d-flex justify-content-between"><div class="form-check"><input class="form-check-input" type="checkbox" name="visible" value="1" id="nv"><label class="form-check-label small" for="nv">Visible to customer</label></div><button class="btn btn-sm btn-dark">Add note</button></div></form>
        <ul class="list-unstyled mb-0">
        <?php foreach ($history as $h): ?>
            <li class="border-top py-2 small"><span class="text-muted"><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['admin_name'] ?? 'System') ?></span>
                <?= $h['is_customer_visible'] ? '<span class="badge text-bg-light">customer-visible</span>' : '<span class="badge text-bg-secondary">internal</span>' ?><br>
                <?= $h['status'] ? status_badge($h['status']) . ' ' : '' ?><?= $h['payment_status'] ? status_badge($h['payment_status']) . ' ' : '' ?><?= e($h['note']) ?></li>
        <?php endforeach; ?></ul>
    </div></div>

    <div class="card a-card"><div class="card-header">Payment transactions (gateway log)</div><div class="table-responsive"><table class="table a-table mb-0 small">
        <thead><tr><th>When</th><th>Gateway</th><th>Event</th><th>Status</th><th>Amount</th><th>Txn</th><th>Signature</th><th>Message</th></tr></thead><tbody>
        <?php foreach ($txns as $t): ?><tr><td class="text-nowrap"><?= e(format_datetime($t['created_at'])) ?></td><td><?= e($t['gateway']) ?></td><td><?= e($t['event_type']) ?></td><td><?= e($t['status']) ?></td>
        <td><?= $t['amount'] !== null ? e(money($t['amount'])) : '' ?></td><td><?= e($t['provider_txn_id']) ?></td><td><?= $t['signature_valid'] === null ? '—' : ((int) $t['signature_valid'] ? '<span class="text-success">valid</span>' : '<span class="text-danger">invalid</span>') ?></td><td><?= e($t['message']) ?></td></tr><?php endforeach; ?>
        <?php if (!$txns): ?><tr><td colspan="8" class="text-muted text-center">No gateway activity.</td></tr><?php endif; ?>
        </tbody></table></div></div>
</div>

<div class="col-xl-4">
    <div class="card a-card"><div class="card-header">Customer</div><div class="card-body small">
        <strong><?= e($order['customer_name']) ?></strong> <?= $customer ? '<a href="' . e(url('admin/customers/view.php?id=' . $customer['id'])) . '">(account)</a>' : '<span class="text-muted">(guest)</span>' ?><br>
        <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a>
        <?php if ($prevOrders): ?><div class="mt-1"><a href="<?= e(url('admin/orders/?q=' . urlencode($order['email']))) ?>"><?= $prevOrders ?> other order(s)</a></div><?php endif; ?>
        <?php if ($addr): ?><hr><strong>Delivery address</strong><br><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city']) ?>, <?= e($addr['region']) ?> <?= e($addr['postal_code']) ?><br><?= e($addr['phone']) ?><?php endif; ?>
        <?php if ($order['customer_notes']): ?><hr><strong>Customer notes</strong><p class="mb-0"><?= nl2br(e($order['customer_notes'])) ?></p><?php endif; ?>
        <hr><span class="text-muted">Estimated delivery: <?= e($order['estimated_delivery']) ?></span>
    </div></div>

    <?php if (!$final): ?>
    <div class="card a-card"><div class="card-header">Update status</div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
            <select class="form-select mb-2" name="status"><?php foreach (order_statuses() as $k => $l): if ($k === 'refunded') continue; ?><option value="<?= $k ?>"<?= $order['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            <textarea class="form-control mb-2" name="note" rows="2" placeholder="Note (optional)"></textarea>
            <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid'): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="cod_collected" value="1" id="cc"><label class="form-check-label" for="cc">If delivered: cash collected</label></div><?php endif; ?>
            <div class="form-check small"><input class="form-check-input" type="checkbox" name="visible" value="1" id="sv" checked><label class="form-check-label" for="sv">Show note to customer</label></div>
            <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" id="sn"<?= mail_enabled() ? ' checked' : ' disabled' ?>><label class="form-check-label" for="sn">Email the customer<?= mail_enabled() ? '' : ' (email not configured)' ?></label></div>
            <button class="btn btn-dark w-100">Update</button>
            <p class="small text-muted mt-2 mb-0">Cancelling restores stock and releases the coupon use.</p>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card a-card"><div class="card-header">Payment</div><div class="card-body">
        <p class="small mb-2"><?= e($pm['name'] ?? $order['payment_method']) ?> · <?= status_badge($order['payment_status']) ?></p>
        <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' && !$final): ?>
            <?= a_post_button(url('admin/orders/view.php?id=' . $id), ['action' => 'cod_collected'], '<i class="bi bi-cash"></i> Mark cash collected (' . e(money($order['grand_total'])) . ')', 'btn btn-success w-100 mb-3', 'Confirm the courier has collected ' . money($order['grand_total']) . '?') ?>
        <?php endif; ?>
        <?php if ($order['payment_method'] !== 'cod' && in_array($order['payment_status'], ['pending', 'failed', 'unpaid'], true)): ?>
            <?= a_post_button(url('admin/orders/view.php?id=' . $id), ['action' => 'verify'], '<i class="bi bi-arrow-repeat"></i> Verify with ' . e($pm['name'] ?? 'gateway'), 'btn btn-outline-dark w-100 mb-3', 'Ask the payment provider for the latest status of this payment?') ?>
        <?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
            <select class="form-select form-select-sm mb-2" name="payment_status"><?php foreach (['unpaid', 'pending', 'paid', 'failed', 'cancelled'] as $k): ?><option value="<?= $k ?>"<?= $order['payment_status'] === $k ? ' selected' : '' ?>><?= payment_statuses()[$k] ?></option><?php endforeach; ?></select>
            <input class="form-control form-control-sm mb-2" name="note" placeholder="Reference / note">
            <?php if ($order['payment_method'] !== 'cod'): ?><div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_manual" value="1" id="cm"><label class="form-check-label" for="cm">I verified this payment in the merchant portal</label></div><?php endif; ?>
            <button class="btn btn-sm btn-outline-dark w-100">Record payment status</button>
        </form>
    </div></div>

    <div class="card a-card"><div class="card-header">Courier &amp; tracking</div><div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="courier">
            <input class="form-control form-control-sm mb-2" name="courier_name" value="<?= e($order['courier_name']) ?>" placeholder="Courier (e.g. TCS, Leopards, M&P)" list="couriers">
            <datalist id="couriers"><option value="TCS"><option value="Leopards Courier"><option value="M&P"><option value="Trax"><option value="PostEx"><option value="Call Courier"><option value="BlueEx"></datalist>
            <input class="form-control form-control-sm mb-2" name="tracking_number" value="<?= e($order['tracking_number']) ?>" placeholder="Tracking number">
            <input class="form-control form-control-sm mb-2" name="tracking_url" value="<?= e($order['tracking_url']) ?>" placeholder="Tracking link (https://…)">
            <?php if (in_array($order['status'], ['pending', 'confirmed', 'processing'], true)): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="mark_shipped" value="1" id="ms" checked><label class="form-check-label" for="ms">Mark as shipped</label></div><?php endif; ?>
            <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" id="cn"<?= mail_enabled() ? ' checked' : ' disabled' ?>><label class="form-check-label" for="cn">Email the customer</label></div>
            <button class="btn btn-sm btn-dark w-100">Save courier details</button>
        </form>
    </div></div>

    <?php if (admin_can('orders.refund') && (float) $order['amount_paid'] > (float) $order['amount_refunded']): ?>
    <div class="card a-card"><div class="card-header">Refund</div><div class="card-body">
        <p class="small text-muted">Records a refund against this order. Send the money back through JazzCash / Easypaisa / Stripe portal or bank transfer — refunds are not pushed to gateways automatically.</p>
        <form method="post" data-confirm="Record this refund?"><?= csrf_field() ?><input type="hidden" name="action" value="refund">
            <input class="form-control form-control-sm mb-2" type="number" step="0.01" name="amount" value="<?= e(round($order['amount_paid'] - $order['amount_refunded'], 2)) ?>" max="<?= e(round($order['amount_paid'] - $order['amount_refunded'], 2)) ?>">
            <input class="form-control form-control-sm mb-2" name="note" placeholder="Reason / refund reference">
            <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="restock" value="1" id="rs"<?= (int) $order['stock_restored'] ? ' disabled' : '' ?>><label class="form-check-label" for="rs">Return items to stock<?= (int) $order['stock_restored'] ? ' (already restored)' : '' ?></label></div>
            <button class="btn btn-sm btn-outline-danger w-100">Record refund</button>
        </form>
    </div></div>
    <?php endif; ?>
</div>
</div>
<?php admin_footer();
