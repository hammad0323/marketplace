<?php
/**
 * Order confirmation / order details:  /order-success/{order_no}?k={access_key}
 */
require __DIR__ . '/includes/bootstrap.php';

$order = row('SELECT * FROM orders WHERE order_no = ?', [get('no')]);
if (!$order || !(hash_equals($order['access_key'], (string)get('k')) || can_view_order($order))) {
    not_found();
}
$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
$history = rows('SELECT * FROM order_history WHERE order_id = ? ORDER BY id', [$order['id']]);
$mode = payment_mode($order['payment_method']);
$needsPay = $mode === 'gateway' && in_array($order['payment_status'], ['unpaid', 'failed'], true) && !in_array($order['status'], ['cancelled', 'returned'], true);
$fresh = in_array($order['order_no'], $_SESSION['my_orders'] ?? [], true) && strtotime($order['created_at']) > time() - 3600;

$seo = ['title' => 'Order ' . $order['order_no'] . ' | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
$steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$cur = array_search($order['status'], $steps, true);
?>
<section class="section">
  <div class="container narrow">
    <div class="success-head" data-reveal>
      <?php if ($fresh): ?>
        <div class="success-check"><?= icon('check', 40) ?></div>
        <h1 class="section-title">Thank you, <?= e(strtok($order['name'], ' ')) ?>!</h1>
        <p class="lead">Your order <strong><?= e($order['order_no']) ?></strong> has been placed. We'll call you on <?= e($order['phone']) ?> to confirm.</p>
      <?php else: ?>
        <span class="ornament">✦</span>
        <h1 class="section-title">Order <?= e($order['order_no']) ?></h1>
        <p class="muted">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
      <?php endif; ?>
    </div>

    <?php $payMsg = ['paid' => ['success', 'Payment successful — thank you!'], 'received' => ['success', 'Your payment has been received and will be confirmed shortly.'], 'pending' => ['info', 'Your payment voucher has been generated. Complete the payment to confirm your order.'], 'failed' => ['error', 'Your payment was not completed.']][get('pay')] ?? null;
    if ($payMsg): ?><div class="alert alert-<?= $payMsg[0] ?>"><?= e($payMsg[1]) ?></div><?php endif; ?>
    <?php if ($needsPay): ?>
      <div class="alert alert-info center">
        Your payment has not been completed yet.
        <a class="btn btn-gold btn-sm" href="<?= url('pay/' . $order['order_no'] . '?k=' . $order['access_key']) ?>">Pay <?= money($order['total']) ?> now</a>
      </div>
    <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
      <div class="alert alert-info">We have received your payment details (TID <?= e($order['txn_id'] ?: '—') ?>). Our team will verify the payment shortly.</div>
    <?php endif; ?>

    <?php if (!in_array($order['status'], ['cancelled', 'returned'], true)): ?>
      <ol class="track-steps" data-reveal>
        <?php foreach ($steps as $i => $st): ?><li class="<?= $cur !== false && $i <= $cur ? 'done' : '' ?>"><span></span><?= order_statuses()[$st] ?></li><?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="alert alert-error center">This order is <?= e(order_statuses()[$order['status']]) ?>.</div>
    <?php endif; ?>

    <div class="order-grid" data-reveal>
      <div class="panel">
        <h3>Items</h3>
        <?php foreach ($items as $it): ?>
          <div class="summary-item">
            <span class="summary-item__img"><img src="<?= e(img($it['image'])) ?>" alt=""><i><?= (int)$it['qty'] ?></i></span>
            <span class="summary-item__name"><?= e($it['name']) ?><small><?= e(trim($it['size'] . ($it['color'] ? ' · ' . $it['color'] : ''), ' ·')) ?></small></span>
            <strong><?= money($it['price'] * $it['qty']) ?></strong>
          </div>
        <?php endforeach; ?>
        <div class="summary__row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
        <?php if ((float)$order['discount'] > 0): ?><div class="summary__row"><span>Discount</span><span>− <?= money($order['discount']) ?></span></div><?php endif; ?>
        <div class="summary__row"><span>Delivery</span><span><?= (float)$order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
        <?php if ((float)$order['payment_fee'] > 0): ?><div class="summary__row"><span>COD Fee</span><span><?= money($order['payment_fee']) ?></span></div><?php endif; ?>
        <div class="summary__row summary__total"><span>Total</span><span><?= money($order['total']) ?></span></div>
      </div>
      <div class="panel">
        <h3>Delivery Details</h3>
        <p><strong><?= e($order['name']) ?></strong><br><?= e($order['address']) ?><br><?= e($order['city']) ?> <?= e($order['postal_code']) ?><br><?= e($order['phone']) ?><br><?= e($order['email']) ?></p>
        <h3>Payment</h3>
        <p><?= e(payment_label($order['payment_method'])) ?><br><?= status_badge($order['payment_status']) ?></p>
        <?php if ($order['tracking_no']): ?><h3>Shipment</h3><p><?= e($order['courier']) ?><br>Tracking #: <strong><?= e($order['tracking_no']) ?></strong></p><?php endif; ?>
        <?php if ($history): ?>
          <h3>Timeline</h3>
          <ul class="timeline"><?php foreach ($history as $h): ?><li><time><?= date('d M, h:i A', strtotime($h['created_at'])) ?></time> <?= e($h['note'] ?: ucfirst($h['status'])) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="center mt-40"><a class="btn btn-dark" href="<?= url('shop') ?>">Continue Shopping</a></div>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
