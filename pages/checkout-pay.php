<?php
/** Online payment hand-off: builds the gateway request and auto-submits it. */
$order = order_for_viewer($ROUTE['params']['number'], input('t', '', 'get'));
if (!$order) {
    route_not_found($ROUTE['path']);
}
$orderLink = 'order/' . $order['order_number'] . '?t=' . $order['access_token'];
if ($order['payment_method'] === 'cod' || in_array($order['payment_status'], ['paid', 'refunded', 'partially_refunded'], true) || in_array($order['status'], ['cancelled', 'refunded'], true)) {
    redirect($orderLink);
}
seo_set(['title' => 'Complete your payment', 'noindex' => true]);
$pm = payment_method_get($order['payment_method']);
$start = !empty($_GET['cancelled']) ? null : payment_start($order);
if ($start && $start['type'] === 'redirect') {
    redirect($start['url']);
}
$bodyClass = 'page-pay';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-narrow text-center">
    <h1 class="uc-page-title">Complete your payment</h1>
    <p>Order <strong><?= e($order['order_number']) ?></strong> · Amount <strong><?= e(money($order['grand_total'])) ?></strong></p>
    <?php if ($start && $start['type'] === 'form'): ?>
        <p class="text-muted">Redirecting you to <?= e($pm['name']) ?>’s secure payment page…</p>
        <form method="post" action="<?= e($start['action']) ?>" data-auto-submit-form>
            <?php foreach ($start['fields'] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
            <button class="uc-btn" type="submit">Continue to <?= e($pm['name']) ?></button>
        </form>
    <?php else: ?>
        <div class="uc-alert uc-alert--error"><?= e($start['message'] ?? 'Payment was cancelled.') ?></div>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a class="uc-btn" href="<?= e(url('checkout/pay/' . $order['order_number'] . '?t=' . $order['access_token'])) ?>">Try again</a>
            <a class="uc-btn uc-btn--outline" href="<?= e(url($orderLink)) ?>">View order</a>
        </div>
        <p class="small text-muted mt-3">Need another payment method? Contact us with your order number and we will help.</p>
    <?php endif; ?>
</section>
<?php require UC_ROOT . '/includes/footer.php';
