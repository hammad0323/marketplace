<?php
/** Order confirmation / detail / tracking (guest via secret key, or signed-in owner). */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$order = db_one('SELECT * FROM orders WHERE order_number = ?', [strtoupper($number)]);
$key = (string)get('key');
$allowed = $order && (
    ($key !== '' && hash_equals($order['lookup_key'], $key))
    || (customer_id() && (int)$order['customer_id'] === customer_id())
    || (!empty($_SESSION['tracked_orders']) && in_array($order['order_number'], $_SESSION['tracked_orders'], true))
);
if (!$allowed) not_found();

$items = order_items((int)$order['id']);
$addr = order_address((int)$order['id']);
$history = order_public_history((int)$order['id']);
$placed = get('placed') === '1';
$online = $order['payment_method'] !== 'cod';
$needsPayment = $online && in_array($order['payment_status'], ['pending', 'failed', 'cancelled'], true) && !in_array($order['status'], ['cancelled', 'returned'], true) && $order['payment_status'] !== 'paid';
$methodName = db_val('SELECT name FROM payment_gateways WHERE code = ?', [$order['payment_method']]) ?: $order['payment_method'];
$steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Preparing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$stepKeys = array_keys($steps);
$cur = $order['status'] === 'in_production' ? 'processing' : $order['status'];
$curIdx = array_search($cur, $stepKeys, true);

