<?php if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!$methods): ?>
  <div class="alert alert-warning small mb-0">No payment method is available for this address yet. Please contact us to complete your order.</div>
<?php else:
$selected = isset($methods[$in['payment']]) ? $in['payment'] : array_key_first($methods);
$icons = ['cod' => 'bi-cash-coin', 'jazzcash' => 'bi-phone', 'easypaisa' => 'bi-wallet2', 'card' => 'bi-credit-card'];
foreach ($methods as $code => $m): ?>
  <label class="option-row">
    <input type="radio" name="payment" value="<?= e($code) ?>"<?= $selected === $code ? ' checked' : '' ?> required data-shipping-input>
    <span class="opt-main"><strong><i class="bi <?= e($icons[$code] ?? 'bi-credit-card') ?>"></i> <?= e($m['name']) ?></strong><?php if ($m['note']): ?><small><?= e($m['note']) ?></small><?php endif; ?>
      <?php if ($code !== 'cod'): ?><small>You'll be redirected to complete payment securely. Your order is confirmed once the payment is verified.</small><?php endif; ?></span>
    <?php if ($m['fee'] > 0): ?><span class="opt-price">+<?= money($m['fee']) ?></span><?php endif; ?>
  </label>
<?php endforeach; endif;
