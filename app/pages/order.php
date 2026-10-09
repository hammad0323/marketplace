<?php
$number = $GLOBALS['route']['number'];
$order = preg_match('/^[A-Z0-9]{6,24}$/', $number) ? db_one('SELECT * FROM orders WHERE order_number = ?', [$number]) : null;
$token = input('t', '', 'get');
if (!$order || !order_visible_to_visitor($order, $token !== '' ? $token : null)) {
    meta_set(['title' => 'Order lookup', 'noindex' => true]);
    flash('info', 'Please verify your order details to view it.');
    redirect(path_url('track-order', ['order' => $number]));
}
if ($token !== '' && hash_equals($order['access_token_hash'], token_hash($token))) {
    $_SESSION['order_access'][$order['order_number']] = $token;
}
meta_set(['title' => 'Order ' . $order['order_number'], 'noindex' => true]);
header('Cache-Control: no-store');
$payment = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
$justPlaced = strtotime($order['created_at']) > time() - 1800 && in_array($order['status'], ['pending', 'confirmed'], true);
partial('header');
?>
<div class="container page-pad order-page">
  <div class="order-hero" data-reveal="fade-up">
    <?php if ($justPlaced && $order['payment_status'] !== 'failed'): ?>
      <span class="order-hero__icon"><i class="bi bi-check2"></i></span>
      <p class="eyebrow">Thank you<?= ' ' . e(explode(' ', $order['customer_name'])[0]) ?></p>
      <h1 class="page-title">Your order is <?= $order['payment_method'] !== 'cod' && $order['payment_status'] !== 'paid' ? 'awaiting payment confirmation' : 'confirmed' ?></h1>
      <p>Order <strong><?= e($order['order_number']) ?></strong> · A confirmation has been sent to <?= e($order['email']) ?>.</p>
    <?php else: ?>
      <p class="eyebrow">Order details</p>
      <h1 class="page-title">Order <?= e($order['order_number']) ?></h1>
    <?php endif; ?>
    <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] === 'unpaid' && !in_array($order['status'], ['cancelled', 'refunded'], true)): ?>
      <p class="order-hero__pay">Please keep <strong><?= e(money($order['grand_total'])) ?></strong> ready in cash on delivery.</p>
    <?php elseif ($payment && $order['payment_method'] !== 'cod' && in_array($payment['status'], ['pending', 'processing'], true) && $order['status'] === 'pending'): ?>
      <p class="order-hero__pay">We are waiting for <?= e(payment_method_label($order['payment_method'])) ?> to confirm your payment. This page updates once verified.</p>
      <a class="btn-outline-lux" href="<?= e(path_url('payment/start/' . $payment['reference'])) ?>">Complete payment</a>
    <?php elseif ($order['payment_status'] === 'failed' || ($order['payment_status'] === 'cancelled' && $order['payment_method'] !== 'cod')): ?>
      <p class="order-hero__pay text-danger">The online payment was not completed, so this order was cancelled and no money was taken. You are welcome to place it again.</p>
      <a class="btn-lux" href="<?= e(path_url('shop')) ?>">Continue shopping</a>
    <?php endif; ?>
    <p class="mt-3"><button type="button" class="link-muted" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></p>
  </div>
  <?php partial('order-detail', ['order' => $order]); ?>
</div>
<?php partial('footer');
