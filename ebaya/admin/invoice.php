<?php
/** Printable invoice / packing slip. */
require __DIR__ . '/partials/bootstrap.php';
require_admin('orders.view');
$o = db_one('SELECT * FROM orders WHERE id = ?', [(int)get('id')]);
if (!$o) exit('Order not found');
$packing = get('type') === 'packing';
$items = order_items((int)$o['id']);
$addr = order_address((int)$o['id']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title><?= $packing ? 'Packing slip' : 'Invoice' ?> <?= e($o['order_number']) ?></title>
<style>
body{font-family:Georgia,serif;color:#332820;margin:0;padding:40px;font-size:13px}
.wrap{max-width:760px;margin:0 auto}
.head{display:flex;justify-content:space-between;border-bottom:2px solid #354638;padding-bottom:16px;margin-bottom:24px}
.brand{font-size:28px;letter-spacing:8px;text-transform:uppercase}
h1{font-size:18px;margin:0 0 6px;font-weight:normal;letter-spacing:2px;text-transform:uppercase}
table{width:100%;border-collapse:collapse;margin-top:16px;font-family:Arial,sans-serif;font-size:12px}
th,td{padding:8px;border-bottom:1px solid #e7d8c4;text-align:left;vertical-align:top}
th{background:#f8f5ef}.r{text-align:right}.muted{color:#8a7d70}
.cols{display:flex;gap:40px;font-family:Arial,sans-serif;font-size:12px}
.tot{margin-left:auto;width:300px}.tot td{border:0;padding:4px 8px}.grand td{font-weight:bold;font-size:14px;border-top:2px solid #354638}
.box{border:1px dashed #b89a64;padding:8px;margin-top:6px}
@media print{body{padding:0}.noprint{display:none}}
</style></head><body><div class="wrap">
<p class="noprint"><button onclick="window.print()">Print</button></p>
<div class="head"><div><div class="brand"><?= e(setting('site_name', 'Ebaya')) ?></div><div class="muted"><?= nl2br(e(setting('address'))) ?><br><?= e(setting('contact_email')) ?> · <?= e(setting('contact_phone')) ?></div></div>
<div class="r"><h1><?= $packing ? 'Packing slip' : 'Invoice' ?></h1>Order <strong><?= e($o['order_number']) ?></strong><br><?= e(date('j M Y', strtotime($o['created_at']))) ?><br>Payment: <?= e(strtoupper($o['payment_method'])) ?> (<?= e($o['payment_status']) ?>)</div></div>
<div class="cols">
  <div><strong>Deliver to</strong><br><?= e($addr['full_name'] ?? $o['customer_name']) ?><br><?= e($addr['address_line1'] ?? '') ?><?= !empty($addr['address_line2']) ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city'] ?? '') ?><?= !empty($addr['province']) ? ', ' . e($addr['province']) : '' ?> <?= e($addr['postal_code'] ?? '') ?><br><?= e($addr['phone'] ?? $o['phone']) ?></div>
  <div><strong>Customer</strong><br><?= e($o['customer_name']) ?><br><?= e($o['email']) ?><br><?= e($o['phone']) ?></div>
  <div><strong>Delivery</strong><br><?= e($o['shipping_label']) ?><br>Est. <?= e($o['estimated_delivery']) ?><?= $o['tracking_number'] ? '<br>' . e($o['courier_name'] . ' ' . $o['tracking_number']) : '' ?></div>
</div>
<table><thead><tr><th>Item</th><th>SKU</th><th class="r">Qty</th><?php if (!$packing): ?><th class="r">Price</th><th class="r">Total</th><?php else: ?><th>Packed ✓</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($items as $it): ?>
<tr><td><?= e($it['product_name']) ?><div class="muted"><?= e($it['variant_label']) ?></div>
  <?php if ($it['custom_length'] || $it['custom_sleeve'] || $it['custom_notes']): ?><div class="box">Custom: <?= $it['custom_length'] ? 'Length ' . (int)$it['custom_length'] . '" ' : '' ?><?= e($it['custom_sleeve'] ?? '') ?> <?= e($it['custom_notes'] ?? '') ?></div><?php endif; ?></td>
  <td><?= e($it['sku']) ?></td><td class="r"><?= (int)$it['quantity'] ?></td>
  <?php if (!$packing): ?><td class="r"><?= money($it['unit_price'] + $it['customization_fee']) ?></td><td class="r"><?= money($it['line_total']) ?></td><?php else: ?><td>☐</td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$packing): ?>
<table class="tot"><tbody>
<tr><td>Subtotal</td><td class="r"><?= money($o['subtotal'] + $o['customization_total']) ?></td></tr>
<?php if ($o['discount_total'] > 0): ?><tr><td>Discount</td><td class="r">−<?= money($o['discount_total']) ?></td></tr><?php endif; ?>
<tr><td>Delivery</td><td class="r"><?= money($o['shipping_total']) ?></td></tr>
<?php if ($o['cod_fee'] > 0): ?><tr><td>COD fee</td><td class="r"><?= money($o['cod_fee']) ?></td></tr><?php endif; ?>
<tr class="grand"><td>Total</td><td class="r"><?= money($o['grand_total']) ?></td></tr>
<?php if ($o['refunded_total'] > 0): ?><tr><td>Refunded</td><td class="r">−<?= money($o['refunded_total']) ?></td></tr><?php endif; ?>
<?php if ($o['payment_method'] === 'cod' && !$o['cod_collected']): ?><tr><td><strong>Amount to collect</strong></td><td class="r"><strong><?= money($o['grand_total']) ?></strong></td></tr><?php endif; ?>
</tbody></table>
<?php endif; ?>
<?php if ($o['customer_note']): ?><p><strong>Customer note:</strong> <?= e($o['customer_note']) ?></p><?php endif; ?>
<p class="muted" style="margin-top:40px;text-align:center">Thank you for choosing <?= e(setting('site_name', 'Ebaya')) ?>.</p>
</div></body></html>
