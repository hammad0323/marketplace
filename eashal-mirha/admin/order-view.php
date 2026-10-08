<?php
require __DIR__ . '/includes/admin.php';

$id = (int)get('id');
$o = row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders'); }

if (is_post()) {
    require_csrf();
    $do = post('do');
    if ($do === 'update') {
        $status = isset(order_statuses()[post('status')]) ? post('status') : $o['status'];
        $pay = isset(payment_statuses()[post('payment_status')]) ? post('payment_status') : $o['payment_status'];
        q('UPDATE orders SET status = ?, payment_status = ?, courier = ?, tracking_no = ?, admin_note = ?, updated_at = NOW() WHERE id = ?',
            [$status, $pay, mb_substr(post('courier'), 0, 80), mb_substr(post('tracking_no'), 0, 80), post('admin_note'), $id]);
        // Restock when an order is cancelled/returned (once)
        $wasActive = !in_array($o['status'], ['cancelled', 'returned'], true);
        $nowInactive = in_array($status, ['cancelled', 'returned'], true);
        if ($wasActive && $nowInactive && post('restock') === '1') {
            foreach (rows('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$id]) as $it) {
                q('UPDATE products SET stock = stock + ?, sales_count = GREATEST(sales_count - ?, 0) WHERE id = ?', [$it['qty'], $it['qty'], $it['product_id']]);
            }
        }
        $notes = [];
        if ($status !== $o['status']) $notes[] = 'Status: ' . order_statuses()[$status];
        if ($pay !== $o['payment_status']) $notes[] = 'Payment: ' . payment_statuses()[$pay];
        if (post('tracking_no') !== '' && post('tracking_no') !== (string)$o['tracking_no']) $notes[] = 'Tracking: ' . post('courier') . ' ' . post('tracking_no');
        if (post('history_note') !== '') $notes[] = post('history_note');
        if ($notes) order_add_history($id, $status, implode(' · ', $notes));
        if (post('notify') === '1' && $status !== $o['status']) {
            notify_status_change(row('SELECT * FROM orders WHERE id = ?', [$id]));
        }
        flash('success', 'Order updated.');
    }
    if ($do === 'customer') {
        q('UPDATE orders SET name = ?, phone = ?, email = ?, address = ?, city = ?, postal_code = ? WHERE id = ?',
            [post('name'), post('phone'), post('email'), post('address'), post('city'), post('postal_code'), $id]);
        order_add_history($id, $o['status'], 'Customer details edited by admin.');
        flash('success', 'Customer details updated.');
    }
    if ($do === 'delete' && admin_can('settings')) {
        q('DELETE FROM order_items WHERE order_id = ?', [$id]);
        q('DELETE FROM order_history WHERE order_id = ?', [$id]);
        q('DELETE FROM orders WHERE id = ?', [$id]);
        flash('success', 'Order ' . $o['order_no'] . ' deleted.');
        redirect('admin/orders');
    }
    redirect('admin/order-view?id=' . $id);
}

$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$id]);
$history = rows('SELECT * FROM order_history WHERE order_id = ? ORDER BY id DESC', [$id]);
$customer = $o['customer_id'] ? row('SELECT * FROM customers WHERE id = ?', [$o['customer_id']]) : null;
$prevOrders = (int)val('SELECT COUNT(*) FROM orders WHERE phone = ? AND id <> ?', [$o['phone'], $id]);
$wa = preg_replace('~\D~', '', $o['phone']);
if (strpos($wa, '0') === 0) $wa = '92' . substr($wa, 1);

