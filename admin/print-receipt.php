<?php
/** 80mm thermal receipt for POS sales */
require __DIR__ . '/includes/init.php';
if (!admin_can('pos') && !admin_can('orders')) exit('Forbidden');
$o = db_row('SELECT * FROM orders WHERE id = ?', [input_int('id')]);
if (!$o) exit('Order not found');
$items = order_items((int) $o['id']);
$pos = db_row('SELECT ps.*, a.name AS cashier FROM pos_sales ps LEFT JOIN admins a ON a.id = ps.admin_id WHERE ps.order_id = ?', [$o['id']]);
?><!doctype html><html><head><meta charset="utf-8"><title>Receipt <?= e($o['order_number']) ?></title>
<style>
  @page { size: 80mm auto; margin: 0; } body { width: 76mm; margin: 0 auto; font-family: 'Courier New', monospace; font-size: 12px; color: #000; padding: 4mm 2mm; }
  .c { text-align: center; } .r { text-align: right; } hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; } table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
  .big { font-size: 16px; font-weight: bold; } img { width: 46mm; } .noprint { text-align: center; margin: 10px; } @media print { .noprint { display: none; } }
</style></head><body>
<div class="noprint"><button onclick="window.print()">Print receipt</button></div>
<div class="c"><img src="<?= url('assets/images/logo.svg') ?>" alt="Fahim Store"><br><?= e(setting('store_address')) ?><br><?= e(setting('store_phone')) ?></div>
<hr>
<div>Receipt: <?= e($o['order_number']) ?><br>Date: <?= date('d/m/Y h:i A', strtotime($o['created_at'])) ?><br>Cashier: <?= e($pos['cashier'] ?? (current_admin()['name'] ?? '')) ?>
<?php if ($o['customer_phone'] !== '00000000000'): ?><br>Customer: <?= e($o['customer_name']) ?> <?= e($o['customer_phone']) ?><?php endif; ?>
<?php if ($o['fulfillment'] !== 'walkin'): ?><br><?= $o['fulfillment'] === 'pickup' ? 'PICKUP: ' . e(fmt_datetime($o['pickup_at'])) : 'DELIVERY: ' . e($o['address_line'] . ', ' . $o['area']) ?><?php endif; ?></div>
<hr>
<table><?php foreach ($items as $it): ?>
<tr><td colspan="2"><?= e($it['product_name']) ?><?= $it['variation_name'] ? ' (' . e($it['variation_name']) . ')' : '' ?></td></tr>
<tr><td>&nbsp;<?= (int) $it['quantity'] ?> x <?= money($it['unit_price'], false) ?></td><td class="r"><?= money($it['line_total'], false) ?></td></tr>
<?php endforeach; ?></table>
<hr>
<table>
<tr><td>Subtotal</td><td class="r"><?= money($o['subtotal'], false) ?></td></tr>
<?php if ($o['discount_amount'] > 0): ?><tr><td>Discount</td><td class="r">-<?= money($o['discount_amount'], false) ?></td></tr><?php endif; ?>
<?php if ($o['delivery_fee'] > 0): ?><tr><td>Delivery</td><td class="r"><?= money($o['delivery_fee'], false) ?></td></tr><?php endif; ?>
<tr class="big"><td>TOTAL</td><td class="r"><?= money($o['total']) ?></td></tr>
<tr><td><?= e(payment_method_name($o['payment_method'])) ?></td><td class="r"><?= $pos ? money($pos['amount_tendered'], false) : e(strtoupper($o['payment_status'])) ?></td></tr>
<?php if ($pos && $pos['change_given'] > 0): ?><tr><td>Change</td><td class="r"><?= money($pos['change_given'], false) ?></td></tr><?php endif; ?>
</table>
<hr><div class="c"><?= e(setting('pos_receipt_footer')) ?><br><?= e(parse_url(site_origin(), PHP_URL_HOST)) ?></div>
<script>if (location.search.indexOf('auto=1') > -1) { window.onload = function () { window.print(); }; }</script>
</body></html>
