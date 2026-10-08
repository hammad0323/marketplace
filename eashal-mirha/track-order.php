<?php
require __DIR__ . '/includes/bootstrap.php';

$error = '';
if (is_post()) {
    require_csrf();
    $o = row('SELECT * FROM orders WHERE order_no = ?', [strtoupper(post('order_no'))]);
    $digits = fn($s) => substr(preg_replace('~\D~', '', (string)$s), -10);
    if ($o && $digits($o['phone']) !== '' && $digits($o['phone']) === $digits(post('phone'))) {
        redirect('order-success/' . $o['order_no'] . '?k=' . $o['access_key']);
    }
    $error = 'We could not find an order with those details.';
}
$seo = ['title' => 'Track Your Order | ' . setting('site_name'), 'description' => 'Track the status of your ' . setting('site_name') . ' order.'];
require ROOT . '/includes/header.php';
?>
<section class="section auth">
  <div class="auth__box" data-reveal>
    <span class="ornament">✦</span>
    <h1 class="section-title">Track Your Order</h1>
    <p class="muted">Enter your order number and the phone number used at checkout.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Order Number<input type="text" name="order_no" value="<?= e(post('order_no')) ?>" placeholder="e.g. EM2610081234" required></label>
      <label>Phone Number<input type="tel" name="phone" value="<?= e(post('phone')) ?>" required></label>
      <button class="btn btn-dark btn-block" type="submit">Track Order</button>
    </form>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
