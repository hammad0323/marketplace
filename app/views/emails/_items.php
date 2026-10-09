<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px;">
<?php foreach ($items as $it): ?>
<tr>
  <td style="padding:10px 0;border-bottom:1px solid #E8DDCC;"><strong><?= e($it['product_name']) ?></strong><?php if ($it['variant_label']): ?><br><span style="color:#6b7280;"><?= e($it['variant_label']) ?></span><?php endif; ?><?php if ($it['gift_wrap']): ?><br><span style="color:#B99A5B;">Gift packaging</span><?php endif; ?></td>
  <td style="padding:10px 0;border-bottom:1px solid #E8DDCC;text-align:center;">× <?= (int) $it['quantity'] ?></td>
  <td style="padding:10px 0;border-bottom:1px solid #E8DDCC;text-align:right;"><?= e(money($it['line_total'])) ?></td>
</tr>
<?php endforeach; ?>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
<tr><td>Subtotal</td><td align="right"><?= e(money($order['subtotal'])) ?></td></tr>
<?php if ((float) $order['discount_total'] > 0): ?><tr><td>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></td><td align="right">− <?= e(money($order['discount_total'])) ?></td></tr><?php endif; ?>
<?php if ((float) $order['gift_wrap_total'] > 0): ?><tr><td>Gift packaging</td><td align="right"><?= e(money($order['gift_wrap_total'])) ?></td></tr><?php endif; ?>
<tr><td>Delivery<?= $order['shipping_label'] ? ' — ' . e($order['shipping_label']) : '' ?></td><td align="right"><?= (float) $order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Free' ?></td></tr>
<?php if ((float) $order['cod_fee'] > 0): ?><tr><td>Cash on delivery fee</td><td align="right"><?= e(money($order['cod_fee'])) ?></td></tr><?php endif; ?>
<tr><td style="padding-top:8px;font-size:16px;"><strong>Total</strong></td><td align="right" style="padding-top:8px;font-size:16px;"><strong><?= e(money($order['grand_total'])) ?></strong></td></tr>
</table>
