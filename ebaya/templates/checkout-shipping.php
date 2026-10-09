<?php if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!$totals['shipping_options']): ?>
  <p class="text-muted small mb-0"><?= $in['city'] ? 'Sorry, we do not deliver to this location yet.' : 'Enter your city to see delivery options.' ?></p>
<?php else: foreach ($totals['shipping_options'] as $m => $o): ?>
  <label class="option-row">
    <input type="radio" name="method" value="<?= e($m) ?>"<?= ($totals['shipping']['method'] ?? '') === $m ? ' checked' : '' ?> data-shipping-input>
    <span class="opt-main"><strong><?= e($o['label']) ?></strong><small>Estimated delivery <?= e($o['est_text']) ?><?= $o['lead_days'] ? ' (includes ' . (int)$o['lead_days'] . ' days production)' : '' ?></small>
      <?php if (!$o['free'] && $o['free_over']): ?><small class="text-success">Free on orders over <?= money($o['free_over']) ?></small><?php endif; ?></span>
    <span class="opt-price"><?= $o['cost'] > 0 && !$totals['free_shipping_coupon'] ? money($o['cost']) : 'Free' ?></span>
  </label>
<?php endforeach; endif;
