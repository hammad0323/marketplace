<?php
meta_set(['title' => 'Track your order', 'description' => 'Check the status of your ' . setting('site_name', 'Beglet') . ' order.', 'noindex' => true]);
$error = null;
if (is_post()) {
    require_csrf();
    $number = strtoupper(input('order_number'));
    $email = mb_strtolower(input('email'));
    if (!rate_limit('track_order', client_ip(), 10, 900)) {
        $error = 'Too many attempts. Please try again in 15 minutes.';
    } else {
        $order = db_one('SELECT * FROM orders WHERE order_number = ?', [$number]);
        // Constant response for wrong number or wrong email (no enumeration).
        if ($order && hash_equals(mb_strtolower($order['email']), $email)) {
            session_regenerate_id(true);
            $_SESSION['order_verified'][$order['order_number']] = time();
            redirect(path_url('order/' . $order['order_number']));
        }
        $error = 'We could not find an order with those details. Please check the order number and the email used at checkout.';
    }
}
partial('header');
?>
<div class="container container--narrow page-pad">
  <div class="auth-card" data-reveal="fade-up">
    <p class="eyebrow">Order status</p>
    <h1 class="page-title">Track your order</h1>
    <p class="text-muted">Enter your order number (from your confirmation email) and the email address used at checkout.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="order_number">Order number</label><input class="form-control" id="order_number" name="order_number" required maxlength="24" placeholder="e.g. BG26100812345" value="<?= e(input('order_number', input('order', '', 'get'))) ?>"></div>
      <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" required maxlength="190" value="<?= e(input('email')) ?>"></div>
      <button class="btn-lux btn-lux--block" type="submit">Find my order</button>
    </form>
    <p class="small text-muted mt-3">Have an account? <a href="<?= e(path_url('account/orders')) ?>">View all your orders</a>.</p>
  </div>
</div>
<?php partial('footer');
