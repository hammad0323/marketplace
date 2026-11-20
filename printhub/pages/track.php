<?php
/** Order tracking: by private link (?t=), by this device's session, or order number + mobile number. */
require __DIR__ . '/_layout.php';
$number = strtoupper(trim((string) ($params['number'] ?? $_GET['n'] ?? '')));
$token = (string) ($_GET['t'] ?? '');
$phone = (string) ($_GET['phone'] ?? '');
$order = $number ? order_for_customer($number, $token ?: null, customer_session(false), $phone ?: null) : null;
$shop = $order ? shop_by_id((int) $order['shop_id']) : null;
$theme = $shop ? shop_settings((int) $shop['id'])['theme_color'] : null;
c_header($order ? 'Order ' . $order['order_number'] : 'Track your order', ['shop' => $shop, 'theme' => $theme, 'active' => 'orders']);
?>
<section class="c-narrow">
<?php if (!$order): ?>
    <h1 class="c-h1">Track your order</h1>
    <?php if ($number): ?><div class="c-notice mb-3">We couldn’t find that order. Check the order number and use the mobile number you entered when ordering.</div><?php endif; ?>
    <form class="c-card" method="get" action="<?= e(path('track')) ?>">
        <div class="mb-3"><label class="form-label" for="n">Order number</label><input class="form-control text-uppercase" id="n" name="n" value="<?= e($number) ?>" placeholder="PO2611…" required></div>
        <div class="mb-3"><label class="form-label" for="ph">Mobile number</label><input class="form-control" id="ph" name="phone" value="<?= e($phone) ?>" inputmode="tel" placeholder="03xx xxxxxxx" required></div>
        <button class="c-btn c-btn-block">Track order</button></form>
<?php else: ?>
    <div data-track data-number="<?= e($order['order_number']) ?>" data-t="<?= e($token ?: $order['tracking_token']) ?>"><div class="c-card c-center c-muted"><span class="spinner-border spinner-border-sm"></span> Loading your order…</div></div>
<?php endif; ?>
</section>
<?php c_footer($order ? ['js/track.js'] : [], ['active' => 'orders']);
