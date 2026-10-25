<?php
/** Live checkout totals for the selected city / region / delivery method / payment method. */
require __DIR__ . '/_init.php';
ajax_require_post();
$method = input('shipping_method', '', 'post');
if ($method === '') {
    // First load: pre-select urgent when the shopper chose it for this city on a product page
    $method = (!empty($_SESSION['urgent_city']) && strcasecmp($_SESSION['urgent_city'], trim(input('city', '', 'post'))) === 0) ? 'urgent' : 'standard';
}
$method = in_array($method, ['express', 'urgent'], true) ? $method : 'standard';
$ctx = [
    'city' => mb_substr(input('city', '', 'post'), 0, 100), 'region' => mb_substr(input('region', '', 'post'), 0, 100),
    'shipping_method' => $method, 'payment_method' => preg_replace('/[^a-z]/', '', input('payment_method', '', 'post')),
    'email' => input('email', '', 'post') ?: null,
];
$lines = cart_lines();
$t = cart_totals($lines, $ctx);
$q = $t['shipping'];
ob_start();
?>
<dl class="uc-totals">
    <dt>Subtotal</dt><dd><?= e(money($t['subtotal'])) ?></dd>
    <?php if ($t['customization_total'] > 0): ?><dt>Customisation</dt><dd><?= e(money($t['customization_total'])) ?></dd><?php endif; ?>
    <?php if ($t['discount'] > 0): ?><dt>Discount <small>(<?= e($t['coupon_code']) ?>)</small></dt><dd class="is-discount">−<?= e(money($t['discount'])) ?></dd><?php endif; ?>
    <dt>Delivery <small><?= e($q['zone']) ?></small></dt><dd><?= $t['shipping_fee'] > 0 ? e(money($t['shipping_fee'])) : 'Free' ?></dd>
    <?php if ($t['payment_fee'] > 0): ?><dt>Payment fee</dt><dd><?= e(money($t['payment_fee'])) ?></dd><?php endif; ?>
    <dt class="is-total">Total</dt><dd class="is-total"><?= e(money($t['total'])) ?></dd>
</dl>
<p class="small mb-2"><i class="bi bi-calendar-check"></i> Estimated delivery: <strong><?= e($q['estimate']) ?></strong><?= $t['max_lead_days'] ? ' (includes ' . (int) $t['max_lead_days'] . ' days production)' : '' ?></p>
<?php if ($t['coupon_message']): ?><p class="uc-cart-error small"><?= e($t['coupon_message']) ?></p><?php endif; ?>
<?php foreach ($t['errors'] as $er): ?><p class="uc-cart-error small"><i class="bi bi-exclamation-triangle"></i> <?= e($er) ?></p><?php endforeach; ?>
<?php
$totalsHtml = ob_get_clean();
ob_start();
$methods = $q['methods'] ?: ['standard' => ['method' => 'standard', 'label' => $q['label'], 'fee' => $q['fee'], 'estimate' => $q['estimate'], 'free' => $q['free']]];
foreach ($methods as $m): ?>
    <label class="uc-ship-opt">
        <input type="radio" name="shipping_method" value="<?= e($m['method']) ?>"<?= $m['method'] === $q['method'] ? ' checked' : '' ?>>
        <span><strong><?= $m['method'] === 'urgent' ? '<i class="bi bi-lightning-charge-fill text-warning"></i> ' : '' ?><?= e($m['label']) ?></strong><small>Arrives <?= e($m['estimate']) ?></small></span>
        <em><?= $m['fee'] > 0 ? e(money($m['fee'])) : 'Free' ?></em>
    </label>
<?php endforeach;
if (!$t['urgent_allowed'] && urgent_rate_for($ctx['city'])) {
    echo '<p class="small text-muted mb-0"><i class="bi bi-info-circle"></i> Urgent delivery is not available for custom-made or made-to-order items in your bag.</p>';
}
$methodsHtml = ob_get_clean();
$cod = payment_method_get('cod');
json_response([
    'ok' => true,
    'totals_html' => $totalsHtml,
    'methods_html' => $methodsHtml,
    'cod_available' => (bool) $q['cod_available'],
    'can_order' => !$t['errors'] && !$t['coupon_message'] && $lines,
    'coupon_applied' => $t['coupon_code'],
]);