seo_set(['title' => 'Order ' . $order['order_number'], 'noindex' => true]);
$bodyClass = 'page-order';
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section">
  <div class="container-eb narrow">
    <?php if ($placed || ($online && $order['payment_status'] === 'paid' && get('paid') === '1')): ?>
      <div class="confirm-hero text-center" data-reveal>
        <span class="confirm-icon"><i class="bi bi-flower1"></i></span>
        <h1 class="page-title">Thank you, <?= e(strtok($order['customer_name'], ' ')) ?></h1>
        <p class="lead">Your order <strong><?= e($order['order_number']) ?></strong> has been received<?= $order['email'] ? '. A confirmation has been sent to ' . e($order['email']) . ' when email is enabled' : '' ?>.</p>
        <?php if ($order['payment_method'] === 'cod'): ?><p>Please keep <strong><?= money($order['grand_total'] - (float)$order['refunded_total']) ?></strong> ready to pay on delivery.</p><?php endif; ?>
      </div>
    <?php else: ?>
      <h1 class="page-title text-center">Order <?= e($order['order_number']) ?></h1>
    <?php endif; ?>

    <?php if ($needsPayment): ?>
      <div class="alert <?= $order['payment_status'] === 'pending' ? 'alert-info' : 'alert-warning' ?> d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?= $order['payment_status'] === 'pending' ? 'We are waiting for confirmation of your ' . e($methodName) . ' payment. If you have paid, this page will update once the payment provider confirms it.' : 'Your payment was not completed.' ?></span>
        <?php if (payment_gateway_available($order['payment_method'])): ?>
          <a class="btn btn-eb btn-primary-eb btn-sm" href="<?= e(url('payment/start/go/' . $order['order_number'] . '?key=' . $order['lookup_key'])) ?>"><?= $order['payment_status'] === 'pending' ? 'Pay now' : 'Try payment again' ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!in_array($order['status'], ['cancelled', 'returned'], true)): ?>
      <ol class="order-steps">
        <?php foreach ($steps as $k => $label): $i = array_search($k, $stepKeys, true); ?>
          <li class="<?= $curIdx !== false && $i <= $curIdx ? 'done' : '' ?><?= $i === $curIdx ? ' current' : '' ?>"><span></span><?= e($k === 'processing' && $order['status'] === 'in_production' ? 'In production' : $label) ?></li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="alert alert-secondary text-center">This order is <?= e(order_statuses()[$order['status']]) ?>.</div>
    <?php endif; ?>

    <div class="row g-4 mt-1">
      <div class="col-md-7">
        <div class="card-eb">
          <h2 class="h5">Items</h2>
          <?php foreach ($items as $it): ?>
            <div class="co-line">
              <span class="co-img"><img src="<?= e(img_url($it['image'])) ?>" alt="" loading="lazy"><span class="co-qty"><?= (int)$it['quantity'] ?></span></span>
              <span class="co-name"><?= e($it['product_name']) ?><small><?= e($it['variant_label']) ?></small>
                <?php if ($it['custom_length'] || $it['custom_sleeve'] || $it['custom_notes']): ?><small><i class="bi bi-scissors"></i> <?= $it['custom_length'] ? 'Length ' . (int)$it['custom_length'] . '" ' : '' ?><?= e($it['custom_sleeve'] ?? '') ?> <?= e(str_limit($it['custom_notes'], 80)) ?></small><?php endif; ?></span>
              <span class="co-amt"><?= money($it['line_total']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php $totals = ['subtotal' => $order['subtotal'], 'custom_total' => $order['customization_total'], 'discount' => $order['discount_total'], 'coupon' => $order['coupon_code'] ? ['code' => $order['coupon_code']] : null,
                           'shipping' => ['est_text' => $order['estimated_delivery']], 'shipping_total' => $order['shipping_total'], 'cod_fee' => $order['cod_fee'], 'grand_total' => $order['grand_total'], 'errors' => []];
                $in = ['payment' => ''];
                include ROOT_PATH . '/templates/checkout-totals.php'; ?>
          <?php if ((float)$order['refunded_total'] > 0): ?><p class="small">Refunded: <?= money($order['refunded_total']) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="col-md-5">
        <div class="card-eb">
          <h2 class="h5">Details</h2>
          <dl class="spec-list">
            <dt>Status</dt><dd><?= status_badge($order['status']) ?></dd>
            <dt>Payment</dt><dd><?= e($methodName) ?> · <?= status_badge($order['payment_status']) ?></dd>
            <dt>Placed</dt><dd><?= e(date('j M Y, g:ia', strtotime($order['created_at']))) ?></dd>
            <?php if ($order['estimated_delivery']): ?><dt>Est. delivery</dt><dd><?= e($order['estimated_delivery']) ?></dd><?php endif; ?>
            <?php if ($order['tracking_number']): ?><dt>Courier</dt><dd><?= e($order['courier_name']) ?><br><?= $order['tracking_url'] ? '<a href="' . e($order['tracking_url']) . '" target="_blank" rel="noopener">' . e($order['tracking_number']) . '</a>' : e($order['tracking_number']) ?></dd><?php endif; ?>
          </dl>
          <?php if ($addr): ?>
            <h3 class="h6 mt-3">Delivering to</h3>
            <p class="small mb-0"><?= e($addr['full_name']) ?><br><?= e($addr['address_line1']) ?><?= $addr['address_line2'] ? '<br>' . e($addr['address_line2']) : '' ?><br><?= e($addr['city']) ?><?= $addr['province'] ? ', ' . e($addr['province']) : '' ?> <?= e($addr['postal_code']) ?><br><?= e($addr['phone']) ?></p>
          <?php endif; ?>
        </div>
        <?php if ($history): ?>
        <div class="card-eb mt-4">
          <h2 class="h5">Updates</h2>
          <ul class="timeline">
            <?php foreach (array_reverse($history) as $h): ?><li><time><?= e(date('j M, g:ia', strtotime($h['created_at']))) ?></time><?= e($h['note']) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!customer_id() && $placed && setting('allow_checkout_registration') && !db_val('SELECT id FROM customers WHERE email = ?', [$order['email']])): ?>
      <div class="card-eb mt-4 text-center">
        <h2 class="h5">Save your details for next time</h2>
        <p class="small text-muted">Create an account to track this order and check out faster.</p>
        <a class="btn btn-eb btn-outline-eb" href="<?= e(url('account/register?email=' . urlencode($order['email']) . '&order=' . urlencode($order['order_number']) . '&key=' . $order['lookup_key'])) ?>">Create account</a>
      </div>
    <?php endif; ?>
    <p class="text-center mt-4"><a href="<?= e(url('shop')) ?>">Continue shopping</a></p>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
