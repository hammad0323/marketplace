<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('orders.view');
$order = db_one('SELECT * FROM orders WHERE id = ?', [input_int('id', 0, 'get')]);
if (!$order) {
    exit('Order not found');
}
$type = input('type', 'invoice', 'get') === 'packing' ? 'packing' : 'invoice';
$items = order_items((int) $order['id']);
$address = order_address((int) $order['id']);
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= $type === 'invoice' ? 'Invoice' : 'Packing slip' ?> <?= e($order['order_number']) ?></title>
<meta name="robots" content="noindex">
<style>
body{font-family:Helvetica,Arial,sans-serif;color:#1B2333;margin:0;padding:40px;font-size:13px}
.wrap{max-width:780px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #101D35;padding-bottom:18px;margin-bottom:24px}
.brand{font-family:Georgia,serif;font-size:30px;letter-spacing:8px;color:#101D35}.brand small{display:block;font-family:Helvetica;font-size:9px;letter-spacing:4px;color:#B99A5B}
h1{font-size:20px;margin:0 0 6px;text-transform:uppercase;letter-spacing:3px;color:#101D35}
table{width:100%;border-collapse:collapse;margin:16px 0}th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:1px solid #ddd;padding:8px 6px}td{padding:10px 6px;border-bottom:1px solid #eee;vertical-align:top}
.r{text-align:right}.cols{display:flex;gap:40px}.cols>div{flex:1}.muted{color:#6b7280}
.totals{margin-left:auto;width:300px}.totals td{border:0;padding:4px 6px}.totals tr:last-child td{font-weight:bold;font-size:15px;border-top:2px solid #101D35;padding-top:8px}
.box{border:1px solid #ddd;padding:10px;width:18px;height:18px;display:inline-block}
.actions{margin-bottom:20px}@media print{.actions{display:none}body{padding:0}}
</style></head>
<body><div class="wrap">
<div class="actions"><button onclick="window.print()">Print</button></div>
<header>
  <div><div class="brand"><?= e(strtoupper(setting('brand_name', 'Beglet'))) ?><small><?= e(strtoupper(setting('site_tagline', 'Crafted Leather'))) ?></small></div>
    <p class="muted"><?= nl2br(e(setting('business_address'))) ?><br><?= e(setting('contact_phone')) ?> · <?= e(setting('support_email')) ?></p></div>
  <div class="r"><h1><?= $type === 'invoice' ? 'Invoice' : 'Packing slip' ?></h1>
    <strong><?= e($order['order_number']) ?></strong><br><?= e(format_date($order['created_at'])) ?><br>
    <?php if ($type === 'invoice'): ?>Payment: <?= e(payment_method_label($order['payment_method'])) ?> (<?= e(status_label($order['payment_status'])) ?>)<?php endif; ?></div>
</header>
<div class="cols">
  <div><strong>Deliver to</strong><br><?php if ($address): ?><?= e($address['full_name']) ?><br><?= e($address['address_line1']) ?><br><?= $address['address_line2'] ? e($address['address_line2']) . '<br>' : '' ?><?= e($address['city']) ?>, <?= e($address['region']) ?> <?= e($address['postal_code']) ?><br><?= e($address['phone']) ?><?php endif; ?></div>
  <div><strong>Customer</strong><br><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></div>
  <div><strong>Shipping</strong><br><?= e($order['shipping_label']) ?><br><?= e($order['courier_name']) ?> <?= e($order['tracking_number']) ?></div>
</div>
<table>
  <thead><tr><?php if ($type === 'packing'): ?><th style="width:30px"></th><?php endif; ?><th>Item</th><th>SKU</th><th class="r">Qty</th><?php if ($type === 'invoice'): ?><th class="r">Unit price</th><th class="r">Total</th><?php endif; ?></tr></thead>
  <tbody><?php foreach ($items as $it): ?>
    <tr><?php if ($type === 'packing'): ?><td><span class="box"></span></td><?php endif; ?>
      <td><?= e($it['product_name']) ?><?= $it['variant_label'] ? '<br><span class="muted">' . e($it['variant_label']) . '</span>' : '' ?><?= $it['gift_wrap'] ? '<br><strong>Gift packaging</strong>' : '' ?></td>
      <td><?= e($it['sku']) ?></td><td class="r"><?= (int) $it['quantity'] ?></td>
      <?php if ($type === 'invoice'): ?><td class="r"><?= e(money($it['unit_price'])) ?></td><td class="r"><?= e(money($it['line_total'])) ?></td><?php endif; ?></tr>
  <?php endforeach; ?></tbody>
</table>
<?php if ($type === 'invoice'): ?>
<table class="totals">
  <tr><td>Subtotal</td><td class="r"><?= e(money($order['subtotal'])) ?></td></tr>
  <?php if ((float) $order['gift_wrap_total']): ?><tr><td>Gift packaging</td><td class="r"><?= e(money($order['gift_wrap_total'])) ?></td></tr><?php endif; ?>
  <?php if ((float) $order['discount_total']): ?><tr><td>Discount <?= e($order['coupon_code']) ?></td><td class="r">− <?= e(money($order['discount_total'])) ?></td></tr><?php endif; ?>
  <tr><td>Delivery</td><td class="r"><?= e(money($order['shipping_total'])) ?></td></tr>
  <?php if ((float) $order['cod_fee']): ?><tr><td>COD fee</td><td class="r"><?= e(money($order['cod_fee'])) ?></td></tr><?php endif; ?>
  <?php if ((float) $order['refunded_total']): ?><tr><td>Refunded</td><td class="r">− <?= e(money($order['refunded_total'])) ?></td></tr><?php endif; ?>
  <tr><td><?= $order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' ? 'Amount to collect' : 'Total' ?></td><td class="r"><?= e(money((float) $order['grand_total'] - (float) $order['refunded_total'])) ?></td></tr>
</table>
<?php endif; ?>
<?php if ($order['customer_note']): ?><p><strong>Customer note:</strong> <?= nl2br(e($order['customer_note'])) ?></p><?php endif; ?>
<p class="muted" style="margin-top:40px;text-align:center">Thank you for choosing <?= e(setting('site_name', 'Beglet')) ?>.</p>
</div></body></html>
