<?php if (!defined('EBAYA')) { http_response_code(403); exit; } ?>
<dl class="totals">
  <dt>Subtotal</dt><dd><?= money($totals['subtotal']) ?></dd>
  <?php if ($totals['custom_total'] > 0): ?><dt>Customisation</dt><dd><?= money($totals['custom_total']) ?></dd><?php endif; ?>
  <?php if ($totals['discount'] > 0): ?><dt>Discount <?= $totals['coupon'] ? '(' . e($totals['coupon']['code']) . ')' : '' ?></dt><dd class="text-success">−<?= money($totals['discount']) ?></dd><?php endif; ?>
  <dt>Delivery</dt><dd><?= $totals['shipping'] ? ($totals['shipping_total'] > 0 ? money($totals['shipping_total']) : 'Free') : '—' ?></dd>
  <?php if ($totals['cod_fee'] > 0): ?><dt>Cash on delivery fee</dt><dd><?= money($totals['cod_fee']) ?></dd><?php endif; ?>
  <dt class="grand">Total</dt><dd class="grand"><?= money($totals['grand_total']) ?></dd>
</dl>
<?php if ($totals['shipping']): ?><p class="small text-muted mb-1"><i class="bi bi-truck"></i> Estimated delivery: <?= e($totals['shipping']['est_text']) ?></p><?php endif; ?>
<?php if (($in['payment'] ?? '') === 'cod' && $totals['shipping']): ?><p class="small mb-1"><i class="bi bi-cash"></i> Amount due on delivery: <strong><?= money($totals['grand_total']) ?></strong></p><?php endif; ?>
<?php foreach ($totals['errors'] as $er): ?><div class="alert alert-warning py-2 small mb-0 mt-2"><?= e($er) ?></div><?php endforeach;
