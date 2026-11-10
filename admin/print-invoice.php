<?php
require __DIR__ . '/includes/init.php';
admin_require('orders');
$o = db_row('SELECT * FROM orders WHERE id = ?', [input_int('id')]);
if (!$o) exit('Order not found');
$items = order_items((int) $o['id']);
$printTitle = 'Invoice ' . $o['order_number'];
require __DIR__ . '/includes/print-head.php';
?>
<div class="page">
  <div class="head">
    <div><img src="<?= url('assets/images/logo.svg') ?>" alt="Fahim Store"><div class="muted" style="margin-top:6px"><?= e(setting('store_address')) ?>, <?= e(setting('store_city')) ?><br><?= e(setting('store_phone')) ?> · <?= e(setting('store_email')) ?></div></div>
    <div class="r"><h1>INVOICE</h1><div><strong><?= e($o['order_number']) ?></strong></div><div class="muted"><?= fmt_datetime($o['created_at']) ?></div><div>Status: <?= e(status_label($o['status'])) ?></div></div>
  </div>
  <div class="grid">
    <div class="box"><div class="muted">Bill to</div><strong><?= e($o['customer_name']) ?></strong><br><?= e($o['customer_phone']) ?><?= $o['customer_email'] ? '<br>' . e($o['customer_email']) : '' ?></div>
    <div class="box"><div class="muted"><?= $o['fulfillment'] === 'pickup' ? 'Store pickup' : ($o['fulfillment'] === 'walkin' ? 'Walk-in' : 'Deliver to') ?></div>
      <?= $o['fulfillment'] === 'delivery' ? e($o['address_line']) . '<br>' . e($o['area'] . ', ' . $o['city']) : ($o['fulfillment'] === 'pickup' ? e(fmt_datetime($o['pickup_at'])) : 'Counter sale') ?></div>
    <div class="box"><div class="muted">Payment</div><?= e(payment_method_name($o['payment_method'])) ?><br><strong><?= e(strtoupper($o['payment_status'])) ?></strong><?= $o['payment_reference'] ? '<br>Ref ' . e($o['payment_reference']) : '' ?></div>
  </div>
  <table><thead><tr><th>#</th><th>Item</th><th>SKU</th><th class="r">Price</th><th class="c">Qty</th><th class="r">Total</th></tr></thead><tbody>
  <?php foreach ($items as $i => $it): ?><tr><td><?= $i + 1 ?></td><td><?= e($it['product_name']) ?><?= $it['variation_name'] ? ' (' . e($it['variation_name']) . ')' : '' ?></td><td class="muted"><?= e($it['sku']) ?></td><td class="r"><?= money($it['unit_price']) ?></td><td class="c"><?= (int) $it['quantity'] ?></td><td class="r"><?= money($it['line_total']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <table style="width:320px;margin-left:auto;margin-top:10px">
    <tr><td>Subtotal</td><td class="r"><?= money($o['subtotal']) ?></td></tr>
    <?php if ($o['discount_amount'] > 0): ?><tr><td>Discount <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></td><td class="r">- <?= money($o['discount_amount']) ?></td></tr><?php endif; ?>
    <tr><td>Delivery</td><td class="r"><?= money($o['delivery_fee']) ?></td></tr>
    <tr><td class="big">Total</td><td class="r big"><?= money($o['total']) ?></td></tr>
  </table>
  <?php if ($o['customer_notes']): ?><p><strong>Notes:</strong> <?= e($o['customer_notes']) ?></p><?php endif; ?>
  <p class="muted c" style="margin-top:30px"><?= e(setting('invoice_footer', 'Thank you for your business!')) ?></p>
</div>
<script>if (location.search.indexOf('auto=1') > -1) window.print();</script>
</body></html>
