<?php
/** Order confirmation / status page. Visible to its owner or via the private link (?t=token). */
$order = order_for_viewer($ROUTE['params']['number'], input('t', '', 'get'));
if (!$order) {
    flash('info', 'Please enter your order details to view it.');
    redirect('track-order');
}
$items = order_items((int) $order['id']);
$addr = order_address((int) $order['id']);
$history = db_all('SELECT * FROM order_status_history WHERE order_id = ? AND is_customer_visible = 1 ORDER BY id DESC', [(int) $order['id']]);
$pm = payment_method_get($order['payment_method']);
$justPlaced = !empty($_GET['placed']);
$needsPayment = $order['payment_method'] !== 'cod' && in_array($order['payment_status'], ['pending', 'failed', 'unpaid'], true) && !in_array($order['status'], ['cancelled', 'refunded'], true);
seo_set(['title' => 'Order ' . $order['order_number'], 'noindex' => true]);
$bodyClass = 'page-order';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?php if ($justPlaced || !empty($_GET['paid'])): ?>
        <div class="uc-confirm" data-reveal="fade-up">
            <span class="uc-confirm-icon"><i class="bi bi-check2"></i></span>
            <h1 class="uc-page-title">Thank you, <?= e(strtok($order['customer_name'], ' ')) ?>!</h1>
            <p class="lead">Your order <strong><?= e($order['order_number']) ?></strong> has been received<?= $order['payment_status'] === 'paid' ? ' and paid' : '' ?>.</p>
            <p class="text-muted">A confirmation has been sent to <?= e($order['email']) ?> (if email is enabled). Save this page to track your order.</p>
        </div>
    <?php else: ?>
        <?= breadcrumbs_html([['Home', url()], ['Order ' . $order['order_number'], null]]) ?>
        <h1 class="uc-page-title">Order <?= e($order['order_number']) ?></h1>
    <?php endif; ?>

    <?php if (input('payment', '', 'get') === 'failed'): ?>
        <div class="uc-alert uc-alert--error">Payment was not completed<?= input('msg', '', 'get') !== '' ? ': ' . e(input('msg', '', 'get')) : '' ?>. You can try again below.</div>
    <?php elseif (input('payment', '', 'get') === 'pending' && $order['payment_status'] !== 'paid'): ?>
        <div class="uc-alert uc-alert--info">We are confirming your payment with the provider. Refresh this page in a minute — it will show “Paid” once confirmed.</div>
    <?php endif; ?>
    <?php if ($needsPayment && input('payment', '', 'get') !== 'pending'): ?>
        <div class="uc-alert uc-alert--warn">
            <strong>Payment <?= $order['payment_status'] === 'failed' ? 'not completed' : 'pending' ?>.</strong>
            <?php if ($order['payment_status'] === 'failed'): ?>Your payment did not go through.<?php else: ?>If you have just paid, it may take a moment to confirm.<?php endif; ?>
            <a class="uc-btn uc-btn--sm ms-2" href="<?= e(url('checkout/pay/' . $order['order_number'] . '?t=' . $order['access_token'])) ?>">Pay now</a>
        </div>
    <?php endif; ?>

    <?php if (!in_array($order['status'], ['cancelled', 'refunded', 'on_hold'], true)): ?>
        <ol class="uc-progress">
            <?php foreach (order_progress_steps($order) as $st): ?>
                <li class="<?= $st['done'] ? 'is-done' : '' ?><?= $st['current'] ? ' is-current' : '' ?>"><span></span><?= e($st['label']) ?></li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p>Status: <?= status_badge($order['status']) ?></p>
    <?php endif; ?>

    <div class="uc-order-grid">
        <div>
            <div class="uc-card-box">
                <h2 class="h5">Items</h2>
                <ul class="uc-summary-items">
                    <?php foreach ($items as $it): ?>
                        <li>
                            <span class="uc-summary-img"><img src="<?= e(img_sized($it['image'], 200)) ?>" alt="" loading="lazy"><em><?= (int) $it['qty'] ?></em></span>
                            <span class="uc-summary-name"><?= e($it['product_name']) ?>
                                <?php if ($it['variant_label']): ?><small><?= e($it['variant_label']) ?></small><?php endif; ?>
                                <?php if ($it['custom_measurements'] || $it['personalization'] || $it['custom_color'] || $it['custom_instructions']): ?><small>Customised<?= $it['personalization'] ? ': “' . e($it['personalization']) . '”' : '' ?></small><?php endif; ?>
                            </span>
                            <span><?= e(money($it['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="uc-totals">
                    <dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd>
                    <?php if ((float) $order['customization_total'] > 0): ?><dt>Customisation</dt><dd><?= e(money($order['customization_total'])) ?></dd><?php endif; ?>
                    <?php if ((float) $order['discount_total'] > 0): ?><dt>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd class="is-discount">−<?= e(money($order['discount_total'])) ?></dd><?php endif; ?>
                    <dt>Delivery</dt><dd><?= (float) $order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Free' ?></dd>
                    <?php if ((float) $order['payment_fee'] > 0): ?><dt>Payment fee</dt><dd><?= e(money($order['payment_fee'])) ?></dd><?php endif; ?>
                    <dt class="is-total">Total</dt><dd class="is-total"><?= e(money($order['grand_total'])) ?></dd>
                    <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' && !in_array($order['status'], ['cancelled', 'refunded'], true)): ?>
                        <dt>Amount due on delivery</dt><dd><strong><?= e(money($order['grand_total'])) ?></strong></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <div>
            <div class="uc-card-box">
                <h2 class="h5">Details</h2>
                <p class="mb-1"><strong>Placed:</strong> <?= e(format_datetime($order['created_at'])) ?></p>
                <p class="mb-1"><strong>Payment:</strong> <?= e($pm['name'] ?? $order['payment_method']) ?> — <?= status_badge($order['payment_status']) ?></p>
                <p class="mb-1"><strong>Delivery:</strong> <?= e(ucfirst($order['shipping_method'])) ?><?= $order['estimated_delivery'] ? ' · est. ' . e($order['estimated_delivery']) : '' ?></p>
                <?php if ($order['tracking_number']): ?>
                    <p class="mb-1"><strong>Courier:</strong> <?= e($order['courier_name']) ?> · <?= e($order['tracking_number']) ?>
                        <?php if ($order['tracking_url']): ?> <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Track shipment</a><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($addr): ?>
                    <h3 class="h6 mt-3">Delivery address</h3>
                    <address class="mb-0"><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city']) ?>, <?= e($addr['region']) ?> <?= e($addr['postal_code']) ?><br><?= e($addr['phone']) ?></address>
                <?php endif; ?>
            </div>
            <?php if ($history): ?>
                <div class="uc-card-box">
                    <h2 class="h5">Updates</h2>
                    <ul class="uc-timeline">
                        <?php foreach ($history as $h): ?>
                            <li><time><?= e(format_datetime($h['created_at'])) ?></time><?= $h['status'] ? status_badge($h['status']) : '' ?> <?= e($h['note']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!customer_id() && !db_val('SELECT 1 FROM customers WHERE email = ?', [$order['email']])): ?>
                <div class="uc-card-box">
                    <h2 class="h5">Save your details</h2>
                    <p class="small">Create an account to track this order and check out faster next time.</p>
                    <a class="uc-btn uc-btn--outline uc-btn--sm" href="<?= e(url('account/register?email=' . urlencode($order['email']) . '&order=' . urlencode($order['order_number']) . '&t=' . $order['access_token'])) ?>">Create account</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-center mt-4"><a class="uc-btn uc-btn--outline" href="<?= e(url('shop')) ?>">Continue Shopping</a></div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
