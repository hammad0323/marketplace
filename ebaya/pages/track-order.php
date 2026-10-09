<?php
/** Guest order lookup by order number + email or phone. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$error = null;
if (is_post()) {
    csrf_check();
    rate_limit_or_fail('track', 10, 900);
    $o = order_find_public(mb_substr(post('order_number'), 0, 30), mb_substr(post('contact'), 0, 190));
    if ($o) {
        $_SESSION['tracked_orders'] = array_slice(array_merge($_SESSION['tracked_orders'] ?? [], [$o['order_number']]), -10);
        redirect('order/' . $o['order_number']);
    }
    $error = 'We could not find an order with those details. Please check your order number and the email or phone used at checkout.';
}
seo_set(['title' => 'Track Your Order', 'noindex' => true]);
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section">
  <div class="container-eb auth-wrap">
    <div class="auth-card" data-reveal>
      <h1 class="page-title text-center">Track Your Order</h1>
      <p class="text-center text-muted">Enter your order number and the email address or phone number used at checkout.</p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="on">Order number</label><input class="form-control" id="on" name="order_number" required placeholder="EB2610081234" value="<?= e(post('order_number')) ?>"></div>
        <div class="mb-3"><label class="form-label" for="ct">Email or phone</label><input class="form-control" id="ct" name="contact" required value="<?= e(post('contact')) ?>"></div>
        <button class="btn btn-eb btn-primary-eb w-100">Find my order</button>
      </form>
    </div>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
