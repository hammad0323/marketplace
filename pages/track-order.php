<?php
/** Guest order lookup: order number + email or phone. */
seo_set(['title' => 'Track Your Order', 'description' => 'Track your Urban Collections order with your order number and email or phone.']);
$error = null;
if (is_post()) {
    csrf_check();
    if (!rate_limit('track:' . client_ip(), 10, 600)) {
        $error = 'Too many attempts. Please try again in a few minutes.';
    } else {
        $num = strtoupper(input('order_number', '', 'post'));
        $contact = strtolower(input('contact', '', 'post'));
        $o = order_by_number($num);
        $digits = fn($s) => substr(preg_replace('/\D/', '', $s), -10);
        if ($o && ($contact === strtolower($o['email']) || ($digits($contact) !== '' && $digits($contact) === $digits($o['phone'])))) {
            redirect('order/' . $o['order_number'] . '?t=' . $o['access_token']);
        }
        $error = 'We could not find an order with those details.';
    }
}
$bodyClass = 'page-track';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-narrow">
    <h1 class="uc-page-title text-center">Track Your Order</h1>
    <p class="text-center text-muted">Enter your order number and the email or phone number used at checkout.</p>
    <?php if (input('payment', '', 'get') === 'unverified'): ?><div class="uc-alert uc-alert--error">We could not verify the payment response. If you were charged, please contact us with your order number.</div><?php endif; ?>
    <?php if ($error): ?><div class="uc-alert uc-alert--error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="uc-card-box">
        <?= csrf_field() ?>
        <label class="uc-field"><span>Order number</span><input type="text" name="order_number" required placeholder="e.g. UC26102212345" value="<?= e(input('order_number', '', 'post')) ?>"></label>
        <label class="uc-field"><span>Email or phone</span><input type="text" name="contact" required></label>
        <button class="uc-btn uc-btn--block" type="submit">Track Order</button>
    </form>
</section>
<?php require UC_ROOT . '/includes/footer.php';
