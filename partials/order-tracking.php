<?php
/** Order status timeline + items (expects $o). */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$hist = db_all('SELECT * FROM order_status_history WHERE order_id = ? AND is_internal = 0 ORDER BY id', [$o['id']]);
$flow = $o['fulfillment'] === 'pickup' ? ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'completed'] : ['pending', 'confirmed', 'processing', 'out_for_delivery', 'completed'];
$reached = [];
foreach ($hist as $h) $reached[$h['to_status']] = $h['created_at'];
$curIdx = array_search($o['status'], $flow, true);
$items = order_items((int) $o['id']);
?>
<div class="box mb-3">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div><h2 class="h5 fw-bold mb-0">Order <?= e($o['order_number']) ?></h2><small class="text-muted">Placed <?= fmt_datetime($o['created_at']) ?> · <?= $o['order_type'] === 'rashan' ? 'Monthly Rashan · ' : '' ?><?= $o['fulfillment'] === 'pickup' ? 'Store pickup' : 'Home delivery' ?></small></div>
    <div><?= status_badge($o['status']) ?></div>
  </div>
  <?php if ($o['status'] === 'cancelled'): ?>
    <div class="alert alert-danger mb-0">This order was cancelled.</div>
  <?php else: ?>
  <ul class="timeline mb-0">
    <?php foreach ($flow as $i => $st): $done = $curIdx !== false && $i <= $curIdx; ?>
    <li class="<?= $done ? 'done' : '' ?>"><strong><?= e(status_label($st)) ?></strong><?php if (isset($reached[$st])): ?><br><small class="text-muted"><?= fmt_datetime($reached[$st]) ?></small><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<div class="box">
  <h3 class="box-title">Items</h3>
  <?php foreach ($items as $it): ?>
  <div class="d-flex justify-content-between py-2 border-bottom small"><span><?= e($it['product_name']) ?><?= $it['variation_name'] ? ' (' . e($it['variation_name']) . ')' : '' ?> × <?= (int) $it['quantity'] ?></span><strong><?= money($it['line_total']) ?></strong></div>
  <?php endforeach; ?>
  <div class="summary-row"><span>Delivery</span><span><?= money($o['delivery_fee']) ?></span></div>
  <?php if ($o['discount_amount'] > 0): ?><div class="summary-row text-success"><span>Discount</span><span>- <?= money($o['discount_amount']) ?></span></div><?php endif; ?>
  <div class="summary-row summary-total"><span>Total</span><span><?= money($o['total']) ?></span></div>
  <p class="small text-muted mb-0">Payment: <?= e(payment_method_name($o['payment_method'])) ?> (<?= e($o['payment_status']) ?>)</p>
</div>
