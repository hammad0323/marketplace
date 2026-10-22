<?php
require __DIR__ . '/../includes/init.php';
admin_require('orders');
$order = db_row('SELECT * FROM orders WHERE id = ?', [input_int('id')]);
if (!$order) exit('Order not found');
$packing = input('type', '', 'get') === 'packing';
$items = order_items((int) $order['id']);
$addr = order_address((int) $order['id']);
$pm = payment_method_get($order['payment_method']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= $packing ? 'Packing slip' : 'Invoice' ?> <?= e($order['order_number']) ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<style>body{background:#f4f3ef;font-size:13px}.inv{background:#fff;max-width:820px;margin:24px auto;padding:44px}.brand{font-family:Georgia,serif;letter-spacing:.2em;text-transform:uppercase;font-size:1.3rem}
.inv th{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#666}@media print{body{background:#fff}.inv{margin:0;padding:0}.noprint{display:none}}</style></head>
<body><div class="inv">
<div class="noprint text-end mb-3"><button class="btn btn-dark btn-sm" onclick="window.print()">Print</button></div>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div class="d-flex gap-3 align-items-center"><img src="<?= e(img_url(setting('logo', 'assets/images/brand/logo-round.webp'))) ?>" width="64" height="64" style="border-radius:50%" alt="">
        <div><div class="brand"><?= e(store_name()) ?></div><div class="text-muted small"><?= e(setting('store_address')) ?><br><?= e(setting('store_phone')) ?> · <?= e(setting('store_email')) ?></div></div></div>
    <div class="text-end"><h1 class="h4 mb-1"><?= $packing ? 'Packing Slip' : 'Invoice' ?></h1><div>Order <strong><?= e($order['order_number']) ?></strong></div><div class="text-muted"><?= e(format_date($order['created_at'])) ?></div></div>
</div>
<div class="row mb-4">
    <div class="col-6"><strong>Ship to</strong><br><?php if ($addr): ?><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city']) ?>, <?= e($addr['region']) ?> <?= e($addr['postal_code']) ?><br><?= e($addr['phone']) ?><?php endif; ?></div>
    <div class="col-6 text-end"><strong>Payment</strong><br><?= e($pm['name'] ?? $order['payment_method']) ?> — <?= e(payment_statuses()[$order['payment_status']] ?? $order['payment_status']) ?><br>
        <strong>Delivery</strong><br><?= e(ucfirst($order['shipping_method'])) ?> · <?= e($order['shipping_zone']) ?><?php if ($order['tracking_number']): ?><br><?= e($order['courier_name']) ?> <?= e($order['tracking_number']) ?><?php endif; ?></div>
</div>
<table class="table"><thead><tr><th>Item</th><th>SKU</th><th class="text-center">Qty</th><?php if (!$packing): ?><th class="text-end">Price</th><th class="text-end">Total</th><?php else: ?><th class="text-center">Packed</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($items as $it): ?><tr><td><?= e($it['product_name']) ?><?= $it['variant_label'] ? '<div class="text-muted small">' . e($it['variant_label']) . '</div>' : '' ?>
<?php if ($it['custom_measurements'] || $it['personalization'] || $it['custom_color'] || $it['custom_instructions']): ?><div class="small">Custom: <?= e(implode(' · ', array_filter([$it['custom_measurements'], $it['custom_color'], $it['personalization'], $it['custom_instructions']]))) ?></div><?php endif; ?></td>
<td><?= e($it['sku']) ?></td><td class="text-center"><?= (int) $it['qty'] ?></td><?php if (!$packing): ?><td class="text-end"><?= e(money($it['unit_price'] + $it['customization_fee'])) ?></td><td class="text-end"><?= e(money($it['line_total'])) ?></td><?php else: ?><td class="text-center">☐</td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table>
<?php if (!$packing): ?>
<table class="table table-sm ms-auto" style="max-width:320px"><tbody>
<tr><td>Subtotal</td><td class="text-end"><?= e(money($order['subtotal'] + $order['customization_total'])) ?></td></tr>
<?php if ((float) $order['discount_total']): ?><tr><td>Discount <?= e($order['coupon_code']) ?></td><td class="text-end">−<?= e(money($order['discount_total'])) ?></td></tr><?php endif; ?>
<tr><td>Delivery</td><td class="text-end"><?= e(money($order['shipping_total'])) ?></td></tr>
<?php if ((float) $order['payment_fee']): ?><tr><td>Payment fee</td><td class="text-end"><?= e(money($order['payment_fee'])) ?></td></tr><?php endif; ?>
<tr class="fw-bold"><td>Total</td><td class="text-end"><?= e(money($order['grand_total'])) ?></td></tr>
<?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid'): ?><tr class="fw-bold"><td>Amount to collect (COD)</td><td class="text-end"><?= e(money($order['grand_total'])) ?></td></tr><?php endif; ?>
</tbody></table>
<?php endif; ?>
<?php if ($order['customer_notes']): ?><p><strong>Customer notes:</strong> <?= nl2br(e($order['customer_notes'])) ?></p><?php endif; ?>
<p class="text-center text-muted mt-5 small">Thank you for shopping with <?= e(store_name()) ?>.</p>
</div></body></html>
