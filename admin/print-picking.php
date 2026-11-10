<?php
require __DIR__ . '/includes/init.php';
admin_require('orders');
$o = db_row('SELECT * FROM orders WHERE id = ?', [input_int('id')]);
if (!$o) exit('Order not found');
$items = order_items((int) $o['id']);
$grouped = [];
foreach ($items as $it) $grouped[$it['category_name'] ?: 'Other'][] = $it;
ksort($grouped);
$printTitle = 'Picking list ' . $o['order_number'];
require __DIR__ . '/includes/print-head.php';
?>
<div class="page">
  <div class="head">
    <div><h1><?= $o['order_type'] === 'rashan' ? 'RASHAN ' : '' ?>PICKING LIST</h1><div class="big"><?= e($o['order_number']) ?></div><div class="muted"><?= fmt_datetime($o['created_at']) ?></div></div>
    <div class="r"><img src="<?= url('assets/images/logo.svg') ?>" alt=""></div>
  </div>
  <div class="grid">
    <div class="box"><div class="muted">Customer</div><strong><?= e($o['customer_name']) ?></strong><br><?= e($o['customer_phone']) ?></div>
    <div class="box"><div class="muted"><?= $o['fulfillment'] === 'pickup' ? 'PICKUP' : ($o['fulfillment'] === 'walkin' ? 'WALK-IN' : 'DELIVERY') ?></div>
      <strong><?= $o['fulfillment'] === 'pickup' ? e(fmt_datetime($o['pickup_at'])) : ($o['fulfillment'] === 'delivery' ? e($o['address_line'] . ', ' . $o['area'] . ', ' . $o['city']) : 'Counter') ?></strong>
      <?= $o['delivery_instructions'] ? '<br>' . e($o['delivery_instructions']) : '' ?></div>
    <div class="box"><div class="muted">Total / payment</div><strong><?= money($o['total']) ?></strong><br><?= e(payment_method_name($o['payment_method'])) ?> (<?= e($o['payment_status']) ?>)</div>
  </div>
  <?php if ($o['customer_notes']): ?><div class="box" style="background:#fffbeb;margin-bottom:12px"><strong>Customer notes:</strong> <?= nl2br(e($o['customer_notes'])) ?></div><?php endif; ?>
  <table><thead><tr><th style="width:30px">✓</th><th>Item</th><th>Size / option</th><th>SKU</th><th class="c">Qty</th></tr></thead><tbody>
  <?php $n = 0; foreach ($grouped as $cat => $rows): ?>
    <tr class="cat"><td colspan="5"><?= e($cat) ?></td></tr>
    <?php foreach ($rows as $it): $n += $it['quantity']; ?><tr><td><span class="chk"></span></td><td><strong><?= e($it['product_name']) ?></strong></td><td><?= e($it['variation_name'] ?: $it['unit']) ?></td><td class="muted"><?= e($it['sku']) ?></td><td class="c big"><?= (int) $it['quantity'] ?></td></tr><?php endforeach; ?>
  <?php endforeach; ?>
  </tbody></table>
  <p><strong><?= count($items) ?> lines · <?= $n ?> units</strong></p>
  <div class="grid" style="margin-top:30px"><div>Packed by: ____________________</div><div>Checked by: ____________________</div><div>Bags/boxes: ______</div></div>
</div>
<script>if (location.search.indexOf('auto=1') > -1) window.print();</script>
</body></html>