admin_header('Order ' . $o['order_no'], 'orders');
?>
<div class="toolbar">
  <div><a class="link" href="<?= url('admin/orders') ?>">← All orders</a> <span class="muted">· Placed <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?> · IP <?= e($o['ip']) ?></span></div>
  <div class="btn-row">
    <a class="btn" target="_blank" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Assalam o Alaikum ' . $o['name'] . ', this is ' . setting('site_name') . ' regarding your order ' . $o['order_no'] . ' (' . money($o['total']) . ').') ?>">WhatsApp Customer</a>
    <a class="btn" target="_blank" href="<?= url('admin/invoice?id=' . $id) ?>"><?= aicon('print') ?> Invoice</a>
    <?php if (admin_can('settings')): ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn btn-danger" data-confirm="Delete this order permanently?"><?= aicon('trash') ?> Delete</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="edit-layout">
  <div class="edit-main">
    <div class="card">
      <div class="card__head"><h3>Items</h3><span><?= status_badge($o['status']) ?> <?= status_badge($o['payment_status']) ?></span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th>Options</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><div class="prod-cell"><img src="<?= e(img($it['image'])) ?>" alt=""><div><?php if ($it['product_id']): ?><a href="<?= url('admin/product-edit?id=' . $it['product_id']) ?>"><?= e($it['name']) ?></a><?php else: ?><?= e($it['name']) ?><?php endif; ?><small class="block muted"><?= e($it['sku']) ?></small></div></div></td>
            <td><?= e(trim($it['size'] . ($it['color'] ? ' · ' . $it['color'] : ''), ' ·')) ?: '—' ?></td>
            <td><?= money($it['price']) ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td><?= money($it['price'] * $it['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="totals">
        <div><span>Subtotal</span><b><?= money($o['subtotal']) ?></b></div>
        <?php if ((float)$o['discount'] > 0): ?><div><span>Discount <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></span><b>− <?= money($o['discount']) ?></b></div><?php endif; ?>
        <div><span>Delivery</span><b><?= money($o['shipping']) ?></b></div>
        <?php if ((float)$o['payment_fee'] > 0): ?><div><span>COD fee</span><b><?= money($o['payment_fee']) ?></b></div><?php endif; ?>
        <div class="grand"><span>Total</span><b><?= money($o['total']) ?></b></div>
      </div>
    </div>

    <div class="grid-2">
      <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="do" value="customer">
        <div class="card__head"><h3>Customer & delivery</h3><?php if ($customer): ?><a class="link sm" href="<?= url('admin/customers?id=' . $customer['id']) ?>">Account →</a><?php else: ?><span class="pill">Guest</span><?php endif; ?></div>
        <?= f_text('name', 'Name', $o['name']) ?>
        <div class="row-2"><?= f_text('phone', 'Phone', $o['phone']) ?><?= f_text('email', 'Email', $o['email'], ['type' => 'email']) ?></div>
        <?= f_text('address', 'Address', $o['address']) ?>
        <div class="row-2"><?= f_text('city', 'City', $o['city']) ?><?= f_text('postal_code', 'Postal code', $o['postal_code']) ?></div>
        <?php if ($o['notes']): ?><div class="note"><b>Customer note:</b> <?= nl2br(e($o['notes'])) ?></div><?php endif; ?>
        <p class="muted sm"><?= $prevOrders ? $prevOrders . ' other order(s) from this phone number.' : 'First order from this phone number.' ?></p>
        <button class="btn btn-sm">Save details</button>
      </form>
      <div class="card">
        <div class="card__head"><h3>Payment</h3></div>
        <div class="kv"><span>Method</span><b><?= e(payment_label($o['payment_method'])) ?> <small class="muted">(<?= e(payment_mode($o['payment_method'])) ?>)</small></b></div>
        <div class="kv"><span>Status</span><b><?= status_badge($o['payment_status']) ?></b></div>
        <?php if ($o['txn_id']): ?><div class="kv"><span>Transaction ID</span><b><?= e($o['txn_id']) ?></b></div><?php endif; ?>
        <?php if ($o['txn_ref']): ?><div class="kv"><span>Gateway ref</span><b><?= e($o['txn_ref']) ?></b></div><?php endif; ?>
        <?php if ($o['payment_proof']): ?><a href="<?= e(img($o['payment_proof'])) ?>" target="_blank" class="proof"><img src="<?= e(img($o['payment_proof'])) ?>" alt="Payment proof"><span>Payment screenshot — click to enlarge</span></a><?php endif; ?>
        <?php if ($o['gateway_response']): ?><details class="seo-box"><summary>Gateway response</summary><pre class="code"><?= e(json_encode(json_decode($o['gateway_response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif; ?>
        <?php if ($o['payment_status'] === 'pending_verification'): ?><div class="note">Check your <?= e(payment_label($o['payment_method'])) ?> account for this payment, then set Payment status to <b>Paid</b>.</div><?php endif; ?>
      </div>
    </div>
  </div>

  <aside class="edit-side">
    <form method="post" class="card sticky">
      <?= csrf_field() ?><input type="hidden" name="do" value="update">
      <div class="card__head"><h3>Update order</h3></div>
      <?= f_select('status', 'Order status', $o['status'], order_statuses()) ?>
      <?= f_select('payment_status', 'Payment status', $o['payment_status'], payment_statuses()) ?>
      <div class="row-2">
        <?= f_text('courier', 'Courier', $o['courier'], ['attrs' => 'list="couriers"']) ?>
        <?= f_text('tracking_no', 'Tracking #', $o['tracking_no']) ?>
      </div>
      <datalist id="couriers"><?php foreach (['TCS', 'Leopards', 'M&P', 'PostEx', 'Trax', 'BlueEx', 'Call Courier', 'Rider', 'Pakistan Post'] as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
      <?= f_text('history_note', 'Add note to timeline', '', ['help' => 'Optional — visible to the customer on their order page.']) ?>
      <?= f_text('admin_note', 'Private admin note', $o['admin_note'], ['type' => 'textarea', 'rows' => 2]) ?>
      <?= f_switch('restock', 'Return items to stock if cancelling/returning', 1) ?>
      <?= f_switch('notify', 'Email customer about status change', $o['email'] ? 1 : 0) ?>
      <button class="btn btn-primary btn-block">Update Order</button>
    </form>
    <div class="card">
      <div class="card__head"><h3>Timeline</h3></div>
      <ul class="timeline">
        <?php foreach ($history as $h): ?><li><time><?= date('d M, h:i A', strtotime($h['created_at'])) ?></time><?= e($h['note'] ?: ucfirst($h['status'])) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </aside>
</div>
<?php admin_footer();
