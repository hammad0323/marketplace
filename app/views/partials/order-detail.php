<?php
/** @var array $order */
$items = order_items((int) $order['id']);
$address = order_address((int) $order['id']);
$history = order_public_history((int) $order['id']);
$notes = db_all('SELECT * FROM order_notes WHERE order_id = ? AND is_customer_visible = 1 ORDER BY created_at', [(int) $order['id']]);
$steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Preparing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$keys = array_keys($steps);
$currentIdx = array_search($order['status'], $keys, true);
?>
<div class="order-detail">
  <?php if (in_array($order['status'], ['cancelled', 'refunded', 'on_hold'], true)): ?>
    <div class="alert alert-<?= $order['status'] === 'on_hold' ? 'warning' : 'secondary' ?>">This order is <strong><?= e(strtolower(status_label($order['status']))) ?></strong>.</div>
  <?php else: ?>
    <ol class="order-steps">
      <?php foreach ($steps as $k => $label): $idx = array_search($k, $keys, true); ?>
        <li class="<?= $currentIdx !== false && $idx <= $currentIdx ? 'is-done' : '' ?><?= $idx === $currentIdx ? ' is-current' : '' ?>"><span></span><?= e($label) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <div class="order-detail__grid">
    <div>
      <h2 class="h5">Items</h2>
      <ul class="summary-lines">
        <?php foreach ($items as $it): ?>
          <li>
            <span class="summary-lines__img"><img src="<?= e(media_url($it['image_path'])) ?>" alt="" width="56" height="70"><em><?= (int) $it['quantity'] ?></em></span>
            <span class="summary-lines__name"><?= e($it['product_name']) ?><?php if ($it['variant_label']): ?><small><?= e($it['variant_label']) ?></small><?php endif; ?><?php if ($it['gift_wrap']): ?><small><i class="bi bi-gift"></i> Gift packaging</small><?php endif; ?><small>SKU <?= e($it['sku']) ?></small></span>
            <span><?= e(money($it['line_total'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="totals">
        <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
        <?php if ((float) $order['gift_wrap_total'] > 0): ?><div><dt>Gift packaging</dt><dd><?= e(money($order['gift_wrap_total'])) ?></dd></div><?php endif; ?>
        <?php if ((float) $order['discount_total'] > 0): ?><div class="totals__discount"><dt>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd>− <?= e(money($order['discount_total'])) ?></dd></div><?php endif; ?>
        <div><dt>Delivery<?= $order['shipping_label'] ? ' · ' . e($order['shipping_label']) : '' ?></dt><dd><?= (float) $order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Free' ?></dd></div>
        <?php if ((float) $order['cod_fee'] > 0): ?><div><dt>Cash on delivery fee</dt><dd><?= e(money($order['cod_fee'])) ?></dd></div><?php endif; ?>
        <div class="totals__grand"><dt>Total</dt><dd><?= e(money($order['grand_total'])) ?></dd></div>
        <?php if ((float) $order['refunded_total'] > 0): ?><div><dt>Refunded</dt><dd>− <?= e(money($order['refunded_total'])) ?></dd></div><?php endif; ?>
      </dl>
    </div>
    <div class="order-detail__side">
      <div class="info-card">
        <h3>Order</h3>
        <p><strong><?= e($order['order_number']) ?></strong><br><?= e(format_date($order['created_at'], true)) ?></p>
        <p>Status: <span class="badge text-bg-<?= e(status_color($order['status'])) ?>"><?= e(status_label($order['status'])) ?></span><br>
           Payment: <?= e(payment_method_label($order['payment_method'])) ?> · <span class="badge text-bg-<?= e(status_color($order['payment_status'])) ?>"><?= e(status_label($order['payment_status'])) ?></span></p>
        <?php if ($order['delivery_estimate'] && !in_array($order['status'], ['delivered', 'cancelled', 'refunded'], true)): ?><p><i class="bi bi-truck"></i> Estimated delivery: <?= e($order['delivery_estimate']) ?></p><?php endif; ?>
      </div>
      <?php if ($order['tracking_number'] || $order['courier_name']): ?>
        <div class="info-card">
          <h3>Tracking</h3>
          <p><?= e($order['courier_name']) ?><br><strong><?= e($order['tracking_number']) ?></strong></p>
          <?php if ($order['tracking_url']): ?><a class="link-arrow" href="<?= e(safe_link($order['tracking_url'])) ?>" target="_blank" rel="noopener">Track parcel <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($address): ?>
        <div class="info-card">
          <h3>Delivery address</h3>
          <p><?= e($address['full_name']) ?><br><?= e($address['address_line1']) ?><?= $address['address_line2'] ? '<br>' . e($address['address_line2']) : '' ?><br><?= e($address['city']) ?>, <?= e($address['region']) ?> <?= e($address['postal_code']) ?><br><?= e($address['phone']) ?></p>
        </div>
      <?php endif; ?>
      <?php if ($history || $notes): ?>
        <div class="info-card">
          <h3>Updates</h3>
          <ul class="timeline">
            <?php foreach ($history as $h): ?><li><time><?= e(format_date($h['created_at'], true)) ?></time><?= e(status_label($h['status'])) ?><?= $h['note'] ? ' — ' . e($h['note']) : '' ?></li><?php endforeach; ?>
            <?php foreach ($notes as $n): ?><li><time><?= e(format_date($n['created_at'], true)) ?></time><?= nl2br(e($n['note'])) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
