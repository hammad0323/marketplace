<?php
require __DIR__ . '/includes/admin.php';

$o = row('SELECT * FROM orders WHERE id = ?', [(int)get('id')]);
if (!$o) exit('Order not found');
$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
$logo = setting('logo');
?><!doctype html>
<html><head><meta charset="utf-8"><title>Invoice <?= e($o['order_no']) ?></title>
<style>
body{font-family:Arial,sans-serif;color:#222;margin:0;background:#f3f1ec}
.inv{max-width:800px;margin:30px auto;background:#fff;padding:48px;border-top:6px solid #c9a24a}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #eee;padding-bottom:24px}
.brand{font-family:Georgia,serif;font-size:28px;letter-spacing:6px;text-transform:uppercase}
.brand small{display:block;font-size:10px;letter-spacing:4px;color:#8f6f24;margin-top:4px}
h2{margin:0;font-family:Georgia,serif;font-weight:normal;font-size:30px;text-align:right}
.meta{text-align:right;font-size:13px;color:#555}
.cols{display:flex;gap:40px;margin:28px 0;font-size:14px;line-height:1.6}.cols div{flex:1}
.cols h4{margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8f6f24}
table{width:100%;border-collapse:collapse;font-size:14px}
th{background:#0b0b0b;color:#e3c77a;text-align:left;padding:10px;font-weight:normal;font-size:12px;letter-spacing:1px;text-transform:uppercase}
td{padding:10px;border-bottom:1px solid #eee}.r{text-align:right}
.tot{width:300px;margin-left:auto;margin-top:16px;font-size:14px}.tot div{display:flex;justify-content:space-between;padding:5px 0}
.tot .g{border-top:2px solid #0b0b0b;font-size:18px;font-weight:bold;margin-top:6px;padding-top:10px}
.foot{margin-top:40px;text-align:center;font-size:12px;color:#777;border-top:1px solid #eee;padding-top:16px}
.btn{display:block;width:160px;margin:20px auto;padding:10px;background:#0b0b0b;color:#e3c77a;text-align:center;border:0;cursor:pointer;letter-spacing:2px}
@media print{body{background:#fff}.inv{margin:0;box-shadow:none}.btn{display:none}}
</style></head><body>
<button class="btn" onclick="print()">PRINT</button>
<div class="inv">
  <div class="head">
    <div><?php if ($logo): ?><img src="<?= e(img($logo)) ?>" style="height:56px" alt=""><?php else: ?><div class="brand"><?= e(setting('site_name')) ?><small><?= e(setting('tagline')) ?></small></div><?php endif; ?>
      <p style="font-size:13px;color:#555;margin:12px 0 0"><?= e(setting('address')) ?><br><?= e(setting('phone')) ?> · <?= e(setting('email')) ?></p></div>
    <div><h2>INVOICE</h2><p class="meta">#<?= e($o['order_no']) ?><br><?= date('d M Y', strtotime($o['created_at'])) ?></p></div>
  </div>
  <div class="cols">
    <div><h4>Bill / Ship To</h4><strong><?= e($o['name']) ?></strong><br><?= e($o['address']) ?><br><?= e($o['city']) ?> <?= e($o['postal_code']) ?><br><?= e($o['phone']) ?><br><?= e($o['email']) ?></div>
    <div><h4>Payment</h4><?= e(payment_label($o['payment_method'])) ?><br>Status: <?= e(payment_statuses()[$o['payment_status']]) ?><?= $o['txn_id'] ? '<br>TID: ' . e($o['txn_id']) : '' ?>
      <?php if ($o['tracking_no']): ?><h4 style="margin-top:12px">Shipment</h4><?= e($o['courier']) ?> · <?= e($o['tracking_no']) ?><?php endif; ?></div>
  </div>
  <table>
    <thead><tr><th>Item</th><th>Options</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead>
    <tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['name']) ?><br><small style="color:#888"><?= e($it['sku']) ?></small></td><td><?= e(trim($it['size'] . ' ' . $it['color'])) ?></td><td class="r"><?= money($it['price']) ?></td><td class="r"><?= (int)$it['qty'] ?></td><td class="r"><?= money($it['price'] * $it['qty']) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <div class="tot">
    <div><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
    <?php if ((float)$o['discount'] > 0): ?><div><span>Discount</span><span>− <?= money($o['discount']) ?></span></div><?php endif; ?>
    <div><span>Delivery</span><span><?= money($o['shipping']) ?></span></div>
    <?php if ((float)$o['payment_fee'] > 0): ?><div><span>COD fee</span><span><?= money($o['payment_fee']) ?></span></div><?php endif; ?>
    <div class="g"><span>Total</span><span><?= money($o['total']) ?></span></div>
  </div>
  <div class="foot">Thank you for shopping with <?= e(setting('site_name')) ?>.<br><?= e(abs_url('')) ?></div>
</div>
</body></html>
