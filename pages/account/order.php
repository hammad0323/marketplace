<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
$o = db_row("SELECT * FROM orders WHERE order_number = ? AND customer_id = ? AND status <> 'draft'", [$GLOBALS['ROUTE_PARAMS']['id'], $c['id']]);
if (!$o) route_404();
$active = 'orders'; $title = 'Order ' . $o['order_number'];
require FS_ROOT . '/partials/account-start.php';
require FS_ROOT . '/partials/order-tracking.php';
?>
<div class="box mt-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
  <div class="small"><strong><?= $o['fulfillment'] === 'pickup' ? 'Pickup:' : 'Delivery address:' ?></strong>
    <?= $o['fulfillment'] === 'pickup' ? e(fmt_datetime($o['pickup_at']) . ' at ' . setting('pickup_address')) : e($o['address_line'] . ', ' . $o['area'] . ', ' . $o['city']) ?></div>
  <form method="post" action="<?= url('my-account/reorder/') ?>"><?= csrf_field() ?><input type="hidden" name="order" value="<?= e($o['order_number']) ?>"><button class="btn btn-brand"><i class="bi bi-arrow-repeat me-1"></i>Reorder</button></form>
</div>
<?php require FS_ROOT . '/partials/account-end.php';
