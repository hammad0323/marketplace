<?php
/** /order-success/{number}/ - only visible to the session that placed it or the owner */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$num = $GLOBALS['ROUTE_PARAMS']['number'];
$o = db_row('SELECT * FROM orders WHERE order_number = ?', [$num]);
$mine = $o && (in_array($num, $_SESSION['last_orders'] ?? [], true) || (customer_id() && (int) $o['customer_id'] === customer_id()));
if (!$mine) route_404();
$items = order_items((int) $o['id']);
$pm = db_row('SELECT * FROM payment_methods WHERE code = ?', [$o['payment_method']]);
page_set(['title' => 'Order placed - ' . $o['order_number'], 'robots' => 'noindex,nofollow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container py-4" style="max-width:820px">
  <div class="box text-center mb-3">
    <div class="success-icon mb-3"><i class="bi bi-check-lg"></i></div>
    <h1 class="h3 fw-bold">Thank you, <?= e(explode(' ', $o['customer_name'])[0]) ?>! Your order is placed.</h1>
    <p class="text-muted mb-1">Order number</p>
    <div class="fs-3 fw-bold text-brand mb-2"><?= e($o['order_number']) ?></div>
    <p class="mb-0"><?= $o['fulfillment'] === 'pickup'
        ? 'We will prepare your order for pickup on <strong>' . e(fmt_datetime($o['pickup_at'])) . '</strong> and notify you when it is ready.'
        : 'We will call/WhatsApp you on <strong>' . e($o['customer_phone']) . '</strong> to confirm delivery.' ?></p>
  </div>
  <?php if ($pm && $pm['requires_reference'] && $pm['instructions']): ?>
  <div class="alert alert-info"><strong>Payment instructions (<?= e($pm['name']) ?>):</strong><div class="pay-instructions"><?= e($pm['instructions']) ?></div></div>
  <?php endif; ?>
  <div class="box">
    <h2 class="box-title">Order details <?= status_badge($o['status']) ?></h2>
    <?php foreach ($items as $it): ?>
    <div class="d-flex justify-content-between py-2 border-bottom small"><span><?= e($it['product_name']) ?><?= $it['variation_name'] ? ' (' . e($it['variation_name']) . ')' : '' ?> × <?= (int) $it['quantity'] ?></span><strong><?= money($it['line_total']) ?></strong></div>
    <?php endforeach; ?>
    <div class="summary-row mt-2"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
    <?php if ($o['discount_amount'] > 0): ?><div class="summary-row text-success"><span>Discount</span><span>- <?= money($o['discount_amount']) ?></span></div><?php endif; ?>
    <div class="summary-row"><span>Delivery</span><span><?= money($o['delivery_fee']) ?></span></div>
    <div class="summary-row summary-total"><span>Total</span><span><?= money($o['total']) ?></span></div>
    <div class="row small mt-3 g-2">
      <div class="col-md-6"><strong>Payment:</strong> <?= e(payment_method_name($o['payment_method'])) ?></div>
      <div class="col-md-6"><strong><?= $o['fulfillment'] === 'pickup' ? 'Pickup' : 'Deliver to' ?>:</strong> <?= $o['fulfillment'] === 'pickup' ? e(setting('pickup_address')) : e($o['address_line'] . ', ' . $o['area'] . ', ' . $o['city']) ?></div>
    </div>
  </div>
  <div class="text-center mt-3">
    <a href="<?= url('track-order/?order=' . urlencode($o['order_number']) . '&phone=' . urlencode($o['customer_phone'])) ?>" class="btn btn-brand me-2"><i class="bi bi-truck me-1"></i>Track order</a>
    <a href="<?= url('shop/') ?>" class="btn btn-outline-brand">Continue shopping</a>
  </div>
</div>
<?php require FS_ROOT . '/partials/footer.php';
